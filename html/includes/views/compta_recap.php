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

// Flash messages from redirect
$_recapOk   = isset($_GET['recapOk'])   ? (int)$_GET['recapOk']   : null;
$_recapSkip = isset($_GET['recapSkip']) ? (int)$_GET['recapSkip'] : 0;

// Pending stats for the selected year (zero-sum excluded)
$_pending = $pdo->prepare(
    "SELECT COUNT(DISTINCT c.user_id) AS members, COUNT(*) AS entries
     FROM compta c
     JOIN contact u ON u.id = c.user_id AND u.status = 1
     WHERE c.notified_at IS NULL AND c.sum <> 0
       AND YEAR(FROM_UNIXTIME(c.date)) = ?"
);
$_pending->execute([$_year]);
$_pending = $_pending->fetchObject();

$_pendingMembers = (int)$_pending->members;
$_pendingEntries = (int)$_pending->entries;

// Last batch date (global, not year-filtered — shows when last send happened)
$_lastBatch = $pdo->query(
    "SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL"
)->fetchColumn();

// Load pending entries per member for the selected year, split by email presence
$_withEmail = [];
$_noEmail   = [];
if ($_pendingMembers > 0) {
    $stmt = $pdo->prepare(
        "SELECT c.user_id, u.firstname, u.lastname, u.email,
                COUNT(*) AS nb_entries,
                SUM(c.sum) AS total,
                MIN(c.date) AS first_date,
                MAX(c.date) AS last_date
         FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL AND c.sum <> 0
           AND YEAR(FROM_UNIXTIME(c.date)) = ?
         GROUP BY c.user_id, u.firstname, u.lastname, u.email
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
    $stmtSent = $pdo->prepare(
        "SELECT c.user_id, u.firstname, u.lastname, u.email,
                COUNT(*) AS nb_entries,
                SUM(c.sum) AS total,
                MAX(c.notified_at) AS last_notified_at
         FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NOT NULL AND c.sum <> 0
           AND YEAR(FROM_UNIXTIME(c.date)) = ?
         GROUP BY c.user_id, u.firstname, u.lastname, u.email
         ORDER BY u.lastname, u.firstname"
    );
    $stmtSent->execute([$_year]);
    $_alreadySent = $stmtSent->fetchAll(PDO::FETCH_OBJ);
}
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['comptaRecapPageTitle'] ?></h1>
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

<!-- Year picker + stats row -->
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $_year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 10; $i++): $y = (int)date('Y') - $i; ?>
      <li><a class="dropdown-item<?= $y === $_year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=comptaRecap&amp;year=<?= $y ?><?= $_extended ? '&amp;extended=1' : '' ?>"><?= $y ?></a></li>
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
  <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
    <input type="hidden" name="action" value="sendComptaRecap">
    <input type="hidden" name="view"   value="comptaRecap">
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
      <th><?= $GLOBAL['member'] ?></th>
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
      <td class="text-nowrap"><?= htmlspecialchars(trim($_pr->lastname . ' ' . $_pr->firstname), ENT_QUOTES, $charset) ?></td>
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
          <th><?= $GLOBAL['member'] ?></th>
          <th class="text-center"><?= $GLOBAL['entriesColumn'] ?></th>
          <th class="text-end"><?= $GLOBAL['total'] ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($_noEmail as $_pr):
          $_total = number_format((float)$_pr->total, 2, '.', "'");
      ?>
        <tr>
          <td>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= (int)$_pr->user_id ?>" class="text-nowrap">
              <?= htmlspecialchars(trim($_pr->lastname . ' ' . $_pr->firstname), ENT_QUOTES, $charset) ?>
            </a>
          </td>
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
        <th><?= $GLOBAL['member'] ?></th>
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
        <td class="text-nowrap"><?= htmlspecialchars(trim($_sr->lastname . ' ' . $_sr->firstname), ENT_QUOTES, $charset) ?></td>
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

<!-- Preview modal -->
<div class="modal fade" id="recapPreviewModal" tabindex="-1" aria-labelledby="recapPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="recapPreviewModalLabel"><?= $GLOBAL['comptaRecapModalTitle'] ?></h5>
          <div class="text-muted small" id="recap-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body p-0" style="min-height:300px">
        <div id="recap-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="recap-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="recap-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
      <div class="modal-footer gap-2">
        <div class="me-auto small text-muted" id="recap-modal-subject"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <?= $GLOBAL['comptaRecapSendLater'] ?>
        </button>
        <button type="button" class="btn btn-primary" id="btn-recap-send-one" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapSendOne'] ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }
  var baseUrl       = <?= json_encode($_SERVER['PHP_SELF']) ?>;
  var recapYear     = <?= (int)$_year ?>;
  var currentUserId   = null;
  var currentForce    = false;

  function openPreview(userId, name, email, force) {
    currentUserId = userId;
    currentForce  = !!force;

    document.getElementById('recap-modal-loading').style.display = '';
    document.getElementById('recap-modal-error').style.display   = 'none';
    document.getElementById('recap-modal-frame').style.display   = 'none';
    document.getElementById('recap-modal-meta').textContent      = name + (email ? ' <' + email + '>' : '');
    document.getElementById('recap-modal-subject').textContent   = '';
    document.getElementById('btn-recap-send-one').disabled       = true;

    var modal = new bootstrap.Modal(document.getElementById('recapPreviewModal'));
    modal.show();

    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
      body: 'action=previewComptaRecap&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf())
          + '&user_id=' + encodeURIComponent(userId)
          + '&year=' + encodeURIComponent(recapYear)
          + (force ? '&force=1' : '')
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.getElementById('recap-modal-loading').style.display = 'none';
      if (!data.ok) {
        var el = document.getElementById('recap-modal-error');
        el.textContent = data.error || '<?= addslashes($GLOBAL['error'] ?? 'Erreur') ?>';
        el.style.display = '';
        return;
      }
      document.getElementById('recap-modal-subject').textContent = data.subject;
      var frame = document.getElementById('recap-modal-frame');
      frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
      frame.style.display = '';
      frame.addEventListener('load', function () {
        try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch(e){}
      }, { once: true });
      frame.style.height = '500px';
      if (email) {
        document.getElementById('btn-recap-send-one').disabled = false;
      }
    })
    .catch(function () {
      document.getElementById('recap-modal-loading').style.display = 'none';
      var el = document.getElementById('recap-modal-error');
      el.textContent = '<?= addslashes($GLOBAL['error'] ?? 'Erreur réseau') ?>';
      el.style.display = '';
    });
  }

  document.querySelectorAll('.recap-row').forEach(function (tr) {
    tr.addEventListener('click', function () {
      openPreview(tr.dataset.userid, tr.dataset.name, tr.dataset.email, tr.dataset.force === '1');
    });
  });

  document.getElementById('recap-extended-toggle').addEventListener('change', function () {
    var url = baseUrl + '?view=comptaRecap&year=' + recapYear;
    if (this.checked) { url += '&extended=1'; }
    window.__dirtyOverride = true;
    window.location = url;
  });

  document.getElementById('btn-recap-send-one').addEventListener('click', function () {
    if (!currentUserId) return;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><?= addslashes($GLOBAL['sending'] ?? 'Envoi…') ?>';

    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
      body: 'action=sendComptaRecapOne&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf())
          + '&user_id=' + encodeURIComponent(currentUserId)
          + '&year=' + encodeURIComponent(recapYear)
          + (currentForce ? '&force=1' : '')
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        bootstrap.Modal.getInstance(document.getElementById('recapPreviewModal')).hide();
        window.__dirtyOverride = true;
        window.location.reload();
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i><?= addslashes($GLOBAL['comptaRecapSendOne']) ?>';
        var el = document.getElementById('recap-modal-error');
        el.textContent = data.error || '<?= addslashes($GLOBAL['error'] ?? 'Erreur') ?>';
        el.style.display = '';
      }
    })
    .catch(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i><?= addslashes($GLOBAL['comptaRecapSendOne']) ?>';
    });
  });
}());
</script>
