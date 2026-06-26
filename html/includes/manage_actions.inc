<?php
if (isset($_REQUEST['action'])) {
    if ($_REQUEST['action'] == 'updateUser') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $_auBefore = [
            'firstName' => (string)$user->firstName,
            'lastName'  => (string)$user->lastName,
            'society'   => (string)$user->society,
            'sexe'      => (string)$user->sexe,
            'title'     => (string)$user->title,
            'address'   => (string)$user->address,
            'npa'       => (string)$user->npa,
            'tel'       => (string)$user->tel,
            'telProf'   => (string)$user->telProf,
            'portable'  => (string)$user->portable,
            'fax'       => (string)$user->fax,
            'email'     => (string)$user->email,
            'web'       => (string)$user->web,
            'birthDay'  => timeStampToformatedDate((int)$user->birthDay),
            'comment'   => (string)$user->comment,
        ];
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
        $_auAfter = [
            'firstName' => (string)$user->firstName,
            'lastName'  => (string)$user->lastName,
            'society'   => (string)$user->society,
            'sexe'      => (string)$user->sexe,
            'title'     => (string)$user->title,
            'address'   => (string)$user->address,
            'npa'       => (string)$user->npa,
            'tel'       => (string)$user->tel,
            'telProf'   => (string)$user->telProf,
            'portable'  => (string)$user->portable,
            'fax'       => (string)$user->fax,
            'email'     => (string)$user->email,
            'web'       => (string)$user->web,
            'birthDay'  => timeStampToformatedDate((int)$user->birthDay),
            'comment'   => (string)$user->comment,
        ];
        $_auDiffs = [];
        foreach ($_auBefore as $_f => $_v) {
            if ($_v !== $_auAfter[$_f]) {
                $_auDiffs[] = "{$_f}: «{$_v}» → «{$_auAfter[$_f]}»";
            }
        }
        $user->save();
        $auDetail = "id={$_REQUEST['id']} | {$user->firstName} {$user->lastName}";
        if ($_auDiffs) { $auDetail .= ' | ' . implode(' ; ', $_auDiffs); }
        else           { $auDetail .= ' | (aucune modification)'; }
        auditLog($pdo, 'updateUser', $auDetail);
        $_savedOk = true;
    } else if ($_REQUEST['action'] == 'updateCategoryOrder') {
        if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $stmt = $pdo->prepare("UPDATE metagroup SET sort_order=? WHERE id=? AND name IS NOT NULL AND is_filter=0");
            foreach ($_REQUEST['ids'] as $i => $id) {
                $stmt->execute([$i, (int)$id]);
            }
        }
    } else if ($_REQUEST['action'] == 'updateComptaTypeOrder') {
        if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
            $stmt = $pdo->prepare("UPDATE compta_type SET sort_order=? WHERE id=?");
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
        auditLog($pdo, 'addUser', "id=$userid | {$user->firstName} {$user->lastName} | email: {$user->email}");
    } else if ($_REQUEST['action'] == 'deleteTeam') {
        $team = new Team();
        $team->lookupTeam($_REQUEST['id']);
        auditLog($pdo, 'deleteTeam', "id={$team->id} {$team->name}");
        $team->remove();
    } else if ($_REQUEST['action'] == 'deleteTeamForce') {
        $teamId = (int) $_REQUEST['id'];
        $teamName = $pdo->prepare("SELECT name FROM team WHERE id=?");
        $teamName->execute([$teamId]);
        auditLog($pdo, 'deleteTeamForce', "id=$teamId " . ($teamName->fetchColumn() ?: ''));
        $pdo->prepare("DELETE FROM user_properties WHERE parameter = ?")->execute(["team_$teamId"]);
        $pdo->prepare("DELETE FROM team WHERE id = ?")->execute([$teamId]);
    } else if ($_REQUEST['action'] == 'reassignTeam') {
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
        $_auTeamN = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auTeamN->execute([$teamId]);
        auditLog($pdo, 'importTeamMembers', "vers groupe: " . ($_auTeamN->fetchColumn() ?: "id=$teamId") . " | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'importCotisants') {
        $teamId = (int)$_REQUEST['id'];
        $year   = isset($_REQUEST['cotis_year']) ? (int)$_REQUEST['cotis_year'] : (int)date('Y');
        // Build cotisation type IDs from already-loaded $comptaTypes
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
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId . '&imported=cotisants') . ');</script>';
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
        $_auTeamD = $pdo->prepare("SELECT name FROM team WHERE id=?"); $_auTeamD->execute([$teamId]);
        auditLog($pdo, 'importDonors', "vers groupe: " . ($_auTeamD->fetchColumn() ?: "id=$teamId") . " | année: $year | min: {$minSum} CHF");
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateTeam&id=' . $teamId . '&imported=donors') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'bulkHide') {
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
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'bulkShow') {
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
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'undoGroupVisibility') {
        $hidden = (int)($_REQUEST['hidden'] ?? 0);
        $ids = array_map('intval', array_filter(explode(',', $_REQUEST['ids'] ?? ''), 'is_numeric'));
        if ($ids) {
            $stmt = $pdo->prepare("UPDATE team SET hidden=? WHERE id=?");
            foreach ($ids as $tid) { $stmt->execute([$hidden, $tid]); }
            auditLog($pdo, $hidden ? 'bulkHide' : 'bulkShow', count($ids) . " groupes (undo) " . ($hidden ? "masqués" : "affichés"));
        }
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
        exit;
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
            auditLog($pdo, 'bulkCreateMetagroup', "nouveau groupe filtre: $name (id=$mid) | " . count($_REQUEST['ids']) . " groupes");
            echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateMetagroup&id=' . $mid) . ');</script>';
            exit;
        }
    } else if ($_REQUEST['action'] == 'createLapsedGroup') {
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
            $prevTeamStmt->execute(["Membre " . ($yr - 1)]);
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
        $newTeamId = (int)$pdo->query("SELECT value FROM maxval WHERE parameter='teamid'")->fetchColumn();
        if ($newTeamId > 0) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, 'true')");
            foreach ($userIds as $uid) {
                $ins->execute([(int)$uid, "team_$newTeamId"]);
            }
        }
        auditLog($pdo, 'createLapsedGroup', "type: $groupType | année: $yr | groupe créé: $groupName (id=$newTeamId) | " . count($userIds) . " membres");
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?team=' . $newTeamId) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'addTeam') {
        $team = new Team();
        $team->name = $_REQUEST['name'];
        $team->setHidden(isset($_REQUEST['hidden']) ? 1 : 0);
        $team->save();
        $_auNewTeam = (int)$pdo->query("SELECT value FROM maxval WHERE parameter='teamid'")->fetchColumn();
        auditLog($pdo, 'addTeam', "id=$_auNewTeam | {$_REQUEST['name']}");
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
        auditLog($pdo, 'addTeamWithImport', "id=$newTeamId | {$_REQUEST['name']} | depuis groupes: " . implode(',', array_map('intval', $_REQUEST['importFrom'] ?? [])));
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&tab=groups') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'addMetagroup') {
        $mg = new Metagroup();
        $mg->name = $_REQUEST['name'];
        $mg->save();
        $newMgId = (int)$pdo->query("SELECT value FROM maxval WHERE parameter='metagroup_id'")->fetchColumn();
        $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 0 ? 0 : 1) : 1;
        if ($newMgId) {
            $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, $newMgId]);
        }
        auditLog($pdo, 'addMetagroup', "id=$newMgId | {$_REQUEST['name']} | filtre: " . ($isFilter ? 'oui' : 'non'));
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=updateMetagroup&id=' . $newMgId . '&created=1') . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'updateMetagroup') {
        $mg = new Metagroup();
        $mg->lookupMetagroup((int)$_REQUEST['id']);
        $_auMgOldName = $mg->name;
        $mg->name = $_REQUEST['name'];
        $mg->save();
        $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 1 ? 1 : 0) : 1;
        $_auMgOldFilter = null;
        $_auMgOldRow = $pdo->prepare("SELECT is_filter FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
        $_auMgOldRow->execute([(int)$_REQUEST['id']]);
        $_auMgOldFilter = (int)$_auMgOldRow->fetchColumn();
        $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, (int)$_REQUEST['id']]);
        $_auMgChanges = [];
        if ($_auMgOldName !== $mg->name) $_auMgChanges[] = "nom: «{$_auMgOldName}» → «{$mg->name}»";
        if ($_auMgOldFilter !== $isFilter) $_auMgChanges[] = "filtre: " . ($_auMgOldFilter ? 'oui' : 'non') . " → " . ($isFilter ? 'oui' : 'non');
        $auMgDetail = "id={$_REQUEST['id']} | {$mg->name}" . (count($_auMgChanges) ? ' | ' . implode(', ', $_auMgChanges) : ' | aucun changement');
        auditLog($pdo, 'updateMetagroup', $auMgDetail);
        $_mgRedirectTab = $isFilter ? 'filters' : 'categories';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=' . $_mgRedirectTab);
        exit;
    } else if ($_REQUEST['action'] == 'updateMetagroupTeams') {
        $mgId = (int)$_REQUEST['id'];
        $selected = !empty($_REQUEST['teams']) && is_array($_REQUEST['teams'])
            ? array_map('intval', $_REQUEST['teams']) : [];
        // Determine if this is a category (is_filter=0)
        $_mgIsFilterRow = $pdo->prepare("SELECT is_filter FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
        $_mgIsFilterRow->execute([$mgId]);
        $_mgIsCategory = ((int)$_mgIsFilterRow->fetchColumn() === 0);
        // For categories: remove each selected team from any other category first
        if ($_mgIsCategory && !empty($selected)) {
            $_stmtEvict = $pdo->prepare(
                "DELETE FROM metagroup WHERE teamid=? AND id!=? AND id IN (SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS _cats)"
            );
            foreach ($selected as $teamId) {
                if ($teamId > 0) $_stmtEvict->execute([$teamId, $mgId]);
            }
        }
        $pdo->prepare("DELETE FROM metagroup WHERE id=? AND teamid IS NOT NULL")->execute([$mgId]);
        $stmt = $pdo->prepare("INSERT INTO metagroup (id, teamid) VALUES (?, ?)");
        foreach ($selected as $teamId) {
            if ($teamId > 0) $stmt->execute([$mgId, $teamId]);
        }
        $_auMgName = $pdo->prepare("SELECT name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
        $_auMgName->execute([$mgId]);
        $_auMgNameVal = $_auMgName->fetchColumn() ?: "id=$mgId";
        $selectedStr = empty($selected) ? 'aucun' : implode(', ', $selected);
        auditLog($pdo, 'updateMetagroupTeams', "groupe filtre: {$_auMgNameVal} (id={$mgId}) | " . count($selected) . " groupes: [{$selectedStr}]");
    } else if ($_REQUEST['action'] == 'deleteMetagroup') {
        $mgId = (int)$_REQUEST['id'];
        $_auMgDel = $pdo->prepare("SELECT name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
        $_auMgDel->execute([$mgId]);
        auditLog($pdo, 'deleteMetagroup', "id=$mgId | " . ($_auMgDel->fetchColumn() ?: ''));
        $pdo->prepare("DELETE FROM metagroup WHERE id=?")->execute([$mgId]);
    } else if ($_REQUEST['action'] == 'updateTeam') {
        $team = new Team();
        $team->lookupTeam($_REQUEST['id']);
        $_auTOldName   = $team->name;
        $_auTOldHidden = (int)$team->isHidden();
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
                    $pdo->prepare("INSERT IGNORE INTO user_properties (userid, parameter)
                        SELECT userid, ? FROM user_properties WHERE parameter = ? AND userid NOT IN
                        (SELECT userid FROM user_properties WHERE parameter = ?)"
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
    } else if ($_REQUEST['action'] == 'addComptaType') {
        $label = trim($_REQUEST['label'] ?? '');
        $color = $_REQUEST['color'] ?? 'bg-light';
        $allowed = ['bg-primary-subtle','bg-secondary-subtle','bg-success-subtle','bg-danger-subtle','bg-warning-subtle','bg-info-subtle','bg-light','bg-dark-subtle','ca-orange-subtle','ca-teal-subtle','ca-pink-subtle','ca-purple-subtle','ca-indigo-subtle','ca-lime-subtle'];
        if (!in_array($color, $allowed)) $color = 'bg-light';
        if ($label !== '') {
            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM compta_type")->fetchColumn();
            $pdo->prepare("INSERT INTO compta_type (label, color, sort_order) VALUES (?, ?, ?)")->execute([$label, $color, $maxOrder + 1]);
            auditLog($pdo, 'addComptaType', "label: $label | couleur: $color");
        }
        $_rvAllowed = ['settings','manageComptaTypes'];
        $rv  = in_array($_REQUEST['returnView'] ?? '', $_rvAllowed) ? $_REQUEST['returnView'] : 'settings';
        $rtb = preg_replace('/[^a-zA-Z]/', '', $_REQUEST['returnTab'] ?? 'compta');
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'updateComptaType') {
        $id = (int)($_REQUEST['id'] ?? 0);
        $label = trim($_REQUEST['label'] ?? '');
        $color = $_REQUEST['color'] ?? 'bg-light';
        $sortOrder = (int)($_REQUEST['sort_order'] ?? 0);
        $isCotisation = isset($_REQUEST['is_cotisation']) ? (int)$_REQUEST['is_cotisation'] : 0;
        $isExcluded = isset($_REQUEST['is_excluded_from_donation']) ? (int)$_REQUEST['is_excluded_from_donation'] : 0;
        $allowed = ['bg-primary-subtle','bg-secondary-subtle','bg-success-subtle','bg-danger-subtle','bg-warning-subtle','bg-info-subtle','bg-light','bg-dark-subtle','ca-orange-subtle','ca-teal-subtle','ca-pink-subtle','ca-purple-subtle','ca-indigo-subtle','ca-lime-subtle'];
        if (!in_array($color, $allowed)) $color = 'bg-light';
        if ($id > 0 && $label !== '') {
            $pdo->prepare("UPDATE compta_type SET label=?, color=?, sort_order=?, is_cotisation=?, is_excluded_from_donation=? WHERE id=?")->execute([$label, $color, $sortOrder, $isCotisation, $isExcluded, $id]);
            auditLog($pdo, 'updateComptaType', "id=$id | label: $label");
        }
        $_rvAllowed = ['settings','manageComptaTypes'];
        $rv  = in_array($_REQUEST['returnView'] ?? '', $_rvAllowed) ? $_REQUEST['returnView'] : 'settings';
        $rtb = preg_replace('/[^a-zA-Z]/', '', $_REQUEST['returnTab'] ?? 'compta');
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'deleteComptaType') {
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE type_id=?");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() === 0) {
                $_auCtLabel = $pdo->prepare("SELECT label FROM compta_type WHERE id=?"); $_auCtLabel->execute([$id]);
                auditLog($pdo, 'deleteComptaType', "id=$id | label: " . ($_auCtLabel->fetchColumn() ?: ''));
                $pdo->prepare("DELETE FROM compta_type WHERE id=?")->execute([$id]);
            }
        }
        $_rvAllowed = ['settings','manageComptaTypes'];
        $rv  = in_array($_REQUEST['returnView'] ?? '', $_rvAllowed) ? $_REQUEST['returnView'] : 'settings';
        $rtb = preg_replace('/[^a-zA-Z]/', '', $_REQUEST['returnTab'] ?? 'compta');
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb) . ');</script>';
        exit;
    } else if ($_REQUEST['action'] == 'addMembership') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->addMembership((int)$_REQUEST['teamId']);
        $_auTeam = $pdo->prepare("SELECT name FROM team WHERE id=?");
        $_auTeam->execute([(int)$_REQUEST['teamId']]);
        auditLog($pdo, 'addMembership', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) → groupe: " . ($_auTeam->fetchColumn() ?: "id={$_REQUEST['teamId']}"));
    } else if ($_REQUEST['action'] == 'removeMembership') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->removeMembership((int)$_REQUEST['teamId']);
        $_auTeam = $pdo->prepare("SELECT name FROM team WHERE id=?");
        $_auTeam->execute([(int)$_REQUEST['teamId']]);
        auditLog($pdo, 'removeMembership', "membre: {$user->firstName} {$user->lastName} (id={$_REQUEST['id']}) ← groupe: " . ($_auTeam->fetchColumn() ?: "id={$_REQUEST['teamId']}"));
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
        $_auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auUser->execute([(int)$compta->userId]);
        $_auType = $comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}";
        auditLog($pdo, 'addCompta', "membre: " . ($_auUser->fetchColumn() ?: "id={$compta->userId}") . " | {$_auType} | {$compta->sum} CHF | {$_REQUEST['date']}");
    } else if ($_REQUEST['action'] == 'updateCompta') {
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        $_auBefore2 = [
            'type'        => ($comptaTypes[(int)$compta->type_id]->label ?? "type={$compta->type_id}"),
            'date'        => timeStampToformatedDate((int)$compta->date),
            'libele'      => (string)$compta->libele,
            'sum'         => number_format((float)$compta->sum, 2, '.', ''),
            'quittance'   => (string)$compta->quittance,
            'attestation' => $compta->wants_attestation ? 'oui' : 'non',
        ];
        $mydate = $_REQUEST['date'];
        $date = formatedDateToTimeStamp($mydate);
        $compta->date = $date;
        $compta->libele = unquote($_REQUEST['libele']);
        $compta->sum = str_replace(',','.',$_REQUEST['sum']);
        $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
        $compta->setTypeId((int)$_REQUEST['type_id']);
        $compta->setWantsAttestation(isset($_REQUEST['wants_attestation']));
        $_auAfter2 = [
            'type'        => ($comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}"),
            'date'        => $mydate,
            'libele'      => (string)$compta->libele,
            'sum'         => number_format((float)$compta->sum, 2, '.', ''),
            'quittance'   => (string)$compta->quittance,
            'attestation' => isset($_REQUEST['wants_attestation']) ? 'oui' : 'non',
        ];
        $compta->save();
        $_auUser2 = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auUser2->execute([(int)$compta->userId]);
        $_auDiffs2 = [];
        foreach ($_auBefore2 as $_f => $_v) {
            if ($_v !== $_auAfter2[$_f]) {
                $_auDiffs2[] = "{$_f}: «{$_v}» → «{$_auAfter2[$_f]}»";
            }
        }
        $auDetail2 = "compta#={$_REQUEST['comptaid']} | membre: " . ($_auUser2->fetchColumn() ?: "id={$compta->userId}");
        if ($_auDiffs2) { $auDetail2 .= ' | ' . implode(' ; ', $_auDiffs2); }
        else            { $auDetail2 .= ' | (aucune modification)'; }
        auditLog($pdo, 'updateCompta', $auDetail2);
    } else if ($_REQUEST['action'] == 'toggleWantsAttestation') {
        $comptaid = (int)$_REQUEST['comptaid'];
        $value    = isset($_REQUEST['wants_attestation']) ? 1 : 0;
        $pdo->prepare("UPDATE compta SET wants_attestation=? WHERE id=?")->execute([$value, $comptaid]);
        $_auTwa = $pdo->prepare("SELECT CONCAT(u.firstName,' ',u.lastName) FROM compta c JOIN users u ON u.id=c.user_id WHERE c.id=?");
        $_auTwa->execute([$comptaid]);
        auditLog($pdo, 'toggleWantsAttestation', "compta#=$comptaid | " . ($_auTwa->fetchColumn() ?: '') . " | attestation: " . ($value ? 'oui' : 'non'));
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
        $_auSuiviU = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auSuiviU->execute([(int)$_REQUEST['userid']]);
        auditLog($pdo, 'addSuivi', "membre: " . ($_auSuiviU->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})");
    } else if ($_REQUEST['action'] == 'updateSuivi') {
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
        $userProperty->userId = $_REQUEST['userid'];
        $userProperty->parameter = $_REQUEST['parameter'];
        $userProperty->value = unquote($_REQUEST['value']);
        $userProperty->save();
        $_auSuiviU2 = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auSuiviU2->execute([(int)$_REQUEST['userid']]);
        auditLog($pdo, 'updateSuivi', "membre: " . ($_auSuiviU2->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | suivi#={$_REQUEST['suiviid']} | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})");

    // ── Auth actions ─────────────────────────────────────────────────────────

    } else if ($_REQUEST['action'] === 'logout') {
        authLogout();
        header('Location: login.php');
        exit;

    } else if ($_REQUEST['action'] === 'changePassword') {
        $currentUser = authUser();
        $pwNew       = $_POST['pw_new']     ?? '';
        $pwConfirm   = $_POST['pw_confirm'] ?? '';
        $pwCurrent   = $_POST['pw_current'] ?? '';
        $forced      = $currentUser->force_password_change;
        $errParam    = '';

        if (strlen($pwNew) < 8) {
            $errParam = urlencode('Le mot de passe doit contenir au moins 8 caractères.');
        } elseif ($pwNew !== $pwConfirm) {
            $errParam = urlencode('Les deux mots de passe ne correspondent pas.');
        } elseif (!$forced) {
            // Must verify current password
            $row = $pdo->prepare("SELECT password_hash FROM app_users WHERE id = ?");
            $row->execute([$currentUser->id]);
            $hash = $row->fetchColumn();
            if (!$hash || !password_verify($pwCurrent, $hash)) {
                $errParam = urlencode('Mot de passe actuel incorrect.');
            }
        }

        if ($errParam) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=changePassword&pw_error=' . $errParam);
            exit;
        }

        $newHash = password_hash($pwNew, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE app_users SET password_hash=?, force_password_change=0 WHERE id=?")
            ->execute([$newHash, $currentUser->id]);
        $_SESSION['force_password_change'] = false;
        auditLog($pdo, 'changePassword', "user={$currentUser->username}");
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;

    } else if ($_REQUEST['action'] === 'createAppUser') {
        if (!isAdmin()) { http_response_code(403); exit; }
        $auUsername    = trim($_POST['au_username']    ?? '');
        $auDisplayName = trim($_POST['au_display_name'] ?? '');
        $auEmail       = trim($_POST['au_email']       ?? '');
        $auRole        = in_array($_POST['au_role'] ?? '', ['admin','user']) ? $_POST['au_role'] : 'user';
        $auPasswordRaw = trim($_POST['au_password'] ?? '');
        $errParam      = '';

        if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $auUsername)) {
            $errParam = urlencode('Identifiant invalide (lettres, chiffres, ., -, _ uniquement).');
        } else {
            try {
                if ($auPasswordRaw === '') {
                    // Token-based invitation: no password set yet
                    $token     = bin2hex(random_bytes(32));
                    $expires   = date('Y-m-d H:i:s', strtotime('+7 days'));
                    $pdo->prepare(
                        "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change, reset_token, token_expires_at)
                         VALUES (?, ?, ?, '', ?, 1, ?, ?)"
                    )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null, $auRole, $token, $expires]);
                    $newId = (int)$pdo->lastInsertId();
                    $_SESSION['invite_token_flash'] = ['uid' => $newId, 'token' => $token];
                    auditLog($pdo, 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=invitation");
                } else {
                    // Classic: password provided by admin
                    $pdo->prepare(
                        "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change)
                         VALUES (?, ?, ?, ?, ?, 1)"
                    )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null,
                                password_hash($auPasswordRaw, PASSWORD_DEFAULT), $auRole]);
                    $newId = (int)$pdo->lastInsertId();
                    auditLog($pdo, 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=password");
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errParam = urlencode('Cet identifiant est déjà utilisé.');
                } else {
                    throw $e;
                }
            }
        }
        if ($errParam) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers&au_error=' . $errParam);
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
        }
        exit;

    } else if ($_REQUEST['action'] === 'deleteAppUser') {
        if (!isAdmin()) { http_response_code(403); exit; }
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId === (int)$_SESSION['app_user_id']) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
            exit;
        }
        // Prevent deleting last admin
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM app_users WHERE role='admin' AND is_active=1")->fetchColumn();
        $targetRole = $pdo->prepare("SELECT role FROM app_users WHERE id=?");
        $targetRole->execute([$targetId]);
        $role = $targetRole->fetchColumn();
        if ($role === 'admin' && $adminCount <= 1) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers&au_error=' . urlencode('Impossible de supprimer le dernier administrateur.'));
            exit;
        }
        $deletedUsername = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
        $deletedUsername->execute([$targetId]);
        auditLog($pdo, 'deleteAppUser', "id=$targetId username=" . ($deletedUsername->fetchColumn() ?: ''));
        $pdo->prepare("DELETE FROM app_users WHERE id=?")->execute([$targetId]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
        exit;

    } else if ($_REQUEST['action'] === 'resetUserPassword') {
        if (!isAdmin()) { http_response_code(403); exit; }
        $targetId  = (int)($_POST['target_id'] ?? 0);
        $tempPw    = bin2hex(random_bytes(6));
        $pdo->prepare("UPDATE app_users SET password_hash=?, force_password_change=1 WHERE id=?")
            ->execute([password_hash($tempPw, PASSWORD_DEFAULT), $targetId]);
        $_SESSION['reset_pw_flash'] = ['uid' => $targetId, 'pw' => $tempPw];
        $resetUsername = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
        $resetUsername->execute([$targetId]);
        auditLog($pdo, 'resetUserPassword', "id=$targetId username=" . ($resetUsername->fetchColumn() ?: ''));
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
        exit;

    } else if ($_REQUEST['action'] === 'flushAuditLog') {
        if (!isAdmin()) { http_response_code(403); exit; }
        $days = isset($_POST['keep_days']) ? (int)$_POST['keep_days'] : 0;
        if ($days > 0) {
            $pdo->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
            auditLog($pdo, 'flushAuditLog', "kept_last={$days}_days");
        } else {
            auditLog($pdo, 'flushAuditLog', 'all');
            $pdo->exec("DELETE FROM audit_log");
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=auditLog&flushed=1');
        exit;
    }
}
?>
