<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Compta recap — preview table with per-member send modal, filtered by year.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isManager()) { ?>
  <div class="alert alert-danger" role="alert">
    <i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?>
  </div>
<?php return; }

$_year     = isset($_GET['year'])     ? (int)$_GET['year'] : (int)date('Y');
$_extended = !empty($_GET['extended']);
if ($_year <= 0) { $_year = (int)date('Y'); }

// Self-referencing links (year picker, extended-mode toggle, bulk-send
// redirect) must stay inside the hub when embedded there — otherwise every
// filter change kicks the user back out to the standalone page (#164 follow-up).
$_selfQuery = !empty($_pfEmbedded) ? 'view=peopleFinance&tab=recap' : 'view=comptaRecap';

// Flash messages from redirect
$_recapOk   = isset($_GET['recapOk'])   ? (int)$_GET['recapOk']   : null;
$_recapSkip = isset($_GET['recapSkip']) ? (int)$_GET['recapSkip'] : 0;
$_recapFail = isset($_GET['recapFail']) ? (int)$_GET['recapFail'] : 0;

// Pending stats for the selected year (zero-sum excluded)
$_pending = db()->prepare(
    "SELECT COUNT(DISTINCT c.user_id) AS members, COUNT(*) AS entries
     FROM compta c
     JOIN contact u ON u.id = c.user_id AND u.status = 1
     WHERE c.notified_at IS NULL AND c.sum <> 0
       AND YEAR(c.date) = ?"
);
$_pending->execute([$_year]);
$_pending = $_pending->fetchObject();

$_pendingMembers = (int)$_pending->members;
$_pendingEntries = (int)$_pending->entries;

// Last batch date (global, not year-filtered — shows when last send happened)
$_lastBatch = db()->query(
    "SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL"
)->fetchColumn();

// Load pending entries per member for the selected year, split by email presence
$_withEmail = [];
$_noEmail   = [];
if ($_pendingMembers > 0) {
    $stmt = db()->prepare(
        "SELECT c.user_id, u.firstname, u.lastname, u.society, u.email,
                COUNT(*) AS nb_entries,
                SUM(c.sum) AS total,
                MIN(c.date) AS first_date,
                MAX(c.date) AS last_date
         FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL AND c.sum <> 0
           AND YEAR(c.date) = ?
         GROUP BY c.user_id, u.firstname, u.lastname, u.society, u.email
         ORDER BY u.lastname, u.firstname"
    );
    $stmt->execute([$_year]);
    foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $_pr) {
        if (trim($_pr->email) !== '') {
            $_withEmail[] = $_pr;
        } else {
            $_noEmail[] = $_pr;
        }
    }
}
$_sendableCount = count($_withEmail);

// Extended mode: load already-notified members for the year
$_alreadySent = [];
if ($_extended) {
    $stmtSent = db()->prepare(
        "SELECT c.user_id, u.firstname, u.lastname, u.society, u.email,
                COUNT(*) AS nb_entries,
                SUM(c.sum) AS total,
                MAX(c.notified_at) AS last_notified_at
         FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NOT NULL AND c.sum <> 0
           AND YEAR(c.date) = ?
         GROUP BY c.user_id, u.firstname, u.lastname, u.society, u.email
         ORDER BY u.lastname, u.firstname"
    );
    $stmtSent->execute([$_year]);
    $_alreadySent = $stmtSent->fetchAll(PDO::FETCH_OBJ);
}
?>

<?php if (empty($_pfEmbedded)): ?>
<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['comptaRecapPageTitle'] ?></h1>
</div>
<?php endif ?>

<?php if ($_recapOk !== null): ?>
<div class="alert <?= $_recapFail > 0 ? 'alert-warning' : 'alert-success' ?> py-2" role="alert">
  <i class="fas <?= $_recapFail > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> me-1" aria-hidden="true"></i>
  <?= sprintf($GLOBAL['comptaRecapSentOk'], $_recapOk) ?>
  <?php if ($_recapSkip > 0): ?>
    &nbsp;—&nbsp;<?= sprintf($GLOBAL['comptaRecapSkipped'], $_recapSkip) ?>
  <?php endif ?>
  <?php if ($_recapFail > 0): ?>
    &nbsp;—&nbsp;<?= sprintf($GLOBAL['comptaRecapFailed'], $_recapFail) ?>
  <?php endif ?>
</div>
<?php endif ?>

<!-- Year picker + stats row -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $_year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 10; $i++): $y = (int)date('Y') - $i; ?>
      <li><a class="dropdown-item<?= $y === $_year ? ' active' : '' ?>"
             href="<?= appUrl() ?>?<?= $_selfQuery ?>&amp;year=<?= $y ?><?= $_extended ? '&amp;extended=1' : '' ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>

  <div class="form-check form-switch ms-1 mb-0" style="font-size:0.875rem">
    <input class="form-check-input" type="checkbox" role="switch" id="recap-extended-toggle"
           data-no-dirty
           <?= $_extended ? 'checked' : '' ?>>
    <label class="form-check-label text-muted" for="recap-extended-toggle">
      <?= $GLOBAL['comptaRecapExtended'] ?>
    </label>
  </div>

  <div class="card text-center px-4 py-2">
    <div style="font-size:1.6rem;font-weight:700;line-height:1"><?= $_pendingMembers ?></div>
    <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapPendingMembers'] ?></div>
  </div>
  <div class="card text-center px-4 py-2">
    <div style="font-size:1.6rem;font-weight:700;line-height:1"><?= $_pendingEntries ?></div>
    <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapPendingEntries'] ?></div>
  </div>
  <?php if ($_lastBatch): ?>
  <div class="card text-center px-4 py-2">
    <div style="font-size:1rem;font-weight:600;line-height:1"><?= htmlspecialchars(date('d.m.Y', strtotime($_lastBatch)), ENT_QUOTES, $charset) ?></div>
    <div class="text-muted small mt-1"><?= $GLOBAL['comptaRecapLastBatch'] ?></div>
  </div>
  <?php endif ?>
</div>

<?php if ($_pendingMembers > 0): ?>

<!-- Preview table — members WITH email -->
<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
  <span class="text-muted small"><i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapPreviewHint'] ?></span>
  <?php if ($_sendableCount > 0): ?>
  <form method="post" action="<?= appUrl() ?>" class="d-inline">
    <input type="hidden" name="action" value="sendComptaRecap">
    <input type="hidden" name="view"   value="<?= !empty($_pfEmbedded) ? 'peopleFinance' : 'comptaRecap' ?>">
    <?php if (!empty($_pfEmbedded)): ?><input type="hidden" name="tab" value="recap"><?php endif ?>
    <input type="hidden" name="year"   value="<?= $_year ?>">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>
      <?= sprintf($GLOBAL['comptaRecapSendBtn'], $_sendableCount) ?>
    </button>
  </form>
  <?php endif ?>
</div>

<?php if (!empty($_withEmail)): ?>
<table class="table table-sm table-hover" id="recap-preview-table">
  <thead class="table-light">
    <tr>
      <th><?= $GLOBAL['society'] ?></th>
      <th><?= $GLOBAL['lastName'] ?></th>
      <th><?= $GLOBAL['firstName'] ?></th>
      <th><?= $GLOBAL['email'] ?></th>
      <th class="text-center"><?= $GLOBAL['entriesColumn'] ?></th>
      <th class="text-end"><?= $GLOBAL['total'] ?></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($_withEmail as $_pr):
      $_total = number_format((float)$_pr->total, 2, '.', "'");
  ?>
    <tr class="recap-row" style="cursor:pointer"
        data-userid="<?= (int)$_pr->user_id ?>"
        data-name="<?= htmlspecialchars(trim($_pr->firstname . ' ' . $_pr->lastname), ENT_QUOTES, $charset) ?>"
        data-email="<?= htmlspecialchars($_pr->email, ENT_QUOTES, $charset) ?>">
      <td class="text-nowrap"><?= htmlspecialchars($_pr->society ?? '', ENT_QUOTES, $charset) ?></td>
      <td class="text-nowrap"><?= htmlspecialchars($_pr->lastname, ENT_QUOTES, $charset) ?></td>
      <td class="text-nowrap"><?= htmlspecialchars($_pr->firstname, ENT_QUOTES, $charset) ?></td>
      <td><?= htmlspecialchars($_pr->email, ENT_QUOTES, $charset) ?></td>
      <td class="text-center"><?= (int)$_pr->nb_entries ?></td>
      <td class="text-end">CHF <?= htmlspecialchars($_total, ENT_QUOTES, $charset) ?></td>
      <td class="text-end"><i class="fas fa-eye text-muted" aria-hidden="true"></i></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>

<!-- Members WITHOUT email — collapsible -->
<?php if (!empty($_noEmail)): ?>
<div class="mt-3">
  <button class="btn btn-outline-warning btn-sm" type="button"
          data-bs-toggle="collapse" data-bs-target="#recap-no-email-section"
          aria-expanded="false" aria-controls="recap-no-email-section">
    <i class="fas fa-envelope-circle-check me-1" aria-hidden="true"></i>
    <?= sprintf($GLOBAL['comptaRecapNoEmailSection'], count($_noEmail)) ?>
  </button>
  <div class="collapse mt-2" id="recap-no-email-section">
    <table class="table table-sm table-warning table-bordered">
      <thead>
        <tr>
          <th><?= $GLOBAL['society'] ?></th>
          <th><?= $GLOBAL['lastName'] ?></th>
          <th><?= $GLOBAL['firstName'] ?></th>
          <th class="text-center"><?= $GLOBAL['entriesColumn'] ?></th>
          <th class="text-end"><?= $GLOBAL['total'] ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($_noEmail as $_pr):
          $_total = number_format((float)$_pr->total, 2, '.', "'");
      ?>
        <tr>
          <td class="text-nowrap"><?= htmlspecialchars($_pr->society ?? '', ENT_QUOTES, $charset) ?></td>
          <td class="text-nowrap">
            <a href="<?= appUrl() ?>?view=compta&amp;userid=<?= (int)$_pr->user_id ?>">
              <?= htmlspecialchars($_pr->lastname, ENT_QUOTES, $charset) ?>
            </a>
          </td>
          <td class="text-nowrap"><?= htmlspecialchars($_pr->firstname, ENT_QUOTES, $charset) ?></td>
          <td class="text-center"><?= (int)$_pr->nb_entries ?></td>
          <td class="text-end">CHF <?= htmlspecialchars($_total, ENT_QUOTES, $charset) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<?php else: ?>
<p class="text-muted"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['comptaRecapNoPending'] ?></p>
<?php endif ?>

<!-- Already-notified members (extended mode) -->
<?php if ($_extended && !empty($_alreadySent)): ?>
<div class="mt-4">
  <h6 class="text-muted fw-semibold mb-2" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.04em">
    <i class="fas fa-check-double me-1" aria-hidden="true"></i>
    <?= sprintf($GLOBAL['comptaRecapAlreadySent'], count($_alreadySent)) ?>
  </h6>
  <table class="table table-sm table-hover opacity-75">
    <thead class="table-light">
      <tr>
        <th><?= $GLOBAL['society'] ?></th>
        <th><?= $GLOBAL['lastName'] ?></th>
        <th><?= $GLOBAL['firstName'] ?></th>
        <th><?= $GLOBAL['email'] ?></th>
        <th class="text-center"><?= $GLOBAL['entriesColumn'] ?></th>
        <th class="text-end"><?= $GLOBAL['total'] ?></th>
        <th><?= $GLOBAL['comptaRecapLastBatch'] ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_alreadySent as $_sr):
        $_total  = number_format((float)$_sr->total, 2, '.', "'");
        $_sentDt = $_sr->last_notified_at ? date('d.m.Y', strtotime($_sr->last_notified_at)) : '—';
    ?>
      <tr class="recap-row" style="cursor:pointer"
          data-userid="<?= (int)$_sr->user_id ?>"
          data-name="<?= htmlspecialchars(trim($_sr->firstname . ' ' . $_sr->lastname), ENT_QUOTES, $charset) ?>"
          data-email="<?= htmlspecialchars($_sr->email, ENT_QUOTES, $charset) ?>"
          data-force="1">
        <td class="text-nowrap"><?= htmlspecialchars($_sr->society ?? '', ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($_sr->lastname, ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($_sr->firstname, ENT_QUOTES, $charset) ?></td>
        <td><?= htmlspecialchars($_sr->email, ENT_QUOTES, $charset) ?></td>
        <td class="text-center"><?= (int)$_sr->nb_entries ?></td>
        <td class="text-end">CHF <?= htmlspecialchars($_total, ENT_QUOTES, $charset) ?></td>
        <td class="text-muted small"><?= htmlspecialchars(sprintf($GLOBAL['comptaRecapSentOn'], $_sentDt), ENT_QUOTES, $charset) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>

<p class="text-muted small mt-3">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapHelp'] ?>
</p>

<!-- Preview/send modal (shared component, #152) -->
<?php
$psmId          = 'recap';
$psmTitle       = $GLOBAL['comptaRecapModalTitle'];
$psmCancelLabel = $GLOBAL['comptaRecapSendLater'];
$psmSendLabel   = $GLOBAL['comptaRecapSendOne'];
require __DIR__ . '/../partials/preview_send_modal.php';
?>

<script src="js/preview-send-modal.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var baseUrl   = <?= json_encode(appUrl()) ?>;
  var recapYear = <?= (int)$_year ?>;

  document.getElementById('recap-extended-toggle').addEventListener('change', function () {
    var url = baseUrl + '?<?= $_selfQuery ?>&year=' + recapYear;
    if (this.checked) { url += '&extended=1'; }
    window.__dirtyOverride = true;
    window.location = url;
  });

  initPreviewSendModal({
    id: 'recap',
    baseUrl: baseUrl,
    triggerSelector: '.recap-row',
    previewAction: 'previewComptaRecap',
    sendAction: 'sendComptaRecapOne',
    getMetaText: function (el) { return el.dataset.name + (el.dataset.email ? ' <' + el.dataset.email + '>' : ''); },
    getPreviewParams: function (el) {
      return 'view=comptaRecap&user_id=' + encodeURIComponent(el.dataset.userid)
          + '&year=' + encodeURIComponent(recapYear)
          + (el.dataset.force === '1' ? '&force=1' : '');
    },
    getSendParams: function (el) {
      return 'view=comptaRecap&user_id=' + encodeURIComponent(el.dataset.userid)
          + '&year=' + encodeURIComponent(recapYear)
          + (el.dataset.force === '1' ? '&force=1' : '');
    },
    onSettled: function (data, trigger, modal) {
      if (data.ok) {
        modal.hide();
        window.__dirtyOverride = true;
        window.location.reload();
      }
    },
    sendBtnHtml: '<i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= addslashes($GLOBAL['comptaRecapSendOne']) ?>',
    sendingText: '<?= addslashes($GLOBAL['sending'] ?? 'Envoi…') ?>',
    genericErrorText: '<?= addslashes($GLOBAL['error'] ?? 'Erreur') ?>'
  });
}());
</script>
