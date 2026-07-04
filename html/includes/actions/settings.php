<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for application settings: save settings and manage accounting types.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: saveSettings, zefixLookup,
//          updateComptaTypeOrder, addComptaType, updateComptaType, deleteComptaType

$action = $_REQUEST['action'];

if ($action === 'saveSettings') {
    if (!isAdmin()) { http_response_code(403); exit; }
} elseif ($action === 'zefixLookup') {
    if (!isAdmin()) { http_response_code(403); exit; }
} elseif (in_array($action, ['updateComptaTypeOrder','addComptaType','updateComptaType','deleteComptaType'], true)) {
    if (!isManager()) { http_response_code(403); exit; }
}

if ($action == 'saveSettings') {
    // Integer settings — stored as numeric values
    $intKeys = ['default_team', 'membre_team', 'member_no_coti_team'];
    // String settings — stored as trimmed text
    $strKeys = ['org_name', 'org_address', 'org_npa', 'org_city', 'org_country',
                'org_ide', 'org_purpose', 'org_tax_status', 'org_zewo',
                'membre_team_prefix'];
    $stmt = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
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
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=settings&saved=1') . ');</script>';
    }
    exit;

} elseif ($action === 'zefixLookup') {
    // Proxy call to Zefix REST API to fetch firm info by IDE/UID number.
    // Returns JSON: { name, address, legalForm, error? }
    header('Content-Type: application/json; charset=utf-8');
    $raw = trim($_REQUEST['ide'] ?? '');
    // Normalize IDE: strip spaces, dashes, dots — keep only digits; then format as CHE-XXX.XXX.XXX
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($digits) < 9) {
        echo json_encode(['error' => 'invalid_ide']);
        exit;
    }
    // Keep last 9 digits (CHE prefix is 3 letters, UID body is 9 digits)
    $uid9 = substr($digits, -9);
    $uidFormatted = 'CHE-' . substr($uid9, 0, 3) . '.' . substr($uid9, 3, 3) . '.' . substr($uid9, 6, 3);
    $apiUrl = 'https://www.zefix.ch/ZefixREST/api/v1/firm/' . urlencode($uidFormatted);
    $ctx = stream_context_create(['http' => [
        'timeout'        => 8,
        'ignore_errors'  => true,
        'header'         => "Accept: application/json\r\n",
    ]]);
    $body = @file_get_contents($apiUrl, false, $ctx);
    if ($body === false || $body === '') {
        echo json_encode(['error' => 'unreachable']);
        exit;
    }
    $data = json_decode($body, true);
    if (!$data || isset($data['error'])) {
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    // Extract the most useful fields from Zefix response
    $result = [];
    // Firm name (main entry)
    if (!empty($data['name'])) {
        $result['name'] = $data['name'];
    } elseif (!empty($data['legalName'])) {
        $result['name'] = $data['legalName'];
    }
    // Address
    if (!empty($data['address'])) {
        $addr = $data['address'];
        $result['street']   = trim(($addr['street'] ?? '') . ' ' . ($addr['houseNumber'] ?? ''));
        $result['npa']      = $addr['swissZipCode'] ?? ($addr['zipCode'] ?? '');
        $result['city']     = $addr['town'] ?? '';
        $result['country']  = $addr['countryIsoCode'] ?? '';
    }
    if (!empty($data['legalForm'])) {
        $result['legalForm'] = is_array($data['legalForm']) ? ($data['legalForm']['name']['fr'] ?? $data['legalForm']['name']['de'] ?? '') : $data['legalForm'];
    }
    $result['ide'] = $uidFormatted;
    echo json_encode($result);
    exit;

} elseif ($action == 'updateComptaTypeOrder') {
    if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $stmt = $pdo->prepare("UPDATE compta_type SET sort_order=? WHERE id=?");
        foreach ($_REQUEST['ids'] as $i => $id) {
            $stmt->execute([$i, (int)$id]);
        }
    }

} elseif ($action == 'addComptaType') {
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
    $_ctUrl = $_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb;
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;

} elseif ($action == 'updateComptaType') {
    $id = (int)($_REQUEST['id'] ?? 0);
    $label = trim($_REQUEST['label'] ?? '');
    $color = $_REQUEST['color'] ?? 'bg-light';
    $sortOrder = (int)($_REQUEST['sort_order'] ?? 0);
    $isCotisation    = isset($_REQUEST['is_cotisation']) ? (int)$_REQUEST['is_cotisation'] : 0;
    $isExcluded      = isset($_REQUEST['is_excluded_from_donation']) ? (int)$_REQUEST['is_excluded_from_donation'] : 0;
    $isInstitutional = isset($_REQUEST['is_institutional']) ? (int)$_REQUEST['is_institutional'] : 0;
    $allowed = ['bg-primary-subtle','bg-secondary-subtle','bg-success-subtle','bg-danger-subtle','bg-warning-subtle','bg-info-subtle','bg-light','bg-dark-subtle','ca-orange-subtle','ca-teal-subtle','ca-pink-subtle','ca-purple-subtle','ca-indigo-subtle','ca-lime-subtle'];
    if (!in_array($color, $allowed)) $color = 'bg-light';
    if ($id > 0 && $label !== '') {
        $pdo->prepare("UPDATE compta_type SET label=?, color=?, sort_order=?, is_cotisation=?, is_excluded_from_donation=?, is_institutional=? WHERE id=?")->execute([$label, $color, $sortOrder, $isCotisation, $isExcluded, $isInstitutional, $id]);
        auditLog($pdo, 'updateComptaType', "id=$id | label: $label");
    }
    $_rvAllowed = ['settings','manageComptaTypes'];
    $rv  = in_array($_REQUEST['returnView'] ?? '', $_rvAllowed) ? $_REQUEST['returnView'] : 'settings';
    $rtb = preg_replace('/[^a-zA-Z]/', '', $_REQUEST['returnTab'] ?? 'compta');
    $_ctUrl = $_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb;
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;

} elseif ($action == 'deleteComptaType') {
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
    $_ctUrl = $_SERVER['PHP_SELF'] . '?view=' . $rv . '&tab=' . $rtb;
    if ($isHtmx) { header('HX-Location: ' . $_ctUrl); } else { echo '<script>window.location.replace(' . json_encode($_ctUrl) . ');</script>'; }
    exit;
}
