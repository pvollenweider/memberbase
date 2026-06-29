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
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
