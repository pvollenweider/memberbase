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
