<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Confirmation dialog: delete a follow-up (suivi) entry.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$userProperty = new UserProperty();
$userProperty->lookupUserProperty($_REQUEST['suiviid']);
?>
<div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
  <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
    <div class="card-body p-4 text-center">
      <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
        <i class="fas fa-trash-can" aria-hidden="true"></i>
      </div>
      <h5 class="card-title mb-1"><?= $GLOBAL['deleteSuiviEntry'] ?>&nbsp;?</h5>
      <p class="text-muted mb-3" style="font-size:0.85rem"><?= $GLOBAL['actionIrreversible'] ?></p>
      <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
        <div class="mb-1"><span class="text-muted"><?= $GLOBAL['date'] ?>&nbsp;:</span> <strong><?= timeStampToformatedDate($userProperty->date) ?></strong></div>
        <div><span class="text-muted"><?= $GLOBAL['content'] ?>&nbsp;:</span> <strong><?= htmlentities($userProperty->getValue(), ENT_COMPAT, $charset) ?></strong></div>
      </div>
      <div class="d-flex gap-2 justify-content-center">
        <a href="<?= appUrl() ?>?view=suivi&amp;userid=<?= (int)$_REQUEST['userid'] ?>" class="btn btn-outline-secondary">
          <?= $GLOBAL['cancel'] ?>
        </a>
        <form method="post" action="<?= appUrl() ?>">
          <input type="hidden" name="action"  value="deleteSuiviEntry">
          <input type="hidden" name="userid"  value="<?= (int)$_REQUEST['userid'] ?>">
          <input type="hidden" name="suiviid" value="<?= (int)$userProperty->getId() ?>">
          <button type="submit" class="btn btn-danger"><?= $GLOBAL['delete'] ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
