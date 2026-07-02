<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Confirmation dialog: delete a follow-up (suivi) entry.
 *
 * @copyright 2024 Philippe Vollenweider
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
      <p class="text-muted mb-3" style="font-size:0.85rem">Cette action est irréversible.</p>
      <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
        <div class="mb-1"><span class="text-muted">Date&nbsp;:</span> <strong><?= timeStampToformatedDate($userProperty->date) ?></strong></div>
        <div><span class="text-muted">Contenu&nbsp;:</span> <strong><?= htmlentities($userProperty->getValue(), ENT_COMPAT, $charset) ?></strong></div>
      </div>
      <div class="d-flex gap-2 justify-content-center">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= (int)$_REQUEST['userid'] ?>" class="btn btn-outline-secondary">
          <?= $GLOBAL['cancel'] ?>
        </a>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=removeSuiviConfirm&amp;userid=<?= (int)$_REQUEST['userid'] ?>&amp;suiviid=<?= $userProperty->getId() ?>" class="btn btn-danger">
          <?= $GLOBAL['delete'] ?>
        </a>
      </div>
    </div>
  </div>
</div>
