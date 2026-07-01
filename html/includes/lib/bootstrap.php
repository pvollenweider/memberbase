<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Bootstrap file — database connection, app settings, shared utility functions.
 *
 * Included by every page before any business logic or output.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

date_default_timezone_set("Europe/Zurich");

// Load DB config from conf/db.php if present (traditional install),
// otherwise fall back to environment variables (Docker / 12-factor).
$_confFile = __DIR__ . '/../../../conf/db.php';
if (file_exists($_confFile)) {
    require_once $_confFile;
}
unset($_confFile);

$hostname = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'members');
$password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: 'members');
$database = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'members');

$dsn = "mysql:host={$hostname};dbname={$database};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    header('Location: install.php'); exit;
}

// Redirect to installer if schema not yet initialized
try {
    $pdo->query("SELECT 1 FROM compta_type LIMIT 1");
} catch (PDOException $e) {
    header('Location: install.php'); exit;
}

// Compta types
$_ctRows = $pdo->query("SELECT id, label, color, sort_order, is_cotisation, is_excluded_from_donation, is_institutional FROM compta_type ORDER BY sort_order ASC, label ASC")->fetchAll(PDO::FETCH_OBJ);
$comptaTypes = [];
foreach ($_ctRows as $_ct) { $comptaTypes[(int)$_ct->id] = $_ct; }
unset($_ctRows, $_ct);

// App settings
$_settingsRows = $pdo->query("SELECT `key`, `value` FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appSettings = array_merge([
    'default_team'       => '0',
    'membre_team'        => '0',
    'archive_id'         => '0',
    'org_name'           => '',
    'org_address'        => '',
    'org_npa'            => '',
    'org_city'           => '',
    'org_country'        => '',
    'membre_team_prefix' => 'Membre',
], $_settingsRows);
unset($_settingsRows);

// Named constants for virtual filter IDs used in view_users.inc
// Negative integers — never collide with real team IDs (always > 0)
const FILTER_ALL_EXCEPT_ARCHIVES  = -3;
const FILTER_UNPAID_COTI_CURRENT  = -4;
const FILTER_UNPAID_COTI_3Y       = -3333;
const FILTER_NO_ACTIVITY_10Y      = -5555;
const FILTER_NON_INSTIT_LAST_YEAR = -6666;
// Sequence counter — still used for metagroup_id and userpropertiesid only.
// team/users/compta are now native AUTO_INCREMENT.
// metagroup cannot use AUTO_INCREMENT (id shared across header + member rows).
// user_properties.id is broken (83k rows with id=0), left for later refactor.
function getMaxVal(PDO $pdo, string $parameter): int
{
    $stmt = $pdo->prepare("SELECT value FROM maxval WHERE parameter=?");
    $stmt->execute([$parameter]);
    $row = $stmt->fetchObject();
    if ($row !== false) {
        return (int) $row->value;
    }
    $pdo->prepare("INSERT INTO maxval (parameter,value) VALUES (?,1)")->execute([$parameter]);
    return 1;
}

function updateAndGetMaxVal(string $parameter): int
{
    global $pdo;
    $value = getMaxVal($pdo, $parameter) + 1;
    $pdo->prepare("UPDATE maxval SET value=? WHERE parameter=?")->execute([$value, $parameter]);
    return $value;
}

/** Parses a d/m/Y date string (as used in form inputs) to a Unix timestamp. */
function formatedDateToTimeStamp(?string $formatedDate): int
{
    if ($formatedDate) {
        $d = DateTime::createFromFormat('d/m/Y', $formatedDate);
        return $d ? $d->getTimestamp() : 0;
    }
    return 0;
}

/** Formats a Unix timestamp to a d/m/Y display string for form inputs and tables. */
function timeStampToformatedDate(?int $timestamp): string
{
    return $timestamp ? date("d/m/Y", $timestamp) : "";
}

/** Replaces typographic apostrophes (') with straight apostrophes (') from user input. */
function unquote(string $s): string
{
    return str_replace("\u{2019}", "'", $s);
}

function auditLog(PDO $pdo, string $action, string $detail = '', ?int $subjectUserId = null): void
{
    $uid      = isset($_SESSION['app_user_id']) ? (int)$_SESSION['app_user_id'] : null;
    $username = $_SESSION['app_user_username'] ?? null;
    $pdo->prepare(
        "INSERT INTO audit_log (app_user_id, username, action, detail, subject_user_id) VALUES (?, ?, ?, ?, ?)"
    )->execute([$uid, $username, $action, $detail ?: null, $subjectUserId]);
}
