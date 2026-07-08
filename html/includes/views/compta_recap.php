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

// Pending stats — zero-sum entries are excluded from emails so not counted
$_pending = $pdo->query(
    "SELECT COUNT(DISTINCT c.user_id) AS members, COUNT(*) AS entries
     FROM compta c
     JOIN users u ON u.id = c.user_id AND u.status = 1
     WHERE c.notified_at IS NULL AND c.sum <> 0"
)->fetchObject();

$_pendingMembers = (int)$_pending->members;
$_pendingEntries = (int)$_pending->entries;

// Load preview rows grouped by member
$_previewRows = $pdo->query(
    "SELECT c.id, c.user_id, c.date, c.libele, c.sum, c.cotisation_year,
            COALESCE(ct.is_cotisation, 0) AS ct_coti, ct.label AS ct_label,
            u.firstname, u.lastname, u.email
     FROM compta c
     JOIN users u ON u.id = c.user_id AND u.status = 1
     LEFT JOIN compta_type ct ON ct.id = c.type_id
     WHERE c.notified_at IS NULL AND c.sum <> 0
     ORDER BY u.lastname ASC, u.firstname ASC, c.date ASC"
)->fetchAll(PDO::FETCH_OBJ);

$_previewByMember = [];
foreach ($_previewRows as $_pr) {
    $_previewByMember[$_pr->user_id]['meta']   = $_pr;
    $_previewByMember[$_pr->user_id]['entries'][] = $_pr;
}

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

<?php if (!empty($_previewByMember)): ?>
<h6 class="mt-4 mb-2 text-muted">
  <i class="fas fa-list me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapPreviewTitle'] ?>
</h6>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle" style="font-size:0.85rem">
  <thead class="table-light">
    <tr>
      <th><?= $GLOBAL['member'] ?></th>
      <th><?= $GLOBAL['email'] ?></th>
      <th class="text-end"><?= $GLOBAL['entriesColumn'] ?></th>
      <th class="text-end"><?= $GLOBAL['total'] ?></th>
      <th><?= $GLOBAL['date'] ?></th>
      <th><?= $GLOBAL['type'] ?></th>
      <th class="text-end"><?= $GLOBAL['amount'] ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($_previewByMember as $_uid => $_pg):
        $_pmeta    = $_pg['meta'];
        $_pentries = $_pg['entries'];
        $_ptotal   = array_sum(array_column($_pentries, 'sum'));
        $_hasEmail = trim($_pmeta->email) !== '';
    ?>
    <?php foreach ($_pentries as $_pi => $_pe): ?>
    <tr<?= !$_hasEmail ? ' class="text-muted"' : '' ?>>
      <?php if ($_pi === 0): ?>
      <td rowspan="<?= count($_pentries) ?>" class="fw-semibold" style="vertical-align:top;padding-top:0.6rem">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=profil&userid=<?= (int)$_uid ?>" class="text-decoration-none">
          <?= htmlspecialchars($_pmeta->lastname . ' ' . $_pmeta->firstname, ENT_QUOTES, $charset) ?>
        </a>
      </td>
      <td rowspan="<?= count($_pentries) ?>" style="vertical-align:top;padding-top:0.6rem">
        <?php if ($_hasEmail): ?>
          <span class="text-truncate d-inline-block" style="max-width:180px"><?= htmlspecialchars($_pmeta->email, ENT_QUOTES, $charset) ?></span>
        <?php else: ?>
          <span class="badge bg-warning text-dark"><?= $GLOBAL['comptaRecapNoEmail'] ?></span>
        <?php endif ?>
      </td>
      <td rowspan="<?= count($_pentries) ?>" class="text-end fw-semibold" style="vertical-align:top;padding-top:0.6rem">
        <?= count($_pentries) ?>
      </td>
      <td rowspan="<?= count($_pentries) ?>" class="text-end fw-semibold" style="vertical-align:top;padding-top:0.6rem">
        <?= number_format($_ptotal, 2, '.', "'") ?>
      </td>
      <?php endif ?>
      <td><?= $_pe->date ? date('d.m.Y', (int)$_pe->date) : '—' ?></td>
      <td>
        <?= htmlspecialchars((string)$_pe->ct_label, ENT_QUOTES, $charset) ?>
        <?php if ($_pe->ct_coti && $_pe->cotisation_year): ?>
          <?php $_payYear = $_pe->date ? (int)date('Y', (int)$_pe->date) : 0; ?>
          <?php if ((int)$_pe->cotisation_year !== $_payYear): ?>
            <span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem"><?= (int)$_pe->cotisation_year ?></span>
          <?php endif ?>
        <?php endif ?>
      </td>
      <td class="text-end"><?= number_format((float)$_pe->sum, 2, '.', "'") ?></td>
    </tr>
    <?php endforeach ?>
    <?php endforeach ?>
  </tbody>
</table>
</div>
<?php endif ?>
