<?php
define('APP_ENTRY', true);
/**
 * Admin-only SQL export (database backup) — streams a full dump as a download.
 *
 * A separate entry point (not an index.php action) so the file can be streamed
 * without the page HTML already buffered. Pure PHP dump — no mysqldump/exec,
 * so it works on locked-down shared hosting.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/includes/lib/auth.php';
requireLogin();
require_once __DIR__ . '/includes/lib/bootstrap.php';
require_once __DIR__ . '/includes/lib/migrations.php';

if (!isAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}

$fname = 'backup_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)($database ?? 'db')) . '_' . date('Ymd_His') . '.sql';

// Drop any buffered output, then stream the dump.
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: no-store');
@set_time_limit(0);

auditLog($pdo, 'dbExport', 'SQL export');
mbDumpDatabase($pdo);
exit;
