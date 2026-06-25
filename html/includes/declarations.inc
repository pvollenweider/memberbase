<?php
function startswith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

date_default_timezone_set("Europe/Zurich");
define('LOG4PHP_DIR', 'log4php');
require_once('log4php/Logger.php');
$logger = Logger::getLogger('declarations.php');
Logger::configure('config.xml');
$logger->info("Get request");

define('ARCHIVE_ID', 19);

// database settings
$hostname = "localhost";
$username = "members";
$password = "members";
$database = "members";

$dsn = "mysql:host={$hostname};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

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
