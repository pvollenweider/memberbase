<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Dashboard landing view (#153) — task banner, quick KPIs, role-filtered docs.
 * Reachable via ?view=dashboard and the nav bar shortcut (not the default
 * landing view — the member list stays the default, see routing/views.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/../lib/cotisation.php';
require_once __DIR__ . '/../lib/donor.php';

$_year = (int)date('Y');

// KPI cards (contributions/donors/active members + type breakdown pie) —
// same figures as the "Dons & attestations" tab (donors_summary.php,
// mbComputeDonorKpis() shares the SQL), fixed to the current year here
// since the dashboard isn't year-filterable.
$_kpi = canWrite() ? mbComputeDonorKpis(db(), $comptaTypes, $appSettings, $_year) : null;

$_cotiTypeIds     = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiSegment   = (int)($appSettings['member_no_coti_segment'] ?? 0);
$_lapsedCount     = count(mbGetLapsedMembers(db(), $_year, $_cotiTypeIds, $_noCotiSegment));
$_newMembersCount = canWrite() ? count(mbGetNewMembers(db(), $_year, $_cotiTypeIds)) : 0;
// Matches the "Membres" tab's own default scope (users_list.php falls back
// to the org's default_segment when no explicit filter is given) — counting
// all active contacts here would overcount if that segment is a subset.
$_defaultSegmentId = (int)($appSettings['default_segment'] ?? 0);
if ($_defaultSegmentId > 0) {
    $_curMembersStmt = db()->prepare(
        "SELECT COUNT(*) FROM contact_segment cs
         JOIN contact u ON u.id = cs.user_id AND u.status = 1
         WHERE cs.segment_id = ?"
    );
    $_curMembersStmt->execute([$_defaultSegmentId]);
    $_currentMembersCount = (int)$_curMembersStmt->fetchColumn();
} else {
    $_currentMembersCount = (int)db()->query("SELECT COUNT(*) FROM contact WHERE status = 1")->fetchColumn();
}
$_pendingMigrationsCount = isAdmin() ? count(pendingMigrations($pdo)) : 0;

// Shortcut: last year's segment ("{membre_segment_prefix} {year-1}"), useful
// to invite members from the prior year to renew their cotisation.
$_lastYearSegmentId    = 0;
$_lastYearSegmentCount = 0;
if (canWrite()) {
    $_lastYearSegmentName = trim($appSettings['membre_segment_prefix'] ?? 'Membre') . ' ' . ($_year - 1);
    $_lastYearSegmentStmt = db()->prepare("SELECT id FROM segment WHERE name = ?");
    $_lastYearSegmentStmt->execute([$_lastYearSegmentName]);
    $_lastYearSegmentId = (int)$_lastYearSegmentStmt->fetchColumn();
    if ($_lastYearSegmentId > 0) {
        $_lastYearSegmentCountStmt = db()->prepare(
            "SELECT COUNT(*) FROM contact_segment cs
             JOIN contact u ON u.id = cs.user_id AND u.status = 1
             WHERE cs.segment_id = ?"
        );
        $_lastYearSegmentCountStmt->execute([$_lastYearSegmentId]);
        $_lastYearSegmentCount = (int)$_lastYearSegmentCountStmt->fetchColumn();
    }
}

// Shortcut: pending compta-recap notifications for the current year (same
// count as compta_recap.php's own stat card).
$_pendingRecapCount = 0;
if (isManager()) {
    $_pendingRecapStmt = db()->prepare(
        "SELECT COUNT(DISTINCT c.user_id) FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL AND c.sum <> 0 AND YEAR(c.date) = ?"
    );
    $_pendingRecapStmt->execute([$_year]);
    $_pendingRecapCount = (int)$_pendingRecapStmt->fetchColumn();
}

// Shortcut: attestable donors for the just-completed year — only surfaced in
// January, when attestations for the prior year are normally sent out.
$_isJanuary          = (int)date('n') === 1;
$_attestableDonsCount = 0;
if ($_isJanuary && isManager()) {
    $_attestYear = $_year - 1;
    $_exclSub    = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $_attestStmt = db()->prepare(
        "SELECT COUNT(*) FROM (
            SELECT c.user_id
            FROM compta c
            JOIN contact u ON u.id = c.user_id AND u.status = 1
            WHERE c.date > ? AND c.date < ?
            GROUP BY c.user_id
            HAVING SUM(CASE WHEN c.type_id NOT IN ($_exclSub) THEN c.sum ELSE 0 END) >= 300
                OR MAX(c.wants_attestation) = 1
         ) donors"
    );
    $_attestStmt->execute([
        mbDateTimeBound(mktime(0, 0, 0, 1, 0, $_attestYear)),
        mbDateTimeBound(mktime(0, 0, 0, 1, 1, $_attestYear + 1)),
    ]);
    $_attestableDonsCount = (int)$_attestStmt->fetchColumn();
}

// Last N accounting entries, light view.
$_recentCompta = canWrite() ? db()->query(
    "SELECT c.id, c.sum, c.date, c.user_id, u.society, u.lastname, u.firstname, ct.label AS type_label, ct.color AS type_color
     FROM compta c
     JOIN contact u ON u.id = c.user_id
     LEFT JOIN compta_type ct ON ct.id = c.type_id
     ORDER BY c.date DESC, c.id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_OBJ) : [];

// Last N contacts created.
$_recentContacts = db()->query(
    "SELECT id, society, lastname, firstname, creationDate
     FROM contact
     WHERE status = 1
     ORDER BY creationDate DESC, id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_OBJ);

// Last N follow-up (suivi) entries, merged with sent emails — same source
// data as journals&tab=suivi (suivi_last_entry.php), just capped shorter.
$_recentSuiviRows = db()->query(
    "SELECT up.user_id, up.date AS ts, up.value AS content, u.society, u.lastname, u.firstname,
            'suivi' AS kind, NULL AS email_log_id
     FROM contact_properties up
     JOIN contact u ON u.id = up.user_id AND u.status = 1
     WHERE up.parameter = 'suivi'"
)->fetchAll(PDO::FETCH_OBJ);
$_recentEmailRows = [];
try {
    $_recentEmailRows = db()->query(
        "SELECT u.id AS user_id, UNIX_TIMESTAMP(el.created_at) AS ts, el.subject AS content,
                u.society, u.lastname, u.firstname, 'email' AS kind, el.id AS email_log_id
         FROM email_log el
         JOIN contact u ON u.id = el.user_id AND u.status = 1
         WHERE el.user_id IS NOT NULL AND el.status = 'sent'"
    )->fetchAll(PDO::FETCH_OBJ);
} catch (\Throwable $e) {
    // email_log.user_id column not yet migrated — skip silently
}
foreach ($_recentSuiviRows as $_r) { $_r->ts = $_r->ts ? strtotime($_r->ts) : 0; }
$_recentSuivi = array_merge($_recentSuiviRows, $_recentEmailRows);
usort($_recentSuivi, fn($a, $b) => (int)$b->ts - (int)$a->ts);
$_recentSuivi = array_slice($_recentSuivi, 0, 8);
?>

<?php
// Hero header owns its own container-xl instead of being boxed by
// index.php's generic wrapper.
$_noOuterContainer = true;
$_phIcon = 'fa-gauge';
$_phTitle = htmlspecialchars(trim($appSettings['org_name'] ?? '') !== '' ? $appSettings['org_name'] : $GLOBAL['dashboardPageTitle'], ENT_QUOTES, $charset);
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

<?php if (canWrite()): ?>
<div class="d-flex align-items-start gap-2 mb-3 flex-wrap">
  <div class="position-relative" style="max-width:22rem;width:100%">
    <label for="dashboard-compta-search" class="visually-hidden"><?= $GLOBAL['dashboardComptaSearchLabel'] ?></label>
    <input type="text" id="dashboard-compta-search" class="form-control form-control-sm"
           placeholder="<?= htmlspecialchars($GLOBAL['dashboardComptaSearchPlaceholder'], ENT_QUOTES, $charset) ?>"
           autocomplete="off" role="combobox" aria-expanded="false" aria-controls="dashboard-compta-results"
           aria-autocomplete="list" data-no-dirty>
    <ul id="dashboard-compta-results" role="listbox" class="list-group position-absolute w-100 shadow-sm"
        style="z-index:20;max-height:16rem;overflow-y:auto;display:none"
        aria-label="<?= htmlspecialchars($GLOBAL['dashboardComptaSearchLabel'], ENT_QUOTES, $charset) ?>"></ul>
    <div id="dashboard-compta-status" role="status" class="visually-hidden"></div>
  </div>
  <a href="<?= appUrl() ?>?view=addUser" class="btn btn-primary btn-sm">
    <i class="fas fa-user-plus me-1" aria-hidden="true"></i><?= $GLOBAL['addUser'] ?>
  </a>
</div>
<script>
(function () {
  var input   = document.getElementById('dashboard-compta-search');
  var results = document.getElementById('dashboard-compta-results');
  var status  = document.getElementById('dashboard-compta-status');
  if (!input || !results) return;

  input.focus();

  var baseUrl = <?= json_encode(appUrl()) ?>;
  var items = [];
  var activeIndex = -1;
  var debounceTimer = null;

  function closeResults() {
    results.style.display = 'none';
    results.innerHTML = '';
    status.textContent = '';
    items = [];
    activeIndex = -1;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
  }

  function goToCompta(userId) {
    window.__dirtyOverride = true;
    window.location = baseUrl + '?view=compta&userid=' + encodeURIComponent(userId);
  }

  function renderResults(data) {
    items = data;
    activeIndex = -1;
    if (!items.length) {
      results.innerHTML = '<li class="list-group-item text-muted small" role="status">'
          + <?= json_encode($GLOBAL['dashboardComptaSearchNoResults']) ?> + '</li>';
      results.style.display = '';
      input.setAttribute('aria-expanded', 'true');
      status.textContent = <?= json_encode($GLOBAL['dashboardComptaSearchNoResults']) ?>;
      return;
    }
    results.innerHTML = items.map(function (m, i) {
      var name = (m.lastName + ' ' + m.firstName).trim() || m.society || '?';
      var sub  = m.email ? ' <span class="text-muted small">' + m.email + '</span>' : '';
      var icon = m.contactTypeIcon
          ? '<i class="fas fa-' + m.contactTypeIcon + ' text-muted me-2" title="' + (m.contactTypeLabel || '') + '" aria-hidden="true"></i>'
          : '';
      return '<li class="list-group-item list-group-item-action" style="cursor:pointer" role="option" '
          + 'aria-selected="false" id="dashboard-compta-opt-' + i + '" data-user-id="' + m.id + '">' + icon + name + sub + '</li>';
    }).join('');
    results.style.display = '';
    input.setAttribute('aria-expanded', 'true');
    status.textContent = items.length === 1
      ? <?= json_encode($GLOBAL['dashboardComptaSearchOneResult']) ?>
      : <?= json_encode($GLOBAL['dashboardComptaSearchResultsCount']) ?>.replace('%d', items.length);
  }

  function setActive(index) {
    var opts = results.querySelectorAll('[role="option"][data-user-id]');
    opts.forEach(function (el) { el.classList.remove('active'); el.setAttribute('aria-selected', 'false'); });
    if (index >= 0 && index < opts.length) {
      opts[index].classList.add('active');
      opts[index].setAttribute('aria-selected', 'true');
      input.setAttribute('aria-activedescendant', opts[index].id);
      activeIndex = index;
    } else {
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }
  }

  input.addEventListener('input', function () {
    var q = input.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { closeResults(); return; }
    debounceTimer = setTimeout(function () {
      fetch('/api/contacts?search=' + encodeURIComponent(q) + '&limit=8', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) { renderResults(data.data || []); })
        .catch(closeResults);
    }, 250);
  });

  results.addEventListener('click', function (e) {
    var li = e.target.closest('[data-user-id]');
    if (li) { goToCompta(li.dataset.userId); }
  });

  input.addEventListener('keydown', function (e) {
    var opts = results.querySelectorAll('[role="option"][data-user-id]');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (opts.length) setActive(Math.min(activeIndex + 1, opts.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (opts.length) setActive(Math.max(activeIndex - 1, 0));
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) {
        e.preventDefault();
        goToCompta(items[activeIndex].id);
      }
    } else if (e.key === 'Escape') {
      closeResults();
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target !== input && !results.contains(e.target)) { closeResults(); }
  });
})();
</script>
<style>
#dashboard-compta-results .list-group-item.active {
  background: var(--ca-primary, #4f7ac7);
  color: #fff;
}
#dashboard-compta-results .list-group-item.active .text-muted { color: rgba(255,255,255,0.85) !important; }
</style>
<?php endif ?>

<?php if ($_kpi): ?>
<div class="ca-resume-cards d-flex gap-2 mb-3 flex-wrap">
  <!-- Contributions -->
  <div class="ca-kpi-box" style="flex:2 0 0;min-width:200px;background:var(--ca-primary-dark)">
    <div class="ca-kpi-label"><?= $GLOBAL['contributions'] ?> <?= $_year ?></div>
    <div class="ca-kpi-value">
      <?= number_format($_kpi->kTotal, 0, '.', '\'') ?> <span style="font-size:1rem;font-weight:400">CHF</span>
    </div>
    <?php if ($_kpi->kYtd !== null):
      $_kYtdChf  = $_kpi->kTotal - $_kpi->kTotalYtd1;
      $_kGap     = $_kpi->kTotal1 - $_kpi->kTotal;
      $_mois     = $GLOBAL['monthsShort'][(int)date('m')];
    ?>
    <div class="ca-kpi-meta">
      <?php if ($_kYtdChf >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i>+<?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(+<?= number_format($_kpi->kYtd, 1) ?>%)</span>
        <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $_year - 1) ?>
      <?php else: ?>
        <span class="ca-kpi-delta ca-kpi-delta--down"><i class="fas fa-arrow-down" aria-hidden="true"></i><?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF (<?= number_format($_kpi->kYtd, 1) ?>%)</span>
        <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $_year - 1) ?>
      <?php endif ?>
    </div>
    <?php if ($_kpi->kTotal1 > 0):
      $_kProgressPct = round($_kpi->kTotal / $_kpi->kTotal1 * 100);
      $_kOverPct     = round(abs($_kGap) / $_kpi->kTotal1 * 100);
    ?>
    <div style="font-size:0.72rem;margin-top:0.2rem;opacity:0.7">
      <?php if ($_kGap > 0): ?>
        <i class="fas fa-flag-checkered me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['gapToTarget'], number_format($_kGap, 0, '.', '\''), $_year - 1, number_format($_kpi->kTotal1, 0, '.', '\''), $_kProgressPct) ?>
      <?php else: ?>
        <i class="fas fa-trophy me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['targetExceeded'], $_year - 1, number_format($_kpi->kTotal1, 0, '.', '\''), number_format(abs($_kGap), 0, '.', '\''), $_kOverPct) ?>
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php endif ?>
    <?php if (!empty($_kpi->monthlyPrev) && array_sum($_kpi->monthlyPrev) > 0): ?>
    <div style="margin-top:0.5rem;height:60px">
      <canvas id="dashboardRevenueChart" aria-label="<?= htmlspecialchars($GLOBAL['dashboardRevenueChartLabel'], ENT_QUOTES, $charset) ?>" role="img"></canvas>
    </div>
    <?php endif ?>
  </div>

  <!-- Donateurs -->
  <div class="ca-kpi-box" style="flex:1 0 0;min-width:160px;background:#0a5f3e">
    <div class="ca-kpi-label"><?= $GLOBAL['donors'] ?></div>
    <div class="ca-kpi-value"><?= $_kpi->kDonateurs ?></div>
    <?php if ($_kpi->kDonDelta !== null): ?>
    <div class="ca-kpi-meta">
      <?php if ($_kpi->kDonDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i><?= number_format($_kpi->kDonDelta, 1) ?>% vs <?= $_year - 1 ?> (<?= $_kpi->kDonateurs1 ?>)
      <?php else: ?>
        <span class="ca-kpi-delta ca-kpi-delta--down"><i class="fas fa-arrow-down" aria-hidden="true"></i><?= number_format(abs($_kpi->kDonDelta), 1) ?>%</span> vs <?= $_year - 1 ?> (<?= $_kpi->kDonateurs1 ?>)
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($_kpi->kDonateursYtd1 !== null):
      $_kDonYtdDelta = $_kpi->kDonateursYtd1 > 0 ? (($_kpi->kDonateurs - $_kpi->kDonateursYtd1) / $_kpi->kDonateursYtd1 * 100) : null;
      $_mois = $_mois ?? $GLOBAL['monthsShort'][(int)date('m')];
    ?>
    <div class="ca-kpi-meta">
      <?php if ($_kDonYtdDelta !== null && $_kDonYtdDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i>+<?= number_format($_kDonYtdDelta, 1) ?>% <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $_year - 1) ?> (<?= $_kpi->kDonateursYtd1 ?>)
      <?php elseif ($_kDonYtdDelta !== null): ?>
        <span class="ca-kpi-delta ca-kpi-delta--down"><i class="fas fa-arrow-down" aria-hidden="true"></i><?= number_format($_kDonYtdDelta, 1) ?>%</span> <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $_year - 1) ?> (<?= $_kpi->kDonateursYtd1 ?>)
      <?php else: ?>
        <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['samePeriodCount'], $_year - 1, $_kpi->kDonateursYtd1) ?>
      <?php endif ?>
    </div>
    <?php endif ?>
    <div style="font-size:0.75rem;opacity:0.85;margin-top:0.3rem;display:flex;gap:0.6rem;flex-wrap:wrap">
      <a href="<?= appUrl() ?>?view=loyalDonors&amp;year=<?= $_year ?>" style="color:inherit;text-decoration:none">
        <i class="fas fa-rotate me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['loyalShort'], $_kpi->kRecurrents) ?>
      </a>
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsedDonors&amp;cohort=new&amp;year=<?= $_year ?>" style="color:inherit;text-decoration:none" hx-boost="false">
        <i class="fas fa-star me-1" aria-hidden="true"></i><?= $_kpi->kNouveaux ?> <?= $GLOBAL['newDonors'] ?>
      </a>
      <?php if ($_kpi->kLapsed > 0): ?>
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsedDonors&amp;cohort=lapsed&amp;year=<?= $_year ?>" style="color:inherit;text-decoration:none" hx-boost="false">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['lapsedShort'], $_kpi->kLapsed) ?>
      </a>
      <?php endif ?>
    </div>
  </div>

  <!-- Membres actifs -->
  <?php if ($_kpi->membreSegmentId > 0): ?>
  <div class="ca-kpi-box" style="flex:1 0 0;min-width:160px;background:var(--ca-secondary,#8039da)">
    <div class="ca-kpi-label"><?= $GLOBAL['dashboardMembersLabel'] ?></div>
    <div class="ca-kpi-value"><?= $_kpi->kMembres ?></div>
    <?php if ($_kpi->kMembresDelta !== null): ?>
    <div class="ca-kpi-meta">
      <?php if ($_kpi->kMembresDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i><?= number_format($_kpi->kMembresDelta, 1) ?>% vs <?= $_year - 1 ?> (<?= $_kpi->kMembresPrev ?>)
      <?php else: ?>
        <span class="ca-kpi-delta ca-kpi-delta--down"><i class="fas fa-arrow-down" aria-hidden="true"></i><?= number_format(abs($_kpi->kMembresDelta), 1) ?>%</span> vs <?= $_year - 1 ?> (<?= $_kpi->kMembresPrev ?>)
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($_newMembersCount > 0 || $_kpi->kMembresLapsed > 0): ?>
    <div style="font-size:0.75rem;opacity:0.85;margin-top:0.3rem;display:flex;gap:0.6rem;flex-wrap:wrap">
      <?php if ($_newMembersCount > 0): ?>
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsed&amp;cohort=new&amp;year=<?= $_year ?>" style="color:inherit;text-decoration:none" hx-boost="false">
        <i class="fas fa-star me-1" aria-hidden="true"></i><?= $_newMembersCount ?> <?= $GLOBAL['newMembers'] ?>
      </a>
      <?php endif ?>
      <?php if ($_kpi->kMembresLapsed > 0): ?>
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsed&amp;cohort=lapsed&amp;year=<?= $_year ?>" style="color:inherit;text-decoration:none" hx-boost="false">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['lapsedShort'], $_kpi->kMembresLapsed) ?>
      </a>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>
  <?php endif ?>

  <?php
  $_solidColorMap = [
      'bg-primary-subtle' => '#0d6efd', 'bg-secondary-subtle' => '#6c757d', 'bg-success-subtle' => '#198754',
      'bg-danger-subtle' => '#dc3545', 'bg-warning-subtle' => '#ffc107', 'bg-info-subtle' => '#0dcaf0',
      'bg-light' => '#adb5bd', 'bg-dark-subtle' => '#343a40', 'ca-orange-subtle' => '#fd7e14',
      'ca-teal-subtle' => '#20c997', 'ca-pink-subtle' => '#d63384', 'ca-purple-subtle' => '#6f42c1',
      'ca-indigo-subtle' => '#6610f2', 'ca-lime-subtle' => '#80bd40',
  ];
  $_showPie = (!empty($_kpi->typeBreakdown) && $_kpi->typeTotal > 0);
  $_pieLabels = []; $_pieData = []; $_pieColors = []; $_pieFormatted = [];
  if ($_showPie) {
      foreach ($_kpi->typeBreakdown as $_tr) {
          $_pieLabels[]    = htmlentities($_tr->label, ENT_COMPAT, $charset);
          $_pieData[]      = round((float)$_tr->total);
          $_pieColors[]    = $_solidColorMap[$_tr->color] ?? '#6c757d';
          $_pct            = $_kpi->typeTotal > 0 ? round((float)$_tr->total / $_kpi->typeTotal * 100) : 0;
          $_pieFormatted[] = number_format((float)$_tr->total, 0, '.', '\'') . ' CHF (' . $_pct . '%)';
      }
  }
  ?>
  <?php if ($_showPie): ?>
  <div style="flex:1 0 0;min-width:150px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:0.85rem 1rem;display:flex;flex-direction:column;align-items:center;gap:0.4rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted);align-self:flex-start"><?= $GLOBAL['dashboardDonationBreakdownTitle'] ?></div>
    <canvas id="dashboardPie" width="80" height="80" aria-label="<?= $GLOBAL['distByType'] ?>" role="img"></canvas>
    <div id="dashboardPieLegend" style="font-size:0.7rem;line-height:1.6;width:100%"></div>
  </div>
  <?php endif ?>
</div>
<?php if ($_showPie): ?>
<script>
(function () {
  var labels    = <?= json_encode($_pieLabels) ?>;
  var data      = <?= json_encode($_pieData) ?>;
  var colors    = <?= json_encode($_pieColors) ?>;
  var formatted = <?= json_encode($_pieFormatted) ?>;
  if (window.Chart && Chart.instances) {
    Object.keys(Chart.instances).forEach(function (k) {
      var c = Chart.instances[k];
      if (c && c.canvas && c.canvas.id === 'dashboardPie') c.destroy();
    });
  }
  var ctx = document.getElementById('dashboardPie').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: false,
      animation: { duration: 400 },
      legend: { display: false },
      tooltips: { callbacks: { label: function (i, d) { return ' ' + d.labels[i.index] + ': ' + formatted[i.index]; } } }
    }
  });
  var leg = document.getElementById('dashboardPieLegend');
  leg.innerHTML = '';
  labels.forEach(function (label, i) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:0.3rem';
    var dot = document.createElement('span');
    dot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;flex-shrink:0;background:' + colors[i];
    var txt = document.createElement('span');
    txt.style.color = 'var(--ca-ink-muted)';
    txt.textContent = label + ' — ' + formatted[i];
    row.appendChild(dot); row.appendChild(txt); leg.appendChild(row);
  });
})();
</script>
<?php endif ?>
<?php if (!empty($_kpi->monthlyPrev) && array_sum($_kpi->monthlyPrev) > 0): ?>
<script>
(function () {
  var labels = <?= json_encode(array_values(array_slice($GLOBAL['monthsShortCap'], 1, 12)), JSON_UNESCAPED_UNICODE) ?>;
  var curr   = <?= json_encode($_kpi->monthlyCurr) ?>;
  var prev   = <?= json_encode($_kpi->monthlyPrev) ?>;
  if (window.Chart && Chart.instances) {
    Object.keys(Chart.instances).forEach(function (k) {
      var c = Chart.instances[k];
      if (c && c.canvas && c.canvas.id === 'dashboardRevenueChart') c.destroy();
    });
  }
  var ctx = document.getElementById('dashboardRevenueChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: <?= json_encode($_year) ?>,
          data: curr,
          borderColor: '#fff',
          backgroundColor: 'rgba(255,255,255,0.15)',
          borderWidth: 2,
          pointRadius: 0,
          fill: true,
          tension: 0.3,
          spanGaps: false
        },
        {
          label: <?= json_encode($_year - 1) ?>,
          data: prev,
          borderColor: 'rgba(255,255,255,0.5)',
          borderDash: [4, 3],
          backgroundColor: 'transparent',
          borderWidth: 1.5,
          pointRadius: 0,
          fill: false,
          tension: 0.3
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 400 },
      legend: { display: false },
      scales: {
        xAxes: [{ display: false }],
        yAxes: [{ display: false, ticks: { beginAtZero: true } }]
      },
      tooltips: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: function (item, data) {
            var ds = data.datasets[item.datasetIndex];
            return ds.label + ': ' + (item.yLabel !== null ? Math.round(item.yLabel).toLocaleString('fr-CH') + ' CHF' : '—');
          }
        }
      }
    }
  });
})();
</script>
<?php endif ?>
<?php endif ?>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['dashboardShortcutsTitle'] ?></h2></div>
      <div class="list-group list-group-flush" style="font-size:0.85rem">
        <?php if (isAdmin() && $_pendingMigrationsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=settings&amp;tab=health" class="list-group-item list-group-item-action list-group-item-warning d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['pendingDbMigrationsLabel'], $_pendingMigrationsCount > 1 ? 's' : '') ?></span>
          <span class="fw-bold"><?= $_pendingMigrationsCount ?></span>
        </a>
        <?php endif ?>
        <?php if ($_isJanuary && $_attestableDonsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=dons" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutAttestations'] ?></span>
          <span class="fw-bold"><?= $_attestableDonsCount ?></span>
        </a>
        <?php endif ?>
        <?php if (isManager()): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=recap"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['peopleFinanceTabRecap'] ?></span>
          <?php if ($_pendingRecapCount > 0): ?>
          <span class="fw-bold"><?= $_pendingRecapCount ?></span>
          <?php endif ?>
        </a>
        <?php endif ?>
        <?php if ($_kpi && $_kpi->kNouveaux > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsedDonors&amp;cohort=new&amp;year=<?= $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutNewDonors'] ?></span>
          <span class="fw-bold"><?= $_kpi->kNouveaux ?></span>
        </a>
        <?php endif ?>
        <?php if ($_kpi && $_kpi->kLapsed > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsedDonors&amp;cohort=lapsed&amp;year=<?= $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutLapsedDonors'] ?> (<?= $GLOBAL['dashboardToRelaunchSuffix'] ?>)</span>
          <span class="fw-bold"><?= $_kpi->kLapsed ?></span>
        </a>
        <?php endif ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutCurrentMembers'] ?></span>
          <span class="fw-bold"><?= $_currentMembersCount ?></span>
        </a>
        <?php if ($_newMembersCount > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsed&amp;cohort=new&amp;year=<?= $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutNewMembers'] ?></span>
          <span class="fw-bold"><?= $_newMembersCount ?></span>
        </a>
        <?php endif ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsed&amp;cohort=lapsed&amp;year=<?= $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['cotiUnpayed'] ?> (<?= $GLOBAL['dashboardToRelaunchSuffix'] ?>)</span>
          <span class="fw-bold"><?= $_lapsedCount ?></span>
        </a>
        <?php if ($_lastYearSegmentId > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members&amp;segment=<?= $_lastYearSegmentId ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1" hx-boost="false">
          <span><?= $GLOBAL['dashboardShortcutLastYearMembers'] ?></span>
          <span class="fw-bold"><?= $_lastYearSegmentCount ?></span>
        </a>
        <?php endif ?>
      </div>
    </div>

    <?php if (!empty($_recentSuivi)): ?>
    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><?= $GLOBAL['suiviActivityListTitle'] ?></h2>
        <a href="<?= appUrl() ?>?view=journals&amp;tab=suivi" hx-boost="false" class="small"><?= $GLOBAL['seeAllEntries'] ?></a>
      </div>
      <div class="list-group list-group-flush">
        <?php foreach ($_recentSuivi as $_rs):
            $_rsName = trim(($_rs->society ? $_rs->society . ' ' : '') . $_rs->lastname . ' ' . $_rs->firstname);
            $_rsContent = html_entity_decode((string)$_rs->content, ENT_COMPAT, $charset);
            $_rsIsEmail = $_rs->kind === 'email';
            $_rsHref = $_rsIsEmail && $_rs->email_log_id
                ? appUrl() . '?view=emailDetail&emailid=' . (int)$_rs->email_log_id
                : appUrl() . '?view=suivi&userid=' . (int)$_rs->user_id;
        ?>
        <a href="<?= htmlspecialchars($_rsHref, ENT_QUOTES, $charset) ?>" hx-boost="false"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="font-size:0.8rem">
          <span class="text-truncate me-2">
            <?php if ($_rsIsEmail): ?>
            <i class="fas fa-envelope me-1 text-primary" aria-hidden="true" title="<?= $GLOBAL['emailSent'] ?>"></i>
            <?php endif ?>
            <span class="fw-semibold"><?= htmlspecialchars($_rsName, ENT_QUOTES, $charset) ?></span>
            <span class="text-muted"> — <?= htmlspecialchars($_rsContent, ENT_QUOTES, $charset) ?></span>
          </span>
          <span class="text-muted text-nowrap ms-2"><?= $_rs->ts ? timeStampToformatedDate((int)$_rs->ts) : '' ?></span>
        </a>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <?php if (!empty($_SESSION['recent_segments'])): ?>
    <div class="card mt-3">
      <div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['dashboardRecentSegmentsTitle'] ?></h2></div>
      <div class="list-group list-group-flush">
        <?php foreach ($_SESSION['recent_segments'] as $_rseg): ?>
        <?php if (empty($_rseg['url'])) continue; ?>
        <a href="<?= appUrl() . htmlspecialchars($_rseg['url'], ENT_QUOTES, $charset) ?>" hx-boost="false"
           class="list-group-item list-group-item-action" style="font-size:0.85rem">
          <i class="fas fa-users me-1 text-muted" aria-hidden="true"></i><?= htmlspecialchars($_rseg['name'] ?? '', ENT_QUOTES, $charset) ?>
        </a>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
  </div>

  <div class="col-12 col-lg-5 d-flex flex-column gap-3">
    <?php if (!empty($_recentCompta)): ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0"><?= $GLOBAL['dashboardRecentComptaTitle'] ?></h2>
        <a href="<?= appUrl() ?>?view=journals&amp;tab=compta" hx-boost="false" class="small"><?= $GLOBAL['seeAllEntries'] ?></a>
      </div>
      <div class="list-group list-group-flush">
        <?php
        $_ctBadge = function (string $color, string $label) use ($charset): string {
            $bg  = $color !== '' ? $color : 'bg-secondary-subtle';
            $txt = (str_contains($bg, '-subtle') || $bg === 'bg-light') ? '#212529' : '#fff';
            return '<span class="d-inline-flex align-items-center justify-content-center rounded border ' . htmlspecialchars($bg, ENT_QUOTES, $charset) . '"'
                 . ' style="width:28px;height:20px;font-size:0.55rem;font-weight:700;line-height:1;letter-spacing:0.02em;color:' . $txt . '"'
                 . ' title="' . htmlspecialchars($label, ENT_QUOTES, $charset) . '">'
                 . htmlspecialchars(mb_strtoupper(mb_substr(trim($label), 0, 3)), ENT_QUOTES, $charset)
                 . '</span>';
        };
        foreach ($_recentCompta as $_ce):
            $_ceName = trim(($_ce->society ? $_ce->society . ' ' : '') . $_ce->lastname . ' ' . $_ce->firstname);
        ?>
        <a href="<?= appUrl() ?>?view=compta&amp;userid=<?= (int)$_ce->user_id ?>" hx-boost="false"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="font-size:0.8rem">
          <span class="text-nowrap text-truncate"><?= htmlspecialchars($_ceName, ENT_QUOTES, $charset) ?></span>
          <span class="d-flex align-items-center gap-2 text-nowrap ms-2">
            <span class="text-muted"><?= htmlspecialchars(timeStampToformatedDate(strtotime($_ce->date)), ENT_QUOTES, $charset) ?></span>
            <span><?= number_format((float)$_ce->sum, 2, '.', "'") ?></span>
            <?= $_ctBadge((string)$_ce->type_color, (string)$_ce->type_label) ?>
          </span>
        </a>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <?php if (!empty($_recentContacts)): ?>
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['dashboardRecentContactsTitle'] ?></h2></div>
      <div class="list-group list-group-flush">
        <?php foreach ($_recentContacts as $_rc):
            $_rcName = trim(($_rc->society ? $_rc->society . ' ' : '') . $_rc->lastname . ' ' . $_rc->firstname);
        ?>
        <a href="<?= appUrl() ?>?view=generalData&amp;id=<?= (int)$_rc->id ?>" hx-boost="false"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" style="font-size:0.8rem">
          <span class="text-nowrap text-truncate"><?= htmlspecialchars($_rcName, ENT_QUOTES, $charset) ?></span>
          <span class="text-muted text-nowrap ms-2"><?= $_rc->creationDate ? timeStampToformatedDate(strtotime($_rc->creationDate)) : '' ?></span>
        </a>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['documentation'] ?></h2></div>
      <div class="list-group list-group-flush">
        <a href="https://pvollenweider.github.io/memberbase/docs/user.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-book me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardUserGuideLink'] ?>
        </a>
      </div>
    </div>
  </div>
</div>
</div>
