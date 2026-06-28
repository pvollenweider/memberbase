<?php
/**
 * Action handler for accounting entries: add, update, and attestation toggle.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addCompta, updateCompta, toggleWantsAttestation

$action = $_REQUEST['action'];

if ($action == 'addCompta') {
    $compta = new Compta();
    $compta->userId = $_REQUEST['userid'];
    $compta->setTypeId((int)$_REQUEST['type_id']);
    $compta->date = formatedDateToTimeStamp($_REQUEST['date']);
    $compta->libele = unquote($_REQUEST['libele']);
    $compta->sum = str_replace(',','.',$_REQUEST['sum']);
    $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
    $compta->save();
    $_auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
    $_auUser->execute([(int)$compta->userId]);
    $_auType = $comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}";
    auditLog($pdo, 'addCompta', "membre: " . ($_auUser->fetchColumn() ?: "id={$compta->userId}") . " | {$_auType} | {$compta->sum} CHF | {$_REQUEST['date']}", (int)$compta->userId);

} elseif ($action == 'updateCompta') {
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
    auditLog($pdo, 'updateCompta', $auDetail2, (int)$compta->userId);

} elseif ($action == 'toggleWantsAttestation') {
    $comptaid = (int)$_REQUEST['comptaid'];
    $value    = isset($_REQUEST['wants_attestation']) ? 1 : 0;
    $pdo->prepare("UPDATE compta SET wants_attestation=? WHERE id=?")->execute([$value, $comptaid]);
    $_auTwa = $pdo->prepare("SELECT CONCAT(u.firstName,' ',u.lastName) FROM compta c JOIN users u ON u.id=c.user_id WHERE c.id=?");
    $_auTwa->execute([$comptaid]);
    auditLog($pdo, 'toggleWantsAttestation', "compta#=$comptaid | " . ($_auTwa->fetchColumn() ?: '') . " | attestation: " . ($value ? 'oui' : 'non'), (int)$_REQUEST['userid']);
    $year   = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $userid = (int)$_REQUEST['userid'];
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=compta&userid=' . $userid . '&year=' . $year);
    exit;
}
