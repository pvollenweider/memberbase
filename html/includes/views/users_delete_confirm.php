<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Confirmation dialog: archive (deactivate) or permanently delete a member.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$user = new Contact();
$user->lookupUser((int)$_REQUEST['id']);
$userName = trim($user->firstName . ' ' . $user->lastName) ?: $user->society;
?>
<div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
  <div class="card shadow-sm border-0" style="max-width:440px;width:100%">
    <div class="card-body p-4">
      <div class="mb-3 text-center" style="font-size:2rem;color:var(--ca-danger)">
        <i class="fas fa-user-slash" aria-hidden="true"></i>
      </div>
      <h5 class="card-title mb-1 text-center"><?= $GLOBAL['deleteOrArchive'] ?>&nbsp;?</h5>
      <p class="text-muted text-center mb-4" style="font-size:0.85rem">
        <?= htmlspecialchars($userName, ENT_QUOTES, $charset) ?>
        <span class="text-muted ms-1" style="font-size:0.78rem">#<?= (int)$user->getId() ?></span>
      </p>
      <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="hidden" name="action" value="deleteOrDeactivateUser">
        <input type="hidden" name="id"     value="<?= (int)$user->getId() ?>">
        <div class="d-flex flex-column gap-2 mb-4">
          <label class="ca-merge-radio" style="cursor:pointer">
            <input type="radio" name="dispose" value="deactivate" checked>
            <span><i class="fas fa-archive me-1 text-muted" aria-hidden="true"></i><strong><?= $GLOBAL['archive'] ?></strong></span>
            <span class="text-muted ms-1" style="font-size:0.78rem"><?= $GLOBAL['archiveKeepsHistoryHint'] ?></span>
          </label>
          <label class="ca-merge-radio ca-merge-radio--danger" style="cursor:pointer">
            <input type="radio" name="dispose" value="delete">
            <span><i class="fas fa-trash-can me-1" aria-hidden="true"></i><strong><?= $GLOBAL['deletePermanently'] ?></strong></span>
            <span class="text-muted ms-1" style="font-size:0.78rem"><?= $GLOBAL['irreversibleHint'] ?></span>
          </label>
        </div>
        <div class="d-flex gap-2 justify-content-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$user->getId() ?>"
             class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
          <button type="submit" class="btn btn-danger"><?= $GLOBAL['confirm'] ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
