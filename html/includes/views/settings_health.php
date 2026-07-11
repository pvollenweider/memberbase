<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin health / observability panel.
 *
 * Read-only system status to diagnose without SSH: app version, runtime, DB
 * server + schema/migration state, a few volume counters, and last activity.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (!isAdmin()): ?>
  <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
<?php
    return;
endif;

/** Safe single-scalar query — returns null on any error (never breaks the page). */
$_hScalar = static function (string $sql) {
    try { return db()->query($sql)->fetchColumn(); }
    catch (Throwable) { return null; }
};

// --- Application / runtime ---
$dbServer = null;
try { $dbServer = db()->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (Throwable) {}
$dbName = $_hScalar('SELECT DATABASE()');

// Git commit — best effort; exec is often disabled in prod, so fail silently.
$gitCommit = null;
if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    $out = @shell_exec('git -C ' . escapeshellarg(dirname(__DIR__, 3)) . ' rev-parse --short HEAD 2>/dev/null');
    $gitCommit = $out ? trim($out) : null;
}

// --- Migrations (see #68) ---
$pending      = pendingMigrations(db());
$drift        = migrationDrift(db());
$appliedCount = $_hScalar('SELECT COUNT(*) FROM schema_migrations');
$lastMigration = null;
try {
    $lastMigration = db()->query('SELECT version, applied_at FROM schema_migrations ORDER BY version DESC LIMIT 1')->fetchObject();
} catch (Throwable) {}

// --- Volume counters ---
$nbTables  = $_hScalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
$nbMembers = $_hScalar('SELECT COUNT(*) FROM contact WHERE status = 1');
$nbCompta  = $_hScalar('SELECT COUNT(*) FROM compta');
$nbAppUsers = $_hScalar('SELECT COUNT(*) FROM app_users WHERE is_active = 1');

// --- Last activity (audit log) ---
$lastAudit = null;
try {
    $lastAudit = db()->query('SELECT action, created_at FROM audit_log ORDER BY id DESC LIMIT 1')->fetchObject();
} catch (Throwable) {}

// --- Overall status ---
$degraded = !empty($pending) || !empty($drift);
?>

<h5 class="mb-3"><i class="fas fa-heart-pulse me-1" aria-hidden="true"></i><?= $GLOBAL['systemHealth'] ?></h5>

<?php
$_migOk  = isset($_GET['migOk']) ? (int)$_GET['migOk'] : null;
$_migErr = $_GET['migErr'] ?? null;
$_bulkComptaOk   = isset($_GET['bulkComptaOk'])   ? (int)$_GET['bulkComptaOk']   : null;
$_bulkComptaErr  = $_GET['bulkComptaErr'] ?? null;
?>
<?php if ($_bulkComptaOk !== null): ?>
  <div class="alert alert-success py-2" role="alert"><i class="fas fa-circle-check me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['comptaBulkOk'], $_bulkComptaOk) ?></div>
<?php elseif ($_bulkComptaErr === 'invalidDate'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['comptaBulkErrDate'] ?></div>
<?php elseif ($_bulkComptaErr !== null): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['comptaBulkErrConfirm'] ?></div>
<?php endif ?>
<?php if ($_migErr === 'backup'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['migErrBackup'] ?></div>
<?php elseif ($_migErr === 'noRecentExport'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['migErrNoRecentExport'] ?></div>
<?php elseif ($_migErr === 'locked'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['migErrLocked'] ?></div>
<?php elseif ($_migErr !== null): ?>
  <div class="alert alert-danger py-2" role="alert"><i class="fas fa-circle-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['migErrGeneric'] ?></div>
<?php elseif ($_migOk !== null): ?>
  <div class="alert alert-success py-2" role="alert"><i class="fas fa-circle-check me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['migrationsAppliedSuccess'], (int)$_migOk) ?></div>
<?php endif ?>

<?php if (!empty($drift)): ?>
  <div class="alert alert-danger py-2" role="alert">
    <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
    <strong><?= $GLOBAL['migrationDriftLabel'] ?></strong> <?= sprintf($GLOBAL['migrationDriftBody'], count($drift), htmlspecialchars(implode(', ', $drift), ENT_QUOTES, $charset)) ?>
  </div>
<?php endif ?>

<?php if (!empty($pending)): ?>
  <div class="alert alert-warning py-2" role="alert">
    <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
    <strong><?= $GLOBAL['warningLabel'] ?></strong> <?= sprintf($GLOBAL['pendingMigrationsBody'], count($pending), htmlspecialchars(implode(', ', $pending), ENT_QUOTES, $charset)) ?>
  </div>
<?php elseif (empty($drift)): ?>
  <div class="alert alert-success py-2" role="alert">
    <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
    <?= $GLOBAL['systemOperational'] ?>
  </div>
<?php endif ?>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-cube me-1" aria-hidden="true"></i><?= $GLOBAL['application'] ?></h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['version'] ?></th><td class="text-end"><code>v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['commit'] ?></th><td class="text-end"><?= $gitCommit ? '<code>' . htmlspecialchars($gitCommit, ENT_QUOTES, $charset) . '</code>' : '<span class="text-muted">—</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">PHP</th><td class="text-end"><code><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['server'] ?></th><td class="text-end small"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—', ENT_QUOTES, $charset) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-database me-1" aria-hidden="true"></i><?= $GLOBAL['database'] ?></h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['connection'] ?></th><td class="text-end"><span class="badge text-bg-success">OK</span></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['server'] ?></th><td class="text-end"><code><?= htmlspecialchars((string)($dbServer ?? '—'), ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['databaseShort'] ?></th><td class="text-end"><code><?= htmlspecialchars((string)($dbName ?? '—'), ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['tables'] ?></th><td class="text-end"><?= $nbTables !== null ? (int)$nbTables : '—' ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-code-branch me-1" aria-hidden="true"></i><?= $GLOBAL['migrations'] ?></h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['appliedLabel'] ?></th><td class="text-end"><?= $appliedCount !== null ? (int)$appliedCount : '<span class="text-muted">' . $GLOBAL['tableMissing'] . '</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['pendingLabel'] ?></th><td class="text-end"><?= empty($pending) ? '<span class="badge text-bg-success">0</span>' : '<span class="badge text-bg-warning">' . count($pending) . '</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['driftChecksumLabel'] ?></th><td class="text-end"><?= empty($drift) ? '<span class="badge text-bg-success">0</span>' : '<span class="badge text-bg-danger">' . count($drift) . '</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['lastLabel'] ?></th><td class="text-end small">
              <?php if ($lastMigration): ?>
                <code><?= htmlspecialchars($lastMigration->version, ENT_QUOTES, $charset) ?></code>
                <span class="text-muted d-block"><?= $lastMigration->applied_at ? date('d.m.Y H:i', (int)$lastMigration->applied_at) : '' ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif ?>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-chart-simple me-1" aria-hidden="true"></i><?= $GLOBAL['volumeActivity'] ?></h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['activeMembers'] ?></th><td class="text-end"><?= $nbMembers !== null ? (int)$nbMembers : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['comptaEntriesLabel'] ?></th><td class="text-end"><?= $nbCompta !== null ? (int)$nbCompta : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['appUsersShort'] ?></th><td class="text-end"><?= $nbAppUsers !== null ? (int)$nbAppUsers : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted"><?= $GLOBAL['lastAction'] ?></th><td class="text-end small">
              <?php if ($lastAudit): ?>
                <code><?= htmlspecialchars((string)$lastAudit->action, ENT_QUOTES, $charset) ?></code>
                <span class="text-muted d-block"><?= htmlspecialchars((string)$lastAudit->created_at, ENT_QUOTES, $charset) ?></span>
              <?php else: ?><span class="text-muted">—</span><?php endif ?>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Maintenance (admin) : SQL export + apply pending migrations from the UI -->
<div class="card mt-3">
  <div class="card-body">
    <h6 class="card-title text-muted mb-3"><i class="fas fa-screwdriver-wrench me-1" aria-hidden="true"></i><?= $GLOBAL['maintenance'] ?></h6>
    <div class="d-flex flex-wrap gap-3 align-items-start">
      <a class="btn btn-outline-secondary btn-sm" href="export.php" hx-boost="false" download>
        <i class="fas fa-download me-1" aria-hidden="true"></i><?= $GLOBAL['exportDbSql'] ?>
      </a>
      <?php if (!empty($pending)):
        // Check if a DB export was done in the last 30 minutes in this session.
        $hasRecentExport = isset($_SESSION['last_db_export']) && (time() - (int)$_SESSION['last_db_export']) <= 1800;
      ?>
        <form method="post" action="<?= appUrl() ?>" class="d-flex flex-column gap-1" hx-boost="false">
          <input type="hidden" name="action" value="applyMigrations">
          <input type="hidden" name="view" value="settings">
          <input type="hidden" name="tab" value="health">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_backup" id="confirm_backup" required data-no-dirty>
            <label class="form-check-label small" for="confirm_backup"><?= $GLOBAL['iHaveBackup'] ?></label>
          </div>
          <?php if (!$hasRecentExport): ?>
            <small class="text-warning"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['exportFirstRequired'] ?></small>
          <?php endif ?>
          <button type="submit" class="btn btn-warning btn-sm" <?= !$hasRecentExport ? 'disabled title="' . $GLOBAL['exportBeforeMigrating'] . '"' : '' ?>>
            <i class="fas fa-database me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['applyMigrationsCount'], count($pending)) ?>
          </button>
        </form>
      <?php endif ?>
    </div>
    <p class="text-muted small mt-2 mb-0">
      <?= $GLOBAL['exportHelpParagraph'] ?>
    </p>
  </div>
</div>


<?php
// Count compta entries not yet included in any recap batch.
// Guard against missing column (migration 0007 not yet applied).
try {
    $_comptaUntouched = (int)db()->query(
        "SELECT COUNT(*) FROM compta WHERE notified_at IS NULL"
    )->fetchColumn();
} catch (PDOException $e) {
    $_comptaUntouched = 0;
}
if ($_comptaUntouched > 0):
?>
<div class="card mt-3 border-warning-subtle">
  <div class="card-body">
    <h6 class="card-title text-muted mb-2">
      <i class="fas fa-coins me-1" aria-hidden="true"></i><?= $GLOBAL['comptaBulkTitle'] ?>
    </h6>
    <p class="small text-muted mb-2"><?= sprintf($GLOBAL['comptaBulkDesc'], $_comptaUntouched) ?></p>
    <form method="post" action="<?= appUrl() ?>" class="d-flex flex-column gap-1" style="max-width:380px" hx-boost="false">
      <input type="hidden" name="action" value="markAllComptaNotified">
      <input type="hidden" name="view"   value="settings">
      <input type="hidden" name="tab"    value="health">
      <div class="mb-1">
        <label class="form-label small" for="bulk_compta_date"><?= $GLOBAL['comptaBulkDateLabel'] ?></label>
        <input type="date" class="form-control form-control-sm" name="bulk_date" id="bulk_compta_date"
               value="<?= date('Y-01-01') ?>" max="<?= date('Y-m-d') ?>" required data-no-dirty>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="confirm_bulk" id="confirm_bulk_compta" required data-no-dirty>
        <label class="form-check-label small" for="confirm_bulk_compta"><?= $GLOBAL['comptaBulkConfirm'] ?></label>
      </div>
      <button type="submit" class="btn btn-outline-warning btn-sm">
        <i class="fas fa-check-double me-1" aria-hidden="true"></i><?= $GLOBAL['comptaBulkBtn'] ?>
      </button>
    </form>
  </div>
</div>
<?php endif ?>

<p class="text-muted small mt-3 mb-0">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
  <?= $GLOBAL['healthEndpointHelp'] ?>
</p>
