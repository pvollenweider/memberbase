<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Tab: tasks for a single member — add form + list.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_priorityLabels = [
    SuiviTask::PRIORITY_HIGH   => $GLOBAL['taskPriorityHigh'],
    SuiviTask::PRIORITY_NORMAL => $GLOBAL['taskPriorityNormal'],
    SuiviTask::PRIORITY_LOW    => $GLOBAL['taskPriorityLow'],
];
?>
<?php if (canWrite()): ?>
<form action="<?= appUrl() ?>" method="post" name="addTask" class="mb-3">
<input type="hidden" name="action" value="addTask"/>
<input type="hidden" name="view" value="memberTasks"/>
<input type="hidden" name="userid" value="<?= $user->getId() ?>"/>
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

<?php
$_taskStmt = db()->prepare(
    "SELECT id,title,body,priority,due_date,done_at FROM suivi_task
     WHERE user_id = ?
     ORDER BY (done_at IS NULL) DESC, due_date IS NULL, due_date ASC, priority ASC"
);
$_taskStmt->execute([(int)$user->getId()]);
$_tasks = $_taskStmt->fetchAll(PDO::FETCH_OBJ);
?>
<?php if (empty($_tasks)): ?>
<p class="text-muted"><?= $GLOBAL['noTasks'] ?></p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-striped table-hover p">
<thead>
<tr class="title">
    <th><?= $GLOBAL['taskTitle'] ?></th>
    <th><?= $GLOBAL['dueDate'] ?></th>
    <th><?= $GLOBAL['priority'] ?></th>
    <th><?= $GLOBAL['status'] ?></th>
    <th>&nbsp;</th>
</tr>
</thead>
<tbody>
<?php foreach ($_tasks as $_t):
    $_dueTs   = $_t->due_date ? strtotime($_t->due_date) : null;
    $_doneTs  = $_t->done_at ? strtotime($_t->done_at) : null;
    $_overdue = mbTaskIsOverdue($_dueTs, $_doneTs);
?>
<tr>
    <td class="<?= $_doneTs ? 'text-muted text-decoration-line-through' : '' ?>">
        <?= htmlspecialchars($_t->title, ENT_QUOTES, $charset) ?>
        <?php if ($_t->body): ?><div class="text-muted small"><?= htmlspecialchars($_t->body, ENT_QUOTES, $charset) ?></div><?php endif ?>
    </td>
    <td class="<?= $_overdue ? 'text-danger fw-semibold' : '' ?>">
        <?= $_dueTs ? htmlspecialchars(date('d.m.Y', $_dueTs), ENT_QUOTES, $charset) : '—' ?>
        <?php if ($_overdue): ?><i class="fas fa-triangle-exclamation ms-1" aria-hidden="true" title="<?= $GLOBAL['taskOverdue'] ?>"></i><?php endif ?>
    </td>
    <td><?= htmlspecialchars($_priorityLabels[(int)$_t->priority] ?? '', ENT_QUOTES, $charset) ?></td>
    <td><?= $_doneTs ? $GLOBAL['taskDone'] : $GLOBAL['taskOpen'] ?></td>
    <td class="text-end" style="white-space:nowrap">
        <?php if (canWrite()): ?>
        <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty>
            <input type="hidden" name="action" value="<?= $_doneTs ? 'reopenTask' : 'closeTask' ?>">
            <input type="hidden" name="taskid" value="<?= (int)$_t->id ?>">
            <input type="hidden" name="view" value="memberTasks">
            <input type="hidden" name="userid" value="<?= $user->getId() ?>">
            <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $_doneTs ? $GLOBAL['reopen'] : $GLOBAL['taskMarkDone'] ?>">
                <i class="fas <?= $_doneTs ? 'fa-rotate-left' : 'fa-check' ?>" style="font-size:0.75rem" aria-hidden="true"></i>
            </button>
        </form>
        <a href="<?= appUrl() ?>?view=updateTask&amp;taskid=<?= (int)$_t->id ?>&amp;userid=<?= $user->getId() ?>"
           class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['edit'] ?>">
            <i class="fas fa-pen" style="font-size:0.75rem" aria-hidden="true"></i>
        </a>
        <a href="<?= appUrl() ?>?view=removeTask&amp;taskid=<?= (int)$_t->id ?>&amp;userid=<?= $user->getId() ?>"
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
