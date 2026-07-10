<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for group management: create, rename, delete, import, and bulk operations.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: deleteSegment, deleteSegmentForce, reassignSegment, importSegmentMembers,
//          importCotisants, importDonors, bulkHide, bulkShow,
//          undoSegmentVisibility, bulkCreateMetagroup, createLapsedSegment,
//          addSegment, addSegmentWithImport, renameSegment, updateSegment,
//          assignSegment, unassignSegment

if (!isManager()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'deleteSegment') {
    $segment = new Segment();
    $segment->lookupSegment((int)$_REQUEST['id']);
    auditLog(db(), 'deleteSegment', "id={$segment->id} {$segment->name}");
    $segment->remove();

} elseif ($action == 'deleteSegmentForce') {
    $segmentId = (int) $_REQUEST['id'];
    $segmentName = db()->prepare("SELECT name FROM segment WHERE id=?");
    $segmentName->execute([$segmentId]);
    auditLog(db(), 'deleteSegmentForce', "id=$segmentId " . ($segmentName->fetchColumn() ?: ''));
    db()->prepare("DELETE FROM contact_segment WHERE segment_id = ?")->execute([$segmentId]);
    db()->prepare("DELETE FROM segment WHERE id = ?")->execute([$segmentId]);

} elseif ($action == 'reassignSegment') {
    $segmentId       = (int) $_REQUEST['id'];
    $targetSegmentId = (int) $_REQUEST['targetTeamId'];
    if ($targetSegmentId > 0 && $targetSegmentId !== $segmentId) {
        $_auSrc = db()->prepare("SELECT name FROM segment WHERE id=?"); $_auSrc->execute([$segmentId]);
        $_auDst = db()->prepare("SELECT name FROM segment WHERE id=?"); $_auDst->execute([$targetSegmentId]);
        auditLog(db(), 'reassignSegment', "groupe source: " . ($_auSrc->fetchColumn() ?: "id=$segmentId") . " → groupe cible: " . ($_auDst->fetchColumn() ?: "id=$targetSegmentId"));
        db()->prepare(
            "INSERT IGNORE INTO contact_segment (user_id, segment_id)
             SELECT user_id, ?
             FROM contact_segment
             WHERE segment_id = ?"
        )->execute([$targetSegmentId, $segmentId]);
        db()->prepare("DELETE FROM contact_segment WHERE segment_id = ?")->execute([$segmentId]);
        db()->prepare("DELETE FROM segment WHERE id = ?")->execute([$segmentId]);
    }

} elseif ($action == 'importSegmentMembers') {
    $segmentId = (int)$_REQUEST['id'];
    if ($segmentId > 0 && !empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        $stmt = db()->prepare(
            "INSERT IGNORE INTO contact_segment (user_id, segment_id)
             SELECT user_id, ?
             FROM contact_segment
             WHERE segment_id = ?"
        );
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int)$srcId;
            if ($srcId > 0 && $srcId !== $segmentId) {
                $stmt->execute([$segmentId, $srcId]);
            }
        }
    }
    $_auSegN = db()->prepare("SELECT name FROM segment WHERE id=?"); $_auSegN->execute([$segmentId]);
    auditLog(db(), 'importSegmentMembers', "vers groupe: " . ($_auSegN->fetchColumn() ?: "id=$segmentId") . " | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
    $_itUrl = appUrl() . '?view=updateSegment&id=' . $segmentId;
    if ($isHtmx) { header('HX-Location: ' . $_itUrl); } else { echo '<script>window.location.replace(' . json_encode($_itUrl) . ');</script>'; }
    exit;

} elseif ($action == 'importCotisants') {
    $segmentId = (int)$_REQUEST['id'];
    $year   = isset($_REQUEST['cotis_year']) ? (int)$_REQUEST['cotis_year'] : (int)date('Y');
    $cotisTypeIds = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
    if ($segmentId > 0 && $year >= 2000 && $year <= 2100 && !empty($cotisTypeIds)) {
        $placeholders = implode(',', array_fill(0, count($cotisTypeIds), '?'));
        $params       = array_merge([$segmentId], $cotisTypeIds, [$year, $segmentId]);
        db()->prepare("
            INSERT IGNORE INTO contact_segment (user_id, segment_id)
            SELECT u.id, ?
            FROM contact u
            JOIN compta c ON c.user_id = u.id
            WHERE c.type_id IN ($placeholders)
              AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
              AND u.id NOT IN (SELECT user_id FROM contact_segment WHERE segment_id = ?)
            GROUP BY u.id
        ")->execute($params);
    }
    $_auSegC = db()->prepare("SELECT name FROM segment WHERE id=?"); $_auSegC->execute([$segmentId]);
    auditLog(db(), 'importCotisants', "vers groupe: " . ($_auSegC->fetchColumn() ?: "id=$segmentId") . " | année: $year");
    $_icUrl = appUrl() . '?view=updateSegment&id=' . $segmentId . '&imported=cotisants';
    if ($isHtmx) { header('HX-Location: ' . $_icUrl); } else { echo '<script>window.location.replace(' . json_encode($_icUrl) . ');</script>'; }
    exit;

} elseif ($action == 'importDonors') {
    $segmentId = (int)$_REQUEST['id'];
    $year      = isset($_REQUEST['donor_year'])   ? (int)$_REQUEST['donor_year']   : (int)date('Y');
    $minSum    = isset($_REQUEST['donor_minsum']) ? (int)$_REQUEST['donor_minsum'] : 1;
    $donorType = in_array($_REQUEST['donor_type'] ?? '', ['all', 'institutional', 'non_institutional'])
                 ? $_REQUEST['donor_type'] : 'all';
    if (!in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }
    if ($segmentId > 0 && $year >= 2000 && $year <= 2100) {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        $instSubClause = '';
        if ($donorType === 'institutional') {
            $instSubClause = 'AND c.type_id IN (SELECT id FROM compta_type WHERE is_institutional = 1)';
        } elseif ($donorType === 'non_institutional') {
            $instSubClause = 'AND c.type_id NOT IN (SELECT id FROM compta_type WHERE is_institutional = 1)';
        }
        db()->prepare("
            INSERT IGNORE INTO contact_segment (user_id, segment_id)
            SELECT u.id, ?
            FROM contact u
            JOIN compta c ON c.user_id = u.id
            WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
              $instSubClause
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM contact_segment WHERE segment_id = ?)
            GROUP BY u.id
            HAVING SUM(c.sum) >= ?
        ")->execute([$segmentId, $from, $to, $segmentId, $minSum]);
    }
    $_auSegD = db()->prepare("SELECT name FROM segment WHERE id=?"); $_auSegD->execute([$segmentId]);
    $typeLabel = ['institutional' => 'institutionnels', 'non_institutional' => 'non-institutionnels', 'all' => 'tous'][$donorType];
    auditLog(db(), 'importDonors', "vers groupe: " . ($_auSegD->fetchColumn() ?: "id=$segmentId") . " | année: $year | min: {$minSum} CHF | type: $typeLabel");
    $_idUrl = appUrl() . '?view=updateSegment&id=' . $segmentId . '&imported=donors';
    if ($isHtmx) { header('HX-Location: ' . $_idUrl); } else { echo '<script>window.location.replace(' . json_encode($_idUrl) . ');</script>'; }
    exit;

} elseif ($action == 'bulkHide') {
    $ids = array_map('intval', array_filter($_REQUEST['ids'] ?? [], 'is_numeric'));
    if ($ids) {
        $stmt = db()->prepare("UPDATE segment SET hidden=1 WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$tid]); }
        auditLog(db(), 'bulkHide', count($ids) . " groupes masqués");
        $n = count($ids);
        if (!isset($_SESSION)) session_start();
        $_SESSION['group_toast'] = [
            'msg'      => $n === 1 ? $GLOBAL['oneGroupHidden'] : sprintf($GLOBAL['groupsHidden'], $n),
            'undo_ids' => $ids,
            'undo_act' => 'bulkShow',
        ];
    }
    if ($isHtmx) {
        header('HX-Location: ' . appUrl() . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'bulkShow') {
    $ids = array_map('intval', array_filter($_REQUEST['ids'] ?? [], 'is_numeric'));
    if ($ids) {
        $stmt = db()->prepare("UPDATE segment SET hidden=0 WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$tid]); }
        auditLog(db(), 'bulkShow', count($ids) . " groupes affichés");
        $n = count($ids);
        if (!isset($_SESSION)) session_start();
        $_SESSION['group_toast'] = [
            'msg'      => $n === 1 ? $GLOBAL['oneGroupShown'] : sprintf($GLOBAL['groupsShown'], $n),
            'undo_ids' => $ids,
            'undo_act' => 'bulkHide',
        ];
    }
    if ($isHtmx) {
        header('HX-Location: ' . appUrl() . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'undoSegmentVisibility') {
    $hidden = (int)($_REQUEST['hidden'] ?? 0);
    $ids = array_map('intval', array_filter(explode(',', $_REQUEST['ids'] ?? ''), 'is_numeric'));
    if ($ids) {
        $stmt = db()->prepare("UPDATE segment SET hidden=? WHERE id=?");
        foreach ($ids as $tid) { $stmt->execute([$hidden, $tid]); }
        auditLog(db(), $hidden ? 'bulkHide' : 'bulkShow', count($ids) . " groupes (undo) " . ($hidden ? "masqués" : "affichés"));
    }
    if ($isHtmx) {
        header('HX-Location: ' . appUrl() . '?view=settings&tab=groups');
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&tab=groups') . ');</script>';
    }
    exit;

} elseif ($action == 'bulkCreateMetagroup') {
    $name = trim($_REQUEST['metagroupName'] ?? '');
    if ($name && !empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        db()->prepare("INSERT INTO metagroup (name) VALUES (?)")->execute([$name]);
        $mid = (int)db()->lastInsertId();
        $stmt = db()->prepare("INSERT IGNORE INTO metagroup_member (metagroup_id, segment_id) VALUES (?, ?)");
        foreach ($_REQUEST['ids'] as $tid) {
            $tid = (int)$tid;
            if ($tid > 0) $stmt->execute([$mid, $tid]);
        }
        auditLog(db(), 'bulkCreateMetagroup', "nouveau groupe filtre: $name (id=$mid) | " . count($_REQUEST['ids']) . " groupes");
        $_bmUrl = appUrl() . '?view=updateMetagroup&id=' . $mid;
        if ($isHtmx) { header('HX-Location: ' . $_bmUrl); } else { echo '<script>window.location.replace(' . json_encode($_bmUrl) . ');</script>'; }
        exit;
    }

} elseif ($action == 'createLapsedSegment') {
    $groupType = in_array($_REQUEST['groupType'] ?? '', ['donors', 'members']) ? $_REQUEST['groupType'] : 'donors';
    $yr        = (int)($_REQUEST['year'] ?? date("Y"));
    $excl      = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $kFrom1    = mktime(0,0,0,1,0,$yr-1);
    $kTo1      = mktime(0,0,0,1,1,$yr);
    $kFrom     = mktime(0,0,0,1,0,$yr);
    $kTo       = mktime(0,0,0,1,1,$yr+1);

    if ($groupType === 'donors') {
        $groupName = sprintf($GLOBAL['lapsedDonorsGroupName'], $yr, date("d.m.Y"));
        $stmt = db()->prepare("SELECT DISTINCT c.user_id FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl) AND c.user_id NOT IN (SELECT DISTINCT user_id FROM compta WHERE date>? AND date<? AND type_id NOT IN ($excl))");
        $stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo]);
    } else {
        $groupName = sprintf($GLOBAL['lapsedMembersGroupName'], $yr, date("d.m.Y"));
        $cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
        if (empty($cotiTypeIds)) {
            echo '<script>alert("' . addslashes($GLOBAL['noComptaCotiType']) . '");history.back();</script>';
            exit;
        }
        $ph = implode(',', array_fill(0, count($cotiTypeIds), '?'));
        $_noCotiTeam  = (int)($appSettings['member_no_coti_team'] ?? 0);
        $noCotiClause = $_noCotiTeam > 0
            ? "AND NOT EXISTS (SELECT 1 FROM contact_segment WHERE user_id=u.id AND segment_id=$_noCotiTeam)"
            : '';
        $stmt = db()->prepare("
            SELECT u.id AS user_id FROM contact u
            WHERE u.status = 1
              $noCotiClause
              AND EXISTS (
                  SELECT 1 FROM compta c WHERE c.user_id = u.id AND c.type_id IN ($ph)
                    AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
              )
              AND NOT EXISTS (
                  SELECT 1 FROM compta c WHERE c.user_id = u.id AND c.type_id IN ($ph)
                    AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
              )
        ");
        $stmt->execute(array_merge(
            array_values($cotiTypeIds), [$yr - 1],
            array_values($cotiTypeIds), [$yr]
        ));
    }
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($userIds)) {
        echo '<script>alert("' . $GLOBAL['noUsersToAdd'] . '");history.back();</script>';
        exit;
    }
    $segment = new Segment();
    $segment->name = $groupName;
    $segment->setHidden(0);
    $segment->save();
    $newSegmentId = $segment->id;
    if ($newSegmentId > 0) {
        $ins = db()->prepare("INSERT IGNORE INTO contact_segment (user_id, segment_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $ins->execute([(int)$uid, $newSegmentId]);
        }
    }
    auditLog(db(), 'createLapsedSegment', "type: $groupType | année: $yr | groupe créé: $groupName (id=$newSegmentId) | " . count($userIds) . " membres");
    $_clUrl = appUrl() . '?team=' . $newSegmentId;
    if ($isHtmx) { header('HX-Location: ' . $_clUrl); } else { echo '<script>window.location.replace(' . json_encode($_clUrl) . ');</script>'; }
    exit;

} elseif ($action == 'addSegment') {
    $segment = new Segment();
    $segment->name = $_REQUEST['name'];
    $segment->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
    $segment->save();
    $_auNewSegment = $segment->id;
    auditLog(db(), 'addSegment', "id=$_auNewSegment | {$_REQUEST['name']}");
    $_addSegmentUrl = appUrl() . '?view=updateSegment&id=' . (int)$_auNewSegment;
    if ($isHtmx) { header('HX-Location: ' . $_addSegmentUrl); } else { header('Location: ' . $_addSegmentUrl); }
    exit;

} elseif ($action == 'addSegmentWithImport') {
    $segment = new Segment();
    $segment->name = $_REQUEST['name'];
    $segment->setHidden(0);
    $segment->save();
    $newSegmentId = $segment->id;
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    if ($newSegmentId && $categoryId > 0) {
        db()->prepare("INSERT IGNORE INTO metagroup_member (metagroup_id, segment_id) VALUES (?, ?)")->execute([$categoryId, $newSegmentId]);
    }
    if ($newSegmentId && !empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int) $srcId;
            if ($srcId <= 0) continue;
            db()->prepare(
                "INSERT IGNORE INTO contact_segment (user_id, segment_id)
                 SELECT user_id, ?
                 FROM contact_segment
                 WHERE segment_id = ?"
            )->execute([$newSegmentId, $srcId]);
        }
    }
    auditLog(db(), 'addSegmentWithImport', "id=$newSegmentId | {$_REQUEST['name']} | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
    $_atwUrl = appUrl() . '?view=updateSegment&id=' . (int)$newSegmentId;
    if ($isHtmx) { header('HX-Location: ' . $_atwUrl); } else { header('Location: ' . $_atwUrl); }
    exit;

} elseif ($action == 'renameSegment') {
    header('Content-Type: application/json; charset=utf-8');
    $segmentId = (int)($_REQUEST['id'] ?? 0);
    $newName = trim($_REQUEST['name'] ?? '');
    if ($segmentId <= 0 || $newName === '') {
        echo json_encode(['ok' => false, 'error' => $GLOBAL['invalidData']]);
        exit;
    }
    $row = db()->prepare("SELECT name FROM segment WHERE id=?");
    $row->execute([$segmentId]);
    $oldName = $row->fetchColumn();
    if ($oldName === false) {
        echo json_encode(['ok' => false, 'error' => $GLOBAL['groupNotFound']]);
        exit;
    }
    db()->prepare("UPDATE segment SET name=? WHERE id=?")->execute([$newName, $segmentId]);
    auditLog(db(), 'renameSegment', 'id=' . $segmentId . ' | ' . $oldName . ' -> ' . $newName);
    echo json_encode(['ok' => true, 'name' => $newName]);
    exit;

} elseif ($action == 'updateSegment') {
    $segment = new Segment();
    $segment->lookupSegment((int)$_REQUEST['id']);
    $_auTOldName   = $segment->name;
    $_auTOldHidden = (int)$segment->getHidden();
    $segmentId = (int)$_REQUEST['id'];
    $_auTOldCatRow = db()->prepare("SELECT m.name FROM metagroup m JOIN metagroup_member mm ON mm.metagroup_id=m.id WHERE mm.segment_id=? AND m.is_filter=0 LIMIT 1");
    $_auTOldCatRow->execute([$segmentId]);
    $_auTOldCat = $_auTOldCatRow->fetchColumn() ?: '—';
    $segment->name = $_REQUEST['name'];
    $segment->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
    $segment->save();
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    $_auTNewCat = '—';
    if ($categoryId > 0) {
        $_auTCatNameRow = db()->prepare("SELECT name FROM metagroup WHERE id=? LIMIT 1");
        $_auTCatNameRow->execute([$categoryId]);
        $_auTNewCat = $_auTCatNameRow->fetchColumn() ?: "id=$categoryId";
    }
    db()->prepare("DELETE FROM metagroup_member WHERE segment_id=? AND metagroup_id IN (
        SELECT id FROM metagroup WHERE is_filter=0
    )")->execute([$segmentId]);
    if ($categoryId > 0) {
        db()->prepare("INSERT IGNORE INTO metagroup_member (metagroup_id, segment_id) VALUES (?, ?)")->execute([$categoryId, $segmentId]);
    }
    $_auTChanges = [];
    if ($_auTOldName !== $segment->name) $_auTChanges[] = "nom: «{$_auTOldName}» → «{$segment->name}»";
    if ($_auTOldHidden !== (isset($_REQUEST['hidden']) ? 1 : 0)) $_auTChanges[] = "masqué: " . ($_auTOldHidden ? 'oui' : 'non') . " → " . (isset($_REQUEST['hidden']) ? 'oui' : 'non');
    if ($_auTOldCat !== $_auTNewCat) $_auTChanges[] = "catégorie: «{$_auTOldCat}» → «{$_auTNewCat}»";
    $auTDetail = "id=$segmentId | {$segment->name}" . (count($_auTChanges) ? ' | ' . implode(', ', $_auTChanges) : ' | aucun changement');
    auditLog(db(), 'updateSegment', $auTDetail);
    if (!empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
        $importedFrom = [];
        foreach ($_REQUEST['importFrom'] as $srcId) {
            $srcId = (int)$srcId;
            if ($srcId > 0 && $srcId !== $segmentId) {
                db()->prepare(
                "INSERT IGNORE INTO contact_segment (user_id, segment_id)
                 SELECT user_id, ? FROM contact_segment WHERE segment_id = ?"
            )->execute([$segmentId, $srcId]);
                $importedFrom[] = $srcId;
            }
        }
        if ($importedFrom) {
            $_auImpSrc = db()->prepare("SELECT name FROM segment WHERE id=?");
            $srcNames = [];
            foreach ($importedFrom as $sid) { $_auImpSrc->execute([$sid]); $srcNames[] = $_auImpSrc->fetchColumn() ?: "id=$sid"; }
            auditLog(db(), 'importSegmentMembers', "vers groupe: {$segment->name} (id=$segmentId) | depuis: " . implode(', ', $srcNames));
        }
    }

} elseif ($action == 'assignSegment') {
    $user = new Contact();
    $user->lookupUser((int)$_REQUEST['id']);
    $user->assignSegment((int)$_REQUEST['segmentId']);
    $_auSeg = db()->prepare("SELECT name FROM segment WHERE id=?");
    $_auSeg->execute([(int)$_REQUEST['segmentId']]);
    auditLog(db(), 'assignSegment', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) → groupe: " . ($_auSeg->fetchColumn() ?: "id={$_REQUEST['segmentId']}"), (int)$_REQUEST['id']);

} elseif ($action == 'unassignSegment') {
    $user = new Contact();
    $user->lookupUser((int)$_REQUEST['id']);
    $user->unassignSegment((int)$_REQUEST['segmentId']);
    $_auSeg = db()->prepare("SELECT name FROM segment WHERE id=?");
    $_auSeg->execute([(int)$_REQUEST['segmentId']]);
    auditLog(db(), 'unassignSegment', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) ← groupe: " . ($_auSeg->fetchColumn() ?: "id={$_REQUEST['segmentId']}"), (int)$_REQUEST['id']);
}
