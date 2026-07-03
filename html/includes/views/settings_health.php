<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin health / observability panel.
 *
 * Read-only system status to diagnose without SSH: app version, runtime, DB
 * server + schema/migration state, a few volume counters, and last activity.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (!isAdmin()): ?>
  <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
<?php
    return;
endif;

/** Safe single-scalar query — returns null on any error (never breaks the page). */
$_hScalar = static function (PDO $pdo, string $sql) {
    try { return $pdo->query($sql)->fetchColumn(); }
    catch (Throwable) { return null; }
};

// --- Application / runtime ---
$dbServer = null;
try { $dbServer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); } catch (Throwable) {}
$dbName = $_hScalar($pdo, 'SELECT DATABASE()');

// Git commit — best effort; exec is often disabled in prod, so fail silently.
$gitCommit = null;
if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    $out = @shell_exec('git -C ' . escapeshellarg(dirname(__DIR__, 3)) . ' rev-parse --short HEAD 2>/dev/null');
    $gitCommit = $out ? trim($out) : null;
}

// --- Migrations (see #68) ---
$pending      = pendingMigrations($pdo);
$drift        = migrationDrift($pdo);
$appliedCount = $_hScalar($pdo, 'SELECT COUNT(*) FROM schema_migrations');
$lastMigration = null;
try {
    $lastMigration = $pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY version DESC LIMIT 1')->fetchObject();
} catch (Throwable) {}

// --- Volume counters ---
$nbTables  = $_hScalar($pdo, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
$nbMembers = $_hScalar($pdo, 'SELECT COUNT(*) FROM users WHERE status = 1');
$nbCompta  = $_hScalar($pdo, 'SELECT COUNT(*) FROM compta');
$nbAppUsers = $_hScalar($pdo, 'SELECT COUNT(*) FROM app_users WHERE is_active = 1');

// --- Last activity (audit log) ---
$lastAudit = null;
try {
    $lastAudit = $pdo->query('SELECT action, created_at FROM audit_log ORDER BY id DESC LIMIT 1')->fetchObject();
} catch (Throwable) {}

// --- Overall status ---
$degraded = !empty($pending) || !empty($drift);
?>

<h5 class="mb-3"><i class="fas fa-heart-pulse me-1" aria-hidden="true"></i>Santé du système</h5>

<?php
$_migOk  = isset($_GET['migOk']) ? (int)$_GET['migOk'] : null;
$_migErr = $_GET['migErr'] ?? null;
if ($_migErr === 'backup'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Cochez « J'ai fait une sauvegarde » avant d'appliquer les migrations.</div>
<?php elseif ($_migErr === 'noRecentExport'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Vous devez <strong>exporter la base</strong> depuis ce navigateur dans les 30 dernières minutes avant d'appliquer les migrations.</div>
<?php elseif ($_migErr === 'locked'): ?>
  <div class="alert alert-warning py-2" role="alert"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Une migration est déjà en cours — attendez qu'elle se termine avant de relancer.</div>
<?php elseif ($_migErr !== null): ?>
  <div class="alert alert-danger py-2" role="alert"><i class="fas fa-circle-xmark me-1" aria-hidden="true"></i>Échec lors de l'application des migrations — consultez le journal d'audit et restaurez depuis votre sauvegarde si besoin.</div>
<?php elseif ($_migOk !== null): ?>
  <div class="alert alert-success py-2" role="alert"><i class="fas fa-circle-check me-1" aria-hidden="true"></i><?= (int)$_migOk ?> migration(s) appliquée(s) avec succès.</div>
<?php endif ?>

<?php if (!empty($drift)): ?>
  <div class="alert alert-danger py-2" role="alert">
    <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
    <strong>Dérive de migration :</strong> <?= count($drift) ?> migration(s) appliquée(s)
    dont le fichier a changé depuis (<?= htmlspecialchars(implode(', ', $drift), ENT_QUOTES, $charset) ?>).
    Un fichier de migration déjà appliqué ne doit jamais être modifié — vérifiez le dépôt.
  </div>
<?php endif ?>

<?php if (!empty($pending)): ?>
  <div class="alert alert-warning py-2" role="alert">
    <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>
    <strong>Attention :</strong> <?= count($pending) ?> migration(s) de base de données en attente
    (<?= htmlspecialchars(implode(', ', $pending), ENT_QUOTES, $charset) ?>).
    Appliquez-les avec <code>php html/tools/migrate.php</code>.
  </div>
<?php elseif (empty($drift)): ?>
  <div class="alert alert-success py-2" role="alert">
    <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
    Système opérationnel — base à jour, aucune migration en attente.
  </div>
<?php endif ?>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-cube me-1" aria-hidden="true"></i>Application</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted">Version</th><td class="text-end"><code>v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Commit</th><td class="text-end"><?= $gitCommit ? '<code>' . htmlspecialchars($gitCommit, ENT_QUOTES, $charset) . '</code>' : '<span class="text-muted">—</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">PHP</th><td class="text-end"><code><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Serveur</th><td class="text-end small"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—', ENT_QUOTES, $charset) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-database me-1" aria-hidden="true"></i>Base de données</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted">Connexion</th><td class="text-end"><span class="badge text-bg-success">OK</span></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Serveur</th><td class="text-end"><code><?= htmlspecialchars((string)($dbServer ?? '—'), ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Base</th><td class="text-end"><code><?= htmlspecialchars((string)($dbName ?? '—'), ENT_QUOTES, $charset) ?></code></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Tables</th><td class="text-end"><?= $nbTables !== null ? (int)$nbTables : '—' ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="card-title text-muted mb-3"><i class="fas fa-code-branch me-1" aria-hidden="true"></i>Migrations</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted">Appliquées</th><td class="text-end"><?= $appliedCount !== null ? (int)$appliedCount : '<span class="text-muted">table absente</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">En attente</th><td class="text-end"><?= empty($pending) ? '<span class="badge text-bg-success">0</span>' : '<span class="badge text-bg-warning">' . count($pending) . '</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Dérive (checksum)</th><td class="text-end"><?= empty($drift) ? '<span class="badge text-bg-success">0</span>' : '<span class="badge text-bg-danger">' . count($drift) . '</span>' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Dernière</th><td class="text-end small">
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
        <h6 class="card-title text-muted mb-3"><i class="fas fa-chart-simple me-1" aria-hidden="true"></i>Volumétrie &amp; activité</h6>
        <table class="table table-sm mb-0" style="font-size:0.85rem">
          <tbody>
            <tr><th scope="row" class="fw-normal text-muted">Membres actifs</th><td class="text-end"><?= $nbMembers !== null ? (int)$nbMembers : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Écritures compta</th><td class="text-end"><?= $nbCompta !== null ? (int)$nbCompta : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Utilisateurs app</th><td class="text-end"><?= $nbAppUsers !== null ? (int)$nbAppUsers : '—' ?></td></tr>
            <tr><th scope="row" class="fw-normal text-muted">Dernière action</th><td class="text-end small">
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
    <h6 class="card-title text-muted mb-3"><i class="fas fa-screwdriver-wrench me-1" aria-hidden="true"></i>Maintenance</h6>
    <div class="d-flex flex-wrap gap-3 align-items-start">
      <a class="btn btn-outline-secondary btn-sm" href="export.php" hx-boost="false" download>
        <i class="fas fa-download me-1" aria-hidden="true"></i>Exporter la base (SQL)
      </a>
      <?php if (!empty($pending)):
        // Check if a DB export was done in the last 30 minutes in this session.
        $hasRecentExport = isset($_SESSION['last_db_export']) && (time() - (int)$_SESSION['last_db_export']) <= 1800;
      ?>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex flex-column gap-1">
          <input type="hidden" name="action" value="applyMigrations">
          <input type="hidden" name="view" value="settings">
          <input type="hidden" name="tab" value="health">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_backup" id="confirm_backup" required data-no-dirty>
            <label class="form-check-label small" for="confirm_backup">J'ai fait une sauvegarde</label>
          </div>
          <?php if (!$hasRecentExport): ?>
            <small class="text-warning"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Exportez d'abord la base ci-dessus (requis).</small>
          <?php endif ?>
          <button type="submit" class="btn btn-warning btn-sm" <?= !$hasRecentExport ? 'disabled title="Exportez la base avant de migrer"' : '' ?>>
            <i class="fas fa-database me-1" aria-hidden="true"></i>Appliquer <?= count($pending) ?> migration(s)
          </button>
        </form>
      <?php endif ?>
    </div>
    <p class="text-muted small mt-2 mb-0">
      L'export génère un dump SQL téléchargeable (restaurable via phpMyAdmin ou <code>make restore</code>) — utile
      sur un hébergement <strong>sans accès SSH</strong>. L'application des migrations exécute le DDL en attente ;
      <strong>exportez juste avant</strong> (le DDL n'est pas annulable).
    </p>
  </div>
</div>

<p class="text-muted small mt-3 mb-0">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
  Un point de contrôle léger pour du monitoring externe est disponible en <code>/health.php</code>
  (JSON <code>{"status":"ok"|"degraded"}</code>, sans authentification ni donnée sensible).
</p>
