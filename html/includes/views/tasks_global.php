<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Global view: all open tasks, sorted by due date / priority.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Guard: paused_at is migration 0039 -- a not-yet-migrated instance doesn't
// have it, and every query below references it. Show a notice instead of
// fataling (same pattern as settings_general.php's $_ctSchemaPending).
if (in_array('0039_suivi_task_paused_at', pendingMigrations($pdo), true)) {
    include __DIR__ . '/settings_schema_pending_notice.php';
    return;
}

$_priorityLabels = [
    SuiviTask::PRIORITY_HIGH   => $GLOBAL['taskPriorityHigh'],
    SuiviTask::PRIORITY_NORMAL => $GLOBAL['taskPriorityNormal'],
    SuiviTask::PRIORITY_LOW    => $GLOBAL['taskPriorityLow'],
];

$_year = (int)date('Y');
$stmt = db()->query(
    "SELECT t.id, t.title, t.body, t.priority, t.rule_key, t.due_date, t.user_id,
            u.firstname, u.lastname, u.society, u.email
     FROM suivi_task t
     LEFT JOIN contact u ON u.id = t.user_id
     WHERE t.done_at IS NULL AND t.paused_at IS NULL
     ORDER BY t.priority ASC, t.due_date IS NULL, t.due_date ASC"
);
$_tasks = $stmt->fetchAll(PDO::FETCH_OBJ);
$_hasCotiTask = false;
$_hasRecapTask = false;
$_hasAttestationTask = false;
foreach ($_tasks as $_t) {
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_')) {
        $_hasCotiTask = true;
    }
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'compta_recap_pending_')) {
        $_hasRecapTask = true;
    }
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'attestation_pending_')) {
        $_hasAttestationTask = true;
    }
}
$_cotiPendingGen        = isAdmin() ? SuiviTask::countUnpaidCotiPendingGeneration($_year, $appSettings) : 0;
$_recapPendingGen       = isAdmin() ? SuiviTask::countComptaRecapPendingGeneration($_year) : 0;
$_dupPendingGen         = isAdmin() ? SuiviTask::countDuplicatePendingGeneration() : 0;
$_hiddenSegPendingGen   = isAdmin() ? SuiviTask::countHiddenSegmentPendingGeneration() : 0;
$_attestationPendingGen = isAdmin() ? SuiviTask::countAttestationPendingGeneration() : 0;

// Completed tasks: separate, capped query — this view's main table is open
// tasks only, done ones don't belong mixed in with what still needs action.
$_doneStmt = db()->query(
    "SELECT t.id, t.title, t.priority, t.user_id, t.done_at,
            u.firstname, u.lastname, u.society
     FROM suivi_task t
     LEFT JOIN contact u ON u.id = t.user_id
     WHERE t.done_at IS NOT NULL
     ORDER BY t.done_at DESC
     LIMIT 200"
);
$_doneTasks = $_doneStmt->fetchAll(PDO::FETCH_OBJ);

// Paused tasks: parked, out of the active list, but not done or forgotten.
$_pausedStmt = db()->query(
    "SELECT t.id, t.title, t.priority, t.user_id, t.paused_at,
            u.firstname, u.lastname, u.society
     FROM suivi_task t
     LEFT JOIN contact u ON u.id = t.user_id
     WHERE t.done_at IS NULL AND t.paused_at IS NOT NULL
     ORDER BY t.paused_at DESC"
);
$_pausedTasks = $_pausedStmt->fetchAll(PDO::FETCH_OBJ);
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['tasksPageTitle'] ?></h1>
</div>

<?php if (isset($_GET['generated'])): ?>
<div class="alert alert-success py-2" role="alert">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
  <?= sprintf($GLOBAL['taskGeneratedCount'], (int)$_GET['generated']) ?>
  <?php if ((int)($_GET['closed'] ?? 0) > 0): ?>
  <?= sprintf($GLOBAL['taskAutoClosedCount'], (int)$_GET['closed']) ?>
  <?php endif ?>
</div>
<?php endif ?>

<?php if (isset($_GET['bulkDeleted'])): ?>
<div class="alert alert-success py-2" role="alert">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['taskBulkDeletedCount'], (int)$_GET['bulkDeleted']) ?>
</div>
<?php endif ?>

<?php if (isAdmin() && ($_cotiPendingGen > 0 || $_recapPendingGen > 0 || $_dupPendingGen > 0 || $_hiddenSegPendingGen > 0 || $_attestationPendingGen > 0)): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
<?php if ($_cotiPendingGen > 0): ?>
<form action="<?= appUrl() ?>" method="post" data-no-dirty>
  <input type="hidden" name="action" value="generateUnpaidCotiTasks"/>
  <button type="submit" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i><?= $GLOBAL['taskGenerateBtn'] ?>
  </button>
</form>
<?php endif ?>
<?php if ($_recapPendingGen > 0): ?>
<form action="<?= appUrl() ?>" method="post" data-no-dirty>
  <input type="hidden" name="action" value="generateComptaRecapTasks"/>
  <button type="submit" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i><?= $GLOBAL['taskGenerateRecapBtn'] ?>
  </button>
</form>
<?php endif ?>
<?php if ($_dupPendingGen > 0): ?>
<form action="<?= appUrl() ?>" method="post" data-no-dirty>
  <input type="hidden" name="action" value="generateDuplicateTasks"/>
  <button type="submit" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i><?= $GLOBAL['taskGenerateDupBtn'] ?>
  </button>
</form>
<?php endif ?>
<?php if ($_hiddenSegPendingGen > 0): ?>
<form action="<?= appUrl() ?>" method="post" data-no-dirty>
  <input type="hidden" name="action" value="generateHiddenSegmentTasks"/>
  <button type="submit" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i><?= $GLOBAL['taskGenerateHiddenSegBtn'] ?>
  </button>
</form>
<?php endif ?>
<?php if ($_attestationPendingGen > 0): ?>
<form action="<?= appUrl() ?>" method="post" data-no-dirty>
  <input type="hidden" name="action" value="generateAttestationTasks"/>
  <button type="submit" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-wand-magic-sparkles me-1" aria-hidden="true"></i><?= $GLOBAL['taskGenerateAttestationBtn'] ?>
  </button>
</form>
<?php endif ?>
</div>
<?php endif ?>

<?php if (canWrite()): ?>
<form action="<?= appUrl() ?>" method="post" name="addTask" class="mb-4">
<input type="hidden" name="action" value="addTask"/>
<input type="hidden" name="view" value="tasks"/>
<div class="row g-2 align-items-end">
  <div class="col-12 col-md-4">
    <label for="title" class="form-label form-label-sm small text-muted"><?= $GLOBAL['taskTitle'] ?></label>
    <input type="text" name="title" id="title" class="form-control form-control-sm" maxlength="255" required>
  </div>
  <div class="col-6 col-md-2">
    <label for="due_date" class="form-label form-label-sm small text-muted"><?= $GLOBAL['dueDate'] ?></label>
    <input type="text" name="due_date" id="due_date" class="form-control form-control-sm datepicker">
  </div>
  <div class="col-6 col-md-2">
    <label for="priority" class="form-label form-label-sm small text-muted"><?= $GLOBAL['priority'] ?></label>
    <select name="priority" id="priority" class="form-select form-select-sm">
      <?php foreach ($_priorityLabels as $_pv => $_pl): ?>
      <option value="<?= $_pv ?>"<?= $_pv === SuiviTask::PRIORITY_NORMAL ? ' selected' : '' ?>><?= htmlspecialchars($_pl, ENT_QUOTES, $charset) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="col-12 col-md-3">
    <label for="body" class="form-label form-label-sm small text-muted"><?= $GLOBAL['taskBody'] ?></label>
    <input type="text" name="body" id="body" class="form-control form-control-sm">
  </div>
  <div class="col-12 col-md-1">
    <button type="submit" class="btn btn-primary btn-sm w-100"><?= $GLOBAL['add'] ?></button>
  </div>
</div>
</form>
<?php endif ?>

<?php if (empty($_tasks)): ?>
<p class="text-muted"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['noOpenTasks'] ?></p>
<?php else: ?>
<table id="tasks-table" class="table table-sm table-striped table-hover mt-2">
<thead>
<tr>
    <th><?= $GLOBAL['dueDate'] ?></th>
    <th><?= $GLOBAL['priority'] ?></th>
    <th><?= $GLOBAL['taskTitle'] ?></th>
    <th><?= $GLOBAL['member'] ?></th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($_tasks as $_t):
    $_dueTs   = $_t->due_date ? strtotime($_t->due_date) : null;
    $_overdue = mbTaskIsOverdue($_dueTs, null);
    $_name    = $_t->user_id
        ? trim(($_t->society ? htmlentities($_t->society, ENT_COMPAT, $charset) . ' ' : '') .
               htmlentities($_t->lastname, ENT_COMPAT, $charset) . ' ' .
               htmlentities($_t->firstname, ENT_COMPAT, $charset))
        : '';
    $_href = $_t->user_id
        ? appUrl() . '?view=memberTasks&userid=' . (int)$_t->user_id
        : appUrl() . '?view=updateTask&taskid=' . (int)$_t->id;
    $_isRecapTask = $_t->rule_key && str_starts_with($_t->rule_key, 'compta_recap_pending_');
?>
    <tr class="position-relative">
        <td class="text-nowrap <?= $_overdue ? 'text-danger fw-semibold' : '' ?>">
            <?= $_dueTs ? htmlspecialchars(date('d.m.Y', $_dueTs), ENT_QUOTES, $charset) : '—' ?>
            <?php if ($_overdue): ?><i class="fas fa-triangle-exclamation ms-1" aria-hidden="true" title="<?= $GLOBAL['taskOverdue'] ?>"></i><?php endif ?>
        </td>
        <td><?= htmlspecialchars($_priorityLabels[(int)$_t->priority] ?? '', ENT_QUOTES, $charset) ?></td>
        <td>
            <?= htmlspecialchars($_t->title, ENT_QUOTES, $charset) ?>
            <?php if ($_t->body): ?><div class="text-muted small"><?= htmlspecialchars($_t->body, ENT_QUOTES, $charset) ?></div><?php endif ?>
        </td>
        <td class="text-nowrap"><?= $_name ?: '<span class="text-muted">' . $GLOBAL['globalTask'] . '</span>' ?></td>
        <td class="text-end" style="white-space:nowrap">
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
            <?php if ($_t->rule_key && str_starts_with($_t->rule_key, 'compta_recap_pending_') && $_t->user_id && trim((string)$_t->email) !== ''): ?>
            <button type="button" class="btn btn-outline-primary btn-sm js-task-send-recap" style="position:relative;z-index:2"
                    data-user-id="<?= (int)$_t->user_id ?>"
                    data-year="<?= $_year ?>"
                    data-task-id="<?= (int)$_t->id ?>"
                    data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['sendRecapConfirmOne'], trim(($_t->firstname ?? '') . ' ' . ($_t->lastname ?? ''))), ENT_QUOTES, $charset) ?>"
                    data-msg-fail="<?= htmlspecialchars($GLOBAL['sendRecapSentFail'], ENT_QUOTES, $charset) ?>"
                    data-label-sending="<?= htmlspecialchars($GLOBAL['sendRecapSending'], ENT_QUOTES, $charset) ?>">
                <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendRecapBtnOne'] ?>
            </button>
            <?php endif ?>
            <?php if ($_t->rule_key && str_starts_with($_t->rule_key, 'attestation_pending_') && $_t->user_id && trim((string)$_t->email) !== ''): ?>
            <button type="button" class="btn btn-outline-primary btn-sm js-task-send-attestation" style="position:relative;z-index:2"
                    data-user-id="<?= (int)$_t->user_id ?>"
                    data-year="<?= (int)substr($_t->rule_key, strlen('attestation_pending_')) ?>"
                    data-task-id="<?= (int)$_t->id ?>"
                    data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['sendAttestationConfirmOne'], trim(($_t->firstname ?? '') . ' ' . ($_t->lastname ?? ''))), ENT_QUOTES, $charset) ?>"
                    data-msg-fail="<?= htmlspecialchars($GLOBAL['sendAttestationSentFail'], ENT_QUOTES, $charset) ?>"
                    data-label-sending="<?= htmlspecialchars($GLOBAL['sendAttestationSending'], ENT_QUOTES, $charset) ?>">
                <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendAttestationBtnOne'] ?>
            </button>
            <?php endif ?>
            <?php if (canWrite()): ?>
            <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty style="position:relative;z-index:2">
                <input type="hidden" name="action" value="closeTask">
                <input type="hidden" name="taskid" value="<?= (int)$_t->id ?>">
                <input type="hidden" name="view" value="tasks">
                <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $_isRecapTask ? $GLOBAL['taskMarkDoneRecap'] : $GLOBAL['taskMarkDone'] ?>">
                    <i class="fas fa-check" aria-hidden="true"></i>
                </button>
            </form>
            <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty style="position:relative;z-index:2">
                <input type="hidden" name="action" value="pauseTask">
                <input type="hidden" name="taskid" value="<?= (int)$_t->id ?>">
                <input type="hidden" name="view" value="tasks">
                <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['taskPause'] ?>">
                    <i class="fas fa-pause" aria-hidden="true"></i>
                </button>
            </form>
            <?php endif ?>
            <a href="<?= $_href ?>" class="stretched-link" hx-boost="false"
               aria-label="<?= $GLOBAL['taskTitle'] ?>: <?= htmlspecialchars($_t->title, ENT_QUOTES, $charset) ?>"></a>
        </td>
    </tr>
<?php endforeach ?>
</tbody>
</table>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD.MM.YYYY');
    $('#tasks-table').DataTable({
        order: [],
        pageLength: 50,
        paging: true,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: '<?= $GLOBAL['export'] ?> <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i><?= $GLOBAL['copy'] ?>' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i><?= $GLOBAL['excel'] ?>' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i><?= $GLOBAL['print'] ?>' }
                ]
            }
        ],
        language: {
            info:           '<?= $GLOBAL['dtInfoEntries'] ?>',
            infoFiltered:   '<?= $GLOBAL['dtInfoFiltered'] ?>',
            search:         '',
            searchPlaceholder: '<?= $GLOBAL['filterPlaceholder'] ?>',
            paginate: {
                first:    '«',
                last:     '»',
                next:     '›',
                previous: '‹'
            }
        }
    });
});
</script>
<?php endif ?>

<?php if (!empty($_doneTasks)): ?>
<div class="mt-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="text-muted fw-semibold mb-0" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.04em">
      <i class="fas fa-check-double me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['taskCompletedTitle'], count($_doneTasks)) ?>
    </h6>
    <?php if (isManager()): ?>
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-bulk-delete-done-tasks">
      <i class="fas fa-trash-can me-1" aria-hidden="true"></i><?= $GLOBAL['taskBulkDeleteBtn'] ?>
    </button>
    <?php endif ?>
  </div>
  <table id="completed-tasks-table" class="table table-sm table-hover opacity-75">
    <thead class="table-light">
      <tr>
        <th><?= $GLOBAL['taskTitle'] ?></th>
        <th><?= $GLOBAL['member'] ?></th>
        <th><?= $GLOBAL['status'] ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_doneTasks as $_dt):
        $_dtName = $_dt->user_id
            ? trim(($_dt->society ? htmlentities($_dt->society, ENT_COMPAT, $charset) . ' ' : '') .
                   htmlentities((string)$_dt->lastname, ENT_COMPAT, $charset) . ' ' .
                   htmlentities((string)$_dt->firstname, ENT_COMPAT, $charset))
            : '';
    ?>
      <tr>
        <td class="text-decoration-line-through"><?= htmlspecialchars($_dt->title, ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= $_dtName ?: '<span class="text-muted">' . $GLOBAL['globalTask'] . '</span>' ?></td>
        <td class="text-muted small"><?= htmlspecialchars(date('d.m.Y', strtotime($_dt->done_at)), ENT_QUOTES, $charset) ?></td>
        <td class="text-end" style="white-space:nowrap">
          <?php if (canWrite()): ?>
          <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty>
              <input type="hidden" name="action" value="reopenTask">
              <input type="hidden" name="taskid" value="<?= (int)$_dt->id ?>">
              <input type="hidden" name="view" value="tasks">
              <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['reopen'] ?>">
                  <i class="fas fa-rotate-left" style="font-size:0.75rem" aria-hidden="true"></i>
              </button>
          </form>
          <a href="<?= appUrl() ?>?view=removeTask&amp;taskid=<?= (int)$_dt->id ?>&amp;userid=<?= (int)$_dt->user_id ?>"
             class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['deleteThisEntry'] ?>">
              <i class="fas fa-trash-can" style="font-size:0.75rem" aria-hidden="true"></i>
          </a>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php if (isManager()): ?>
<div class="modal fade" id="modal-bulk-delete-done-tasks" tabindex="-1" aria-labelledby="modal-bulk-delete-done-tasks-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-bulk-delete-done-tasks-label"><?= $GLOBAL['taskBulkDeleteBtn'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body"><?= sprintf($GLOBAL['taskBulkDeleteConfirm'], count($_doneTasks)) ?></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= appUrl() ?>" data-no-dirty>
          <input type="hidden" name="action" value="bulkDeleteCompletedTasks">
          <input type="hidden" name="view" value="tasks">
          <button type="submit" class="btn btn-danger"><?= $GLOBAL['delete'] ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif ?>
<?php endif ?>

<?php if (!empty($_pausedTasks)): ?>
<div class="mt-4">
  <h6 class="text-muted fw-semibold mb-2" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.04em">
    <i class="fas fa-pause me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['taskPausedTitle'], count($_pausedTasks)) ?>
  </h6>
  <table id="paused-tasks-table" class="table table-sm table-hover opacity-75">
    <thead class="table-light">
      <tr>
        <th><?= $GLOBAL['taskTitle'] ?></th>
        <th><?= $GLOBAL['priority'] ?></th>
        <th><?= $GLOBAL['member'] ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_pausedTasks as $_pt):
        $_ptName = $_pt->user_id
            ? trim(($_pt->society ? htmlentities($_pt->society, ENT_COMPAT, $charset) . ' ' : '') .
                   htmlentities((string)$_pt->lastname, ENT_COMPAT, $charset) . ' ' .
                   htmlentities((string)$_pt->firstname, ENT_COMPAT, $charset))
            : '';
    ?>
      <tr>
        <td><?= htmlspecialchars($_pt->title, ENT_QUOTES, $charset) ?></td>
        <td><?= htmlspecialchars($_priorityLabels[(int)$_pt->priority] ?? '', ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= $_ptName ?: '<span class="text-muted">' . $GLOBAL['globalTask'] . '</span>' ?></td>
        <td class="text-end" style="white-space:nowrap">
          <?php if (canWrite()): ?>
          <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty>
              <input type="hidden" name="action" value="resumeTask">
              <input type="hidden" name="taskid" value="<?= (int)$_pt->id ?>">
              <input type="hidden" name="view" value="tasks">
              <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['taskResume'] ?>">
                  <i class="fas fa-play" style="font-size:0.75rem" aria-hidden="true"></i>
              </button>
          </form>
          <a href="<?= appUrl() ?>?view=removeTask&amp;taskid=<?= (int)$_pt->id ?>&amp;userid=<?= (int)$_pt->user_id ?>"
             class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['deleteThisEntry'] ?>">
              <i class="fas fa-trash-can" style="font-size:0.75rem" aria-hidden="true"></i>
          </a>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>

<?php if ($_hasCotiTask): ?>
<?php require __DIR__ . '/../partials/task_coti_reminder_modal.php'; ?>
<?php endif ?>
<?php if ($_hasRecapTask): ?>
<?php require __DIR__ . '/../partials/task_recap_notify_modal.php'; ?>
<?php endif ?>
<?php if ($_hasAttestationTask): ?>
<?php require __DIR__ . '/../partials/task_attestation_modal.php'; ?>
<?php endif ?>
