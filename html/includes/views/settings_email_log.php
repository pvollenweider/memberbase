<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Email send log tab (Settings → Email → Journal).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { ?>
  <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
<?php return; }

// Fetch latest 200 entries (most recent first)
$_logRows = [];
try {
    $_logRows = $pdo->query(
        "SELECT id, created_at, to_email, subject, status, error_msg FROM email_log ORDER BY created_at DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    // Migration may be pending — show empty state gracefully
}
?>
<div class="col-md-10">
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <p class="form-section-title mb-0"><i class="fas fa-list me-1" aria-hidden="true"></i><?= $GLOBAL['emailLog'] ?></p>
    <?php if (!empty($_logRows)): ?>
    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-email-log-purge"
            data-confirm="<?= htmlspecialchars($GLOBAL['emailLogPurgeConfirm'], ENT_QUOTES, $charset) ?>"
            data-label-purging="<?= htmlspecialchars($GLOBAL['emailLogPurge'], ENT_QUOTES, $charset) ?>">
      <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogPurge'] ?>
    </button>
    <?php endif ?>
  </div>
  <div id="email-log-purge-msg" class="mb-2"></div>

<?php if (empty($_logRows)): ?>
  <p class="text-muted" style="font-size:0.875rem"><?= $GLOBAL['emailLogEmpty'] ?></p>
<?php else: ?>
  <div class="table-responsive">
  <table class="table table-sm table-hover align-middle" style="font-size:0.82rem">
    <thead class="table-light">
      <tr>
        <th><?= $GLOBAL['emailLogDate'] ?></th>
        <th><?= $GLOBAL['emailLogTo'] ?></th>
        <th><?= $GLOBAL['emailLogSubject'] ?></th>
        <th><?= $GLOBAL['emailLogStatus'] ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_logRows as $_row): ?>
      <tr>
        <td class="text-nowrap"><?= htmlspecialchars($_row->created_at, ENT_QUOTES, $charset) ?></td>
        <td><?= htmlspecialchars($_row->to_email, ENT_QUOTES, $charset) ?></td>
        <td><?= htmlspecialchars($_row->subject, ENT_QUOTES, $charset) ?></td>
        <td>
          <?php if ($_row->status === 'sent'): ?>
            <span class="badge bg-success-subtle text-success-emphasis">
              <i class="fas fa-check me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogStatusSent'] ?>
            </span>
          <?php else: ?>
            <span class="badge bg-danger-subtle text-danger-emphasis" title="<?= htmlspecialchars($_row->error_msg ?? '', ENT_QUOTES, $charset) ?>">
              <i class="fas fa-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogStatusError'] ?>
            </span>
            <?php if (!empty($_row->error_msg)): ?>
            <span class="text-danger ms-1" style="font-size:0.78rem"><?= htmlspecialchars($_row->error_msg, ENT_QUOTES, $charset) ?></span>
            <?php endif ?>
          <?php endif ?>
        </td>
        <td class="text-end">
          <?php if ($_row->status === 'error'): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0 btn-email-resend"
                  data-id="<?= (int)$_row->id ?>"
                  data-label-sending="<?= htmlspecialchars($GLOBAL['emailLogResending'], ENT_QUOTES, $charset) ?>">
            <i class="fas fa-rotate-right me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogResend'] ?>
          </button>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
<?php endif ?>
</div>

<script>
(function () {
  // Purge log
  var purgeBtn = document.getElementById('btn-email-log-purge');
  if (purgeBtn) {
    purgeBtn.addEventListener('click', function () {
      if (!confirm(this.dataset.confirm)) return;
      var btn = this;
      btn.disabled = true;
      fetch(<?= json_encode(appUrl()) ?>, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
        },
        body: 'action=purgeEmailLog&view=settings&tab=log'
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          document.getElementById('email-log-purge-msg').innerHTML =
            '<div class="alert alert-success alert-sm py-1"><?= addslashes($GLOBAL['emailLogPurged']) ?></div>';
          // Remove table rows
          var tb = document.querySelector('#email-log-purge-msg ~ .table-responsive');
          if (tb) tb.remove();
          btn.remove();
        }
      })
      .catch(function () {})
      .finally(function () { btn.disabled = false; });
    });
  }

  // Re-send failed email
  document.querySelectorAll('.btn-email-resend').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id   = this.dataset.id;
      var orig = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + this.dataset.labelSending;
      var statusCell = this.closest('tr').querySelector('td:nth-child(4)');
      fetch(<?= json_encode(appUrl()) ?>, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
        },
        body: 'action=resendEmail&view=settings&tab=log&id=' + encodeURIComponent(id)
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          statusCell.innerHTML = '<span class="badge bg-success-subtle text-success-emphasis">'
            + '<i class="fas fa-check me-1" aria-hidden="true"></i><?= addslashes($GLOBAL['emailLogStatusSent']) ?></span>';
          btn.remove();
        } else {
          statusCell.innerHTML = '<span class="badge bg-danger-subtle text-danger-emphasis">'
            + '<i class="fas fa-xmark me-1" aria-hidden="true"></i><?= addslashes($GLOBAL['emailLogStatusError']) ?></span>'
            + ' <span class="text-danger ms-1" style="font-size:0.78rem">' + (data.error || '<?= addslashes($GLOBAL['emailLogResendFail']) ?>') + '</span>';
          btn.disabled = false;
          btn.innerHTML = orig;
        }
      })
      .catch(function () { btn.disabled = false; btn.innerHTML = orig; });
    });
  });
}());
</script>
