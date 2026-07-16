<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Reusable email log section — shows sent emails for a given member.
 * Expects $user to be set in the calling scope.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_elMemberId = (int)$user->getId();

$_emailLogs = [];
try {
    $s = $pdo->prepare(
        "SELECT id, created_at, subject, status, error_msg
         FROM email_log
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 200"
    );
    $s->execute([$_elMemberId]);
    $_emailLogs = $s->fetchAll(PDO::FETCH_OBJ);
} catch (\Throwable $_e) {
    // Table may not exist yet
}
?>

<p class="form-section-title mt-4 mb-1">
  <i class="fas fa-envelope-open-text me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogSection'] ?>
</p>
<p class="small text-muted mb-3"><?= $GLOBAL['emailLogHint'] ?></p>

<?php if (empty($_emailLogs)): ?>
<div class="alert alert-secondary py-2 px-3" style="font-size:0.85rem">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogNoEntries'] ?>
</div>
<?php else: ?>
<table class="table table-sm table-hover" style="font-size:0.85rem">
  <thead class="table-light">
    <tr>
      <th style="white-space:nowrap"><?= $GLOBAL['date'] ?></th>
      <th><?= $GLOBAL['emailTemplateSubject'] ?></th>
      <th style="width:90px"><?= $GLOBAL['emailLogStatus'] ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($_emailLogs as $_el): ?>
    <tr class="js-email-row-link<?= $_el->status === 'error' ? ' table-danger' : '' ?>" style="cursor:pointer" data-email-id="<?= (int)$_el->id ?>"
        <?php if ($_el->status === 'error' && $_el->error_msg): ?>
        title="<?= htmlspecialchars($_el->error_msg, ENT_QUOTES, $charset) ?>"
        <?php endif ?>>
      <td style="white-space:nowrap"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($_el->created_at)), ENT_QUOTES, $charset) ?></td>
      <td><?= htmlspecialchars($_el->subject, ENT_QUOTES, $charset) ?></td>
      <td>
        <?php if ($_el->status === 'sent'): ?>
          <span class="badge bg-success-subtle text-success-emphasis"><?= $GLOBAL['emailLogStatusSent'] ?></span>
        <?php else: ?>
          <span class="badge bg-danger-subtle text-danger-emphasis" title="<?= htmlspecialchars($_el->error_msg ?? '', ENT_QUOTES, $charset) ?>">
            <?= $GLOBAL['emailLogStatusError'] ?>
          </span>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>

<!-- Sent-email detail, loaded on demand into this modal on row click instead
     of navigating to a separate page. -->
<div class="modal fade" id="email-detail-modal" tabindex="-1" aria-labelledby="email-detail-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="email-detail-modal-label"><?= $GLOBAL['viewEmail'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body" id="email-detail-modal-body">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var modalEl   = document.getElementById('email-detail-modal');
  var modalBody = document.getElementById('email-detail-modal-body');
  if (!modalEl || !modalBody) return;
  document.querySelectorAll('tr.js-email-row-link').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button')) return;
      modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>';
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      var url = <?= json_encode(appUrl()) ?> + '?view=emailDetail&emailid=' + encodeURIComponent(row.dataset.emailId) + '&embedded=1';
      fetch(url, { headers: { 'HX-Request': 'true' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          modalBody.innerHTML = html;
          // <script> tags set via innerHTML never execute — the
          // iframe-population script in the fetched fragment needs to be
          // manually re-created to actually run.
          modalBody.querySelectorAll('script').forEach(function (old) {
              var s = document.createElement('script');
              if (old.src) { s.src = old.src; } else { s.textContent = old.textContent; }
              old.replaceWith(s);
          });
          if (window.htmx) htmx.process(modalBody);
          if (window.casaInit) casaInit(modalBody);
        })
        .catch(function () {
          modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + <?= json_encode($GLOBAL['loadError']) ?> + '</div>';
        });
    });
  });
})();
</script>
<?php endif ?>
