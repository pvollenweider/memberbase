<?php
function startswith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

date_default_timezone_set("Europe/Zurich");

// database settings — env vars override defaults (Docker dev)
$hostname = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "members";
$password = getenv('DB_PASS') ?: "members";
$database = getenv('DB_NAME') ?: "members";

$dsn = "mysql:host={$hostname};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// Compta types
$_ctRows = $pdo->query("SELECT id, label, color, sort_order, is_cotisation, is_excluded_from_donation FROM compta_type ORDER BY sort_order ASC, label ASC")->fetchAll(PDO::FETCH_OBJ);
$comptaTypes = [];
foreach ($_ctRows as $_ct) { $comptaTypes[(int)$_ct->id] = $_ct; }
unset($_ctRows, $_ct);

// App settings
$_settingsRows = $pdo->query("SELECT `key`, `value` FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appSettings = array_merge([
    'default_team' => '249',
    'membre_team'  => '245',
    'archive_id'   => '19',
], $_settingsRows);
unset($_settingsRows);
define('ARCHIVE_ID', (int)$appSettings['archive_id']);

// Sequence counter — wraps the maxval table that tracks auto-increment IDs.
// TODO: replace with native AUTO_INCREMENT + lastInsertId() after a schema migration.
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

function formatedDateToTimeStamp(?string $formatedDate): int
{
    if ($formatedDate) {
        $d = DateTime::createFromFormat('d/m/Y', $formatedDate);
        return $d ? $d->getTimestamp() : 0;
    }
    return 0;
}

function timeStampToformatedDate(?int $timestamp): string
{
    return $timestamp ? date("d/m/Y", $timestamp) : "";
}

function unquote(string $s): string
{
    return str_replace("'", "'", $s);
}

function auditLog(PDO $pdo, string $action, string $detail = ''): void
{
    $uid      = isset($_SESSION['app_user_id']) ? (int)$_SESSION['app_user_id'] : null;
    $username = $_SESSION['app_user_username'] ?? null;
    $pdo->prepare(
        "INSERT INTO audit_log (app_user_id, username, action, detail) VALUES (?, ?, ?, ?)"
    )->execute([$uid, $username, $action, $detail ?: null]);
}
