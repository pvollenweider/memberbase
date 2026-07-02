<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for group management: create, rename, delete, import, and bulk operations.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: deleteTeam, deleteTeamForce, reassignTeam, importTeamMembers,
//          importCotisants, importDonors, bulkHide, bulkShow,
//          undoGroupVisibility, bulkCreateMetagroup, createLapsedGroup,
//          addTeam, addTeamWithImport, renameTeam, updateTeam,
//          addMembership, removeMembership

if (!isManager()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'deleteTeam') {
    $team = new Team();
    $team->lookupTeam((int)$_REQUEST['id']);
    auditLog($pdo, 'deleteTeam', "id={$team->id} {$team->name}");
    $team->remove();

} elseif ($action == 'deleteTeamForce') {
    $teamId = (int) $_REQUEST['id'];
    $teamName = $pdo->prepare("SELECT name FROM team WHERE id=?");
    $teamName->execute([$teamId]);
    auditLog($pdo, 'deleteTeamForce', "id=$teamId " . ($teamName->fetchColumn() ?: ''));
    $pdo->prepare("DELETE FROM user_properties WHERE parameter = ?")->execute(["team_$teamId"]);
    $pdo->prepare("DELETE FROM team WHERE id = ?")->execute([$teamId]);

} elseif ($action == 'reassignTeam') {
    $teamId       = (int) $_REQUEST['id'];
    $targetTeamId = (int) $_REQUEST['targetTeamId'];
    if ($targetTeamId > 0 && $targetTeamId !== $teamId) {
        $_auSrc = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auSrc->execute([$teamId]);
        $_auDst = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auDst->execute([$targetTeamId]);
        auditLog($pdo, 'reassignTeam', "groupe source: " . ($_auSrc->fetchColumn() ?: "id=$teamId") . " → groupe cible: " . ($_auDst->fetchColumn() ?: "id=$targetTeamId"));
        $pdo->prepare(
            "UPDATE user_properties
             SET parameter = ?
             WHERE parameter = ?
             AND user_id NOT IN (
                 SELECT user_id FROM (
                     SELECT user_id FROM user_properties WHERE parameter = ?
                 ) AS already
             )"
        )->execute(["team_$targetTeamId", "team_$teamId", "team_$targetTeamId"]);
        $pdo->prepare("DELETE FROM user_properties WHERE parameter = ?")->execute(["team_$teamId"]);
        $pdo->prepare("DELETE FROM team WHERE id = ?")->execute([$teamId]);
    }

} elseif ($action == 'importTeamMembers') {
    $teamId = (int)$_REQUEST['id'];
    if ($teamId > 0 && !empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        $stmt = $pdo->prepare(
            "INSERT INTO user_properties (user_id, parameter, value)
             SELECT up.user_id, ?, up.value
             FROM user_properties up
             WHERE up.parameter = ?
             AND up.user_id NOT IN (
                 SELECT user_id FROM user_properties WHERE parameter = ?
             )"
        );
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int)$srcId;
            if ($srcId > 0 && $srcId !== $teamId) {
                $stmt->execute(["team_$teamId", "team_$srcId", "team_$teamId"]);
            }
        }
    }
    $_auTeamN = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auTeamN->execute([$teamId]);
    auditLog($pdo, 'importTeamMembers', "vers groupe: " . ($_auTeamN->fetchColumn() ?: "id=$teamId") . " | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
    $_itUrl = $_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId;
    if ($isHtmx) { header('HX-Location: ' . $_itUrl); } else { echo '<script>window.location.replace(' . json_encode($_itUrl) . ');</script>'; }
    exit;

} elseif ($action == 'importCotisants') {
    $teamId = (int)$_REQUEST['id'];
    $year   = isset($_REQUEST['cotis_year']) ? (int)$_REQUEST['cotis_year'] : (int)date('Y');
    $cotisTypeIds = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
    if ($teamId > 0 && $year >= 2000 && $year <= 2100 && !empty($cotisTypeIds)) {
        $from        = mktime(0, 0, 0, 1, 0, $year);
        $to          = mktime(0, 0, 0, 1, 1, $year + 1);
        $placeholders = implode(',', array_fill(0, count($cotisTypeIds), '?'));
        $params       = array_merge(["team_$teamId"], $cotisTypeIds, [$from, $to, "team_$teamId"]);
        $pdo->prepare("
            INSERT INTO user_properties (user_id, parameter, value)
            SELECT u.id, ?, 'true'
            FROM users u
            JOIN compta c ON c.user_id = u.id
            WHERE c.type_id IN ($placeholders)
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
            GROUP BY u.id
        ")->execute($params);
    }
    $_auTeamC = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auTeamC->execute([$teamId]);
    auditLog($pdo, 'importCotisants', "vers groupe: " . ($_auTeamC->fetchColumn() ?: "id=$teamId") . " | année: $year");
    $_icUrl = $_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId . '&imported=cotisants';
    if ($isHtmx) { header('HX-Location: ' . $_icUrl); } else { echo '<script>window.location.replace(' . json_encode($_icUrl) . ');</script>'; }
    exit;

} elseif ($action == 'importDonors') {
    $teamId    = (int)$_REQUEST['id'];
    $year      = isset($_REQUEST['donor_year'])   ? (int)$_REQUEST['donor_year']   : (int)date('Y');
    $minSum    = isset($_REQUEST['donor_minsum']) ? (int)$_REQUEST['donor_minsum'] : 1;
    $donorType = in_array($_REQUEST['donor_type'] ?? '', ['all', 'institutional', 'non_institutional'])
                 ? $_REQUEST['donor_type'] : 'all';
    if (!in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }
    if ($teamId > 0 && $year >= 2000 && $year <= 2100) {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        $instSubClause = '';
        if ($donorType === 'institutional') {
            $instSubClause = 'AND c.type_id IN (SELECT id FROM compta_type WHERE is_institutional = 1)';
        } elseif ($donorType === 'non_institutional') {
            $instSubClause = 'AND c.type_id NOT IN (SELECT id FROM compta_type WHERE is_institutional = 1)';
        }
        $pdo->prepare("
            INSERT INTO user_properties (user_id, parameter, value)
            SELECT u.id, ?, 'true'
            FROM users u
            JOIN compta c ON c.user_id = u.id
            WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
              $instSubClause
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
            GROUP BY u.id
            HAVING SUM(c.sum) >= ?
        ")->execute(["team_$teamId", $from, $to, "team_$teamId", $minSum]);
    }
    $_auTeamD = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auTeamD->execute([$teamId]);
    $typeLabel = ['institutional' => 'institutionnels', 'non_institutional' => 'non-institutionnels', 'all' => 'tous'][$donorType];
    auditLog($pdo, 'importDonors', "vers groupe: " . ($_auTeamD->fetchColumn() ?: "id=$teamId") . " | année: $year | min: {$minSum} CHF | type: $typeLabel");
    $_idUrl = $_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId . '&imported=donors';
    if ($isHtmx) { header('HX-Location: ' . $_idUrl); } else { echo '<script>window.location.replace(' . json_encode($_idUrl) . ');</script>'; }
    exit;

} elseif ($action == 'bulkHide') {
    $ids = array_map('intval', array_filter($_REQUEST['ids'] ?? [], 'is_numeric'));
    if ($ids) {
        $stmt = $pdo->prepare("UPDATE team SET hidden=1 WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$tid]); }
        auditLog($pdo, 'bulkHide', count($ids) . " groupes masqués");
        $n = count($ids);
        if (!isset($_SESSION)) session_start();
        $_SESSION['group_toast'] = [
            'msg'      => $n === 1 ? '1 groupe masqué.' : "$n groupes masqués.",
            'undo_ids' => $ids,
            'undo_act' => 'bulkShow',
        ];
    }
    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'bulkShow') {
    $ids = array_map('intval', array_filter($_REQUEST['ids'] ?? [], 'is_numeric'));
    if ($ids) {
        $stmt = $pdo->prepare("UPDATE team SET hidden=0 WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$tid]); }
        auditLog($pdo, 'bulkShow', count($ids) . " groupes affichés");
        $n = count($ids);
        if (!isset($_SESSION)) session_start();
        $_SESSION['group_toast'] = [
            'msg'      => $n === 1 ? '1 groupe affiché.' : "$n groupes affichés.",
            'undo_ids' => $ids,
            'undo_act' => 'bulkHide',
        ];
    }
    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'undoGroupVisibility') {
    $hidden = (int)($_REQUEST['hidden'] ?? 0);
    $ids = array_map('intval', array_filter(explode(',', $_REQUEST['ids'] ?? ''), 'is_numeric'));
    if ($ids) {
        $stmt = $pdo->prepare("UPDATE team SET hidden=? WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$hidden, $tid]); }
        auditLog($pdo, $hidden ? 'bulkHide' : 'bulkShow', count($ids) . " groupes (undo) " . ($hidden ? "masqués" : "affichés"));
    }
    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'bulkCreateMetagroup') {
    $name = trim($_REQUEST['metagroupName'] ?? '');
    if ($name && !empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $mid = updateAndGetMaxVal("metagroup_id");
        $pdo->prepare("INSERT INTO metagroup (id, name) VALUES (?, ?)")->execute([$mid, $name]);
        $stmt = $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)");
        foreach ($_REQUEST['ids'] as $tid) {
            $tid = (int)$tid;
            if ($tid > 0) $stmt->execute([$mid, $tid]);
        }
        auditLog($pdo, 'bulkCreateMetagroup', "nouveau groupe filtre: $name (id=$mid) | " . count($_REQUEST['ids']) . " groupes");
        $_bmUrl = $_SERVER['PHP_SELF'] . '?view=updateMetagroup&id=' . $mid;
        if ($isHtmx) { header('HX-Location: ' . $_bmUrl); } else { echo '<script>window.location.replace(' . json_encode($_bmUrl) . ');</script>'; }
        exit;
    }

} elseif ($action == 'createLapsedGroup') {
    $groupType = in_array($_REQUEST['groupType'] ?? '', ['donors', 'members']) ? $_REQUEST['groupType'] : 'donors';
    $yr        = (int)($_REQUEST['year'] ?? date("Y"));
    $excl      = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $kFrom1    = mktime(0,0,0,1,0,$yr-1);
    $kTo1      = mktime(0,0,0,1,1,$yr);
    $kFrom     = mktime(0,0,0,1,0,$yr);
    $kTo       = mktime(0,0,0,1,1,$yr+1);

    if ($groupType === 'donors') {
        $groupName = "Donateurs à relancer " . $yr . " (" . date("d.m.Y") . ")";
        $stmt = $pdo->prepare("SELECT DISTINCT c.user_id FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl) AND c.user_id NOT IN (SELECT DISTINCT user_id FROM compta WHERE date>? AND date<? AND type_id NOT IN ($excl))");
        $stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo]);
    } else {
        $groupName = "Membres à relancer " . $yr . " (" . date("d.m.Y") . ")";
        $membreTeamId  = (int)($appSettings['default_team'] ?? 0);
        $prevTeamStmt  = $pdo->prepare("SELECT id FROM team WHERE name = ?");
        $prevTeamStmt->execute([($appSettings['membre_team_prefix'] ?? 'Membre') . ' ' . ($yr - 1)]);
        $prevTeamId    = (int)$prevTeamStmt->fetchColumn();
        if ($prevTeamId <= 0 || $membreTeamId <= 0) {
            echo '<script>alert("Impossible de trouver les équipes membres.");history.back();</script>';
            exit;
        }
        $stmt = $pdo->prepare("SELECT user_id FROM user_properties WHERE parameter=? AND value='true' AND user_id NOT IN (SELECT user_id FROM user_properties WHERE parameter=? AND value='true')");
        $stmt->execute(["team_$prevTeamId", "team_$membreTeamId"]);
    }
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($userIds)) {
        echo '<script>alert("Aucun utilisateur à ajouter.");history.back();</script>';
        exit;
    }
    $team = new Team();
    $team->name = $groupName;
    $team->setHidden(0);
    $team->save();
    $newTeamId = $team->id;
    if ($newTeamId > 0) {
        $ins = $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, 'true')");
        foreach ($userIds as $uid) {
            $ins->execute([(int)$uid, "team_$newTeamId"]);
        }
    }
    auditLog($pdo, 'createLapsedGroup', "type: $groupType | année: $yr | groupe créé: $groupName (id=$newTeamId) | " . count($userIds) . " membres");
    $_clUrl = $_SERVER['PHP_SELF'] . '?team=' . $newTeamId;
    if ($isHtmx) { header('HX-Location: ' . $_clUrl); } else { echo '<script>window.location.replace(' . json_encode($_clUrl) . ');</script>'; }
    exit;

} elseif ($action == 'addTeam') {
    $team = new Team();
    $team->name = $_REQUEST['name'];
    $team->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
    $team->save();
    $_auNewTeam = $team->id;
    auditLog($pdo, 'addTeam', "id=$_auNewTeam | {$_REQUEST['name']}");
    $_addTeamUrl = $_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . (int)$_auNewTeam;
    if ($isHtmx) { header('HX-Location: ' . $_addTeamUrl); } else { header('Location: ' . $_addTeamUrl); }
    exit;

} elseif ($action == 'addTeamWithImport') {
    $team = new Team();
    $team->name = $_REQUEST['name'];
    $team->setHidden(0);
    $team->save();
    $newTeamId = $team->id;
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    if ($newTeamId && $categoryId > 0) {
        $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)")->execute([$categoryId, $newTeamId]);
    }
    if ($newTeamId && !empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int) $srcId;
            if ($srcId <= 0) continue;
            $pdo->prepare(
                "INSERT INTO user_properties (user_id, parameter, value)
                 SELECT up.user_id, ?, up.value
                 FROM user_properties up
                 WHERE up.parameter = ?
                 AND up.user_id NOT IN (
                     SELECT user_id FROM user_properties WHERE parameter = ?
                 )"
            )->execute(["team_$newTeamId", "team_$srcId", "team_$newTeamId"]);
        }
    }
    auditLog($pdo, 'addTeamWithImport', "id=$newTeamId | {$_REQUEST['name']} | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
    $_atwUrl = $_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . (int)$newTeamId;
    if ($isHtmx) { header('HX-Location: ' . $_atwUrl); } else { header('Location: ' . $_atwUrl); }
    exit;

} elseif ($action == 'renameTeam') {
    header('Content-Type: application/json; charset=utf-8');
    $teamId  = (int)($_REQUEST['id'] ?? 0);
    $newName = trim($_REQUEST['name'] ?? '');
    if ($teamId <= 0 || $newName === '') {
        echo json_encode(['ok' => false, 'error' => 'Données invalides']);
        exit;
    }
    $row = $pdo->prepare("SELECT name FROM team WHERE id=?");
    $row->execute([$teamId]);
    $oldName = $row->fetchColumn();
    if ($oldName === false) {
        echo json_encode(['ok' => false, 'error' => 'Groupe introuvable']);
        exit;
    }
    $pdo->prepare("UPDATE team SET name=? WHERE id=?")->execute([$newName, $teamId]);
    auditLog($pdo, 'renameTeam', 'id=' . $teamId . ' | ' . $oldName . ' -> ' . $newName);
    echo json_encode(['ok' => true, 'name' => $newName]);
    exit;

} elseif ($action == 'updateTeam') {
    $team = new Team();
    $team->lookupTeam((int)$_REQUEST['id']);
    $_auTOldName   = $team->name;
    $_auTOldHidden = (int)$team->getHidden();
    $teamId = (int)$_REQUEST['id'];
    $_auTOldCatRow = $pdo->prepare("SELECT m.name FROM metagroup m JOIN metagroup j ON j.id=m.id WHERE j.teamid=? AND m.name IS NOT NULL AND m.is_filter=0 LIMIT 1");
    $_auTOldCatRow->execute([$teamId]);
    $_auTOldCat = $_auTOldCatRow->fetchColumn() ?: '—';
    $team->name = $_REQUEST['name'];
    $team->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
    $team->save();
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    $_auTNewCat = '—';
    if ($categoryId > 0) {
        $_auTCatNameRow = $pdo->prepare("SELECT name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
        $_auTCatNameRow->execute([$categoryId]);
        $_auTNewCat = $_auTCatNameRow->fetchColumn() ?: "id=$categoryId";
    }
    $pdo->prepare("DELETE FROM metagroup WHERE teamid=? AND id IN (
        SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS cats
    )")->execute([$teamId]);
    if ($categoryId > 0) {
        $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)")->execute([$categoryId, $teamId]);
    }
    $_auTChanges = [];
    if ($_auTOldName !== $team->name) $_auTChanges[] = "nom: «{$_auTOldName}» → «{$team->name}»";
    if ($_auTOldHidden !== (isset($_REQUEST['hidden']) ? 1 : 0)) $_auTChanges[] = "masqué: " . ($_auTOldHidden ? 'oui' : 'non') . " → " . (isset($_REQUEST['hidden']) ? 'oui' : 'non');
    if ($_auTOldCat !== $_auTNewCat) $_auTChanges[] = "catégorie: «{$_auTOldCat}» → «{$_auTNewCat}»";
    $auTDetail = "id=$teamId | {$team->name}" . (count($_auTChanges) ? ' | ' . implode(', ', $_auTChanges) : ' | aucun changement');
    auditLog($pdo, 'updateTeam', $auTDetail);
    if (!empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        $importedFrom = [];
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int)$srcId;
            if ($srcId > 0 && $srcId !== $teamId) {
                $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter)
                    SELECT user_id, ? FROM user_properties WHERE parameter = ? AND user_id NOT IN
                    (SELECT user_id FROM user_properties WHERE parameter = ?)"
                )->execute(["team_$teamId", "team_$srcId", "team_$teamId"]);
                $importedFrom[] = $srcId;
            }
        }
        if ($importedFrom) {
            $_auImpSrc = $pdo->prepare("SELECT name FROM team WHERE id=?");
            $srcNames = [];
            foreach ($importedFrom as $sid) { $_auImpSrc->execute([$sid]); $srcNames[] = $_auImpSrc->fetchColumn() ?: "id=$sid"; }
            auditLog($pdo, 'importTeamMembers', "vers groupe: {$team->name} (id=$teamId) | depuis: " . implode(', ', $srcNames));
        }
    }

} elseif ($action == 'addMembership') {
    $user = new User();
    $user->lookupUser((int)$_REQUEST['id']);
    $user->addMembership((int)$_REQUEST['teamId']);
    $_auTeam = $pdo->prepare("SELECT name FROM team WHERE id=?");
    $_auTeam->execute([(int)$_REQUEST['teamId']]);
    auditLog($pdo, 'addMembership', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) → groupe: " . ($_auTeam->fetchColumn() ?: "id={$_REQUEST['teamId']}"), (int)$_REQUEST['id']);

} elseif ($action == 'removeMembership') {
    $user = new User();
    $user->lookupUser((int)$_REQUEST['id']);
    $user->removeMembership((int)$_REQUEST['teamId']);
    $_auTeam = $pdo->prepare("SELECT name FROM team WHERE id=?");
    $_auTeam->execute([(int)$_REQUEST['teamId']]);
    auditLog($pdo, 'removeMembership', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) ← groupe: " . ($_auTeam->fetchColumn() ?: "id={$_REQUEST['teamId']}"), (int)$_REQUEST['id']);
}
