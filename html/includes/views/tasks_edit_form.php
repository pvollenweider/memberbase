<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for editing an existing task.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$taskid = (int)$_REQUEST['taskid'];
$task = new SuiviTask();
$task->lookupTask($taskid);
$_priorityLabels = [
    SuiviTask::PRIORITY_HIGH   => $GLOBAL['taskPriorityHigh'],
    SuiviTask::PRIORITY_NORMAL => $GLOBAL['taskPriorityNormal'],
    SuiviTask::PRIORITY_LOW    => $GLOBAL['taskPriorityLow'],
];
$_backUrl = $task->getUserId()
    ? appUrl() . '?view=memberTasks&userid=' . $task->getUserId()
    : appUrl() . '?view=tasks';
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-7 col-lg-5">

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <h6 class="text-muted mb-0" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em">
        <?= $GLOBAL['updateTask'] ?>
      </h6>
      <a href="<?= $_backUrl ?>" class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['back'] ?>
      </a>
    </div>

    <form role="form" action="<?= appUrl() ?>" method="post" name="updateTask">
      <input type="hidden" name="taskid" value="<?= $task->getId() ?>"/>
      <input type="hidden" name="action" value="updateTask"/>
      <input type="hidden" name="view"   value="<?= $task->getUserId() ? 'memberTasks' : 'tasks' ?>"/>
      <input type="hidden" name="userid" value="<?= $task->getUserId() ?>"/>

      <div class="row mb-2 align-items-center">
        <label for="title" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['taskTitle'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="title" name="title" class="form-control form-control-sm" maxlength="255" required
                 value="<?= htmlspecialchars((string)$task->getTitle(), ENT_QUOTES, $charset) ?>"/>
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="due_date" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['dueDate'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="due_date" name="due_date" class="form-control form-control-sm datepicker"
                 value="<?= $task->getDueDate() ? timeStampToformatedDate($task->getDueDate()) : '' ?>"/>
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="priority" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['priority'] ?></label>
        <div class="col-sm-9">
          <select name="priority" id="priority" class="form-select form-select-sm">
            <?php foreach ($_priorityLabels as $_pv => $_pl): ?>
            <option value="<?= $_pv ?>"<?= $_pv === (int)$task->getPriority() ? ' selected' : '' ?>><?= htmlspecialchars($_pl, ENT_QUOTES, $charset) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <div class="row mb-3 align-items-start">
        <label for="body" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['taskBody'] ?></label>
        <div class="col-sm-9">
          <textarea id="body" name="body" rows="4" class="form-control form-control-sm"><?= htmlspecialchars((string)$task->getBody(), ENT_QUOTES, $charset) ?></textarea>
        </div>
      </div>

      <div class="row">
        <div class="col-sm-9 offset-sm-3 d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= appUrl() ?>?view=removeTask&amp;taskid=<?= $taskid ?>&amp;userid=<?= $task->getUserId() ?>"
             class="btn btn-outline-danger btn-sm">
            <i class="fas fa-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
          </a>
        </div>
      </div>

    </form>
  </div>
</div>
