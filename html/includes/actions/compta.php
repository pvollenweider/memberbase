<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for accounting entries: add, update, and attestation toggle.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addCompta, updateCompta, toggleWantsAttestation

if (!canWrite()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'addCompta') {
    $_rawSum = trim(str_replace(',', '.', $_REQUEST['sum'] ?? ''));
    if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $_rawSum)) { http_response_code(422); exit; }

    require_once __DIR__ . '/../lib/contact_type.php';
    $_addUser = new Contact();
    $_addUser->lookupUser((int)$_REQUEST['userid']);
    $_addAllowedTypeIds = mbAllowedComptaTypeIdsForContact(db(), $_addUser->getContactTypeId(), (array)$comptaTypes);
    if (!in_array((int)$_REQUEST['type_id'], $_addAllowedTypeIds, true)) { http_response_code(422); exit; }

    $compta = new Compta();
    $compta->userId = (int)$_REQUEST['userid'];
    $compta->setTypeId((int)$_REQUEST['type_id']);
    $compta->date = formatedDateToTimeStamp($_REQUEST['date']);
    $compta->libele = unquote($_REQUEST['libele']);
    $compta->sum = $_rawSum;
    $compta->comment = str_replace(',','.',$_REQUEST['comment']);
    $compta->setCotisationYear($_REQUEST['cotisation_year'] ?? null);
    // Auto-fill libele for cotisation entries when user left it blank.
    // Prefer the type's default_libele (same source as the client-side
    // prefill), falling back to the type label.
    if (trim((string)$compta->libele) === '' && !empty($comptaTypes[(int)$_REQUEST['type_id']]->is_cotisation)) {
        $_cotiYear = $compta->cotisation_year ?: (int)date('Y', (int)$compta->date ?: time());
        $_cotiBase = trim((string)($comptaTypes[(int)$_REQUEST['type_id']]->default_libele ?? ''))
            ?: ($comptaTypes[(int)$_REQUEST['type_id']]->label ?? '');
        $compta->libele = $_cotiBase . ' ' . $_cotiYear;
    }
    $compta->save();
    $_auType = $comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}";
    $_auCotiYear = $compta->cotisation_year ? " | annee coti: {$compta->cotisation_year}" : '';
    auditLog(db(), 'addCompta', "membre: " . Contact::getMemberName((int)$compta->userId) . " | {$_auType} | {$compta->sum} CHF | {$_REQUEST['date']}{$_auCotiYear}", (int)$compta->userId);

    // Optional receipt email — only when the checkbox is checked
    if (!empty($_REQUEST['send_receipt'])) {
        require_once __DIR__ . '/../lib/mailer.php';
        $_rcpUser = new Contact();
        $_rcpUser->lookupUser((int)$compta->userId);
        $_rcpEmail = $_rcpUser->getEmail();
        if ($_rcpEmail) {
            $_rcpLibele = trim((string)$compta->libele);
            $_rcpLibeleLine = $_rcpLibele !== '' ? "  Note    : {$_rcpLibele}\n" : '';
            $_rcpSoc = trim($_rcpUser->getSociety());
            mbSendTemplate(db(), $_rcpEmail, 'tpl_payment_receipt', array_merge(
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

    require_once __DIR__ . '/../lib/contact_type.php';
    $_updUser = new Contact();
    $_updUser->lookupUser((int)$compta->userId);
    // The entry's ORIGINAL type stays a valid choice (no-op resubmission of
    // an already-archived/excluded type must not be rejected) — only
    // switching to a genuinely disallowed type is blocked.
    $_updAllowedTypeIds = array_unique(array_merge(
        mbAllowedComptaTypeIdsForContact(db(), $_updUser->getContactTypeId(), (array)$comptaTypes),
        [(int)$compta->type_id]
    ));
    if (!in_array((int)$_REQUEST['type_id'], $_updAllowedTypeIds, true)) { http_response_code(422); exit; }
    $_auBefore2 = [
        'type'           => ($comptaTypes[(int)$compta->type_id]->label ?? "type={$compta->type_id}"),
        'date'           => timeStampToformatedDate((int)$compta->date),
        'libele'         => (string)$compta->libele,
        'sum'            => number_format((float)$compta->sum, 2, '.', ''),
        'comment'        => (string)$compta->comment,
        'attestation'    => $compta->wants_attestation ? 'oui' : 'non',
        'cotisation_year'=> (string)($compta->cotisation_year ?? ''),
    ];
    $mydate = $_REQUEST['date'];
    $date = formatedDateToTimeStamp($mydate);
    $compta->date = $date;
    $compta->libele = unquote($_REQUEST['libele']);
    $compta->sum = $_rawSum2;
    $compta->comment = str_replace(',','.',$_REQUEST['comment']);
    $compta->setTypeId((int)$_REQUEST['type_id']);
    $compta->setWantsAttestation(isset($_REQUEST['wants_attestation']));
    $compta->setCotisationYear($_REQUEST['cotisation_year'] ?? null);
    // Auto-fill libele for cotisation entries when user left it blank.
    // Prefer the type's default_libele (same source as the client-side
    // prefill), falling back to the type label.
    if (trim((string)$compta->libele) === '' && !empty($comptaTypes[(int)$_REQUEST['type_id']]->is_cotisation)) {
        $_cotiYear2 = $compta->cotisation_year ?: (int)date('Y', $compta->date ?: time());
        $_cotiBase2 = trim((string)($comptaTypes[(int)$_REQUEST['type_id']]->default_libele ?? ''))
            ?: ($comptaTypes[(int)$_REQUEST['type_id']]->label ?? '');
        $compta->libele = $_cotiBase2 . ' ' . $_cotiYear2;
    }
    $_auAfter2 = [
        'type'           => ($comptaTypes[(int)$_REQUEST['type_id']]->label ?? "type={$_REQUEST['type_id']}"),
        'date'           => $mydate,
        'libele'         => (string)$compta->libele,
        'sum'            => number_format((float)$compta->sum, 2, '.', ''),
        'comment'        => (string)$compta->comment,
        'attestation'    => isset($_REQUEST['wants_attestation']) ? 'oui' : 'non',
        'cotisation_year'=> (string)($compta->cotisation_year ?? ''),
    ];
    $compta->save();
    $_auDiffs2 = [];
    foreach ($_auBefore2 as $_f => $_v) {
        if ($_v !== $_auAfter2[$_f]) {
            $_auDiffs2[] = "{$_f}: [{$_v}] -> [{$_auAfter2[$_f]}]";
        }
    }
    $auDetail2 = "compta#={$_REQUEST['comptaid']} | membre: " . Contact::getMemberName((int)$compta->userId);
    if ($_auDiffs2) { $auDetail2 .= ' | ' . implode(' ; ', $_auDiffs2); }
    else            { $auDetail2 .= ' | (aucune modification)'; }
    auditLog(db(), 'updateCompta', $auDetail2, (int)$compta->userId);

} elseif ($action == 'deleteComptaEntry') {
    $comptaid = (int)$_REQUEST['comptaid'];
    $_auDel = db()->prepare("SELECT user_id, sum FROM compta WHERE id=?");
    $_auDel->execute([$comptaid]);
    $_auDelRow = $_auDel->fetch(PDO::FETCH_OBJ);
    db()->prepare("DELETE FROM compta WHERE id=?")->execute([$comptaid]);
    $_auDelName = $_auDelRow ? Contact::getMemberName((int)$_auDelRow->user_id) : '';
    auditLog(db(), 'deleteComptaEntry', "compta#=$comptaid | " . $_auDelName . " | sum=" . ($_auDelRow->sum ?? ''));
    // With a userid the delete came from the member's compta tab; otherwise
    // from the integrity screen — go back to where the user was.
    $_delUserId = (int)($_REQUEST['userid'] ?? 0);
    $_delTarget = $_delUserId > 0
        ? '?view=compta&userid=' . $_delUserId
        : '?view=settings&tab=integrity';
    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    if ($isHtmx) { header('HX-Location: ' . appUrl() . $_delTarget); exit; }
    header('Location: ' . appUrl() . $_delTarget); exit;

} elseif ($action == 'toggleWantsAttestation') {
    $comptaid = (int)$_REQUEST['comptaid'];
    $value    = isset($_REQUEST['wants_attestation']) ? 1 : 0;
    db()->prepare("UPDATE compta SET wants_attestation=? WHERE id=?")->execute([$value, $comptaid]);
    auditLog(db(), 'toggleWantsAttestation', "compta#=$comptaid | " . Contact::getMemberName((int)$_REQUEST['userid']) . " | attestation: " . ($value ? 'oui' : 'non'), (int)$_REQUEST['userid']);
    $year   = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $userid = (int)$_REQUEST['userid'];
    header('Location: ' . appUrl() . '?view=compta&userid=' . $userid . '&year=' . $year);
    exit;
}
