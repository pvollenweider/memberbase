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
    } else if ($_REQUEST['action'] == 'updateMetagroup') {
        $mg = new Metagroup();
        $mg->lookupMetagroup((int)$_REQUEST['id']);
        $mg->name = $_REQUEST['name'];
        $mg->save();
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
        $compta->type = $_REQUEST['type'];
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
        $compta->type = $_REQUEST['type'];
        $compta->save();
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
