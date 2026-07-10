<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Dashboard summary of members, donations, and attestation figures.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$showAll             = isset($_REQUEST['showAll']) && $_REQUEST['showAll'] == '1';
$includeAttestation  = !isset($_REQUEST['includeAttestation']) || $_REQUEST['includeAttestation'] == '1';
$minSum              = $showAll ? 0 : max(0, (int)(isset($_REQUEST['minSum']) ? $_REQUEST['minSum'] : 100));
if (!$showAll && !in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 100; }

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year === -1) { $year = (int)date("Y"); }
$type = "allTypes";

$membreTeamId = (int)($appSettings['default_team'] ?? 0);
$membreTeamLabel = $GLOBAL['activeQuestion'];
if ($membreTeamId > 0) {
    $r = $pdo->prepare("SELECT name FROM segment WHERE id = ?");
    $r->execute([$membreTeamId]);
    $membreTeamLabel = $r->fetchColumn() ?: $GLOBAL['activeQuestion'];
}
?>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$_yearLabel = match(true) {
    isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -2 => $GLOBAL['allYear'],
    isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -3 => $GLOBAL['last12Months'],
    isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -4 => $GLOBAL['last24Months'],
    default => $year,
};
if ($showAll) {
    $_amountLabel = '<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' . $GLOBAL['allEntries'];
} else {
    $_amountLabel = sprintf($GLOBAL['minAmountChf'], number_format($minSum, 0, '.', '\''));
}
if ($year != -2) {
    if ($year === -3) {
        $_kFrom  = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 1);
        $_kTo    = time();
        $_kFrom1 = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 2);
        $_kTo1   = $_kFrom;
    } elseif ($year === -4) {
        $_kFrom  = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 2);
        $_kTo    = time();
        $_kFrom1 = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 4);
        $_kTo1   = $_kFrom;
    } else {
        $_kFrom  = mktime(0,0,0,1,0,$year);
        $_kTo    = mktime(0,0,0,1,1,$year+1);
        $_kFrom1 = mktime(0,0,0,1,0,$year-1);
        $_kTo1   = mktime(0,0,0,1,1,$year);
    }

    $_excl = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";

    $_s = $pdo->prepare("SELECT COALESCE(SUM(c.sum),0) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($_excl)");
    $_s->execute([$_kFrom,$_kTo]);   $_kTotal  = (float)$_s->fetchColumn();
    $_s->execute([$_kFrom1,$_kTo1]); $_kTotal1 = (float)$_s->fetchColumn();

    $_sDon = $pdo->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($_excl)");
    $_sDon->execute([$_kFrom,$_kTo]);   $_kDonateurs  = (int)$_sDon->fetchColumn();
    $_sDon->execute([$_kFrom1,$_kTo1]); $_kDonateurs1 = (int)$_sDon->fetchColumn();
    $_kDonDelta = $_kDonateurs1 > 0 ? (($_kDonateurs - $_kDonateurs1) / $_kDonateurs1 * 100) : null;

    $_sAtt = $pdo->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<? AND c.wants_attestation=1");
    $_sAtt->execute([$_kFrom,$_kTo]); $_kAttestations = (int)$_sAtt->fetchColumn();

    $_kDonMoyen = $_kDonateurs > 0 ? $_kTotal / $_kDonateurs : 0;

    $_sRec = $pdo->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($_excl) AND c.user_id IN (SELECT DISTINCT user_id FROM compta WHERE date>? AND date<? AND type_id NOT IN ($_excl))");
    $_sRec->execute([$_kFrom, $_kTo, $_kFrom1, $_kTo1]);
    $_kRecurrents = (int)$_sRec->fetchColumn();
    $_kNouveaux   = $_kDonateurs - $_kRecurrents;
    $_kLapsed     = $_kDonateurs1 - $_kRecurrents;

    $_sTypeBreak = $pdo->prepare("SELECT ct.label, ct.color, COALESCE(SUM(c.sum),0) AS total FROM compta c JOIN compta_type ct ON ct.id=c.type_id WHERE c.date>? AND c.date<? AND ct.is_excluded_from_donation=0 GROUP BY ct.id, ct.label, ct.color ORDER BY total DESC");
    $_sTypeBreak->execute([$_kFrom, $_kTo]);
    $_typeBreakdown = $_sTypeBreak->fetchAll(PDO::FETCH_OBJ);
    $_typeTotal = array_sum(array_map(fn($r) => (float)$r->total, $_typeBreakdown));

    // Member counts by cotisation_year (fallback: YEAR of payment date)
    $_cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
    $_noCotiTeam  = (int)($appSettings['member_no_coti_team'] ?? 0);
    $_noCotiJoin  = $_noCotiTeam > 0
        ? "AND NOT EXISTS (SELECT 1 FROM contact_segment WHERE user_id=u.id AND segment_id=$_noCotiTeam)"
        : '';
    $_kMembres = 0;
    $_kMembresPrev = 0;
    $_kMembresDelta = null;
    $_kMembresLapsed = 0;
    if (!empty($_cotiTypeIds)) {
        $_ph = implode(',', array_fill(0, count($_cotiTypeIds), '?'));
        $_sM = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM contact u JOIN compta c ON c.user_id=u.id WHERE u.status=1 $_noCotiJoin AND c.type_id IN ($_ph) AND COALESCE(c.cotisation_year,YEAR(FROM_UNIXTIME(c.date)))=?");
        $_sM->execute(array_merge(array_values($_cotiTypeIds), [$year]));
        $_kMembres = (int)$_sM->fetchColumn();
        $_sM->execute(array_merge(array_values($_cotiTypeIds), [$year - 1]));
        $_kMembresPrev = (int)$_sM->fetchColumn();
        $_kMembresDelta = $_kMembresPrev > 0 ? (($_kMembres - $_kMembresPrev) / $_kMembresPrev * 100) : null;

        $_sLapsedM = $pdo->prepare("
            SELECT COUNT(*) FROM contact u
            WHERE u.status=1
              $_noCotiJoin
              AND EXISTS (SELECT 1 FROM compta c WHERE c.user_id=u.id AND c.type_id IN ($_ph) AND COALESCE(c.cotisation_year,YEAR(FROM_UNIXTIME(c.date)))=?)
              AND NOT EXISTS (SELECT 1 FROM compta c WHERE c.user_id=u.id AND c.type_id IN ($_ph) AND COALESCE(c.cotisation_year,YEAR(FROM_UNIXTIME(c.date)))=?)
        ");
        $_sLapsedM->execute(array_merge(array_values($_cotiTypeIds), [$year - 1], array_values($_cotiTypeIds), [$year]));
        $_kMembresLapsed = (int)$_sLapsedM->fetchColumn();
    }

    $_kDelta = $_kTotal1 > 0 ? (($_kTotal - $_kTotal1) / $_kTotal1 * 100) : null;

    // YTD "même période" -- only meaningful when viewing current year
    $_kYtd = null;
    $_kDonateursYtd1 = null;
    $_kMembresYtd1   = null;
    if ($year === (int)date("Y")) {
        // "today at midnight" in current year vs same date last year
        $_kToYtd  = mktime(23, 59, 59, (int)date("m"), (int)date("d"), $year);
        $_kToYtd1 = mktime(23, 59, 59, (int)date("m"), (int)date("d"), $year - 1);
        // total CHF same period last year
        $_sYtd = $pdo->prepare("SELECT COALESCE(SUM(c.sum),0) FROM compta c WHERE c.date>? AND c.date<=? AND c.type_id NOT IN ($_excl)");
        $_sYtd->execute([$_kFrom1, $_kToYtd1]);
        $_kTotalYtd1 = (float)$_sYtd->fetchColumn();
        $_kYtd = $_kTotalYtd1 > 0 ? (($_kTotal - $_kTotalYtd1) / $_kTotalYtd1 * 100) : null;
        // donateurs same period last year
        $_sDonYtd = $pdo->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<=? AND c.type_id NOT IN ($_excl)");
        $_sDonYtd->execute([$_kFrom1, $_kToYtd1]);
        $_kDonateursYtd1 = (int)$_sDonYtd->fetchColumn();
        // membres same period: just use prev team count (membership doesn't change intra-year)
        // -- shown as prev team count, already computed as $_kMembresPrev
    }
}
?>
<?php if ($year != -2): ?>
<div class="ca-resume-cards d-flex gap-2 mb-4">
  <!-- Total contributions -->
  <div style="flex:2 0 0;min-width:160px;background:var(--ca-primary);color:#fff;border-radius:10px;padding:0.85rem 1rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;opacity:0.8"><?= $GLOBAL['contributions'] ?> <?= $year ?></div>
    <div style="font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:0.25rem">
      <?= number_format($_kTotal, 0, '.', '\'') ?> <span style="font-size:1rem;font-weight:400">CHF</span>
    </div>
    <?php if ($_kYtd !== null): ?>
    <?php
      $_kYtdChf  = $_kTotal - $_kTotalYtd1;
      $_kGap     = $_kTotal1 - $_kTotal;  // positive = still below full 2025, negative = already above
      $_moisNoms = $GLOBAL['monthsShort'];
      $_mois = $_moisNoms[(int)date('m')];
    ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;opacity:0.85">
      <?php if ($_kYtdChf >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i>+<?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(+<?= number_format($_kYtd, 1) ?>%)</span>
        <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $year-1) ?>
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true"></i><?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(<?= number_format($_kYtd, 1) ?>%)</span>
        <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $year-1) ?>
      <?php endif ?>
    </div>
    <?php if ($_kTotal1 > 0): ?>
    <div style="font-size:0.72rem;margin-top:0.2rem;opacity:0.7">
      <?php
        $_kProgressPct = round($_kTotal / $_kTotal1 * 100);
        $_kOverPct     = round(abs($_kGap) / $_kTotal1 * 100);
      ?>
      <?php if ($_kGap > 0): ?>
        <i class="fas fa-flag-checkered me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['gapToTarget'], number_format($_kGap, 0, '.', '\''), $year-1, number_format($_kTotal1, 0, '.', '\''), $_kProgressPct) ?>
      <?php else: ?>
        <i class="fas fa-trophy me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['targetExceeded'], $year-1, number_format($_kTotal1, 0, '.', '\''), number_format(abs($_kGap), 0, '.', '\''), $_kOverPct) ?>
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php elseif ($_kDelta !== null): ?>
    <?php $_kDeltaChf = $_kTotal - $_kTotal1; ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;opacity:0.85">
      <?php if ($_kDeltaChf >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i>+<?= number_format($_kDeltaChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(+<?= number_format($_kDelta, 1) ?>%)</span>
        vs <?= $year-1 ?>
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true"></i><?= number_format($_kDeltaChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(<?= number_format(abs($_kDelta), 1) ?>%)</span>
        vs <?= $year-1 ?>
      <?php endif ?>
    </div>
    <?php endif ?>
  </div>
  <!-- Donateurs -->
  <div style="flex:1 0 0;min-width:120px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:0.85rem 1rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)"><?= $GLOBAL['donors'] ?></div>
    <div style="font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:0.25rem;color:var(--ca-ink,#212529)"><?= $_kDonateurs ?></div>
    <?php if ($_kDonDelta !== null): ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;color:var(--ca-ink-muted)">
      <?php if ($_kDonDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true" style="color:var(--bs-success)"></i><?= number_format($_kDonDelta, 1) ?>% vs <?= $year-1 ?> (<?= $_kDonateurs1 ?>)
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true" style="color:var(--bs-danger)"></i><?= number_format(abs($_kDonDelta), 1) ?>% vs <?= $year-1 ?> (<?= $_kDonateurs1 ?>)
      <?php endif ?>
    </div>
    <?php endif ?>
    <?php if ($_kDonateursYtd1 !== null):
      $_kDonYtdDelta = $_kDonateursYtd1 > 0 ? (($_kDonateurs - $_kDonateursYtd1) / $_kDonateursYtd1 * 100) : null;
    ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;color:var(--ca-ink-muted)">
      <?php if ($_kDonYtdDelta !== null && $_kDonYtdDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true" style="color:var(--bs-success)"></i>+<?= number_format($_kDonYtdDelta, 1) ?>% <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $year-1) ?> (<?= $_kDonateursYtd1 ?>)
      <?php elseif ($_kDonYtdDelta !== null): ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true" style="color:var(--bs-danger)"></i><?= number_format($_kDonYtdDelta, 1) ?>% <?= sprintf($GLOBAL['vsJanMonth'], $_mois, $year-1) ?> (<?= $_kDonateursYtd1 ?>)
      <?php else: ?>
        <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['samePeriodCount'], $year-1, $_kDonateursYtd1) ?>
      <?php endif ?>
    </div>
    <?php elseif ($_kDonDelta !== null): ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;color:var(--ca-ink-muted)">
      <?php if ($_kDonDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true" style="color:var(--bs-success)"></i>+<?= number_format($_kDonDelta, 1) ?>% vs <?= $year-1 ?> (<?= $_kDonateurs1 ?>)
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true" style="color:var(--bs-danger)"></i><?= number_format(abs($_kDonDelta), 1) ?>% vs <?= $year-1 ?> (<?= $_kDonateurs1 ?>)
      <?php endif ?>
    </div>
    <?php endif ?>
    <div style="font-size:0.75rem;color:var(--ca-ink-muted);margin-top:0.3rem;display:flex;gap:0.6rem;flex-wrap:wrap">
      <a href="<?= appUrl() ?>?view=loyalDonors&amp;year=<?= $year ?>"
         title="<?= sprintf($GLOBAL['alsoDonatedIn'], $year-1) ?>"
         style="color:inherit;text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-rotate me-1" aria-hidden="true" style="color:var(--bs-success)"></i><?= sprintf($GLOBAL['loyalShort'], $_kRecurrents) ?>
      </a>
      <a href="<?= appUrl() ?>?view=newDonors&amp;year=<?= $year ?>"
         title="<?= sprintf($GLOBAL['firstContributionIn'], $year) ?>"
         style="color:inherit;text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-star me-1" aria-hidden="true" style="color:var(--bs-warning)"></i><?= $_kNouveaux ?> <?= $GLOBAL['newDonors'] ?>
      </a>
      <?php if ($_kLapsed > 0): ?>
      <a href="<?= appUrl() ?>?view=lapsedDonors&amp;year=<?= $year ?>"
         title="<?= sprintf($GLOBAL['donatedButNotIn'], $year-1, $year) ?>"
         style="color:var(--bs-danger);text-decoration:none"
         hx-boost="false"
         onclick="event.stopPropagation()">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['lapsedShort'], $_kLapsed) ?>
      </a>
      <?php endif ?>
    </div>
  </div>
  <!-- Membres actifs -->
  <?php if ($membreTeamId > 0): ?>
  <div style="flex:1 0 0;min-width:120px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:0.85rem 1rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)"><?= $GLOBAL['activeMembers'] ?></div>
    <div style="font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:0.25rem;color:var(--ca-ink,#212529)"><?= $_kMembres ?></div>
    <?php if ($_kMembresDelta !== null): ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;color:var(--ca-ink-muted)">
      <?php if ($_kMembresDelta >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true" style="color:var(--bs-success)"></i><?= number_format($_kMembresDelta, 1) ?>% vs <?= $year-1 ?> (<?= $_kMembresPrev ?>)
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true" style="color:var(--bs-danger)"></i><?= number_format(abs($_kMembresDelta), 1) ?>% vs <?= $year-1 ?> (<?= $_kMembresPrev ?>)
      <?php endif ?>
    </div>
    <?php if (!empty($_kMembresLapsed) && $_kMembresLapsed > 0): ?>
    <div style="font-size:0.75rem;margin-top:0.3rem">
      <a href="<?= appUrl() ?>?view=lapsedMembers&amp;year=<?= $year ?>"
         title="<?= sprintf($GLOBAL['membersNotRenewed'], $year-1, $year) ?>"
         style="color:var(--bs-danger);text-decoration:none"
         hx-boost="false"
         onclick="event.stopPropagation()">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['lapsedShort'], $_kMembresLapsed) ?>
      </a>
    </div>
    <?php endif ?>
    <?php else: ?>
    <div style="font-size:0.78rem;color:var(--ca-ink-muted);margin-top:0.3rem"><?= htmlentities($membreTeamLabel, ENT_COMPAT, $charset) ?></div>
    <?php endif ?>
  </div>
  <?php endif ?>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
// Pie — computed before closing the flex row so canvas lands inside it
$_solidColorMap = [
    'bg-primary-subtle'   => '#0d6efd',
    'bg-secondary-subtle' => '#6c757d',
    'bg-success-subtle'   => '#198754',
    'bg-danger-subtle'    => '#dc3545',
    'bg-warning-subtle'   => '#ffc107',
    'bg-info-subtle'      => '#0dcaf0',
    'bg-light'            => '#adb5bd',
    'bg-dark-subtle'      => '#343a40',
    'ca-orange-subtle'    => '#fd7e14',
    'ca-teal-subtle'      => '#20c997',
    'ca-pink-subtle'      => '#d63384',
    'ca-purple-subtle'    => '#6f42c1',
    'ca-indigo-subtle'    => '#6610f2',
    'ca-lime-subtle'      => '#80bd40',
];
$_showPie = ($year != -2 && !empty($_typeBreakdown) && $_typeTotal > 0);
$_pieLabels = []; $_pieData = []; $_pieColors = []; $_pieFormatted = [];
if ($_showPie) {
    foreach ($_typeBreakdown as $_tr) {
        $_pieLabels[]    = htmlentities($_tr->label, ENT_COMPAT, $charset);
        $_pieData[]      = round((float)$_tr->total);
        $_pieColors[]    = $_solidColorMap[$_tr->color] ?? '#6c757d';
        $_pct            = $_typeTotal > 0 ? round((float)$_tr->total / $_typeTotal * 100) : 0;
        $_pieFormatted[] = number_format((float)$_tr->total, 0, '.', '\'') . ' CHF (' . $_pct . '%)';
    }
}
?>
<?php if ($_showPie): ?>
  <div style="flex:1 0 0;min-width:130px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:0.85rem 1rem;display:flex;flex-direction:column;align-items:center;gap:0.4rem">
    <canvas id="resumePie" width="80" height="80" aria-label="<?= $GLOBAL['distByType'] ?>" role="img"></canvas>
    <div id="resumePieLegend" style="font-size:0.7rem;line-height:1.6;width:100%"></div>
  </div>
<?php endif ?>
</div>
<?php endif ?>
<?php if (!empty($_showPie)): ?>
<script>
(function() {
  var labels    = <?= json_encode($_pieLabels) ?>;
  var data      = <?= json_encode($_pieData) ?>;
  var colors    = <?= json_encode($_pieColors) ?>;
  var formatted = <?= json_encode($_pieFormatted) ?>;
  function destroyResumePie() {
    if (window.Chart && Chart.instances) {
      Object.keys(Chart.instances).forEach(function(k) {
        var c = Chart.instances[k];
        if (c && c.canvas && c.canvas.id === 'resumePie') c.destroy();
      });
    }
  }
  destroyResumePie();
  var ctx = document.getElementById('resumePie').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: false,
      animation: { duration: 400 },
      legend: { display: false },
      tooltips: { callbacks: { label: function(i, d) { return ' ' + d.labels[i.index] + ': ' + formatted[i.index]; } } }
    }
  });
  var leg = document.getElementById('resumePieLegend');
  leg.innerHTML = '';
  labels.forEach(function(label, i) {
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

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">

  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $GLOBAL['contributions'] ?></span>

  <!-- Montant minimum -->
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle<?= $showAll ? ' active' : '' ?>" type="button"
            data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= $GLOBAL['minAmountLabel'] ?>">
      <?= $_amountLabel ?>
    </button>
    <ul class="dropdown-menu">
      <?php foreach ([1, 100, 200, 500, 1000] as $_ms): ?>
      <li><a class="dropdown-item<?= (!$showAll && $minSum == $_ms) ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=resume&amp;minSum=<?= $_ms ?>&amp;year=<?= $year ?>&amp;includeAttestation=<?= $includeAttestation ? 1 : 0 ?>">
        <?= sprintf($GLOBAL['minAmountChf'], number_format($_ms, 0, '.', '\'')) ?>
      </a></li>
      <?php endforeach ?>
    </ul>
  </div>

  <!-- Année -->
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="<?= $GLOBAL['year'] ?>">
      <i class="fas fa-calendar-days me-1" aria-hidden="true"></i><?= htmlspecialchars($_yearLabel, ENT_QUOTES, $charset) ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -2) ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -3) ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=-3">
          <i class="fas fa-rotate me-1 text-muted" aria-hidden="true"></i><?= $GLOBAL['last12Months'] ?></a></li>
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && (int)$_REQUEST['year'] === -4) ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=-4">
          <i class="fas fa-rotate me-1 text-muted" aria-hidden="true"></i><?= $GLOBAL['last24Months'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= (!isset($_REQUEST['year']) && $y == $year && !$showAll) || (isset($_REQUEST['year']) && $_REQUEST['year'] == $y) ? ' active' : '' ?>"
               href="<?= appUrl() ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <!-- Mode étendu -->
  <label class="ca-filter-btn d-flex align-items-center gap-1<?= $showAll ? ' active' : '' ?>" style="cursor:pointer;user-select:none" id="extended-mode-label">
    <input type="checkbox" id="extendedMode" data-no-dirty <?= $showAll ? 'checked' : '' ?> style="outline:none;box-shadow:none">
    <i class="fas fa-triangle-exclamation<?= $showAll ? ' text-warning' : '' ?>" aria-hidden="true"></i> <?= $GLOBAL['extendedMode'] ?>
  </label>

  <!-- Séparateur visuel -->
  <span class="text-muted d-none d-sm-inline" aria-hidden="true" style="font-size:0.9rem;padding:0 0.15rem">|</span>

  <!-- Attestations demandées -->
  <label class="ca-filter-btn ca-attest-label d-none d-sm-flex align-items-center gap-1" style="cursor:pointer;user-select:none">
    <input type="checkbox" id="includeAttestation" <?= $includeAttestation ? 'checked' : '' ?> style="outline:none;box-shadow:none">
    <i class="fas fa-file-pdf" aria-hidden="true"></i> <?= $GLOBAL['includeIfAttestationRequested'] ?>
  </label>
  <style>
  .ca-attest-label:focus-visible { outline: none; }
  .ca-attest-label:focus-within { border-color: var(--ca-primary); background: var(--ca-primary-light); color: var(--ca-primary-dark); }
  </style>
  <button type="button" class="btn btn-link p-0 text-muted d-none d-sm-inline" style="font-size:0.85rem;line-height:1"
          tabindex="0"
          data-bs-toggle="popover"
          data-bs-trigger="hover focus"
          data-bs-placement="top"
          data-bs-content="<?= $GLOBAL['attestationFilterExplanation'] ?>"
          aria-label="<?= $GLOBAL['attestationFilterAriaLabel'] ?>">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
  </button>

  <!-- Attestations bulk (télécharger / envoyer par email) — poussé à droite -->
  <?php if ($year != -2): ?>
  <div class="dropdown ms-auto d-none d-sm-block">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-file-pdf me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['attestationsYear'], (int)$year) ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" style="min-width:16rem">
      <li>
        <button type="button" class="dropdown-item" id="btn-bulk-attest"
                data-url="/attestation_bulk.php?year=<?= (int)$year ?>&amp;minSum=<?= (int)$minSum ?>">
          <i class="fas fa-download me-2" aria-hidden="true"></i><?= $GLOBAL['downloadAllAttestationsPdf'] ?>
        </button>
      </li>
      <li>
        <button type="button" class="dropdown-item" id="btn-bulk-attest-stamped"
                data-url="/attestation_bulk.php?year=<?= (int)$year ?>&amp;minSum=<?= (int)$minSum ?>&amp;stamp=1">
          <i class="fas fa-download me-2" aria-hidden="true"></i><?= $GLOBAL['downloadAllAttestationsPdfStamped'] ?>
        </button>
      </li>
      <?php if (isManager()): ?>
      <li><hr class="dropdown-divider"></li>
      <li>
        <button type="button" class="dropdown-item" id="btn-bulk-attest-send"
                data-year="<?= (int)$year ?>"
                data-min-sum="<?= (int)$minSum ?>">
          <i class="fas fa-paper-plane me-2" aria-hidden="true"></i><?= $GLOBAL['sendAllAttestationsEmail'] ?>
        </button>
      </li>
      <?php endif ?>
    </ul>
  </div>
  <?php endif ?>

</div>
<?php if ($showAll):
    $excludedLabels = array_map(
        fn($ct) => htmlentities($ct->label, ENT_COMPAT, $charset),
        array_filter($comptaTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 1)
    );
?>
<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><?= $GLOBAL['extendedModeWarningIntro'] ?><?php if ($excludedLabels): ?><?= sprintf($GLOBAL['extendedModeWarningExcluded'], implode(', ', $excludedLabels)) ?><?php endif ?><?= $GLOBAL['extendedModeWarningOutro'] ?></span>
</div>
<?php endif ?>
<table class="table table-striped table-hover export">
<thead>
<tr>
    <th><?=$GLOBAL['society']?></th>
    <th><?=$GLOBAL['sexe']?></th>
    <th><?=$GLOBAL['lastName']?></th>
    <th><?=$GLOBAL['firstName']?></th>
    <th><?=$GLOBAL['email']?></th>
    <th title="<?= sprintf($GLOBAL['statusTitleInstitutional'], htmlentities($membreTeamLabel, ENT_COMPAT, $charset)) ?>"><?= $GLOBAL['status'] ?></th>
    <th><?=$GLOBAL['address']?></th>
    <th><?=$GLOBAL['npa']?></th>
    <th style="text-align:right"><?= $GLOBAL['donations'] ?></th>
    <?php if ($showAll): ?>
    <th style="text-align:right"><?= $GLOBAL['others'] ?></th>
    <th style="text-align:right"><?= $GLOBAL['total'] ?></th>
    <?php endif ?>
    <th title="<?= $GLOBAL['wantsAttestationShort'] ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i></th>
    <th></th>
</tr>
</thead>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$params = [$membreTeamId];
$_exclSub = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
$_sumExpr = $showAll ? 'SUM(c.sum)' : "SUM(CASE WHEN c.type_id NOT IN ($_exclSub) THEN c.sum ELSE 0 END)";
$baseSelect = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           $_sumExpr AS total,
           SUM(CASE WHEN c.type_id NOT IN ($_exclSub) THEN c.sum ELSE 0 END) AS don_total,
           SUM(CASE WHEN c.type_id IN ($_exclSub) THEN c.sum ELSE 0 END) AS autres_total,
           MAX(c.wants_attestation) AS wants_attestation,
           MAX(COALESCE(ct.is_institutional, 0)) AS has_institutional,
           MAX(COALESCE(ct.is_excluded_from_donation, 0)) AS has_excluded,
           EXISTS(
               SELECT 1 FROM contact_segment us
               WHERE us.user_id = u.id AND us.segment_id = ?
           ) AS is_actif
    FROM contact u
    JOIN compta c ON u.id = c.user_id
    LEFT JOIN compta_type ct ON ct.id = c.type_id
";

// Always include all rows so attestation filter can work, exclusion handled in SUM above
$sql = $baseSelect . " WHERE u.status=1";

if ($year != -2) {
    if ($year === -3) {
        $from = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 1);
        $to   = time();
    } elseif ($year === -4) {
        $from = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 2);
        $to   = time();
    } else {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
    }
    $sql .= " AND c.date > ? AND c.date < ?";
    $params[] = $from;
    $params[] = $to;
}
$sql .= " GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email";
if (!$showAll) {
    if ($includeAttestation) {
        $sql .= " HAVING SUM(c.sum) >= ? OR MAX(c.wants_attestation) = 1";
    } else {
        $sql .= " HAVING SUM(c.sum) >= ?";
    }
    $params[] = $minSum;
}
$sql .= " ORDER BY u.lastname, u.firstname, u.society";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
$i = count($rows);

foreach ($rows as $row):
    $society = htmlentities($row->society ?? '', ENT_COMPAT, $charset);
    $lastName = htmlentities($row->lastname ?? '', ENT_COMPAT, $charset);
    $firstName = htmlentities($row->firstname ?? '', ENT_COMPAT, $charset);
    $email = htmlentities($row->email ?? '', ENT_COMPAT, $charset);
    $address = htmlentities($row->address ?? '', ENT_COMPAT, $charset);
    $npa = htmlentities($row->npa ?? '', ENT_COMPAT, $charset);
    $isActif  = $row->is_actif        ? "<i class='fas fa-id-card text-success' title='" . htmlspecialchars($membreTeamLabel, ENT_QUOTES, $charset) . "' aria-label='" . htmlspecialchars($membreTeamLabel, ENT_QUOTES, $charset) . "'></i><span class='visually-hidden'>1</span>" : "";
    $isInstit = $row->has_institutional ? "<i class='fas fa-building ms-1 text-info' title='{$GLOBAL['institutionalDonation']}' aria-label='{$GLOBAL['institutionalDonation']}'></i>" : "";
    $sexeRaw = $row->sexe;
    $sexe2 = match($sexeRaw) { 'f' => $GLOBAL['madame'], 'm' => $GLOBAL['monsieur'], 'hf' => $GLOBAL['hf'], default => '-' };
    $sexe = match($sexeRaw) {
        'f'  => "<i class='fas fa-female s'></i>",
        'm'  => "<i class='fas fa-male s'></i>",
        'hf' => "<i class='fas fa-male s'></i><i class='fas fa-female s'></i>",
        default => ''
    };
    ?>
    <tr class="ca-row-link<?= ($showAll && $row->has_excluded) ? ' table-warning' : '' ?>" data-href="<?=appUrl()?>?view=compta&amp;userid=<?=(int)$row->id?>" style="cursor:pointer">
        <td><?=$society?></td>
        <td><?=$sexe?><span class="hide"><?=$sexe2?></span></td>
        <td><strong><?=$lastName?></strong></td>
        <td><?=$firstName?></td>
        <td><?=$email?></td>
        <td class="text-nowrap"><?=$isActif?><?=$isInstit?></td>
        <td><?=$address?></td>
        <td><?=$npa?></td>
        <td style="text-align:right"><?=number_format((float)$row->don_total, 2, '.', '\'')?></td>
        <?php if ($showAll): ?>
        <td style="text-align:right"><?=number_format((float)$row->autres_total, 2, '.', '\'')?></td>
        <td style="text-align:right"><strong><?=number_format((float)$row->total, 2, '.', '\'')?></strong></td>
        <?php endif ?>
        <td class="text-center">
            <?php if ($row->wants_attestation): ?>
            <i class="fas fa-check text-success" aria-label="<?= $GLOBAL['wantsAttestationShort'] ?>"></i>
            <?php endif ?>
        </td>
        <td class="text-end" style="white-space:nowrap">
            <?php if ($year != -2): ?>
            <a href="/attestation_don.php?userid=<?=(int)$row->id?>&amp;year=<?=(int)$year?>"
               class="btn btn-sm py-0 px-1 text-muted attest-row-link"
               data-href="/attestation_don.php?userid=<?=(int)$row->id?>&amp;year=<?=(int)$year?>"
               style="position:relative;z-index:2"
               title="<?= sprintf($GLOBAL['attestationOfDonations'], (int)$year) ?>"
               aria-label="<?= sprintf($GLOBAL['attestationOfDonationsFor'], (int)$year, htmlspecialchars($lastName . ' ' . $firstName, ENT_QUOTES, $charset)) ?>"
               target="_blank">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
            </a>
            <?php if (isManager() && trim($row->email ?? '') !== ''): ?>
            <button type="button" class="btn btn-sm py-0 px-1 text-muted js-preview-attest-row"
                    style="position:relative;z-index:2"
                    data-user-id="<?= (int)$row->id ?>"
                    data-name="<?= htmlspecialchars(trim($lastName . ' ' . $firstName), ENT_QUOTES, $charset) ?>"
                    data-email="<?= htmlspecialchars($row->email, ENT_QUOTES, $charset) ?>"
                    title="<?= $GLOBAL['sendAttestationBtn'] ?>">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
            </button>
            <?php endif ?>
            <?php endif ?>
        </td>
    </tr>
    <?php
endforeach;
?>
</table>
<script>
$(document).ready(function() {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
        new bootstrap.Popover(el);
    });
    document.getElementById('extendedMode').addEventListener('change', function() {
        window.__dirtyOverride = true;
        var url = '<?= appUrl() ?>?view=resume&year=<?= $year ?>&includeAttestation=<?= $includeAttestation ? 1 : 0 ?>';
        if (this.checked) { url += '&showAll=1'; } else { url += '&minSum=<?= $showAll ? 100 : $minSum ?>'; }
        window.location = url;
    });
    document.getElementById('includeAttestation').addEventListener('change', function() {
        window.__dirtyOverride = true; // intentional navigation — skip beforeunload
        var loc = window.location.href;
        loc = loc.replace(/[?&]includeAttestation=[01]/, '');
        var sep = loc.indexOf('?') === -1 ? '?' : '&';
        window.location = loc + sep + 'includeAttestation=' + (this.checked ? 1 : 0);
    });
    $.fn.dataTable.moment('DD/MM/YYYY');
    if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
    $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        columnDefs: [{ targets: [1, 6, 7], visible: false }],
        dom: CA_DT_DOM,
        buttons: [...CA_DT_BUTTONS, CA_DT_COLVIS],
        language: CA_DT_LANGUAGE
    });
    // Clic-ligne fiable via data-href (pattern standard, sans overlay CSS
    // stretched-link qui, sur mobile Safari, retombait sur la dernière ligne).
    // Les clics sur un lien/bouton interne (PDF) ne déclenchent pas la navigation.
    $('.export tbody').off('click.rowlink').on('click.rowlink', 'tr[data-href]', function(e) {
        if ($(e.target).closest('a, button').length) return;
        window.__dirtyOverride = true;
        window.location = $(this).data('href');
    });
});
</script>

<!-- Modal confirmation attestations bulk -->
<div class="modal fade" id="bulk-attest-modal" tabindex="-1" aria-labelledby="bulk-attest-title" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="bulk-attest-title" style="font-size:0.9rem">
          <i class="fas fa-file-pdf me-2 text-danger" aria-hidden="true"></i><?= $GLOBAL['generateAttestations'] ?>
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div id="bulk-attest-confirm-body" class="modal-body py-3" style="font-size:0.875rem">
        <p class="mb-2"><?= sprintf($GLOBAL['bulkAttestConfirmBody'], '<strong id="bulk-attest-count">…</strong>') ?></p>
        <p class="text-muted mb-0" style="font-size:0.8rem"><?= $GLOBAL['bulkAttestDuration'] ?></p>
      </div>
      <div id="bulk-attest-progress-body" class="modal-body py-3 d-none" style="font-size:0.875rem">
        <p class="mb-2"><?= $GLOBAL['bulkAttestInProgress'] ?></p>
        <div class="progress mb-2" role="progressbar" aria-label="<?= $GLOBAL['progress'] ?>" style="height:6px">
          <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
        </div>
        <p class="text-muted mb-0" style="font-size:0.8rem"><?= $GLOBAL['bulkAttestCanClose'] ?></p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" id="bulk-attest-go" class="btn btn-danger btn-sm">
          <i class="fas fa-file-pdf me-1" aria-hidden="true"></i><?= $GLOBAL['generate'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var triggers = [document.getElementById('btn-bulk-attest'), document.getElementById('btn-bulk-attest-stamped')].filter(Boolean);
  if (!triggers.length) return;
  var modal = new bootstrap.Modal(document.getElementById('bulk-attest-modal'));
  var confirmBody  = document.getElementById('bulk-attest-confirm-body');
  var progressBody = document.getElementById('bulk-attest-progress-body');
  var goBtn = document.getElementById('bulk-attest-go');
  var countEl = document.getElementById('bulk-attest-count');
  var pendingUrl = null;

  triggers.forEach(function (btn) {
    btn.addEventListener('click', function() {
      pendingUrl = btn.dataset.url;
      var rowCount = document.querySelectorAll('table.export tbody tr').length;
      countEl.textContent = rowCount;
      confirmBody.classList.remove('d-none');
      progressBody.classList.add('d-none');
      goBtn.classList.remove('d-none');
      modal.show();
    });
  });

  goBtn.addEventListener('click', function() {
    confirmBody.classList.add('d-none');
    progressBody.classList.remove('d-none');
    goBtn.classList.add('d-none');
    window.open(pendingUrl, '_blank');
  });

  document.getElementById('bulk-attest-modal').addEventListener('hidden.bs.modal', function() {
    confirmBody.classList.remove('d-none');
    progressBody.classList.add('d-none');
    goBtn.classList.remove('d-none');
  });
})();
</script>

<!-- Preview modal for individual attestation send (per row) -->
<div class="modal fade" id="attestRowPreviewModal" tabindex="-1" aria-labelledby="attestRowPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="attestRowPreviewModalLabel"><?= htmlspecialchars($GLOBAL['sendAttestationBtn'], ENT_QUOTES, $charset) ?></h5>
          <div class="text-muted small" id="attest-row-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body p-0" style="min-height:300px">
        <div id="attest-row-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="attest-row-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="attest-row-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
      <?php if ((int)date('n') !== 1): ?>
      <div class="alert alert-warning d-flex align-items-start gap-2 mx-3 mb-0 py-2" role="alert" style="font-size:0.85rem">
        <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div><?= $GLOBAL['attestationOffSeasonWarning'] ?></div>
          <div class="form-check mt-1 mb-0">
            <input class="form-check-input" type="checkbox" id="attest-row-off-season-confirm">
            <label class="form-check-label" for="attest-row-off-season-confirm"><?= $GLOBAL['attestationOffSeasonConfirm'] ?></label>
          </div>
        </div>
      </div>
      <?php endif ?>
      <div class="modal-footer gap-2">
        <div class="me-auto small text-muted" id="attest-row-modal-subject"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($GLOBAL['cancel'], ENT_QUOTES, $charset) ?></button>
        <button type="button" class="btn btn-primary" id="btn-attest-row-send" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['sendAttestationBtn'], ENT_QUOTES, $charset) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
    var rowBtns = document.querySelectorAll('.js-preview-attest-row');
    if (!rowBtns.length) return;

    var baseUrl = <?= json_encode(appUrl()) ?>;
    var year    = <?= (int)$year ?>;
    function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }

    var modal      = new bootstrap.Modal(document.getElementById('attestRowPreviewModal'));
    var loadingEl   = document.getElementById('attest-row-modal-loading');
    var errorEl     = document.getElementById('attest-row-modal-error');
    var frame       = document.getElementById('attest-row-modal-frame');
    var metaEl      = document.getElementById('attest-row-modal-meta');
    var subjectEl   = document.getElementById('attest-row-modal-subject');
    var sendBtn     = document.getElementById('btn-attest-row-send');
    var offSeasonCb = document.getElementById('attest-row-off-season-confirm');
    var currentUserId = null;
    var previewOk      = false;

    function syncSendEnabled() {
        sendBtn.disabled = !previewOk || (offSeasonCb && !offSeasonCb.checked);
    }
    if (offSeasonCb) { offSeasonCb.addEventListener('change', syncSendEnabled); }

    rowBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentUserId = btn.dataset.userId;
            loadingEl.style.display = '';
            errorEl.style.display   = 'none';
            frame.style.display     = 'none';
            metaEl.textContent      = btn.dataset.name + ' <' + btn.dataset.email + '> — ' + year;
            subjectEl.textContent   = '';
            previewOk               = false;
            if (offSeasonCb) { offSeasonCb.checked = false; }
            syncSendEnabled();
            modal.show();

            fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                body: 'action=previewAttestation&user_id=' + currentUserId + '&year=' + year
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
                syncSendEnabled();
            })
            .catch(function () {
                loadingEl.style.display = 'none';
                errorEl.textContent = '?';
                errorEl.style.display = '';
            });
        });
    });

    sendBtn.addEventListener('click', function () {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + sendBtn.textContent.trim();
        fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
            body: 'action=sendAttestationOne&user_id=' + currentUserId + '&year=' + year
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            modal.hide();
            var rowBtn = document.querySelector('.js-preview-attest-row[data-user-id="' + currentUserId + '"]');
            if (rowBtn) {
                rowBtn.innerHTML = data.ok
                    ? '<i class="fas fa-check text-success" aria-hidden="true"></i>'
                    : '<i class="fas fa-triangle-exclamation text-danger" aria-hidden="true"></i>';
            }
        })
        .catch(function () { modal.hide(); });
    });
})();
</script>

<!-- Confirm modal for bulk attestation send by email -->
<div class="modal fade" id="bulk-attest-send-modal" tabindex="-1" aria-labelledby="bulk-attest-send-title" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="bulk-attest-send-title" style="font-size:0.9rem">
          <i class="fas fa-paper-plane me-2 text-primary" aria-hidden="true"></i><?= $GLOBAL['sendAllAttestationsEmail'] ?>
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div id="bulk-attest-send-confirm-body" class="modal-body py-3" style="font-size:0.875rem">
        <p class="mb-0"><?= sprintf($GLOBAL['bulkAttestSendConfirmBody'], '<strong id="bulk-attest-send-count">…</strong>') ?></p>
        <?php if ((int)date('n') !== 1): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 mt-3 mb-0 py-2" role="alert" style="font-size:0.85rem">
          <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
          <div>
            <div><?= $GLOBAL['attestationOffSeasonWarning'] ?></div>
            <div class="form-check mt-1 mb-0">
              <input class="form-check-input" type="checkbox" id="bulk-attest-off-season-confirm">
              <label class="form-check-label" for="bulk-attest-off-season-confirm"><?= $GLOBAL['attestationOffSeasonConfirm'] ?></label>
            </div>
          </div>
        </div>
        <?php endif ?>
      </div>
      <div id="bulk-attest-send-progress-body" class="modal-body py-3 d-none" style="font-size:0.875rem">
        <p class="mb-2"><?= $GLOBAL['bulkAttestSendInProgress'] ?></p>
        <div class="progress mb-2" role="progressbar" aria-label="<?= $GLOBAL['progress'] ?>" style="height:6px">
          <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
        </div>
      </div>
      <div id="bulk-attest-send-result-body" class="modal-body py-3 d-none" style="font-size:0.875rem"></div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" id="bulk-attest-send-go" class="btn btn-primary btn-sm"
                data-msg-ok="<?= htmlspecialchars($GLOBAL['sendAttestationsBulkOk'], ENT_QUOTES, $charset) ?>"
                data-msg-fail="<?= htmlspecialchars($GLOBAL['sendAttestationsBulkFail'], ENT_QUOTES, $charset) ?>">
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendBtn'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var btn = document.getElementById('btn-bulk-attest-send');
  if (!btn) return;
  var modal        = new bootstrap.Modal(document.getElementById('bulk-attest-send-modal'));
  var confirmBody   = document.getElementById('bulk-attest-send-confirm-body');
  var progressBody  = document.getElementById('bulk-attest-send-progress-body');
  var resultBody    = document.getElementById('bulk-attest-send-result-body');
  var goBtn         = document.getElementById('bulk-attest-send-go');
  var countEl       = document.getElementById('bulk-attest-send-count');
  var offSeasonCb   = document.getElementById('bulk-attest-off-season-confirm');

  function syncGoEnabled() {
    goBtn.disabled = !!(offSeasonCb && !offSeasonCb.checked);
  }
  if (offSeasonCb) { offSeasonCb.addEventListener('change', syncGoEnabled); }

  btn.addEventListener('click', function() {
    var rowCount = document.querySelectorAll('table.export tbody tr').length;
    countEl.textContent = rowCount;
    confirmBody.classList.remove('d-none');
    progressBody.classList.add('d-none');
    resultBody.classList.add('d-none');
    goBtn.classList.remove('d-none');
    if (offSeasonCb) { offSeasonCb.checked = false; }
    syncGoEnabled();
    modal.show();
  });

  goBtn.addEventListener('click', function() {
    confirmBody.classList.add('d-none');
    progressBody.classList.remove('d-none');
    goBtn.classList.add('d-none');
    fetch(<?= json_encode(appUrl()) ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
        },
        body: 'action=sendAttestationsBulk&year=' + encodeURIComponent(btn.dataset.year) + '&minSum=' + encodeURIComponent(btn.dataset.minSum)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        progressBody.classList.add('d-none');
        resultBody.classList.remove('d-none');
        if (data.ok) {
            var msg = goBtn.dataset.msgOk.replace('%d', data.sent).replace('%sk', data.skipped);
            resultBody.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fas fa-circle-check me-1" aria-hidden="true"></i>' + msg + '</div>';
        } else {
            resultBody.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + goBtn.dataset.msgFail + '</div>';
        }
    })
    .catch(function () {
        progressBody.classList.add('d-none');
        resultBody.classList.remove('d-none');
        resultBody.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + goBtn.dataset.msgFail + '</div>';
    });
  });

  document.getElementById('bulk-attest-send-modal').addEventListener('hidden.bs.modal', function() {
    confirmBody.classList.remove('d-none');
    progressBody.classList.add('d-none');
    resultBody.classList.add('d-none');
    goBtn.classList.remove('d-none');
  });
})();
</script>

