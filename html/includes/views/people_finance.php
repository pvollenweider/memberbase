<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Unified "Membres & finances" hub — tab shell over the three list views
 * that used to be separate destinations (#164). All three tabs are now
 * fully ported: Membres (users_list.php), Relances cotisation
 * (compta_recap.php, managers only), Dons & attestations
 * (donors_summary.php — KPI cards/pie already live on the dashboard,
 * #153, so they're suppressed here via $_pfEmbedded).
 *
 * Linked from the navbar ("Membres & finances", desktop and mobile),
 * replacing the old separate Listes / Relances cotisation / Aperçu des
 * dons entries. The three underlying routes (?view=list, ?view=comptaRecap,
 * ?view=resume) still work — only removed from the menu.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_pfTab = in_array($_REQUEST['tab'] ?? '', ['members', 'recap', 'dons'], true) ? $_REQUEST['tab'] : 'members';
$_pfPane = fn(string $tab): string => $_pfTab === $tab ? ' show active' : '';

// Each tab's content is an independently-written view file, never designed
// to coexist in one request (Bootstrap tabs render every pane server-side,
// only toggling CSS visibility client-side — so with 2+ real tabs, more
// than one of these actually executes per request). A closure gives each
// its own local scope so top-level variable names (both use $year!) can't
// collide, while still exposing the handful of bootstrap globals they read.
$_pfRequireIsolated = function (string $file, array $vars = []) use ($GLOBAL, $charset, $appSettings, $comptaTypes, $pdo) {
    extract($vars);
    require $file;
};
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['peopleFinancePageTitle'] ?></h1>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_pfTab === 'members' ? ' active' : '' ?>" id="pf-tab-members-btn"
            data-bs-toggle="tab" data-bs-target="#pf-tab-members" type="button" role="tab"
            aria-controls="pf-tab-members" aria-selected="<?= $_pfTab === 'members' ? 'true' : 'false' ?>">
      <i class="fas fa-users me-1" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabMembers'] ?>
    </button>
  </li>
  <?php if (isManager()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_pfTab === 'recap' ? ' active' : '' ?>" id="pf-tab-recap-btn"
            data-bs-toggle="tab" data-bs-target="#pf-tab-recap" type="button" role="tab"
            aria-controls="pf-tab-recap" aria-selected="<?= $_pfTab === 'recap' ? 'true' : 'false' ?>">
      <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabRecap'] ?>
    </button>
  </li>
  <?php endif ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_pfTab === 'dons' ? ' active' : '' ?>" id="pf-tab-dons-btn"
            data-bs-toggle="tab" data-bs-target="#pf-tab-dons" type="button" role="tab"
            aria-controls="pf-tab-dons" aria-selected="<?= $_pfTab === 'dons' ? 'true' : 'false' ?>">
      <i class="fas fa-hand-holding-heart me-1" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabDons'] ?>
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade<?= $_pfPane('members') ?>" id="pf-tab-members" role="tabpanel" aria-labelledby="pf-tab-members-btn">
    <?php $_pfRequireIsolated(__DIR__ . '/users_list.php'); ?>
  </div>

  <?php if (isManager()): ?>
  <div class="tab-pane fade<?= $_pfPane('recap') ?>" id="pf-tab-recap" role="tabpanel" aria-labelledby="pf-tab-recap-btn">
    <?php $_pfRequireIsolated(__DIR__ . '/compta_recap.php', ['_pfEmbedded' => true]); ?>
  </div>
  <?php endif ?>

  <div class="tab-pane fade<?= $_pfPane('dons') ?>" id="pf-tab-dons" role="tabpanel" aria-labelledby="pf-tab-dons-btn">
    <?php $_pfRequireIsolated(__DIR__ . '/donors_summary.php', ['_pfEmbedded' => true]); ?>
  </div>
</div>

<script>
// If the page lands on a non-Membres tab (?tab=recap/dons) the DataTable
// initializes while its pane is hidden and mis-measures column widths —
// force a re-adjust once the Membres tab is actually shown.
document.getElementById('pf-tab-members-btn')?.addEventListener('shown.bs.tab', function () {
  if (window.CA_DT_INSTANCE) { CA_DT_INSTANCE.columns.adjust(); }
});
document.getElementById('pf-tab-dons-btn')?.addEventListener('shown.bs.tab', function () {
  if (window.CA_DT_INSTANCE_DONS) { CA_DT_INSTANCE_DONS.columns.adjust(); }
});
</script>
