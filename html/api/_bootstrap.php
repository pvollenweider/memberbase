<?php
/**
 * Shared bootstrap for all API endpoints.
 * Handles JSON headers, DB/session init, and auth guard.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
define('APP_ENTRY', true);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/lib/bootstrap.php';
require_once __DIR__ . '/../includes/lib/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function apiError(int $code, string $message): never
{
    // Security log: access denied on the API (role guard). Best-effort — never
    // let logging break the API response.
    if ($code === 403) {
        global $pdo;
        try {
            auditLog($pdo, 'accessDenied', 'api ' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' role=' . ($_SESSION['app_user_role'] ?? '?'));
        } catch (Throwable) { /* ignore */ }
    }
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// CSRF hardening for the JSON API (#89). Body-carrying mutations must declare
// Content-Type: application/json. A browser cannot set that header on a
// cross-origin "simple" request without a CORS preflight (which this API never
// grants), so a form/text-plain CSRF POST is rejected while legit JSON clients
// pass. GET (read) and DELETE (query-param only, already preflighted
// cross-origin) are not gated.
$_apiMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($_apiMethod, ['POST', 'PUT', 'PATCH'], true)) {
    $_apiCt = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($_apiCt, 'application/json') !== 0) {
        apiError(415, 'Content-Type application/json required');
    }
}

// Rate limiting (#92). Fixed-window counter per (user + IP): at most
// API_RATE_MAX requests per API_RATE_WINDOW seconds → 429. Self-contained (the
// tracking table is created lazily), and best-effort: any limiter error is
// swallowed so it can never take the API down. The UI mostly talks to
// index.php, not /api, so the limit is generous.
const API_RATE_WINDOW = 60;
const API_RATE_MAX    = 600;
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `api_rate_limit` (
            `bucket`       VARCHAR(190) NOT NULL,
            `hits`         INT(11)      NOT NULL DEFAULT 0,
            `window_start` INT(11)      NOT NULL DEFAULT 0,
            PRIMARY KEY (`bucket`),
            KEY `idx_window_start` (`window_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $_rlId  = 'u' . ($_SESSION['app_user_id'] ?? '0') . ':' . ($_SERVER['REMOTE_ADDR'] ?? '?');
    $_rlKey = $_rlId . ':' . intdiv(time(), API_RATE_WINDOW);
    $pdo->prepare(
        "INSERT INTO api_rate_limit (bucket, hits, window_start) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE hits = hits + 1"
    )->execute([$_rlKey, time()]);
    $_rlStmt = $pdo->prepare("SELECT hits FROM api_rate_limit WHERE bucket = ?");
    $_rlStmt->execute([$_rlKey]);
    $_rlHits = (int)$_rlStmt->fetchColumn();
    // Opportunistic cleanup of stale buckets (~1% of requests).
    if (random_int(1, 100) === 1) {
        $pdo->prepare("DELETE FROM api_rate_limit WHERE window_start < ?")
            ->execute([time() - API_RATE_WINDOW * 5]);
    }
    if ($_rlHits > API_RATE_MAX) {
        header('Retry-After: ' . API_RATE_WINDOW);
        apiError(429, 'Too Many Requests');
    }
} catch (Throwable) { /* never block the API on a limiter failure */ }
