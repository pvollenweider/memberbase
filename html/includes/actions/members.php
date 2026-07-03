<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for member records: add and update.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: updateUser, addUser

if (!canWrite()) { http_response_code(403); exit; }

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
        'emailAlt'  => (string)$user->emailAlt,
        'web'       => (string)$user->web,
        'birthDay'  => timeStampToformatedDate((int)$user->birthDay),
        'comment'   => preg_replace(['/(<(?!\/)[^>]+>)\s+/', '/\s+(<\/[^>]+>)/'], ['$1', '$1'], trim((string)$user->comment)),
    ];
    $user->firstName = unquote($_REQUEST['firstName'] ?? '');
    $user->lastName = unquote($_REQUEST['lastName'] ?? '');
    $user->society = unquote($_REQUEST['society'] ?? '');
    $user->sexe = unquote($_REQUEST['sexe'] ?? '');
    $user->title = unquote($_REQUEST['title'] ?? '');
    $user->address = unquote($_REQUEST['address'] ?? '');
    $user->npa = unquote($_REQUEST['npa'] ?? '');
    $user->tel= unquote($_REQUEST['tel'] ?? '');
    $user->telProf= unquote($_REQUEST['telProf'] ?? '');
    $user->portable = unquote($_REQUEST['portable'] ?? '');
    $user->fax = unquote($_REQUEST['fax'] ?? '');
    $user->email = unquote($_REQUEST['email'] ?? '');
    $user->emailAlt = unquote($_REQUEST['emailAlt'] ?? '');
    $user->web = unquote($_REQUEST['web'] ?? '');
    $user->birthDay = unquote((string)formatedDateToTimeStamp($_REQUEST['birthDay'] ?? ''));
    $_rawComment = trim(unquote($_REQUEST['comment'] ?? ''));
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
        'emailAlt'  => (string)$user->emailAlt,
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
    if (!isManager()) { http_response_code(403); exit; }
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

    $allowed = ['firstName','lastName','society','sexe','title','address','npa','tel','telProf','portable','fax','email','emailAlt','web','birthDay','comment'];
    $fields  = array_intersect_key($_REQUEST['fields'] ?? [], array_flip($allowed));
    $changedFields = [];
    foreach ($fields as $k => $side) {
        if ($k === 'comment' && $side === 'both') {
            $survivorComment = trim((string)$survivor->comment);
            $sourceComment   = trim((string)($survivorSide === 'a' ? $userB->comment : $userA->comment));
            $merged = $survivorComment . ($survivorComment && $sourceComment ? '<hr>' : '') . $sourceComment;
            if ($merged !== (string)$survivor->comment) { $changedFields[] = $k; }
            $survivor->comment = $merged;
            continue;
        }
        $from = $side === 'b' ? $userB : $userA;
        if ((string)$survivor->$k !== (string)$from->$k) {
            $changedFields[] = $k;
        }
        $survivor->$k = $from->$k;
    }
    // All writes must succeed or none — otherwise moved compta/properties are orphaned
    $pdo->beginTransaction();
    try {
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

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
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
    if (!isAdmin()) { http_response_code(403); exit; }
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid <= 0) { return; }
    $chk = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE user_id=?");
    $chk->execute([$uid]);
    if ((int)$chk->fetchColumn() === 0) { return; } // safety: only anonymize if has compta
    // Stored data marker, intentionally NOT localized: the DB value must stay
    // stable regardless of the UI language of whoever anonymizes.
    $pdo->prepare("UPDATE users SET
        firstName='Anonymisé', lastName='', society='', sexe='na', title='',
        address='', npa='', tel='', telprof='', portable='', fax='',
        email='', web='', birthday=0, comment='', status=0
        WHERE id=?")->execute([$uid]);
    auditLog($pdo, 'anonymizeUser', 'id=' . $uid, $uid);
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid); exit; }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=updateUser&id=' . $uid); exit;

} elseif ($_REQUEST['action'] == 'deactivateUser') {
    if (!isManager()) { http_response_code(403); exit; }
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
    if (!isAdmin()) { http_response_code(403); exit; }
    $uid = (int)($_REQUEST['id'] ?? 0);
    if ($uid <= 0) { return; }
    $user = new User(); $user->lookupUser($uid);
    $dispose = $_REQUEST['dispose'] ?? 'deactivate';
    if ($dispose === 'delete') {
        // Suppression irréversible : on trace dans le journal toutes les données
        // non vides du membre (champs + user_properties) avant de les effacer.
        $_delParts = [];
        $_delFields = [
            'société'    => (string)$user->society,
            'sexe'       => ($user->sexe !== '' && $user->sexe !== 'na') ? (string)$user->sexe : '',
            'titre'      => (string)$user->title,
            'adresse'    => (string)$user->address,
            'npa'        => (string)$user->npa,
            'tél'        => (string)$user->tel,
            'tél. prof'  => (string)$user->telProf,
            'portable'   => (string)$user->portable,
            'fax'        => (string)$user->fax,
            'email'      => (string)$user->email,
            'email alt'  => (string)$user->emailAlt,
            'web'        => (string)$user->web,
            'naissance'  => ((int)$user->birthDay > 0) ? timeStampToformatedDate((int)$user->birthDay) : '',
            'note'       => trim(strip_tags((string)$user->comment)),
        ];
        foreach ($_delFields as $_k => $_v) {
            $_v = trim((string)$_v);
            if ($_v === '') continue;
            if (mb_strlen($_v) > 500) { $_v = mb_substr($_v, 0, 500) . '…'; }
            $_delParts[] = "{$_k}: {$_v}";
        }
        // user_properties non vides (appartenances aux segments, suivi, etc.)
        $_delProps = $pdo->prepare("SELECT parameter, value FROM user_properties WHERE user_id=? AND TRIM(value) != '' ORDER BY parameter");
        $_delProps->execute([$uid]);
        while ($_p = $_delProps->fetchObject()) {
            $_pv = trim((string)$_p->value);
            if (mb_strlen($_pv) > 500) { $_pv = mb_substr($_pv, 0, 500) . '…'; }
            $_delParts[] = "{$_p->parameter}: {$_pv}";
        }
        $_delDetail = 'id=' . $uid . ' | ' . trim($user->firstName . ' ' . $user->lastName);
        if ($_delParts) { $_delDetail .= ' | ' . implode(' ; ', $_delParts); }
        auditLog($pdo, 'deleteUser', $_delDetail);
        $user->remove();
    } else {
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([$uid]);
        auditLog($pdo, 'deactivateUser', 'id=' . $uid . ' | ' . trim($user->firstName . ' ' . $user->lastName), $uid);
    }
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF']); exit; }
    header('Location: ' . $_SERVER['PHP_SELF']); exit;

} elseif ($_REQUEST['action'] == 'reactivateUser') {
    if (!isManager()) { http_response_code(403); exit; }
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
    $user->firstName = unquote($_REQUEST['firstName'] ?? '');
    $user->lastName = unquote($_REQUEST['lastName'] ?? '');
    $user->society = unquote($_REQUEST['society'] ?? '');
    $user->sexe = unquote($_REQUEST['sexe'] ?? '');
    $user->title = unquote($_REQUEST['title'] ?? '');
    $user->address = unquote($_REQUEST['address'] ?? '');
    $user->npa = unquote($_REQUEST['npa'] ?? '');
    $user->tel= unquote($_REQUEST['tel'] ?? '');
    $user->telProf= unquote($_REQUEST['telProf'] ?? '');
    $user->portable = unquote($_REQUEST['portable'] ?? '');
    $user->fax = unquote($_REQUEST['fax'] ?? '');
    $user->email = unquote($_REQUEST['email'] ?? '');
    $user->emailAlt = unquote($_REQUEST['emailAlt'] ?? '');
    $user->web = unquote($_REQUEST['web'] ?? '');
    $user->birthDay = unquote((string)formatedDateToTimeStamp($_REQUEST['birthDay'] ?? ''));
    $user->comment = unquote($_REQUEST['comment'] ?? '');
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
