<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for DB maintenance from the admin UI: apply pending migrations.
 * (The SQL export is a separate streamed entry point, html/export.php.)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/../lib/migrations.php';

$action = $_REQUEST['action'];

if ($action === 'applyMigrations') {
    if (!isAdmin()) { http_response_code(403); exit; }

    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect = static function (string $q) use ($isHtmx): void {
        $url = $_SERVER['PHP_SELF'] . '?view=settings&tab=health&' . $q;
        if ($isHtmx) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
        exit;
    };

    // Require the explicit "I have a backup" confirmation (DDL is not reversible).
    if (empty($_REQUEST['confirm_backup'])) {
        $redirect('migErr=backup');
    }

    // Require a recent export (within 30 minutes) — enforce the backup step, not just the checkbox.
    $lastExport = (int)($_SESSION['last_db_export'] ?? 0);
    if ($lastExport === 0 || (time() - $lastExport) > 1800) {
        $redirect('migErr=noRecentExport');
    }

    // Exclusive in-process lock: prevent concurrent migration runs (e.g. double-click).
    $lockFile = sys_get_temp_dir() . '/memberbase_migrate_' . md5(__FILE__) . '.lock';
    $lock = fopen($lockFile, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $redirect('migErr=locked');
    }

    @set_time_limit(120);
    $res = mbRunPendingMigrations($pdo);

    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);

    $detail = 'applied: ' . (implode(',', $res['applied']) ?: '(none)');
    if ($res['error']) { $detail .= ' | FAILED ' . $res['failed'] . ': ' . $res['error']; }
    auditLog($pdo, 'applyMigrations', $detail);

    $redirect($res['error'] ? 'migErr=1' : 'migOk=' . count($res['applied']));

}
