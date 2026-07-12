<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Dashboard landing view (#153) — task banner, quick KPIs, role-filtered docs.
 * Only reached as the landing page when mbHasOpenTasks() is true (see
 * includes/routing/views.php); always reachable via ?view=dashboard.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$_year = (int)date('Y');

$_urgentStmt = db()->prepare(
    "SELECT t.id, t.title, t.rule_key, t.due_date, t.user_id,
            u.firstname, u.lastname, u.society, u.email
     FROM suivi_task t
     LEFT JOIN contact u ON u.id = t.user_id
     WHERE t.done_at IS NULL AND t.due_date IS NOT NULL AND t.due_date <= ?
     ORDER BY t.due_date ASC, t.priority ASC
     LIMIT 5"
);
$_urgentStmt->execute([date('Y-m-d', strtotime('+3 days'))]);
$_urgentTasks = $_urgentStmt->fetchAll(PDO::FETCH_OBJ);

$_openTaskCount = (int)db()->query("SELECT COUNT(*) FROM suivi_task WHERE done_at IS NULL")->fetchColumn();

$_hasCotiTask = false;
foreach ($_urgentTasks as $_t) {
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_')) {
        $_hasCotiTask = true;
        break;
    }
}

$_unpaidCotiCount = count(MemberFilter::resolveIds(FILTER_UNPAID_COTI_CURRENT, db(), $_year, $appSettings));
$_pendingMigrationsCount = isAdmin() ? count(pendingMigrations($pdo)) : 0;
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['dashboardPageTitle'] ?></h1>
</div>

<?php if (canWrite()): ?>
<div class="d-flex mb-3">
  <a href="<?= appUrl() ?>?view=addUser" class="btn btn-primary btn-sm">
    <i class="fas fa-user-plus me-1" aria-hidden="true"></i><?= $GLOBAL['addUser'] ?>
  </a>
</div>
<?php endif ?>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= $GLOBAL['dashboardTasksTitle'] ?></span>
        <span class="text-muted small"><?= sprintf($GLOBAL['dashboardOpenTaskCount'], $_openTaskCount) ?></span>
      </div>
      <?php if (empty($_urgentTasks)): ?>
      <div class="card-body">
        <p class="text-muted mb-0"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['noOpenTasks'] ?></p>
      </div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($_urgentTasks as $_t):
            $_dueTs   = strtotime($_t->due_date);
            $_overdue = mbTaskIsOverdue($_dueTs, null);
            $_name    = $_t->user_id
                ? trim(($_t->society ? htmlentities($_t->society, ENT_COMPAT, $charset) . ' ' : '') .
                       htmlentities($_t->lastname, ENT_COMPAT, $charset) . ' ' .
                       htmlentities($_t->firstname, ENT_COMPAT, $charset))
                : $GLOBAL['globalTask'];
            $_href = $_t->user_id
                ? appUrl() . '?view=memberTasks&userid=' . (int)$_t->user_id
                : appUrl() . '?view=updateTask&taskid=' . (int)$_t->id;
            $_statusLabel = $_overdue ? $GLOBAL['taskOverdue'] : date('d.m.Y', $_dueTs);
            $_rowLabel = sprintf('%s: %s — %s (%s)', $GLOBAL['taskTitle'], $_t->title, $_statusLabel,
                $_t->user_id ? trim(($_t->society ? $_t->society . ' ' : '') . $_t->lastname . ' ' . $_t->firstname) : $GLOBAL['globalTask']);
        ?>
        <div class="list-group-item d-flex align-items-center gap-2 position-relative">
          <i class="fas fa-circle <?= $_overdue ? 'text-danger' : 'text-warning' ?>" style="font-size:0.5rem" aria-hidden="true"></i>
          <span class="flex-grow-1">
            <span class="fw-medium"><?= htmlspecialchars($_t->title, ENT_QUOTES, $charset) ?></span>
            <span class="text-muted small d-block"><?= $_name ?></span>
          </span>
          <span class="small fw-semibold <?= $_overdue ? 'text-danger' : 'text-warning' ?> text-nowrap">
            <?= $_overdue
                ? $GLOBAL['taskOverdue']
                : htmlspecialchars(date('d.m.Y', $_dueTs), ENT_QUOTES, $charset) ?>
          </span>
          <?php if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_') && $_t->user_id && trim((string)$_t->email) !== ''): ?>
          <button type="button" class="btn btn-outline-primary btn-sm js-task-send-coti" style="position:relative;z-index:2"
                  data-user-id="<?= (int)$_t->user_id ?>"
                  data-year="<?= $_year ?>"
                  data-task-id="<?= (int)$_t->id ?>"
                  data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderConfirmOne'], trim(($_t->firstname ?? '') . ' ' . ($_t->lastname ?? ''))), ENT_QUOTES, $charset) ?>"
                  data-msg-fail="<?= htmlspecialchars($GLOBAL['cotiReminderSentFail'], ENT_QUOTES, $charset) ?>"
                  data-label-sending="<?= htmlspecialchars($GLOBAL['sendCotiRemindersSending'], ENT_QUOTES, $charset) ?>">
              <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendCotiRemindersBtnOne'] ?>
          </button>
          <?php endif ?>
          <a href="<?= $_href ?>" class="stretched-link" hx-boost="false"
             aria-label="<?= htmlspecialchars($_rowLabel, ENT_QUOTES, $charset) ?>"></a>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
      <div class="card-footer">
        <a href="<?= appUrl() ?>?view=tasks"><?= $GLOBAL['dashboardViewAllTasks'] ?></a>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5 d-flex flex-column gap-3">
    <div class="card">
      <div class="card-header"><?= $GLOBAL['dashboardKpiTitle'] ?></div>
      <div class="list-group list-group-flush">
        <a href="<?= appUrl() . '?segment=' . FILTER_UNPAID_COTI_CURRENT ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= $GLOBAL['cotiUnpayed'] ?></span>
          <span class="fw-bold"><?= $_unpaidCotiCount ?></span>
        </a>
        <?php if (isAdmin() && $_pendingMigrationsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=settings&amp;tab=health" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= sprintf($GLOBAL['pendingDbMigrationsLabel'], $_pendingMigrationsCount > 1 ? 's' : '') ?></span>
          <span class="fw-bold"><?= $_pendingMigrationsCount ?></span>
        </a>
        <?php endif ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><?= $GLOBAL['documentation'] ?></div>
      <div class="list-group list-group-flush">
        <a href="https://pvollenweider.github.io/memberbase/docs/user.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-book me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardUserGuideLink'] ?>
        </a>
        <?php if (isManager()): ?>
        <a href="https://pvollenweider.github.io/memberbase/docs/admin.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-screwdriver-wrench me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardAdminGuideLink'] ?>
        </a>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<?php if ($_hasCotiTask): ?>
<?php require __DIR__ . '/../partials/task_coti_reminder_modal.php'; ?>
<?php endif ?>
