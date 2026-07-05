<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Compta recap batch send — shows pending count and triggers the batch.
 *
 * Future: this view will expose a "schedule" option when issue #117 (scheduled
 * tasks) is implemented, allowing automatic weekly/monthly dispatch.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isManager()) { ?>
  <div class="alert alert-danger" role="alert">
    <i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?>
  </div>
<?php return; }

// Flash messages from redirect
$_recapOk   = isset($_GET['recapOk'])   ? (int)$_GET['recapOk']   : null;
$_recapSkip = isset($_GET['recapSkip']) ? (int)$_GET['recapSkip'] : 0;

// Pending stats
$_pending = $pdo->query(
    "SELECT COUNT(DISTINCT c.user_id) AS members, COUNT(*) AS entries
     FROM compta c
     JOIN users u ON u.id = c.user_id AND u.status = 1
     WHERE c.notified_at IS NULL"
)->fetchObject();

$_pendingMembers = (int)$_pending->members;
$_pendingEntries = (int)$_pending->entries;

// Last batch date
$_lastBatch = $pdo->query(
    "SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL"
)->fetchColumn();
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['comptaRecapTitle'] ?></h1>
</div>

<?php if ($_recapOk !== null): ?>
<div class="alert alert-success py-2" role="alert">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
  <?= sprintf($GLOBAL['comptaRecapSentOk'], $_recapOk) ?>
  <?php if ($_recapSkip > 0): ?>
    &nbsp;—&nbsp;<?= sprintf($GLOBAL['comptaRecapSkipped'], $_recapSkip) ?>
  <?php endif ?>
</div>
<?php endif ?>

<div class="row g-3 mb-4">
  <div class="col-sm-auto">
    <div class="card text-center px-4 py-3">
      <div style="font-size:2rem;font-weight:700;line-height:1"><?= $_pendingMembers ?></div>
      <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapPendingMembers'] ?></div>
    </div>
  </div>
  <div class="col-sm-auto">
    <div class="card text-center px-4 py-3">
      <div style="font-size:2rem;font-weight:700;line-height:1"><?= $_pendingEntries ?></div>
      <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapPendingEntries'] ?></div>
    </div>
  </div>
  <?php if ($_lastBatch): ?>
  <div class="col-sm-auto">
    <div class="card text-center px-4 py-3">
      <div style="font-size:1.1rem;font-weight:600;line-height:1"><?= htmlspecialchars(date('d.m.Y', strtotime($_lastBatch)), ENT_QUOTES, $charset) ?></div>
      <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapLastBatch'] ?></div>
    </div>
  </div>
  <?php endif ?>
</div>

<?php if ($_pendingMembers > 0): ?>
<form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
  <input type="hidden" name="action" value="sendComptaRecap">
  <input type="hidden" name="view"   value="comptaRecap">
  <button type="submit" class="btn btn-primary">
    <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>
    <?= sprintf($GLOBAL['comptaRecapSendBtn'], $_pendingMembers) ?>
  </button>
</form>
<?php else: ?>
<p class="text-muted"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['comptaRecapNoPending'] ?></p>
<?php endif ?>

<p class="text-muted small mt-3">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapHelp'] ?>
</p>
