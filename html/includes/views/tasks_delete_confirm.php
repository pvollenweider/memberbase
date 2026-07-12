<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Confirmation dialog: delete a task.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$task = new SuiviTask();
$task->lookupTask((int)$_REQUEST['taskid']);
$_backUrl = $task->getUserId()
    ? appUrl() . '?view=memberTasks&userid=' . $task->getUserId()
    : appUrl() . '?view=tasks';
?>
<div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
  <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
    <div class="card-body p-4 text-center">
      <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
        <i class="fas fa-trash-can" aria-hidden="true"></i>
      </div>
      <h5 class="card-title mb-1"><?= $GLOBAL['deleteTask'] ?>&nbsp;?</h5>
      <p class="text-muted mb-3" style="font-size:0.85rem"><?= $GLOBAL['actionIrreversible'] ?></p>
      <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
        <div><span class="text-muted"><?= $GLOBAL['taskTitle'] ?>&nbsp;:</span> <strong><?= htmlentities((string)$task->getTitle(), ENT_COMPAT, $charset) ?></strong></div>
      </div>
      <div class="d-flex gap-2 justify-content-center">
        <a href="<?= $_backUrl ?>" class="btn btn-outline-secondary">
          <?= $GLOBAL['cancel'] ?>
        </a>
        <form method="post" action="<?= appUrl() ?>">
          <input type="hidden" name="action" value="deleteTask">
          <input type="hidden" name="taskid" value="<?= (int)$task->getId() ?>">
          <button type="submit" class="btn btn-danger"><?= $GLOBAL['delete'] ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
