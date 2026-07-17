<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * "Membres & finances" hub — the sidebar nav navigates straight to a
 * specific tab via ?tab=, so this renders only the ONE matching pane per
 * request rather than all five.
 * Liste (users_list.php), Notification de versement (compta_recap.php,
 * managers only — pending unnotified compta entries), Mouvements
 * membres/donateurs (members_lapsed.php/members_new.php/donors_lapsed.php
 * /donors_new.php — open to all roles; the actions inside (send reminder,
 * create segment) are individually isManager()-gated), Dons & attestations
 * (donors_summary.php — KPI cards/pie already live on the dashboard,
 * so they're suppressed here via $_pfEmbedded).
 *
 * Linked from the sidebar nav ("Membres & finances" submenu). The underlying
 * standalone routes (?view=list, ?view=comptaRecap, ?view=resume) still
 * work. ?view=lapsedMembers no longer exists as its own route; it redirects
 * to this hub's "lapsed" tab (includes/routing/views.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_pfTab = in_array($_REQUEST['tab'] ?? '', ['members', 'recap', 'lapsed', 'lapsedDonors', 'dons'], true) ? $_REQUEST['tab'] : 'members';
if ($_pfTab === 'recap' && !isManager()) {
    $_pfTab = 'members'; // guard against a stale/crafted ?tab=recap for non-managers
}

// Each embedded view file is independently written, not designed to coexist
// in one request — a closure gives it its own local scope so top-level
// variable names (both use $year!) can't collide, while still exposing the
// handful of bootstrap globals it reads.
$_pfRequireIsolated = function (string $file, array $vars = []) use ($GLOBAL, $charset, $appSettings, $comptaTypes, $pdo) {
    extract($vars);
    require $file;
};

// Hero header needs to span edge-to-edge under the topbar, so this view
// owns its own container-xl instead of being boxed by index.php's generic
// wrapper.
$_noOuterContainer = true;
$_phIcon = 'fa-users';
$_phTitle = $GLOBAL['peopleFinancePageTitle'];
$_phSubtitle = $GLOBAL['peopleFinanceTab' . ucfirst($_pfTab)] ?? '';
include __DIR__ . '/../partials/page_header.php';
?>

<div class="container-xl px-4 ca-hero-overlap">
<?php /* Mobile-only substitute for the sidebar (hidden ≥991.98px, the exact
         breakpoint where .ca-sidebar-col itself collapses — see custom.css):
         real links, boosted like the rest of the app now that the sidebar
         refreshes itself via OOB swap on every boosted request regardless of
         which link triggered it. Visual language deliberately mirrors
         .ca-sidebar-body .nav-link (light-tint active pill) instead of a
         generic Bootstrap nav-tabs strip, so it doesn't read as a second,
         contradictory navigation system. */ ?>
<ul class="nav ca-hub-tabs mb-3">
  <li class="nav-item">
    <a id="pf-tab-members-btn" class="nav-link<?= $_pfTab === 'members' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members">
      <i class="fas fa-users ca-hub-tab-icon" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabMembers'] ?>
    </a>
  </li>
  <?php if (isManager()): ?>
  <li class="nav-item">
    <a id="pf-tab-recap-btn" class="nav-link<?= $_pfTab === 'recap' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&amp;tab=recap">
      <i class="fas fa-money-check-dollar ca-hub-tab-icon" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabRecap'] ?>
    </a>
  </li>
  <?php endif ?>
  <li class="nav-item">
    <a id="pf-tab-dons-btn" class="nav-link<?= $_pfTab === 'dons' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&amp;tab=dons">
      <i class="fas fa-file-pdf ca-hub-tab-icon" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabDons'] ?>
    </a>
  </li>
  <li class="nav-item">
    <a id="pf-tab-lapsed-btn" class="nav-link<?= $_pfTab === 'lapsed' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsed">
      <i class="fas fa-user-clock ca-hub-tab-icon" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabLapsed'] ?>
    </a>
  </li>
  <li class="nav-item">
    <a id="pf-tab-lapsedDonors-btn" class="nav-link<?= $_pfTab === 'lapsedDonors' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&amp;tab=lapsedDonors">
      <i class="fas fa-hand-holding-dollar ca-hub-tab-icon" aria-hidden="true"></i><?= $GLOBAL['peopleFinanceTabLapsedDonors'] ?>
    </a>
  </li>
</ul>
<div id="pf-tab-<?= $_pfTab ?>" class="pf-active-pane">
  <?php if ($_pfTab === 'members'): ?>
    <?php $_pfRequireIsolated(__DIR__ . '/users_list.php', ['_pfEmbedded' => true]); ?>
  <?php elseif ($_pfTab === 'recap'): ?>
    <?php $_pfRequireIsolated(__DIR__ . '/compta_recap.php', ['_pfEmbedded' => true]); ?>
  <?php elseif ($_pfTab === 'dons'): ?>
    <?php $_pfRequireIsolated(__DIR__ . '/donors_summary.php', ['_pfEmbedded' => true]); ?>
  <?php elseif ($_pfTab === 'lapsed'): ?>
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
      <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members" class="btn btn-outline-light btn-sm">
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

  <?php elseif ($_pfTab === 'lapsedDonors'): ?>
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
  <?php endif ?>
</div>
</div>

<script src="js/hub-tabs.js?v=<?= APP_VERSION ?>"></script>
<script>
// Filter links inside the active pane still point at their own standalone
// route (?view=comptaRecap / ?view=lapsedMembers / ?view=resume) — rewrite
// them to stay inside the hub (sidebar-nav-driven ?view=peopleFinance&tab=...).
caHubRewriteEmbeddedLinks('.pf-active-pane', 'comptaRecap', 'peopleFinance', 'recap');
caHubRewriteEmbeddedLinks('.pf-active-pane', 'lapsedMembers', 'peopleFinance', 'lapsed');
caHubRewriteEmbeddedLinks('.pf-active-pane', 'resume', 'peopleFinance', 'dons');
caHubRewriteEmbeddedLinks('.pf-active-pane', 'lapsedDonors', 'peopleFinance', 'lapsedDonors');

// "Perdus"/"Nouveaux" cohort pills inside the lapsed-members and
// lapsed-donors panes — keep ?cohort= in sync so a direct cohort pill is
// bookmarkable/shareable (dashboard shortcuts link straight to
// ?tab=lapsed&cohort=new, for instance).
caHubEnableTabDeepLink('#pf-cohort-members-lapsed-btn, #pf-cohort-members-new-btn', /^pf-cohort-members-(\w+)-btn$/, 'cohort');
caHubEnableTabDeepLink('#pf-cohort-donors-lapsed-btn, #pf-cohort-donors-new-btn', /^pf-cohort-donors-(\w+)-btn$/, 'cohort');
</script>
