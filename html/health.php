<?php
declare(strict_types=1);
/**
 * Lightweight health check for external monitoring — no authentication.
 *
 * Returns JSON {"status":"ok"} with HTTP 200 when the database is reachable and
 * no schema migration is pending, otherwise {"status":"degraded"} with HTTP 503.
 * Deliberately leaks NO sensitive detail (no versions, hostnames, counts).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

// Resolve DB config like the migration runner (no app bootstrap / no session).
$repoRoot = dirname(__DIR__);
$confFile = $repoRoot . '/conf/db.php';
if (is_file($confFile)) {
    require_once $confFile;
}
$host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'members');
$pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: 'members');
$name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'members');

/** Emit the status and stop. */
function health_respond(string $status, int $code): never
{
    http_response_code($code);
    echo json_encode(['status' => $status], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->query('SELECT 1');
} catch (Throwable) {
    health_respond('degraded', 503);
}

// Pending migrations = degraded (schema not fully applied). Missing tracking
// table (never migrated) is treated as OK for a plain reachability probe.
try {
    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files   = glob(__DIR__ . '/migrations/*.sql') ?: [];
    $appliedMap = array_flip($applied);
    foreach ($files as $f) {
        if (!isset($appliedMap[basename($f, '.sql')])) {
            health_respond('degraded', 503);
        }
    }
} catch (Throwable) {
    // schema_migrations absent — reachable DB is enough for this probe.
}

health_respond('ok', 200);
