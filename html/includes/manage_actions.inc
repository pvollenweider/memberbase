<?php
if (isset($_REQUEST['action'])) {
    if ($_REQUEST['action'] == 'updateUser') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->firstName = unquote($_REQUEST['firstName']);
        $user->lastName = unquote($_REQUEST['lastName']);
        $user->society = unquote($_REQUEST['society']);
        $user->sexe = unquote($_REQUEST['sexe']);
        $user->title = unquote($_REQUEST['title']);
        $user->address = unquote($_REQUEST['address']);
        $user->npa = unquote($_REQUEST['npa']);
        $user->tel= unquote($_REQUEST['tel']);
        $user->telProf= unquote($_REQUEST['telProf']);
        $user->portable = unquote($_REQUEST['portable']);
        $user->fax = unquote($_REQUEST['fax']);
        $user->email = unquote($_REQUEST['email']);
        $user->web = unquote($_REQUEST['web']);
        $user->birthDay = unquote(formatedDateToTimeStamp($_REQUEST['birthDay']));
        $user->comment = unquote($_REQUEST['comment']);
        $user->save();
    } else if ($_REQUEST['action'] == 'updateCategoryOrder') {
        if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $stmt = $pdo->prepare("UPDATE metagroup SET sort_order=? WHERE id=? AND name IS NOT NULL AND is_filter=0");
            foreach ($_REQUEST['ids'] as $i => $id) {
                $stmt->execute([$i, (int)$id]);
            }
        }
    } else if ($_REQUEST['action'] == 'updateTeamCategory') {
        $teamId = (int)$_REQUEST['id'];
        $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
        // Remove any existing category junction for this team
        $pdo->prepare("DELETE FROM metagroup WHERE teamid=? AND id IN (
            SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS cats
        )")->execute([$teamId]);
        // Assign new category
        if ($categoryId > 0) {
            $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)")->execute([$categoryId, $teamId]);
        }
    } else if ($_REQUEST['action'] == 'saveSettings') {
        $keys = ['default_team', 'membre_team', 'archive_id'];
        $stmt = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        foreach ($keys as $key) {
            if (isset($_REQUEST[$key])) {
                $stmt->execute([$key, (int)$_REQUEST[$key]]);
            }
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&saved=1') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'addUser') {
        $user = new User();
        $user->firstName = unquote($_REQUEST['firstName']);
        $user->lastName = unquote($_REQUEST['lastName']);
        $user->society = unquote($_REQUEST['society']);
        $user->sexe = unquote($_REQUEST['sexe']);
        $user->title = unquote($_REQUEST['title']);
        $user->address = unquote($_REQUEST['address']);
        $user->npa = unquote($_REQUEST['npa']);
        $user->tel= unquote($_REQUEST['tel']);
        $user->telProf= unquote($_REQUEST['telProf']);
        $user->portable = unquote($_REQUEST['portable']);
        $user->fax = unquote($_REQUEST['fax']);
        $user->email = unquote($_REQUEST['email']);
        $user->web = unquote($_REQUEST['web']);
        $user->birthDay = unquote(formatedDateToTimeStamp($_REQUEST['birthDay']));
        $user->comment = unquote($_REQUEST['comment']);
        $userid = $user->save();
    } else if ($_REQUEST['action'] == 'deleteTeam') {
        $team = new Team();
        $team->lookupTeam($_REQUEST['id']);
        $team->remove();
    } else if ($_REQUEST['action'] == 'deleteTeamForce') {
        $teamId = (int) $_REQUEST['id'];
        // Remove all memberships then delete the team
        $pdo->prepare("DELETE FROM user_properties WHERE parameter = ?")->execute(["team_$teamId"]);
        $pdo->prepare("DELETE FROM team WHERE id = ?")->execute([$teamId]);
    } else if ($_REQUEST['action'] == 'reassignTeam') {
        $teamId       = (int) $_REQUEST['id'];
        $targetTeamId = (int) $_REQUEST['targetTeamId'];
        if ($targetTeamId > 0 && $targetTeamId !== $teamId) {
            // Move memberships not already in target team
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
            // Remove any remaining (duplicates)
            $pdo->prepare("DELETE FROM user_properties WHERE parameter = ?")->execute(["team_$teamId"]);
            $pdo->prepare("DELETE FROM team WHERE id = ?")->execute([$teamId]);
        }
    } else if ($_REQUEST['action'] == 'importTeamMembers') {
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
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'importDonors') {
        $teamId  = (int)$_REQUEST['id'];
        $year    = isset($_REQUEST['donor_year'])   ? (int)$_REQUEST['donor_year']   : (int)date('Y');
        $minSum  = isset($_REQUEST['donor_minsum']) ? (int)$_REQUEST['donor_minsum'] : 1;
        if (!in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }
        if ($teamId > 0 && $year >= 2000 && $year <= 2100) {
            $from = mktime(0, 0, 0, 1, 0, $year);
            $to   = mktime(0, 0, 0, 1, 1, $year + 1);
            // Insert donors not already in the team
            $pdo->prepare("
                INSERT INTO user_properties (user_id, parameter, value)
                SELECT u.id, ?, 'true'
                FROM users u
                JOIN compta c ON c.user_id = u.id
                WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
                  AND c.date > ? AND c.date < ?
                  AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
                GROUP BY u.id
                HAVING SUM(c.sum) >= ?
            ")->execute(["team_$teamId", $from, $to, "team_$teamId", $minSum]);
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId . '&imported=donors') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'bulkHide') {
        if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $stmt = $pdo->prepare("UPDATE team SET hidden=1 WHERE id=?");
            foreach ($_REQUEST['ids'] as $tid) { $stmt->execute([(int)$tid]); }
        }
    } else if ($_REQUEST['action'] == 'bulkShow') {
        if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $stmt = $pdo->prepare("UPDATE team SET hidden=0 WHERE id=?");
            foreach ($_REQUEST['ids'] as $tid) { $stmt->execute([(int)$tid]); }
        }
    } else if ($_REQUEST['action'] == 'bulkCreateMetagroup') {
        $name = trim($_REQUEST['metagroupName'] ?? '');
        if ($name && !empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $mid = updateAndGetMaxVal("metagroup_id");
            $pdo->prepare("INSERT INTO metagroup (id, name) VALUES (?, ?)")->execute([$mid, $name]);
            $stmt = $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)");
            foreach ($_REQUEST['ids'] as $tid) {
                $tid = (int)$tid;
                if ($tid > 0) $stmt->execute([$mid, $tid]);
            }
            echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateMetagroup&id=' . $mid) . ');</script>';
            exit;
        }
    } else if ($_REQUEST['action'] == 'addTeam') {
        $team = new Team();
        $team->name = $_REQUEST['name'];
        $team->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
        $team->save();
    } else if ($_REQUEST['action'] == 'addTeamWithImport') {
        $team = new Team();
        $team->name = $_REQUEST['name'];
        $team->setHidden(0);
        $team->save();
        // getId() is null for new teams (save() doesn't set $this->id); read from maxval
        $newTeamId = (int) $pdo->query("SELECT value FROM maxval WHERE parameter='teamid'")->fetchColumn();
        $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
        if ($newTeamId && $categoryId > 0) {
            $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)")->execute([$categoryId, $newTeamId]);
        }
        if ($newTeamId && !empty($_REQUEST['importFrom']) && is_array($_REQUEST['importFrom'])) {
            foreach ($_REQUEST['importFrom'] as $srcId) {
                $srcId = (int) $srcId;
                if ($srcId <= 0) continue;
                // Insert members from source team not already in new team
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
    } else if ($_REQUEST['action'] == 'addMetagroup') {
        $mg = new Metagroup();
        $mg->name = $_REQUEST['name'];
        $mg->save();
        $newMgId = (int)$pdo->query("SELECT value FROM maxval WHERE parameter='metagroup_id'")->fetchColumn();
        $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 0 ? 0 : 1) : 1;
        if ($newMgId) {
            $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, $newMgId]);
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateMetagroup&id=' . $newMgId) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'updateMetagroup') {
        $mg = new Metagroup();
        $mg->lookupMetagroup((int)$_REQUEST['id']);
        $mg->name = $_REQUEST['name'];
        $mg->save();
        $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 1 ? 1 : 0) : 1;
        $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, (int)$_REQUEST['id']]);
    } else if ($_REQUEST['action'] == 'updateMetagroupTeams') {
        $mgId = (int)$_REQUEST['id'];
        $selected = !empty($_REQUEST['teams']) && is_array($_REQUEST['teams'])
            ? array_map('intval', $_REQUEST['teams']) : [];
        // Remove all existing junction rows for this metagroup
        $pdo->prepare("DELETE FROM metagroup WHERE id=? AND teamid IS NOT NULL")->execute([$mgId]);
        // Re-insert selected
        $stmt = $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)");
        foreach ($selected as $teamId) {
            if ($teamId > 0) $stmt->execute([$mgId, $teamId]);
        }
    } else if ($_REQUEST['action'] == 'deleteMetagroup') {
        $mgId = (int)$_REQUEST['id'];
        $pdo->prepare("DELETE FROM metagroup WHERE id=?")->execute([$mgId]);
    } else if ($_REQUEST['action'] == 'updateTeam') {
        $team = new Team();
        $team->lookupTeam($_REQUEST['id']);
        $team->name = $_REQUEST['name'];
        $team->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
        $team->save();
        $teamId = (int)$_REQUEST['id'];
        $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
        $pdo->prepare("DELETE FROM metagroup WHERE teamid=? AND id IN (
            SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS cats
        )")->execute([$teamId]);
        if ($categoryId > 0) {
            $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)")->execute([$categoryId, $teamId]);
        }
    } else if ($_REQUEST['action'] == 'addComptaType') {
        $label = trim($_REQUEST['label'] ?? '');
        $color = $_REQUEST['color'] ?? 'bg-light';
        $allowed = ['bg-primary-subtle','bg-secondary-subtle','bg-success-subtle','bg-danger-subtle','bg-warning-subtle','bg-info-subtle','bg-light','bg-dark-subtle'];
        if (!in_array($color, $allowed)) $color = 'bg-light';
        if ($label !== '') {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM compta_type")->fetchColumn();
            $pdo->prepare("INSERT INTO compta_type (label, color, sort_order) VALUES (?, ?, ?)")->execute([$label, $color, $maxOrder + 1]);
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=manageComptaTypes') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'updateComptaType') {
        $id = (int)($_REQUEST['id'] ?? 0);
        $label = trim($_REQUEST['label'] ?? '');
        $color = $_REQUEST['color'] ?? 'bg-light';
        $sortOrder = (int)($_REQUEST['sort_order'] ?? 0);
        $isCotisation = isset($_REQUEST['is_cotisation']) ? (int)$_REQUEST['is_cotisation'] : 0;
        $isExcluded = isset($_REQUEST['is_excluded_from_donation']) ? (int)$_REQUEST['is_excluded_from_donation'] : 0;
        $allowed = ['bg-primary-subtle','bg-secondary-subtle','bg-success-subtle','bg-danger-subtle','bg-warning-subtle','bg-info-subtle','bg-light','bg-dark-subtle'];
        if (!in_array($color, $allowed)) $color = 'bg-light';
        if ($id > 0 && $label !== '') {
            $pdo->prepare("UPDATE compta_type SET label=?, color=?, sort_order=?, is_cotisation=?, is_excluded_from_donation=? WHERE id=?")->execute([$label, $color, $sortOrder, $isCotisation, $isExcluded, $id]);
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=manageComptaTypes') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'deleteComptaType') {
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE type_id=?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM compta_type WHERE id=?")->execute([$id]);
            }
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=manageComptaTypes') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'addMembership') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->addMembership($_REQUEST['teamId']);
        //$user->save();
    } else if ($_REQUEST['action'] == 'removeMembership') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->removeMembership($_REQUEST['teamId']);
        //$user->save();
    } else if ($_REQUEST['action'] == 'addCompta') {
        $compta = new Compta();
        $compta->userId = $_REQUEST['userid'];
        $compta->setTypeId((int)$_REQUEST['type_id']);
        $compta->date = formatedDateToTimeStamp($_REQUEST['date']);
        $compta->libele = unquote($_REQUEST['libele']);
        $compta->sum = str_replace(',','.',$_REQUEST['sum']);
        $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
        $userid = $compta->userId;
        $compta->save();
    } else if ($_REQUEST['action'] == 'updateCompta') {
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        $mydate = $_REQUEST['date'];
        $date = formatedDateToTimeStamp($mydate);
        $compta->date = $date;
        $logger = Logger::getLogger('Action.updateCompta');
        $logger->debug("date is $date / $mydate");
        $compta->libele = unquote($_REQUEST['libele']);
        $compta->sum = str_replace(',','.',$_REQUEST['sum']);
        $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
        $compta->setTypeId((int)$_REQUEST['type_id']);
        $compta->setWantsAttestation(isset($_REQUEST['wants_attestation']));
        $compta->save();
    } else if ($_REQUEST['action'] == 'toggleWantsAttestation') {
        $comptaid = (int)$_REQUEST['comptaid'];
        $value    = isset($_REQUEST['wants_attestation']) ? 1 : 0;
        $pdo->prepare("UPDATE compta SET wants_attestation=? WHERE id=?")->execute([$value, $comptaid]);
        // redirect back to compta view, preserving year
        $year   = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
        $userid = (int)$_REQUEST['userid'];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=compta&userid=' . $userid . '&year=' . $year);
        exit;
    } else if ($_REQUEST['action'] == 'addSuivi') {
        $userProperty = new UserProperty();
        $userProperty->userId = $_REQUEST['userid'];
        $userProperty->parameter = $_REQUEST['parameter'];
        $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
        $userProperty->value = unquote(str_replace(',','.',$_REQUEST['value']));
        $userProperty->save();
    } else if ($_REQUEST['action'] == 'updateSuivi') {
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
        $userProperty->userId = $_REQUEST['userid'];
        $userProperty->parameter = $_REQUEST['parameter'];
        $userProperty->value = unquote($_REQUEST['value']);
        $userProperty->save();
    }
}
?>
