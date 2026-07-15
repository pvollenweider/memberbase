<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Global view: all open tasks, sorted by due date / priority.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
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
     WHERE t.done_at IS NULL
     ORDER BY t.due_date IS NULL, t.due_date ASC, t.priority ASC"
);
$_tasks = $stmt->fetchAll(PDO::FETCH_OBJ);
$_hasCotiTask = false;
$_hasRecapTask = false;
foreach ($_tasks as $_t) {
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_')) {
        $_hasCotiTask = true;
    }
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'compta_recap_pending_')) {
        $_hasRecapTask = true;
    }
}
$_cotiPendingGen  = isAdmin() ? SuiviTask::countUnpaidCotiPendingGeneration($_year, $appSettings) : 0;
$_recapPendingGen = isAdmin() ? SuiviTask::countComptaRecapPendingGeneration($_year) : 0;
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

<?php if (isAdmin() && ($_cotiPendingGen > 0 || $_recapPendingGen > 0)): ?>
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

<?php if ($_hasCotiTask): ?>
<?php require __DIR__ . '/../partials/task_coti_reminder_modal.php'; ?>
<?php endif ?>
<?php if ($_hasRecapTask): ?>
<?php require __DIR__ . '/../partials/task_recap_notify_modal.php'; ?>
<?php endif ?>
