<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for accounting entries: add, update, and attestation toggle.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addCompta, updateCompta, toggleWantsAttestation

if (!canWrite()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'addCompta') {
    $_rawSum = trim(str_replace(',', '.', $_REQUEST['sum'] ?? ''));
    if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $_rawSum)) { http_response_code(422); exit; }
    $compta = new Compta();
    $compta->userId = (int)$_REQUEST['userid'];
    $compta->setTypeId((int)$_REQUEST['type_id']);
    $compta->date = formatedDateToTimeStamp($_REQUEST['date']);
    $compta->libele = unquote($_REQUEST['libele']);
    $compta->sum = $_rawSum;
    $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
    $compta->setCotisationYear($_REQUEST['cotisation_year'] ?? null);
    $compta->save();
    $_auUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
    $_auUser->execute([(int)$compta->userId]);
    $_auType = $comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}";
    $_auCotiYear = $compta->cotisation_year ? " | année coti: {$compta->cotisation_year}" : '';
    auditLog($pdo, 'addCompta', "membre: " . ($_auUser->fetchColumn() ?: "id={$compta->userId}") . " | {$_auType} | {$compta->sum} CHF | {$_REQUEST['date']}{$_auCotiYear}", (int)$compta->userId);

    // Optional receipt email — only when the checkbox is checked
    if (!empty($_REQUEST['send_receipt'])) {
        require_once __DIR__ . '/../lib/mailer.php';
        $_rcpUser = new User();
        $_rcpUser->lookupUser((int)$compta->userId);
        $_rcpEmail = $_rcpUser->getEmail();
        if ($_rcpEmail) {
            $_rcpLibele = trim((string)$compta->libele);
            $_rcpLibeleLine = $_rcpLibele !== '' ? "  Note    : {$_rcpLibele}\n" : '';
            $_rcpSoc = trim($_rcpUser->getSociety());
            mbSendTemplate($pdo, $_rcpEmail, 'tpl_payment_receipt', array_merge(
                mbBuildSalutation($_rcpUser->getFirstName(), $_rcpUser->getLastName(), $_rcpSoc),
            [
                'firstname'    => $_rcpUser->getFirstName(),
                'lastname'     => $_rcpUser->getLastName(),
                'email'        => $_rcpEmail,
                'society_line' => $_rcpSoc !== '' ? " de $_rcpSoc" : '',
                'type'         => $comptaTypes[(int)$_REQUEST['type_id']]->label ?? '',
                'amount'       => number_format((float)$compta->sum, 2, '.', "'"),
                'entry_date'   => date('d.m.Y', (int)$compta->date),
                'libele_line'  => $_rcpLibeleLine,
                'org_name'     => $appSettings['org_name']      ?? '',
                'org_address'  => $appSettings['org_address']   ?? '',
                'org_city'     => $appSettings['org_city']      ?? '',
                'org_web'      => $appSettings['org_web']       ?? '',
                'contact_email' => $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? ''),
            ]));
        }
    }

} elseif ($action == 'updateCompta') {
    $_rawSum2 = trim(str_replace(',', '.', $_REQUEST['sum'] ?? ''));
    if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $_rawSum2)) { http_response_code(422); exit; }
    $compta = new Compta();
    $compta->lookupCompta((int)$_REQUEST['comptaid']);
    $_auBefore2 = [
        'type'           => ($comptaTypes[(int)$compta->type_id]->label ?? "type={$compta->type_id}"),
        'date'           => timeStampToformatedDate((int)$compta->date),
        'libele'         => (string)$compta->libele,
        'sum'            => number_format((float)$compta->sum, 2, '.', ''),
        'quittance'      => (string)$compta->quittance,
        'attestation'    => $compta->wants_attestation ? 'oui' : 'non',
        'cotisation_year'=> (string)($compta->cotisation_year ?? ''),
    ];
    $mydate = $_REQUEST['date'];
    $date = formatedDateToTimeStamp($mydate);
    $compta->date = $date;
    $compta->libele = unquote($_REQUEST['libele']);
    $compta->sum = $_rawSum2;
    $compta->quittance = str_replace(',','.',$_REQUEST['quittance']);
    $compta->setTypeId((int)$_REQUEST['type_id']);
    $compta->setWantsAttestation(isset($_REQUEST['wants_attestation']));
    $compta->setCotisationYear($_REQUEST['cotisation_year'] ?? null);
    $_auAfter2 = [
        'type'           => ($comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}"),
        'date'           => $mydate,
        'libele'         => (string)$compta->libele,
        'sum'            => number_format((float)$compta->sum, 2, '.', ''),
        'quittance'      => (string)$compta->quittance,
        'attestation'    => isset($_REQUEST['wants_attestation']) ? 'oui' : 'non',
        'cotisation_year'=> (string)($compta->cotisation_year ?? ''),
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

} elseif ($action == 'deleteComptaEntry') {
    $comptaid = (int)$_REQUEST['comptaid'];
    $_auDel = $pdo->prepare("SELECT CONCAT(u.firstName,' ',u.lastName), c.sum FROM compta c JOIN users u ON u.id=c.user_id WHERE c.id=?");
    $_auDel->execute([$comptaid]);
    $_auDelRow = $_auDel->fetch(PDO::FETCH_NUM);
    $pdo->prepare("DELETE FROM compta WHERE id=?")->execute([$comptaid]);
    auditLog($pdo, 'deleteComptaEntry', "compta#=$comptaid | " . ($_auDelRow[0] ?? '') . " | sum={$_auDelRow[1]}");
    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=integrity'); exit; }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=integrity'); exit;

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
