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
}
