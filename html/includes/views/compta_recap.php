<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Compta recap — preview table with per-member send modal.
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

// Last batch date
$_lastBatch = $pdo->query(
    "SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL"
)->fetchColumn();

// Load pending entries per member for the preview table
$_previewRows = [];
if ($_pendingMembers > 0) {
    $stmt = $pdo->query(
        "SELECT c.user_id, u.firstname, u.lastname, u.email,
                COUNT(*) AS nb_entries,
                SUM(c.sum) AS total,
                MIN(c.date) AS first_date,
                MAX(c.date) AS last_date
         FROM compta c
         JOIN users u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL AND c.sum <> 0
         GROUP BY c.user_id, u.firstname, u.lastname, u.email
         ORDER BY u.lastname, u.firstname"
    );
    $_previewRows = $stmt->fetchAll(PDO::FETCH_OBJ);
}
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

<!-- Stats row -->
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

<!-- Preview table -->
<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
  <span class="text-muted small"><i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapPreviewHint'] ?></span>
  <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
    <input type="hidden" name="action" value="sendComptaRecap">
    <input type="hidden" name="view"   value="comptaRecap">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>
      <?= sprintf($GLOBAL['comptaRecapSendBtn'], $_pendingMembers) ?>
    </button>
  </form>
</div>

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
  <?php foreach ($_previewRows as $_pr):
      $_hasEmail = trim($_pr->email) !== '';
      $_total    = number_format((float)$_pr->total, 2, '.', "'");
  ?>
    <tr class="recap-row" style="cursor:pointer"
        data-userid="<?= (int)$_pr->user_id ?>"
        data-name="<?= htmlspecialchars(trim($_pr->firstname . ' ' . $_pr->lastname), ENT_QUOTES, $charset) ?>"
        data-email="<?= htmlspecialchars($_pr->email, ENT_QUOTES, $charset) ?>"
        data-has-email="<?= $_hasEmail ? '1' : '0' ?>">
      <td class="text-nowrap"><?= htmlspecialchars(trim($_pr->lastname . ' ' . $_pr->firstname), ENT_QUOTES, $charset) ?></td>
      <td>
        <?php if ($_hasEmail): ?>
          <?= htmlspecialchars($_pr->email, ENT_QUOTES, $charset) ?>
        <?php else: ?>
          <span class="badge bg-warning text-dark"><?= $GLOBAL['comptaRecapNoEmail'] ?></span>
        <?php endif ?>
      </td>
      <td class="text-center"><?= (int)$_pr->nb_entries ?></td>
      <td class="text-end">CHF <?= htmlspecialchars($_total, ENT_QUOTES, $charset) ?></td>
      <td class="text-end">
        <i class="fas fa-eye text-muted" aria-hidden="true"></i>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>

<?php else: ?>
<p class="text-muted"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['comptaRecapNoPending'] ?></p>
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
        <div id="recap-modal-loading" class="d-flex align-items-center justify-content-center py-5">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="recap-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="recap-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin"></iframe>
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
  var baseUrl   = <?= json_encode($_SERVER['PHP_SELF']) ?>;
  var currentUserId = null;

  function openPreview(userId, name, email) {
    currentUserId = userId;

    // Reset modal state
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
      body: 'action=previewComptaRecap&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf()) + '&user_id=' + encodeURIComponent(userId)
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
      frame.style.display = '';
      // Write HTML into iframe
      var doc = frame.contentDocument || frame.contentWindow.document;
      doc.open(); doc.write(data.html || '<pre>' + (data.text || '') + '</pre>'); doc.close();
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

  // Row click → open modal
  document.querySelectorAll('.recap-row').forEach(function (tr) {
    tr.addEventListener('click', function () {
      openPreview(
        tr.dataset.userid,
        tr.dataset.name,
        tr.dataset.email
      );
    });
  });

  // Send one button
  document.getElementById('btn-recap-send-one').addEventListener('click', function () {
    if (!currentUserId) return;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><?= addslashes($GLOBAL['sending'] ?? 'Envoi…') ?>';

    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
      body: 'action=sendComptaRecapOne&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf()) + '&user_id=' + encodeURIComponent(currentUserId)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        bootstrap.Modal.getInstance(document.getElementById('recapPreviewModal')).hide();
        // Remove the sent row from the table
        var row = document.querySelector('.recap-row[data-userid="' + currentUserId + '"]');
        if (row) row.remove();
        // Update pending counter
        var counter = document.querySelector('.card .page-stat-members');
        // Reload page to refresh counters accurately
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
