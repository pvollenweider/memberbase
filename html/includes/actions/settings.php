<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for application settings: save settings and manage accounting types.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: saveSettings, zefixLookup, saveSmtp, sendTestEmail,
//          updateComptaTypeOrder, addComptaType, updateComptaType, deleteComptaType

$action = $_REQUEST['action'];

if (in_array($action, ['saveSettings', 'zefixLookup', 'saveSmtp', 'sendTestEmail', 'purgeEmailLog', 'resendEmail', 'saveEmailTemplate', 'resetEmailTemplate', 'applyContactTypes'], true)) {
    if (!isAdmin()) { http_response_code(403); exit; }
} elseif (in_array($action, ['updateComptaTypeOrder','addComptaType','updateComptaType','deleteComptaType'], true)) {
    if (!isManager()) { http_response_code(403); exit; }
}

if ($action == 'saveSettings') {
    // Integer settings — stored as numeric values
    $intKeys = ['default_segment', 'membre_segment', 'member_no_coti_segment'];
    // String settings — stored as trimmed text
    $strKeys = ['org_name', 'org_address', 'org_npa', 'org_city', 'org_country',
                'org_ide', 'org_iban', 'org_coti_amount_desc', 'org_purpose', 'org_tax_status',
                'membre_segment_prefix', 'membership_url'];
    $stmt = db()->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ($intKeys as $key) {
        if (isset($_REQUEST[$key])) {
            $stmt->execute([$key, (int)$_REQUEST[$key]]);
        }
    }
    foreach ($strKeys as $key) {
        if (isset($_REQUEST[$key])) {
            $stmt->execute([$key, trim((string)$_REQUEST[$key])]);
        }
    }
    if ($isHtmx) {
        echo '<div id="casa-save-ok" hidden></div>';
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&saved=1') . ');</script>';
    }
    exit;

} elseif ($action === 'zefixLookup') {
    // Proxy call to the Zefix public REST API to fetch firm info by IDE/UID number.
    // The GET /firm/{uid} endpoint is decommissioned (always returns a generic
    // 400 API.ERROR.GENERAL, for any input) — the working flow is two calls:
    //   1. POST /firm/search.json {name: uid, searchType: exact} -> ehraid
    //   2. GET  /firm/{ehraid}.json                               -> full detail
    // Returns JSON: { name, street, npa, city, country, purpose, error? }
    header('Content-Type: application/json; charset=utf-8');
    $raw = trim($_REQUEST['ide'] ?? '');
    $uidFormatted = mbFormatSwissIde($raw);
    if ($uidFormatted === null) {
        echo json_encode(['error' => 'invalid_ide']);
        exit;
    }

    $searchCtx = stream_context_create(['http' => [
        'method'        => 'POST',
        'timeout'       => 8,
        'ignore_errors' => true,
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => json_encode(['name' => $uidFormatted, 'searchType' => 'exact']),
    ]]);
    $searchBody = @file_get_contents('https://www.zefix.ch/ZefixREST/api/v1/firm/search.json', false, $searchCtx);
    if ($searchBody === false || $searchBody === '') {
        echo json_encode(['error' => 'unreachable']);
        exit;
    }
    $searchData = json_decode($searchBody, true);
    $ehraid = $searchData['list'][0]['ehraid'] ?? null;
    if (!$ehraid) {
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $detailCtx = stream_context_create(['http' => [
        'timeout'        => 8,
        'ignore_errors'  => true,
        'header'         => "Accept: application/json\r\n",
    ]]);
    $body = @file_get_contents('https://www.zefix.ch/ZefixREST/api/v1/firm/' . $ehraid . '.json', false, $detailCtx);
    if ($body === false || $body === '') {
        echo json_encode(['error' => 'unreachable']);
        exit;
    }
    $data = json_decode($body, true);
    if (!$data || isset($data['error'])) {
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    // Extract the fields the settings form can prefill
    $result = [];
    if (!empty($data['name'])) {
        $result['name'] = $data['name'];
    }
    if (!empty($data['address'])) {
        $addr = $data['address'];
        $result['street']  = trim(($addr['street'] ?? '') . ' ' . ($addr['houseNumber'] ?? ''));
        $result['npa']     = $addr['swissZipCode'] ?? '';
        $result['city']    = $addr['town'] ?? '';
        $result['country'] = ($addr['country'] ?? '') === 'CH' ? 'Suisse' : ($addr['country'] ?? '');
    }
    if (!empty($data['purpose'])) {
        $result['purpose'] = $data['purpose'];
    }
    $result['ide'] = $uidFormatted;
    echo json_encode($result);
    exit;

} elseif ($action === 'saveSmtp') {
    require_once __DIR__ . '/../lib/mailer.php';
    $encKey = mbSmtpGetOrCreateEncKey(db());
    $strKeys = ['smtp_host', 'smtp_encryption', 'smtp_user', 'smtp_from_email', 'smtp_from_name', 'smtp_reply_to'];
    $stmt = db()->prepare("INSERT INTO app_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach ($strKeys as $key) {
        if (isset($_REQUEST[$key])) {
            $stmt->execute([$key, trim((string)$_REQUEST[$key])]);
        }
    }
    $stmt->execute(['smtp_port', (int)($_REQUEST['smtp_port'] ?? 587)]);
    $stmt->execute(['smtp_auth', isset($_REQUEST['smtp_auth']) ? '1' : '0']);
    // Only update password if a new one was submitted
    if (isset($_REQUEST['smtp_password']) && $_REQUEST['smtp_password'] !== '') {
        $encrypted = mbSmtpEncryptPassword(trim($_REQUEST['smtp_password']), $encKey);
        $stmt->execute(['smtp_password', $encrypted]);
    }
    if ($isHtmx) {
        echo '<div id="casa-save-ok" hidden></div>';
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&tab=email&saved=1') . ');</script>';
    }
    exit;

} elseif ($action === 'sendTestEmail') {
    require_once __DIR__ . '/../lib/mailer.php';
    header('Content-Type: application/json; charset=utf-8');
    $to = trim($_REQUEST['to'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_email']);
        exit;
    }
    // Re-fetch fresh settings (request may arrive before bootstrap appSettings populated)
    $rows = db()->query("SELECT `key`,`value` FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $cfg  = array_merge($appSettings, $rows);
    $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey(db());
    $subject = 'Test SMTP — memberbase';
    $body    = "Ceci est un email de test envoyé depuis memberbase.\nSi vous recevez ce message, la configuration SMTP est correcte.";
    $result  = mbSmtpSend($cfg, $to, $subject, $body);
    // Log the test send attempt
    $logStatus = $result['ok'] ? 'sent' : 'error';
    $logErr    = $result['ok'] ? null : ($result['error'] ?? '');
    try { db()->prepare("INSERT INTO email_log (to_email, subject, status, error_msg) VALUES (?,?,?,?)")->execute([$to, $subject, $logStatus, $logErr]); } catch (\Throwable $e) {}
    echo json_encode($result);
    exit;

} elseif ($action == 'updateComptaTypeOrder') {
    if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $stmt = db()->prepare("UPDATE compta_type SET sort_order=? WHERE id=?");
        foreach ($_REQUEST['ids'] as $i => $id) {
            $stmt->execute([$i, (int)$id]);
        }
    }

} elseif ($action == 'addComptaType') {
    $label = trim($_REQUEST['label'] ?? '');
    $color = mbValidComptaTypeColor($_REQUEST['color'] ?? 'bg-light');
    if ($label !== '') {
        $defaultLibele = trim((string)($_REQUEST['default_libele'] ?? ''));
        $maxOrder = (int)db()->query("SELECT COALESCE(MAX(sort_order),0) FROM compta_type")->fetchColumn();
        db()->prepare("INSERT INTO compta_type (label, color, default_libele, sort_order) VALUES (?, ?, ?, ?)")->execute([$label, $color, $defaultLibele, $maxOrder + 1]);
        auditLog(db(), 'addComptaType', "label: $label | couleur: $color");
    }
    $_ctUrl = appUrl() . mbComptaTypeReturnUrl($_REQUEST['returnView'] ?? null, $_REQUEST['returnTab'] ?? null);
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;

} elseif ($action == 'updateComptaType') {
    $id = (int)($_REQUEST['id'] ?? 0);
    $label = trim($_REQUEST['label'] ?? '');
    $sortOrder = (int)($_REQUEST['sort_order'] ?? 0);
    // Flags absent from the request keep their current value: the inline edit
    // form only sends label/color/order — it must not reset the flags to 0
    // (only the flag-toggle mini-forms send them explicitly).
    $_ctCur = db()->prepare("SELECT is_cotisation, is_excluded_from_donation, is_institutional, is_financial_institution, is_company FROM compta_type WHERE id=?");
    $_ctCur->execute([$id]);
    $_ctCurRow = $_ctCur->fetchObject();
    $isCotisation    = isset($_REQUEST['is_cotisation']) ? (int)$_REQUEST['is_cotisation'] : (int)($_ctCurRow->is_cotisation ?? 0);
    $isExcluded      = isset($_REQUEST['is_excluded_from_donation']) ? (int)$_REQUEST['is_excluded_from_donation'] : (int)($_ctCurRow->is_excluded_from_donation ?? 0);
    $isInstitutional = isset($_REQUEST['is_institutional']) ? (int)$_REQUEST['is_institutional'] : (int)($_ctCurRow->is_institutional ?? 0);
    $isFinancial     = isset($_REQUEST['is_financial_institution']) ? (int)$_REQUEST['is_financial_institution'] : (int)($_ctCurRow->is_financial_institution ?? 0);
    $isCompany       = isset($_REQUEST['is_company']) ? (int)$_REQUEST['is_company'] : (int)($_ctCurRow->is_company ?? 0);
    $color = mbValidComptaTypeColor($_REQUEST['color'] ?? 'bg-light');
    if ($id > 0 && $label !== '') {
        db()->prepare("UPDATE compta_type SET label=?, color=?, sort_order=?, is_cotisation=?, is_excluded_from_donation=?, is_institutional=?, is_financial_institution=?, is_company=? WHERE id=?")->execute([$label, $color, $sortOrder, $isCotisation, $isExcluded, $isInstitutional, $isFinancial, $isCompany, $id]);
        // Only sent by the edit form — the flag-toggle mini-forms omit it and
        // must not wipe the stored value.
        if (isset($_REQUEST['default_libele'])) {
            db()->prepare("UPDATE compta_type SET default_libele=? WHERE id=?")->execute([trim((string)$_REQUEST['default_libele']), $id]);
        }
        auditLog(db(), 'updateComptaType', "id=$id | label: $label");
    }
    $_ctUrl = appUrl() . mbComptaTypeReturnUrl($_REQUEST['returnView'] ?? null, $_REQUEST['returnTab'] ?? null);
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;

} elseif ($action == 'deleteComptaType') {
    $id = (int)($_REQUEST['id'] ?? 0);
    if ($id > 0) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM compta WHERE type_id=?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() === 0) {
            $_auCtLabel = db()->prepare("SELECT label FROM compta_type WHERE id=?"); $_auCtLabel->execute([$id]);
            auditLog(db(), 'deleteComptaType', "id=$id | label: " . ($_auCtLabel->fetchColumn() ?: ''));
            db()->prepare("DELETE FROM compta_type WHERE id=?")->execute([$id]);
        }
    }
    $_ctUrl = appUrl() . mbComptaTypeReturnUrl($_REQUEST['returnView'] ?? null, $_REQUEST['returnTab'] ?? null);
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;

} elseif ($action === 'applyContactTypes') {
    require_once __DIR__ . '/../lib/contact_type.php';
    $_applyRaw = (array)($_REQUEST['apply'] ?? []);
    $_typeIdByUserId = [];
    foreach ($_applyRaw as $_userId => $_typeId) {
        $_typeIdByUserId[(int)$_userId] = (int)$_typeId;
    }
    $_appliedCount = mbApplyContactTypes(db(), $_typeIdByUserId);
    auditLog(db(), 'applyContactTypes', "applied=$_appliedCount");
    $_returnView = ($_REQUEST['returnView'] ?? '') === 'settings' ? 'settings' : 'contactTypes';
    $_ctUrl = appUrl() . '?view=' . $_returnView . '&tab=contactTypes&contactTypesApplied=' . $_appliedCount;
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { header('Location: ' . $_ctUrl); }
    exit;

} elseif ($action === 'purgeEmailLog') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        db()->exec("DELETE FROM email_log");
        auditLog(db(), 'purgeEmailLog', '');
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;

} elseif ($action === 'saveEmailTemplate') {
    $key      = trim($_REQUEST['tpl_key']       ?? '');
    $subject  = trim($_REQUEST['tpl_subject']   ?? '');
    $body     = trim($_REQUEST['tpl_body']      ?? '');
    $bodyHtml = trim($_REQUEST['tpl_body_html'] ?? '');
    $allowed  = ['tpl_payment_receipt', 'tpl_cotisation_reminder', 'tpl_attestation_don', 'tpl_compta_recap', 'tpl_task_digest'];
    if (in_array($key, $allowed, true) && $subject !== '' && $body !== '') {
        try {
            db()->prepare(
                "INSERT INTO email_templates (`key`, subject, body_text, body_html) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_text=VALUES(body_text), body_html=VALUES(body_html)"
            )->execute([$key, $subject, $body, $bodyHtml]);
        } catch (\PDOException $e) {
            // body_html column may not exist yet (migration pending) — fall back
            db()->prepare(
                "INSERT INTO email_templates (`key`, subject, body_text) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_text=VALUES(body_text)"
            )->execute([$key, $subject, $body]);
        }
        auditLog(db(), 'saveEmailTemplate', "key=$key");
    }
    if ($isHtmx) {
        echo '<div id="casa-save-ok" hidden></div>';
    } else {
        echo '<script>window.location.replace(' . json_encode(appUrl() . '?view=settings&tab=email&saved=1') . ');</script>';
    }
    exit;

} elseif ($action === 'resetEmailTemplate') {
    $key     = trim($_REQUEST['tpl_key'] ?? '');
    $allowed = ['tpl_payment_receipt', 'tpl_cotisation_reminder', 'tpl_attestation_don', 'tpl_compta_recap', 'tpl_task_digest'];
    if (in_array($key, $allowed, true)) {
        db()->prepare("DELETE FROM email_templates WHERE `key` = ?")->execute([$key]);
        auditLog(db(), 'resetEmailTemplate', "key=$key");
    }
    if ($isHtmx) {
        header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=email&subtab=templates&reset=1');
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=email&subtab=templates&reset=1');
    }
    exit;

} elseif ($action === 'resendEmail') {
    require_once __DIR__ . '/../lib/mailer.php';
    header('Content-Type: application/json; charset=utf-8');
    $id  = (int)($_REQUEST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'invalid_id']); exit; }
    $row = db()->prepare("SELECT to_email, subject FROM email_log WHERE id=? LIMIT 1");
    $row->execute([$id]);
    $entry = $row->fetchObject();
    if (!$entry) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }
    $cfg = $appSettings;
    $cfg['smtp_enc_key'] = mbSmtpGetOrCreateEncKey(db());
    $result = mbSmtpSend($cfg, $entry->to_email, $entry->subject, '(message original non disponible — renvoi depuis le journal)');
    if ($result['ok']) {
        db()->prepare("UPDATE email_log SET status='sent', error_msg=NULL WHERE id=?")->execute([$id]);
        auditLog(db(), 'resendEmail', "id=$id to={$entry->to_email}");
    } else {
        db()->prepare("UPDATE email_log SET status='error', error_msg=? WHERE id=?")->execute([$result['error'] ?? '', $id]);
    }
    echo json_encode($result);
    exit;
}
