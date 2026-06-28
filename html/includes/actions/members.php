<?php
// actions: updateUser, addUser

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
        'comment'   => preg_replace(['/(<(?!\/)[^>]+>)\s+/', '/\s+(<\/[^>]+>)/'], ['$1', '$1'], trim((string)$user->comment)),
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
    $_rawComment = trim(unquote($_REQUEST['comment']));
    // Normalize whitespace inside HTML tags to match TipTap output
    $_rawComment = preg_replace('/(<(?!\/)[^>]+>)\s+/', '$1', $_rawComment);
    $_rawComment = preg_replace('/\s+(<\/[^>]+>)/', '$1', $_rawComment);
    $user->comment = $_rawComment;
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
        'comment'   => trim((string)$user->comment),
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
    auditLog($pdo, 'updateUser', $auDetail, (int)$_REQUEST['id']);
    $_savedOk = true;

} elseif ($_REQUEST['action'] == 'mergeUsers') {
    $idA = (int)($_REQUEST['idA'] ?? 0);
    $idB = (int)($_REQUEST['idB'] ?? 0);
    if ($idA <= 0 || $idB <= 0 || $idA === $idB) { return; }

    $userA = new User(); $userA->lookupUser($idA);
    $userB = new User(); $userB->lookupUser($idB);
    if (!$userA->getId() || !$userB->getId()) { return; }

    $survivorSide = ($_REQUEST['survivor'] ?? 'a') === 'b' ? 'b' : 'a';
    $survivorId   = $survivorSide === 'a' ? $idA : $idB;
    $sourceId     = $survivorSide === 'a' ? $idB : $idA;
    $survivor     = $survivorSide === 'a' ? $userA : $userB;

    $disposal = ($_REQUEST['disposal'] ?? 'hide') === 'delete' ? 'delete' : 'hide';

    $allowed = ['firstName','lastName','society','sexe','title','address','npa','tel','telProf','portable','fax','email','web','birthDay','comment'];
    $fields  = array_intersect_key($_REQUEST['fields'] ?? [], array_flip($allowed));
    $changedFields = [];
    foreach ($fields as $k => $side) {
        $from = $side === 'b' ? $userB : $userA;
        if ((string)$survivor->$k !== (string)$from->$k) {
            $changedFields[] = $k;
        }
        if ($k === 'birthDay') {
            $survivor->$k = $from->$k;
        } else {
            $survivor->$k = $from->$k;
        }
    }
    $survivor->save();

    // Move compta
    $pdo->prepare("UPDATE compta SET user_id=? WHERE user_id=?")->execute([$survivorId, $sourceId]);

    // Move non-team user_properties
    $pdo->prepare("UPDATE user_properties SET user_id=? WHERE user_id=? AND parameter NOT LIKE 'team_%'")->execute([$survivorId, $sourceId]);

    // Move team memberships (dedup)
    $srcTeams = $pdo->prepare("SELECT parameter, value FROM user_properties WHERE user_id=? AND parameter LIKE 'team_%'");
    $srcTeams->execute([$sourceId]);
    $insTeam = $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, ?)");
    while ($t = $srcTeams->fetchObject()) {
        $insTeam->execute([$survivorId, $t->parameter, $t->value]);
    }
    $pdo->prepare("DELETE FROM user_properties WHERE user_id=? AND parameter LIKE 'team_%'")->execute([$sourceId]);

    // Dispose source
    if ($disposal === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$sourceId]);
    } else {
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$sourceId]);
    }

    $sourceName   = trim($userA->firstName . ' ' . $userA->lastName);
    $survivorName = trim($survivor->firstName . ' ' . $survivor->lastName);
    if ($survivorSide === 'b') { $sourceName = trim($userA->firstName . ' ' . $userA->lastName); }
    $sourceUser = $survivorSide === 'a' ? $userB : $userA;
    $sourceName = trim($sourceUser->firstName . ' ' . $sourceUser->lastName);

    $auDetail = "source=#{$sourceId} {$sourceName} → survivant=#{$survivorId} {$survivorName} | disposition: {$disposal}";
    if ($changedFields) { $auDetail .= ' | champs: ' . implode(', ', $changedFields); }
    auditLog($pdo, 'mergeUsers', $auDetail, $survivorId);

    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=integrity');
        exit;
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=integrity');
    exit;

} elseif ($_REQUEST['action'] == 'anonymizeUser') {
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid <= 0) { return; }
    $chk = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE user_id=?");
    $chk->execute([$uid]);
    if ((int)$chk->fetchColumn() === 0) { return; } // safety: only anonymize if has compta
    $pdo->prepare("UPDATE users SET
        firstName='Anonymisé', lastName='', society='', sexe='na', title='',
        address='', npa='', tel='', telprof='', portable='', fax='',
        email='', web='', birthday=0, comment='', status=0
        WHERE id=?")->execute([$uid]);
    auditLog($pdo, 'anonymizeUser', 'id=' . $uid, $uid);
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid); exit; }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid); exit;

} elseif ($_REQUEST['action'] == 'deactivateUser') {
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid > 0) {
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$uid]);
        $auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $auUser->execute([$uid]);
        auditLog($pdo, 'deactivateUser', 'id=' . $uid . ' | ' . ($auUser->fetchColumn() ?: "id=$uid"), $uid);
    }
    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid);
        exit;
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid);
    exit;

} elseif ($_REQUEST['action'] == 'deleteOrDeactivateUser') {
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid <= 0) { return; }
    $user = new User(); $user->lookupUser($uid);
    $dispose = $_REQUEST['dispose'] ?? 'deactivate';
    if ($dispose === 'delete') {
        auditLog($pdo, 'deleteUser', 'id=' . $uid . ' | ' . trim($user->firstName . ' ' . $user->lastName));
        $user->remove();
    } else {
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$uid]);
        auditLog($pdo, 'deactivateUser', 'id=' . $uid . ' | ' . trim($user->firstName . ' ' . $user->lastName), $uid);
    }
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF']); exit; }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;

} elseif ($_REQUEST['action'] == 'reactivateUser') {
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid > 0) {
        $pdo->prepare("UPDATE users SET status=1 WHERE id=?")->execute([$uid]);
        $auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $auUser->execute([$uid]);
        auditLog($pdo, 'reactivateUser', 'id=' . $uid . ' | ' . ($auUser->fetchColumn() ?: "id=$uid"), $uid);
    }
    $redirectTarget = ($_REQUEST['redirect'] ?? '') === 'inactiveUsers'
        ? '?view=inactiveUsers'
        : '?view=updateUser&id=' . $uid;
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF'] . $redirectTarget); exit; }
    header('Location: ' . $_SERVER['PHP_SELF'] . $redirectTarget); exit;

} elseif ($_REQUEST['action'] == 'addUser') {
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
    auditLog($pdo, 'addUser', "id=$userid | {$user->firstName} {$user->lastName} | email: {$user->email}", (int)$userid);
    $fromTeam = (int)($_REQUEST['fromTeam'] ?? 0);
    if ($fromTeam > 0 && !empty($_REQUEST['addToFromTeam'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM team WHERE id=?");
        $chk->execute([$fromTeam]);
        if ($chk->fetchColumn() > 0) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, 'true')");
            $ins->execute([$userid, 'team_' . $fromTeam]);
        }
    }
}
