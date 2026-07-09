<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Anonymizes a member's personal data on request.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$user = new Contact();
$user->lookupUser((int)($_REQUEST['id'] ?? 0));
if (!$user->getId()) { echo '<div class="alert alert-danger">' . $GLOBAL['memberNotFound'] . '</div>'; return; }

$_stC = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE user_id=?");
$_stC->execute([$user->getId()]);
$_comptaCount = (int)$_stC->fetchColumn();
$_userName = trim($user->firstName . ' ' . $user->lastName) ?: $user->society;
?>
<div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
  <div class="card shadow-sm border-0" style="max-width:480px;width:100%">
    <div class="card-body p-4">
      <div class="mb-3 text-center" style="font-size:2rem;color:var(--ca-ink-muted)">
        <i class="fas fa-user-secret" aria-hidden="true"></i>
      </div>
      <h5 class="card-title mb-1 text-center"><?= $GLOBAL['anonymizeProfile'] ?></h5>
      <p class="text-muted text-center mb-4" style="font-size:0.85rem">
        <?= htmlspecialchars($_userName, ENT_QUOTES, $charset) ?>
        <span class="ms-1" style="font-size:0.78rem">#<?= (int)$user->getId() ?></span>
      </p>

      <div class="alert alert-info py-2 px-3 mb-4" style="font-size:0.82rem">
        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
        <?= sprintf($GLOBAL['anonymizeComptaCount'], $_comptaCount, $_comptaCount > 1 ? 's' : '', $_comptaCount > 1 ? 's' : '') . "\n        " . $GLOBAL['anonymizeNoDeleteReason'] ?><br><br>
        <?= $GLOBAL['anonymizeExplanation'] ?>
      </div>

      <p class="text-muted mb-4" style="font-size:0.82rem">
        <?= $GLOBAL['anonymizeIrreversibleIntro'] . "\n        " . $GLOBAL['anonymizeErasedFieldsList'] ?>
      </p>

      <div class="d-flex gap-2 justify-content-end">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$user->getId() ?>"
           class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
          <input type="hidden" name="action" value="anonymizeUser">
          <input type="hidden" name="id"     value="<?= (int)$user->getId() ?>">
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-user-secret me-1" aria-hidden="true"></i><?= $GLOBAL['confirmAnonymize'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
