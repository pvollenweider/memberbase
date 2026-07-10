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

// Members who paid a cotisation for year-1 but not for year (by cotisation_year).
require_once __DIR__ . '/../lib/cotisation.php';
$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiTeam = (int)($appSettings['member_no_coti_team'] ?? 0);
$rows        = mbGetLapsedMembers($pdo, $year, $cotiTypeIds, $_noCotiTeam);

// Map: user_id → last reminder sent_at (this year) from email_log.
$reminderSentMap = [];
if (!empty($rows)) {
    $rowIds          = array_map(fn($r) => (int)$r->id, $rows);
    $reminderSentMap = mbGetAlreadyRemindedIds($pdo, $year, $rowIds);
}

$count       = count($rows);
$alreadySent = count($reminderSentMap);
$prevTeamId  = 1; // non-zero so the table renders
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= appUrl() ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
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
             href="<?= appUrl() ?>?view=lapsedMembers&amp;year=<?= $y ?>"><?= $y ?></a></li>
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
          <input type="hidden" name="view"      value="lapsedMembers">
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
<!-- Confirm modal for individual send/resend -->
<div class="modal fade" id="sendOneModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($GLOBAL['sendCotiRemindersTitle'], ENT_QUOTES, $charset) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body" id="sendOneModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($GLOBAL['cancel'], ENT_QUOTES, $charset) ?></button>
        <button type="button" class="btn btn-primary" id="sendOneModalConfirm">
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['sendCotiRemindersBtnOne'], ENT_QUOTES, $charset) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
    var modal        = new bootstrap.Modal(document.getElementById('sendOneModal'));
    var modalBody    = document.getElementById('sendOneModalBody');
    var modalConfirm = document.getElementById('sendOneModalConfirm');
    var pendingBtn   = null;

    // Per-row individual send / resend buttons
    document.querySelectorAll('.js-send-one').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingBtn = btn;
            modalBody.textContent = btn.dataset.confirm;
            modal.show();
        });
    });

    modalConfirm.addEventListener('click', function () {
        modal.hide();
        var btn = pendingBtn;
        if (!btn) return;
        pendingBtn = null;
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
        fetch(<?= json_encode(appUrl()) ?>, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
            },
            body: 'action=sendCotisationReminderOne&user_id=' + encodeURIComponent(btn.dataset.userId)
                + '&year=' + encodeURIComponent(btn.dataset.year)
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
