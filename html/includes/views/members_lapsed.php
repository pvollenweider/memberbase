<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members whose membership lapsed compared to the prior year.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }
$_pfEmbedded = $_pfEmbedded ?? false;
$_selfQuery  = !empty($_pfEmbedded) ? 'view=peopleFinance&tab=lapsed' : 'view=lapsedMembers';

// Members who paid a cotisation for year-1 but not for year (by cotisation_year).
require_once __DIR__ . '/../lib/cotisation.php';
$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiSegment = (int)($appSettings['member_no_coti_segment'] ?? 0);
$rows        = mbGetLapsedMembers(db(), $year, $cotiTypeIds, $_noCotiSegment);

// Map: user_id → last reminder sent_at (this year) from email_log.
$reminderSentMap = [];
if (!empty($rows)) {
    $rowIds          = array_map(fn($r) => (int)$r->id, $rows);
    $reminderSentMap = mbGetAlreadyRemindedIds(db(), $year, $rowIds);
}

$count       = count($rows);
$alreadySent = count($reminderSentMap);
$prevSegmentId  = 1; // non-zero so the table renders
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <?php if (empty($_pfEmbedded)): ?>
  <a href="<?= appUrl() ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
  <?php endif ?>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <?= sprintf($GLOBAL['lapsedMembersTitle'], $year-1, $year) ?>
  </span>

  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= appUrl() ?>?<?= $_selfQuery ?>&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<?php if (empty($cotiTypeIds)): ?>
<div class="alert alert-secondary" style="font-size:0.85rem">
  <?= $GLOBAL['noComptaCotiType'] ?>
</div>
<?php else: ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
<button type="button" class="btn btn-outline-warning btn-sm"
        data-bs-toggle="modal" data-bs-target="#modal-create-lapsed-members">
  <i class="fas fa-users me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['createSegmentLapsedMembers'], $year) ?>
</button>
<?php if (isManager() && $count > 0): ?>
<button type="button" class="btn btn-outline-primary btn-sm"
        data-bs-toggle="modal" data-bs-target="#modal-send-coti-reminders"
        data-count="<?= $count ?>">
  <i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['sendCotiRemindersBtn'] ?>
</button>
<?php endif ?>
</div>

<div class="modal fade" id="modal-create-lapsed-members" tabindex="-1" aria-labelledby="modal-create-lapsed-members-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-create-lapsed-members-label"><?= $GLOBAL['createSegmentTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['confirmCreateLapsedMembersSegment'], $year, $count) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= appUrl() ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"    value="createLapsedSegment">
          <input type="hidden" name="groupType" value="members">
          <input type="hidden" name="year"      value="<?= $year ?>">
          <input type="hidden" name="view"      value="peopleFinance">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-users me-1" aria-hidden="true"></i><?= $GLOBAL['create'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Cotisation reminder send modal -->
<div class="modal fade" id="modal-send-coti-reminders" tabindex="-1" aria-labelledby="modal-send-coti-reminders-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-send-coti-reminders-label">
          <i class="fas fa-envelope me-2" aria-hidden="true"></i><?= $GLOBAL['sendCotiRemindersTitle'] ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body" id="coti-reminder-modal-body">
        <p><?= sprintf($GLOBAL['sendCotiRemindersConfirm'], $count - $alreadySent, $count - $alreadySent > 1 ? 's' : '', $year) ?></p>
        <?php if ($alreadySent > 0): ?>
        <p class="text-muted small mb-0"><i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['sendCotiRemindersSkipAlready'], $alreadySent, $alreadySent > 1 ? 's' : '', $alreadySent > 1 ? 's' : '') ?></p>
        <?php endif ?>
        <?php if (trim($appSettings['smtp_reply_to'] ?? '') !== ''): ?>
        <div class="form-check mt-2 mb-0">
          <input class="form-check-input" type="checkbox" id="coti-bulk-bcc">
          <label class="form-check-label small" for="coti-bulk-bcc"><?= sprintf($GLOBAL['sendBccCopyLabel'], htmlspecialchars($appSettings['smtp_reply_to'], ENT_QUOTES, $charset)) ?></label>
        </div>
        <?php endif ?>
        <div id="coti-reminder-result" class="mt-3" style="display:none"></div>
      </div>
      <div class="modal-footer" id="coti-reminder-modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-primary" id="btn-send-coti-reminders"
                data-year="<?= $year ?>"
                data-label-sending="<?= htmlspecialchars($GLOBAL['sendCotiRemindersSending'], ENT_QUOTES, $charset) ?>"
                data-msg-ok="<?= htmlspecialchars($GLOBAL['sendCotiRemindersOk'], ENT_QUOTES, $charset) ?>"
                data-msg-fail="<?= htmlspecialchars($GLOBAL['sendCotiRemindersFail'], ENT_QUOTES, $charset) ?>">
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['smtpTestSend'] ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" role="status" style="font-size:0.85rem">
  <i class="fas fa-user-clock mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><?= sprintf($GLOBAL['lapsedMembersCount'], $count, $count > 1 ? 's' : '', $year-1, $year) ?></span>
</div>

<?php if (isManager()): ?>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle" style="font-size:0.875rem">
  <thead class="table-light">
    <tr>
      <th><?= $GLOBAL['lastname'] ?? 'Nom' ?></th>
      <th><?= $GLOBAL['firstname'] ?? 'Prénom' ?></th>
      <th><?= $GLOBAL['email'] ?? 'Email' ?></th>
      <th><?= $GLOBAL['sendCotiRemindersTitle'] ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <?php
    $sentAt  = $reminderSentMap[(int)$m->id] ?? null;
    $hasEmail = trim($m->email) !== '';
    ?>
    <tr>
      <td><a href="<?= appUrl() ?>?view=compta&userid=<?= (int)$m->id ?>"><?= htmlspecialchars($m->lastname  ?? '', ENT_QUOTES, $charset) ?></a></td>
      <td><?= htmlspecialchars($m->firstname ?? $m->society ?? '', ENT_QUOTES, $charset) ?></td>
      <td><?= htmlspecialchars($m->email ?? '', ENT_QUOTES, $charset) ?></td>
      <td>
        <?php if ($sentAt): ?>
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary" title="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderAlreadySent'], date('d.m.Y', strtotime($sentAt))), ENT_QUOTES, $charset) ?>">
              <i class="fas fa-check me-1" aria-hidden="true"></i><?= date('d.m.Y', strtotime($sentAt)) ?>
            </span>
            <?php if ($hasEmail): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm js-send-one"
                    data-user-id="<?= (int)$m->id ?>"
                    data-year="<?= $year ?>"
                    data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderResendConfirm'], trim(($m->firstname ?? $m->society ?? '') . ' ' . ($m->lastname ?? '')), date('d.m.Y', strtotime($sentAt))), ENT_QUOTES, $charset) ?>"
                    data-msg-ok="<?= htmlspecialchars($GLOBAL['cotiReminderSentOk'], ENT_QUOTES, $charset) ?>"
                    data-msg-fail="<?= htmlspecialchars($GLOBAL['cotiReminderSentFail'], ENT_QUOTES, $charset) ?>"
                    data-label-sending="<?= htmlspecialchars($GLOBAL['sendCotiRemindersSending'], ENT_QUOTES, $charset) ?>">
              <i class="fas fa-rotate-right me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['cotiReminderResendBtn'], ENT_QUOTES, $charset) ?>
            </button>
            <?php endif ?>
          </div>
        <?php elseif ($hasEmail): ?>
          <button type="button" class="btn btn-outline-primary btn-sm js-send-one"
                  data-user-id="<?= (int)$m->id ?>"
                  data-year="<?= $year ?>"
                  data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderConfirmOne'], trim(($m->firstname ?? $m->society ?? '') . ' ' . ($m->lastname ?? ''))), ENT_QUOTES, $charset) ?>"
                  data-msg-ok="<?= htmlspecialchars($GLOBAL['cotiReminderSentOk'], ENT_QUOTES, $charset) ?>"
                  data-msg-fail="<?= htmlspecialchars($GLOBAL['cotiReminderSentFail'], ENT_QUOTES, $charset) ?>"
                  data-label-sending="<?= htmlspecialchars($GLOBAL['sendCotiRemindersSending'], ENT_QUOTES, $charset) ?>">
            <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendCotiRemindersBtnOne'] ?>
          </button>
        <?php else: ?>
          <span class="text-muted small"><i class="fas fa-ban me-1" aria-hidden="true"></i><?= $GLOBAL['noEmail'] ?? 'Pas d\'email' ?></span>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?php else: ?>
<?php
$dt_order      = [[1, 'asc']];
$extra_columns = [];
$row_href      = fn($row) => appUrl() . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
<?php endif ?>
<?php endif ?>

<?php if (isManager() && $count > 0): ?>
<!-- Preview modal for individual send/resend -->
<div class="modal fade" id="cotiPreviewModal" tabindex="-1" aria-labelledby="cotiPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="cotiPreviewModalLabel"><?= htmlspecialchars($GLOBAL['sendCotiRemindersTitle'], ENT_QUOTES, $charset) ?></h5>
          <div class="text-muted small" id="coti-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body p-0" style="min-height:300px">
        <div id="coti-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="coti-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="coti-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
      <?php if (trim($appSettings['smtp_reply_to'] ?? '') !== ''): ?>
      <div class="px-3 pt-2">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="coti-one-bcc">
          <label class="form-check-label small" for="coti-one-bcc"><?= sprintf($GLOBAL['sendBccCopyLabel'], htmlspecialchars($appSettings['smtp_reply_to'], ENT_QUOTES, $charset)) ?></label>
        </div>
      </div>
      <?php endif ?>
      <div class="modal-footer gap-2">
        <div class="me-auto small text-muted" id="coti-modal-subject"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($GLOBAL['cancel'], ENT_QUOTES, $charset) ?></button>
        <button type="button" class="btn btn-primary" id="btn-coti-send-one" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['sendCotiRemindersBtnOne'], ENT_QUOTES, $charset) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
    var rowBtns = document.querySelectorAll('.js-send-one');
    if (!rowBtns.length) return;

    var baseUrl = <?= json_encode(appUrl()) ?>;
    var year    = <?= (int)$year ?>;
    function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }

    var modal      = new bootstrap.Modal(document.getElementById('cotiPreviewModal'));
    var loadingEl   = document.getElementById('coti-modal-loading');
    var errorEl     = document.getElementById('coti-modal-error');
    var frame       = document.getElementById('coti-modal-frame');
    var metaEl      = document.getElementById('coti-modal-meta');
    var subjectEl   = document.getElementById('coti-modal-subject');
    var sendBtn     = document.getElementById('btn-coti-send-one');
    var bccCb       = document.getElementById('coti-one-bcc');
    var pendingBtn  = null;
    var previewOk   = false;

    rowBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingBtn = btn;
            loadingEl.style.display = '';
            errorEl.style.display   = 'none';
            frame.style.display     = 'none';
            metaEl.textContent      = btn.dataset.confirm;
            subjectEl.textContent   = '';
            previewOk               = false;
            sendBtn.disabled        = true;
            modal.show();

            fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                body: 'action=previewCotisationReminder&user_id=' + encodeURIComponent(btn.dataset.userId) + '&year=' + year
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loadingEl.style.display = 'none';
                if (!data.ok) {
                    errorEl.textContent = data.error || '?';
                    errorEl.style.display = '';
                    return;
                }
                subjectEl.textContent = data.subject;
                frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
                frame.style.display = '';
                frame.addEventListener('load', function () {
                    try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch(e){}
                }, { once: true });
                frame.style.height = '500px';
                previewOk = true;
                sendBtn.disabled = false;
            })
            .catch(function () {
                loadingEl.style.display = 'none';
                errorEl.textContent = '?';
                errorEl.style.display = '';
            });
        });
    });

    sendBtn.addEventListener('click', function () {
        var btn = pendingBtn;
        if (!btn || !previewOk) return;
        modal.hide();
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
        fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
            body: 'action=sendCotisationReminderOne&user_id=' + encodeURIComponent(btn.dataset.userId)
                + '&year=' + encodeURIComponent(btn.dataset.year)
                + (bccCb && bccCb.checked ? '&bcc=1' : '')
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                var today = new Date();
                var d = String(today.getDate()).padStart(2,'0') + '.'
                      + String(today.getMonth()+1).padStart(2,'0') + '.'
                      + today.getFullYear();
                btn.closest('td').innerHTML =
                    '<span class="badge text-bg-secondary"><i class="fas fa-check me-1" aria-hidden="true"></i>' + d + '</span>';
            } else {
                btn.innerHTML = '<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + btn.dataset.msgFail;
                btn.classList.replace('btn-outline-primary', 'btn-outline-danger');
                btn.classList.replace('btn-outline-secondary', 'btn-outline-danger');
                btn.disabled = false;
            }
        })
        .catch(function () {
            btn.innerHTML = orig;
            btn.disabled = false;
        });
    });

    var btn = document.getElementById('btn-send-coti-reminders');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var result = document.getElementById('coti-reminder-result');
        var footer = document.getElementById('coti-reminder-modal-footer');
        var orig   = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
        result.style.display = 'none';
        fetch(<?= json_encode(appUrl()) ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
            },
            body: 'action=sendCotisationReminders&year=' + encodeURIComponent(btn.dataset.year)
                + (document.getElementById('coti-bulk-bcc') && document.getElementById('coti-bulk-bcc').checked ? '&bcc=1' : '')
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            result.style.display = '';
            if (data.ok) {
                var msg = btn.dataset.msgOk
                    .replace('%d', data.sent)
                    .replace('%sk', data.skipped)
                    .replace(/%s/g, data.sent > 1 ? 's' : '');
                result.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-circle-check me-1" aria-hidden="true"></i>' + msg + '</div>';
                footer.querySelector('button[data-bs-dismiss]').textContent = <?= json_encode($GLOBAL['close']) ?>;
                btn.style.display = 'none';
            } else {
                result.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + btn.dataset.msgFail + '</div>';
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        })
        .catch(function () {
            result.style.display = '';
            result.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + btn.dataset.msgFail + '</div>';
            btn.disabled = false;
            btn.innerHTML = orig;
        });
    });
}());
</script>
<?php endif ?>
