<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members whose membership lapsed compared to the prior year.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

// Members who paid a cotisation for year-1 but not for year (by cotisation_year).
$cotiTypeIds  = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiTeam  = (int)($appSettings['member_no_coti_team'] ?? 0);
$rows = [];
if (!empty($cotiTypeIds)) {
    $ph = implode(',', array_fill(0, count($cotiTypeIds), '?'));
    // Exclude members belonging to the no-cotisation team if configured.
    $noCotiClause = $_noCotiTeam > 0
        ? "AND NOT EXISTS (SELECT 1 FROM user_properties WHERE user_id=u.id AND parameter='team_$_noCotiTeam' AND value='true')"
        : '';
    $stmt = $pdo->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        FROM users u
        WHERE u.status = 1
          $noCotiClause
          AND EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
          )
          AND NOT EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
          )
        ORDER BY u.lastname, u.firstname, u.society
    ");
    $params = array_merge(
        array_values($cotiTypeIds), [$year - 1],
        array_values($cotiTypeIds), [$year]
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
}

// Map: user_id → last reminder sent_at (this year) from email_log.
$reminderSentMap = [];
if (!empty($rows)) {
    $rowIds = array_map(fn($r) => (int)$r->id, $rows);
    $phIds  = implode(',', array_fill(0, count($rowIds), '?'));
    try {
        $rStmt = $pdo->prepare(
            "SELECT user_id, MAX(created_at) AS sent_at
             FROM email_log
             WHERE tpl_key = 'tpl_cotisation_reminder'
               AND YEAR(created_at) = ?
               AND user_id IN ($phIds)
             GROUP BY user_id"
        );
        $rStmt->execute(array_merge([$year], $rowIds));
        foreach ($rStmt->fetchAll(PDO::FETCH_OBJ) as $r) {
            $reminderSentMap[(int)$r->user_id] = $r->sent_at;
        }
    } catch (\Throwable) {}
}

$count       = count($rows);
$alreadySent = count($reminderSentMap);
$prevTeamId  = 1; // non-zero so the table renders
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
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
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedMembers&amp;year=<?= $y ?>"><?= $y ?></a></li>
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
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"    value="createLapsedGroup">
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
      <td><a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&userid=<?= (int)$m->id ?>"><?= htmlspecialchars($m->lastname  ?? '', ENT_QUOTES, $charset) ?></a></td>
      <td><?= htmlspecialchars($m->firstname ?? $m->society ?? '', ENT_QUOTES, $charset) ?></td>
      <td><?= htmlspecialchars($m->email ?? '', ENT_QUOTES, $charset) ?></td>
      <td>
        <?php if ($sentAt): ?>
          <span class="badge text-bg-secondary" title="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderAlreadySent'], date('d.m.Y', strtotime($sentAt))), ENT_QUOTES, $charset) ?>">
            <i class="fas fa-check me-1" aria-hidden="true"></i><?= date('d.m.Y', strtotime($sentAt)) ?>
          </span>
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
$row_href      = fn($row) => $_SERVER['PHP_SELF'] . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
<?php endif ?>
<?php endif ?>

<?php if (isManager() && $count > 0): ?>
<script>
(function () {
    // Per-row individual send buttons
    document.querySelectorAll('.js-send-one').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm(btn.dataset.confirm)) return;
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
            fetch(<?= json_encode($_SERVER['PHP_SELF']) ?>, {
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
                    btn.disabled = false;
                }
            })
            .catch(function () {
                btn.innerHTML = orig;
                btn.disabled = false;
            });
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
        fetch(<?= json_encode($_SERVER['PHP_SELF']) ?>, {
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
