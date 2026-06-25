<?php
$showAll             = isset($_REQUEST['showAll']) && $_REQUEST['showAll'] == '1';
$includeAttestation  = !isset($_REQUEST['includeAttestation']) || $_REQUEST['includeAttestation'] == '1';
$minSum              = $showAll ? 0 : max(0, (int)(isset($_REQUEST['minSum']) ? $_REQUEST['minSum'] : 100));
if (!$showAll && !in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 100; }

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year === -1) { $year = (int)date("Y"); }
$type = "allTypes";

$membreTeamId = (int)($appSettings['default_team'] ?? 0);
$membreTeamLabel = 'Actif?';
if ($membreTeamId > 0) {
    $r = $pdo->prepare("SELECT name FROM team WHERE id = ?");
    $r->execute([$membreTeamId]);
    $membreTeamLabel = $r->fetchColumn() ?: 'Actif?';
}
?>
<?php
$_yearLabel = (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? $GLOBAL['allYear'] : $year;
if ($showAll) {
    $_amountLabel = '<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Toutes entrées';
} else {
    $_amountLabel = 'Min. ' . number_format($minSum, 0, '.', '\'') . ' CHF';
}
if ($year != -2) {
    $_kFrom  = mktime(0,0,0,1,0,$year);
    $_kTo    = mktime(0,0,0,1,1,$year+1);
    $_kFrom1 = mktime(0,0,0,1,0,$year-1);
    $_kTo1   = mktime(0,0,0,1,1,$year);

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

    $_kMembres = 0;
    $_kMembresPrev = 0;
    $_kMembresDelta = null;
    if ($membreTeamId > 0) {
        $_sM = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE parameter=? AND value='true'");
        $_sM->execute(["team_$membreTeamId"]);
        $_kMembres = (int)$_sM->fetchColumn();

        $_rPrev = $pdo->prepare("SELECT id FROM team WHERE name = ?");
        $_rPrev->execute(["Membre " . ($year - 1)]);
        $_prevTeamId = (int)$_rPrev->fetchColumn();
        if ($_prevTeamId > 0) {
            $_sM->execute(["team_$_prevTeamId"]);
            $_kMembresPrev = (int)$_sM->fetchColumn();
            $_kMembresDelta = $_kMembresPrev > 0 ? (($_kMembres - $_kMembresPrev) / $_kMembresPrev * 100) : null;

            $_sLapsedM = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE parameter=? AND value='true' AND user_id NOT IN (SELECT user_id FROM user_properties WHERE parameter=? AND value='true')");
            $_sLapsedM->execute(["team_$_prevTeamId", "team_$membreTeamId"]);
            $_kMembresLapsed = (int)$_sLapsedM->fetchColumn();
        }
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
<div class="d-flex gap-3 mb-4 flex-wrap">
  <!-- Total contributions -->
  <div class="flex-fill" style="min-width:180px;background:var(--ca-primary);color:#fff;border-radius:10px;padding:1rem 1.25rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;opacity:0.8">Contributions <?= $year ?></div>
    <div style="font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:0.25rem">
      <?= number_format($_kTotal, 0, '.', '\'') ?> <span style="font-size:1rem;font-weight:400">CHF</span>
    </div>
    <?php if ($_kYtd !== null): ?>
    <?php
      $_kYtdChf  = $_kTotal - $_kTotalYtd1;
      $_kGap     = $_kTotal1 - $_kTotal;  // positive = still below full 2025, negative = already above
      $_mois = mb_strtolower(strftime('%b', mktime(0,0,0,(int)date('m'),1,$year)));
    ?>
    <div style="font-size:0.78rem;margin-top:0.3rem;opacity:0.85">
      <?php if ($_kYtdChf >= 0): ?>
        <i class="fas fa-arrow-up me-1" aria-hidden="true"></i>+<?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(+<?= number_format($_kYtd, 1) ?>%)</span>
        vs jan–<?= $_mois ?> <?= $year-1 ?>
      <?php else: ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true"></i><?= number_format($_kYtdChf, 0, '.', '\'') ?> CHF
        <span style="opacity:0.75">(<?= number_format($_kYtd, 1) ?>%)</span>
        vs jan–<?= $_mois ?> <?= $year-1 ?>
      <?php endif ?>
    </div>
    <?php if ($_kTotal1 > 0): ?>
    <div style="font-size:0.72rem;margin-top:0.2rem;opacity:0.7">
      <?php
        $_kProgressPct = round($_kTotal / $_kTotal1 * 100);
        $_kOverPct     = round(abs($_kGap) / $_kTotal1 * 100);
      ?>
      <?php if ($_kGap > 0): ?>
        <i class="fas fa-flag-checkered me-1" aria-hidden="true"></i>Il manque <?= number_format($_kGap, 0, '.', '\'') ?> CHF pour atteindre <?= $year-1 ?> (<?= number_format($_kTotal1, 0, '.', '\'') ?>) — <?= $_kProgressPct ?>% atteint
      <?php else: ?>
        <i class="fas fa-trophy me-1" aria-hidden="true"></i>Total <?= $year-1 ?> (<?= number_format($_kTotal1, 0, '.', '\'') ?>) dépassé de +<?= number_format(abs($_kGap), 0, '.', '\'') ?> CHF (+<?= $_kOverPct ?>%)
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
  <div class="flex-fill" style="min-width:140px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:1rem 1.25rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">Donateurs</div>
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
        <i class="fas fa-arrow-up me-1" aria-hidden="true" style="color:var(--bs-success)"></i>+<?= number_format($_kDonYtdDelta, 1) ?>% vs jan–<?= mb_strtolower(strftime('%b', mktime(0,0,0,(int)date('m'),1,$year))) ?> <?= $year-1 ?> (<?= $_kDonateursYtd1 ?>)
      <?php elseif ($_kDonYtdDelta !== null): ?>
        <i class="fas fa-arrow-down me-1" aria-hidden="true" style="color:var(--bs-danger)"></i><?= number_format($_kDonYtdDelta, 1) ?>% vs jan–<?= mb_strtolower(strftime('%b', mktime(0,0,0,(int)date('m'),1,$year))) ?> <?= $year-1 ?> (<?= $_kDonateursYtd1 ?>)
      <?php else: ?>
        <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>Même période <?= $year-1 ?>&nbsp;: <?= $_kDonateursYtd1 ?>
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
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=loyalDonors&amp;year=<?= $year ?>"
         title="Ont aussi donné en <?= $year-1 ?>"
         style="color:inherit;text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-rotate me-1" aria-hidden="true" style="color:var(--bs-success)"></i><?= $_kRecurrents ?> fidèles
      </a>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=newDonors&amp;year=<?= $year ?>"
         title="Première contribution en <?= $year ?>"
         style="color:inherit;text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-star me-1" aria-hidden="true" style="color:var(--bs-warning)"></i><?= $_kNouveaux ?> nouveaux
      </a>
      <?php if ($_kLapsed > 0): ?>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedDonors&amp;year=<?= $year ?>"
         title="Ont donné en <?= $year-1 ?> mais pas en <?= $year ?>"
         style="color:var(--bs-danger);text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $_kLapsed ?> perdus
      </a>
      <?php endif ?>
    </div>
  </div>
  <!-- Don moyen -->
  <div class="flex-fill" style="min-width:140px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:1rem 1.25rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">Don moyen</div>
    <div style="font-size:1.75rem;font-weight:700;line-height:1.2;margin-top:0.25rem;color:var(--ca-ink,#212529)">
      <?= number_format($_kDonMoyen, 0, '.', '\'') ?> <span style="font-size:0.9rem;font-weight:400">CHF</span>
    </div>
    <div style="font-size:0.78rem;color:var(--ca-ink-muted);margin-top:0.3rem">par donateur</div>
  </div>
  <!-- Membres actifs -->
  <?php if ($membreTeamId > 0): ?>
  <div class="flex-fill" style="min-width:140px;background:var(--ca-ground);border:1px solid var(--ca-border,#dee2e6);border-radius:10px;padding:1rem 1.25rem">
    <div style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">Membres actifs</div>
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
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedMembers&amp;year=<?= $year ?>"
         title="Membres <?= $year-1 ?> non reconduits en <?= $year ?>"
         style="color:var(--bs-danger);text-decoration:none"
         onclick="event.stopPropagation()">
        <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $_kMembresLapsed ?> perdus
      </a>
    </div>
    <?php endif ?>
    <?php else: ?>
    <div style="font-size:0.78rem;color:var(--ca-ink-muted);margin-top:0.3rem"><?= htmlentities($membreTeamLabel, ENT_COMPAT, $charset) ?></div>
    <?php endif ?>
  </div>
  <?php endif ?>
</div>
<?php endif ?>

<?php if ($year != -2 && !empty($_typeBreakdown) && $_typeTotal > 0): ?>
<?php
static $_bgVarMapR = [
    'bg-primary-subtle'   => ['bg'=>'rgba(13,110,253,0.15)','border'=>'rgba(13,110,253,0.5)','text'=>'#084298'],
    'bg-secondary-subtle' => ['bg'=>'rgba(108,117,125,0.15)','border'=>'rgba(108,117,125,0.5)','text'=>'#41464b'],
    'bg-success-subtle'   => ['bg'=>'rgba(25,135,84,0.15)','border'=>'rgba(25,135,84,0.5)','text'=>'#0a3622'],
    'bg-danger-subtle'    => ['bg'=>'rgba(220,53,69,0.15)','border'=>'rgba(220,53,69,0.5)','text'=>'#58151c'],
    'bg-warning-subtle'   => ['bg'=>'rgba(255,193,7,0.15)','border'=>'rgba(255,193,7,0.5)','text'=>'#664d03'],
    'bg-info-subtle'      => ['bg'=>'rgba(13,202,240,0.15)','border'=>'rgba(13,202,240,0.5)','text'=>'#055160'],
    'bg-light'            => ['bg'=>'rgba(173,181,189,0.15)','border'=>'rgba(173,181,189,0.5)','text'=>'#495057'],
    'bg-dark-subtle'      => ['bg'=>'rgba(52,58,64,0.15)','border'=>'rgba(52,58,64,0.5)','text'=>'#1a1d20'],
];
?>
<div class="d-flex gap-2 mb-3 flex-wrap align-items-start" style="font-size:0.78rem">
  <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted);padding-top:0.35rem;white-space:nowrap">Répartition</span>
  <?php foreach ($_typeBreakdown as $_tr):
    $_pct = $_typeTotal > 0 ? round((float)$_tr->total / $_typeTotal * 100) : 0;
    $_colors = $_bgVarMapR[$_tr->color] ?? $_bgVarMapR['bg-secondary-subtle'];
  ?>
  <div style="display:flex;align-items:center;gap:0.35rem;background:<?= $_colors['bg'] ?>;border:1px solid <?= $_colors['border'] ?>;border-radius:6px;padding:0.25rem 0.55rem;white-space:nowrap">
    <span style="color:<?= $_colors['text'] ?>;font-weight:600"><?= htmlentities($_tr->label, ENT_COMPAT, $charset) ?></span>
    <span style="color:var(--ca-ink-muted)"><?= number_format((float)$_tr->total, 0, '.', '\'') ?> CHF</span>
    <span style="color:var(--ca-ink-muted);font-size:0.72rem">(<?= $_pct ?>%)</span>
  </div>
  <?php endforeach ?>
</div>
<?php endif ?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">

  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Contributions</span>

  <!-- Montant minimum -->
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle<?= $showAll ? ' active' : '' ?>" type="button"
            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Montant minimum">
      <?= $_amountLabel ?>
    </button>
    <ul class="dropdown-menu">
      <?php foreach ([1, 100, 200, 500, 1000] as $_ms): ?>
      <li><a class="dropdown-item<?= (!$showAll && $minSum == $_ms) ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $_ms ?>&amp;year=<?= $year ?>&amp;includeAttestation=<?= $includeAttestation ? 1 : 0 ?>">
        Min. <?= number_format($_ms, 0, '.', '\'') ?> CHF
      </a></li>
      <?php endforeach ?>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item<?= $showAll ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;showAll=1&amp;year=<?= $year ?>&amp;includeAttestation=<?= $includeAttestation ? 1 : 0 ?>">
        <i class="fas fa-triangle-exclamation me-1 text-warning" aria-hidden="true"></i>Toutes entrées (cotis. incluses)
      </a></li>
    </ul>
  </div>

  <!-- Année -->
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="Année">
      <i class="fas fa-calendar-alt me-1" aria-hidden="true"></i><?= $_yearLabel ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= (!isset($_REQUEST['year']) && $y == $year && !$showAll) || (isset($_REQUEST['year']) && $_REQUEST['year'] == $y) ? ' active' : '' ?>"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <!-- Séparateur visuel -->
  <span class="text-muted" aria-hidden="true" style="font-size:0.9rem;padding:0 0.15rem">|</span>

  <!-- Attestations demandées -->
  <label class="ca-filter-btn ca-attest-label d-flex align-items-center gap-1" style="cursor:pointer;user-select:none">
    <input type="checkbox" id="includeAttestation" <?= $includeAttestation ? 'checked' : '' ?> style="outline:none;box-shadow:none">
    <i class="fas fa-file-pdf" aria-hidden="true"></i> Inclure si attestation demandée
  </label>
  <style>
  .ca-attest-label:focus-visible { outline: none; }
  .ca-attest-label:focus-within { border-color: var(--ca-primary); background: var(--ca-primary-light); color: var(--ca-primary-dark); }
  </style>
  <button type="button" class="btn btn-link p-0 text-muted" style="font-size:0.85rem;line-height:1"
          tabindex="0"
          data-bs-toggle="popover"
          data-bs-trigger="hover focus"
          data-bs-placement="top"
          data-bs-content="Affiche les personnes ayant coché &laquo;souhaite une attestation de don&raquo; même si leur total est inférieur au montant minimum sélectionné."
          aria-label="Explication du filtre attestations">
    <i class="fas fa-circle-info" aria-hidden="true"></i>
  </button>

  <!-- PDF bulk — poussé à droite -->
  <?php if ($year != -2): ?>
  <button type="button" id="btn-bulk-attest"
          class="ca-filter-btn ms-auto"
          data-url="/attestation_bulk.php?year=<?= (int)$year ?>&amp;minSum=<?= (int)$minSum ?>"
          title="Générer toutes les attestations en un seul PDF">
    <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Attestations <?= (int)$year ?>
  </button>
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
  <span><strong>Mode étendu</strong> — tous les types comptables sont inclus<?php if ($excludedLabels): ?>, y compris ceux habituellement exclus des dons : <?= implode(', ', $excludedLabels) ?><?php endif ?>. Les totaux ne reflètent pas uniquement les dons.</span>
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
    <th><?= htmlentities($membreTeamLabel, ENT_COMPAT, $charset) ?></th>
    <th><?=$GLOBAL['address']?></th>
    <th><?=$GLOBAL['npa']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th title="Souhaite attestation"><i class="fas fa-file-pdf" aria-hidden="true"></i></th>
    <th></th>
</tr>
</thead>
<?php
$params = ["team_$membreTeamId"];
$baseSelect = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           SUM(c.sum) AS total,
           MAX(c.wants_attestation) AS wants_attestation,
           EXISTS(
               SELECT 1 FROM user_properties up
               WHERE up.user_id = u.id AND up.parameter = ? AND up.value = 'true'
           ) AS is_actif
    FROM users u
    JOIN compta c ON u.id = c.user_id
";

if ($showAll || $wantsAttestation) {
    $sql = $baseSelect . " WHERE 1=1";
} else {
    $sql = $baseSelect . " WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)";
}

if ($year != -2) {
    $from = mktime(0, 0, 0, 1, 0, $year);
    $to   = mktime(0, 0, 0, 1, 1, $year + 1);
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
    $isActif = $row->is_actif ? "<i class='fas fa-check'></i><span class='visually-hidden'>1</span>" : "";
    $sexeRaw = $row->sexe;
    $sexe2 = match($sexeRaw) { 'f' => 'Madame', 'm' => 'Monsieur', 'hf' => 'Monsieur et Madame', default => '-' };
    $sexe = match($sexeRaw) {
        'f'  => "<i class='fas fa-female s'></i>",
        'm'  => "<i class='fas fa-male s'></i>",
        'hf' => "<i class='fas fa-male s'></i><i class='fas fa-female s'></i>",
        default => ''
    };
    ?>
    <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=(int)$row->id?>&year=<?=$year?>">
        <td><?=$society?></td>
        <td><?=$sexe?><span class="hide"><?=$sexe2?></span></td>
        <td><strong><?=$lastName?></strong></td>
        <td><?=$firstName?></td>
        <td><?=$email?></td>
        <td><?=$isActif?></td>
        <td><?=$address?></td>
        <td><?=$npa?></td>
        <td style="text-align:right"><?=number_format((float)$row->total, 2, '.', '\'')?></td>
        <td class="text-center">
            <?php if ($row->wants_attestation): ?>
            <i class="fas fa-check text-success" aria-label="Souhaite attestation"></i>
            <?php endif ?>
        </td>
        <td class="text-end">
            <?php if ($year != -2): ?>
            <a href="/attestation_don.php?userid=<?=(int)$row->id?>&amp;year=<?=(int)$year?>"
               class="btn btn-sm py-0 px-1 text-muted"
               title="Attestation de dons <?=(int)$year?>"
               target="_blank"
               onclick="event.stopPropagation()">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
            </a>
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
    document.getElementById('includeAttestation').addEventListener('change', function() {
        var loc = window.location.href;
        loc = loc.replace(/[?&]includeAttestation=[01]/, '');
        var sep = loc.indexOf('?') === -1 ? '?' : '&';
        window.location = loc + sep + 'includeAttestation=' + (this.checked ? 1 : 0);
    });
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' },
                    { extend: 'pdf',   text: '<i class="fas fa-file-pdf me-2" aria-hidden="true"></i>PDF' }
                ]
            }
        ],
        language: {
            info: '_TOTAL_ entrées',
            infoFiltered: '(filtrées sur _MAX_)',
            search: '',
            searchPlaceholder: 'Filtrer…'
        }
    });
});
</script>

<!-- Modal confirmation attestations bulk -->
<div class="modal fade" id="bulk-attest-modal" tabindex="-1" aria-labelledby="bulk-attest-title" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="bulk-attest-title" style="font-size:0.9rem">
          <i class="fas fa-file-pdf me-2 text-danger" aria-hidden="true"></i>Générer les attestations
        </h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div id="bulk-attest-confirm-body" class="modal-body py-3" style="font-size:0.875rem">
        <p class="mb-2">Générer un PDF avec les attestations de dons pour <strong id="bulk-attest-count">…</strong> personne(s).</p>
        <p class="text-muted mb-0" style="font-size:0.8rem">Cette opération peut prendre plusieurs minutes selon le nombre d'entrées.</p>
      </div>
      <div id="bulk-attest-progress-body" class="modal-body py-3 d-none" style="font-size:0.875rem">
        <p class="mb-2">Génération en cours — le PDF s'ouvrira dans un nouvel onglet.</p>
        <div class="progress mb-2" role="progressbar" aria-label="Progression" style="height:6px">
          <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
        </div>
        <p class="text-muted mb-0" style="font-size:0.8rem">Vous pouvez fermer cette fenêtre. La génération continue dans l'onglet ouvert.</p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
        <button type="button" id="bulk-attest-go" class="btn btn-danger btn-sm">
          <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Générer
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var btn = document.getElementById('btn-bulk-attest');
  if (!btn) return;
  var modal = new bootstrap.Modal(document.getElementById('bulk-attest-modal'));
  var confirmBody  = document.getElementById('bulk-attest-confirm-body');
  var progressBody = document.getElementById('bulk-attest-progress-body');
  var goBtn = document.getElementById('bulk-attest-go');
  var countEl = document.getElementById('bulk-attest-count');

  btn.addEventListener('click', function() {
    var rowCount = document.querySelectorAll('table.export tbody tr').length;
    countEl.textContent = rowCount;
    confirmBody.classList.remove('d-none');
    progressBody.classList.add('d-none');
    goBtn.classList.remove('d-none');
    modal.show();
  });

  goBtn.addEventListener('click', function() {
    confirmBody.classList.add('d-none');
    progressBody.classList.remove('d-none');
    goBtn.classList.add('d-none');
    window.open(btn.dataset.url, '_blank');
  });

  document.getElementById('bulk-attest-modal').addEventListener('hidden.bs.modal', function() {
    confirmBody.classList.remove('d-none');
    progressBody.classList.add('d-none');
    goBtn.classList.remove('d-none');
  });
})();
</script>

