<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Unified "Membres & finances" hub — tab shell over four list views that
 * used to be separate destinations (#164). Liste (users_list.php),
 * Notification de versement (compta_recap.php, managers only — pending
 * unnotified compta entries), Cotisations non renouvelées
 * (members_lapsed.php, managers only), Dons & attestations
 * (donors_summary.php — KPI cards/pie already live on the dashboard,
 * #153, so they're suppressed here via $_pfEmbedded).
 *
 * Linked from the navbar ("Membres & finances", desktop and mobile). The
 * underlying standalone routes (?view=list, ?view=comptaRecap, ?view=resume)
 * still work — only removed from the menu. ?view=lapsedMembers no longer
 * exists as its own route; it redirects to this hub's "lapsed" tab
 * (includes/routing/views.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_pfTab = in_array($_REQUEST['tab'] ?? '', ['members', 'recap', 'lapsed', 'lapsedDonors', 'dons'], true) ? $_REQUEST['tab'] : 'members';
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
  <?php if (isManager()): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_pfTab === 'lapsed' ? ' active' : '' ?>" id="pf-tab-lapsed-btn"
            data-bs-toggle="tab" data-bs-target="#pf-tab-lapsed" type="button" role="tab"
            aria-controls="pf-tab-lapsed" aria-selected="<?= $_pfTab === 'lapsed' ? 'true' : 'false' ?>">
      <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabLapsed'] ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_pfTab === 'lapsedDonors' ? ' active' : '' ?>" id="pf-tab-lapsedDonors-btn"
            data-bs-toggle="tab" data-bs-target="#pf-tab-lapsedDonors" type="button" role="tab"
            aria-controls="pf-tab-lapsedDonors" aria-selected="<?= $_pfTab === 'lapsedDonors' ? 'true' : 'false' ?>">
      <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabLapsedDonors'] ?>
    </button>
  </li>
  <?php endif ?>
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

  <?php if (isManager()): ?>
  <div class="tab-pane fade<?= $_pfPane('lapsed') ?>" id="pf-tab-lapsed" role="tabpanel" aria-labelledby="pf-tab-lapsed-btn">
    <?php $_pfCohortMembers = ($_REQUEST['cohort'] ?? '') === 'new' ? 'new' : 'lapsed'; ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <ul class="nav nav-pills mb-0" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link<?= $_pfCohortMembers === 'lapsed' ? ' active' : '' ?>" id="pf-cohort-members-lapsed-btn"
                  data-bs-toggle="pill" data-bs-target="#pf-cohort-members-lapsed" type="button" role="tab"
                  aria-controls="pf-cohort-members-lapsed" aria-selected="<?= $_pfCohortMembers === 'lapsed' ? 'true' : 'false' ?>">
            <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $GLOBAL['lapsedDonors'] ?>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link<?= $_pfCohortMembers === 'new' ? ' active' : '' ?>" id="pf-cohort-members-new-btn"
                  data-bs-toggle="pill" data-bs-target="#pf-cohort-members-new" type="button" role="tab"
                  aria-controls="pf-cohort-members-new" aria-selected="<?= $_pfCohortMembers === 'new' ? 'true' : 'false' ?>">
            <i class="fas fa-star me-1" aria-hidden="true"></i><?= $GLOBAL['newMembers'] ?>
          </button>
        </li>
      </ul>
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members" class="btn btn-outline-secondary btn-sm" hx-boost="false">
        <i class="fas fa-users me-1" aria-hidden="true"></i><?= $GLOBAL['viewAllCurrentMembers'] ?>
      </a>
    </div>
    <div class="tab-content">
      <div class="tab-pane fade<?= $_pfCohortMembers === 'lapsed' ? ' show active' : '' ?>" id="pf-cohort-members-lapsed" role="tabpanel" aria-labelledby="pf-cohort-members-lapsed-btn">
        <?php $_pfRequireIsolated(__DIR__ . '/members_lapsed.php', ['_pfEmbedded' => true]); ?>
      </div>
      <div class="tab-pane fade<?= $_pfCohortMembers === 'new' ? ' show active' : '' ?>" id="pf-cohort-members-new" role="tabpanel" aria-labelledby="pf-cohort-members-new-btn">
        <?php $_pfRequireIsolated(__DIR__ . '/members_new.php', ['_pfEmbedded' => true]); ?>
      </div>
    </div>
  </div>
  <?php endif ?>

  <?php if (isManager()): ?>
  <div class="tab-pane fade<?= $_pfPane('lapsedDonors') ?>" id="pf-tab-lapsedDonors" role="tabpanel" aria-labelledby="pf-tab-lapsedDonors-btn">
    <?php $_pfCohortDonors = ($_REQUEST['cohort'] ?? '') === 'new' ? 'new' : 'lapsed'; ?>
    <ul class="nav nav-pills mb-3" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $_pfCohortDonors === 'lapsed' ? ' active' : '' ?>" id="pf-cohort-donors-lapsed-btn"
                data-bs-toggle="pill" data-bs-target="#pf-cohort-donors-lapsed" type="button" role="tab"
                aria-controls="pf-cohort-donors-lapsed" aria-selected="<?= $_pfCohortDonors === 'lapsed' ? 'true' : 'false' ?>">
          <i class="fas fa-user-clock me-1" aria-hidden="true"></i><?= $GLOBAL['lapsedDonors'] ?>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link<?= $_pfCohortDonors === 'new' ? ' active' : '' ?>" id="pf-cohort-donors-new-btn"
                data-bs-toggle="pill" data-bs-target="#pf-cohort-donors-new" type="button" role="tab"
                aria-controls="pf-cohort-donors-new" aria-selected="<?= $_pfCohortDonors === 'new' ? 'true' : 'false' ?>">
          <i class="fas fa-star me-1" aria-hidden="true"></i><?= $GLOBAL['dashboardShortcutNewDonors'] ?>
        </button>
      </li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade<?= $_pfCohortDonors === 'lapsed' ? ' show active' : '' ?>" id="pf-cohort-donors-lapsed" role="tabpanel" aria-labelledby="pf-cohort-donors-lapsed-btn">
        <?php $_pfRequireIsolated(__DIR__ . '/donors_lapsed.php', ['_pfEmbedded' => true]); ?>
      </div>
      <div class="tab-pane fade<?= $_pfCohortDonors === 'new' ? ' show active' : '' ?>" id="pf-cohort-donors-new" role="tabpanel" aria-labelledby="pf-cohort-donors-new-btn">
        <?php $_pfRequireIsolated(__DIR__ . '/donors_new.php', ['_pfEmbedded' => true]); ?>
      </div>
    </div>
  </div>
  <?php endif ?>
</div>

<script src="js/hub-tabs.js?v=<?= APP_VERSION ?>"></script>
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

// Filter links inside the Relances/Cotisations non renouvelées/Dons tabs
// still point at their own standalone route (?view=comptaRecap /
// ?view=lapsedMembers / ?view=resume) — rewrite them to stay inside the
// hub, and keep ?tab= in sync when switching tabs so the current tab is
// directly linkable.
caHubRewriteEmbeddedLinks('#pf-tab-recap', 'comptaRecap', 'peopleFinance', 'recap');
caHubRewriteEmbeddedLinks('#pf-tab-lapsed', 'lapsedMembers', 'peopleFinance', 'lapsed');
caHubRewriteEmbeddedLinks('#pf-tab-dons', 'resume', 'peopleFinance', 'dons');
caHubRewriteEmbeddedLinks('#pf-tab-lapsedDonors', 'lapsedDonors', 'peopleFinance', 'lapsedDonors');
caHubEnableTabDeepLink('#pf-tab-members-btn, #pf-tab-recap-btn, #pf-tab-lapsed-btn, #pf-tab-dons-btn, #pf-tab-lapsedDonors-btn', /^pf-tab-(\w+)-btn$/);

// "Perdus"/"Nouveaux" cohort pills inside the lapsed-members and
// lapsed-donors tabs — keep ?cohort= in sync the same way ?tab= is, so a
// direct cohort pill is bookmarkable/shareable (dashboard shortcuts link
// straight to ?tab=lapsed&cohort=new, for instance).
caHubEnableTabDeepLink('#pf-cohort-members-lapsed-btn, #pf-cohort-members-new-btn', /^pf-cohort-members-(\w+)-btn$/, 'cohort');
caHubEnableTabDeepLink('#pf-cohort-donors-lapsed-btn, #pf-cohort-donors-new-btn', /^pf-cohort-donors-(\w+)-btn$/, 'cohort');
</script>
