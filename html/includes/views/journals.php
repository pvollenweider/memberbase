<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * "Journaux" hub — tab shell over the two global activity logs that used
 * to be separate destinations: journal compta (compta_last_entry.php) and
 * journal suivi (suivi_last_entry.php). Same pattern as the "Membres &
 * finances" hub (#164): each tab reuses its source view unchanged, isolated
 * in its own closure scope since Bootstrap tabs render every pane
 * server-side (both views' PHP runs in the same request).
 *
 * Open to every logged-in role, same as the two routes it replaces.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_jhTab = in_array($_REQUEST['tab'] ?? '', ['compta', 'suivi'], true) ? $_REQUEST['tab'] : 'compta';
$_jhPane = fn(string $tab): string => $_jhTab === $tab ? ' show active' : '';

$_jhRequireIsolated = function (string $file, array $vars = []) use ($GLOBAL, $charset, $appSettings, $comptaTypes, $pdo) {
    extract($vars);
    require $file;
};
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['journalsPageTitle'] ?></h1>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_jhTab === 'compta' ? ' active' : '' ?>" id="jh-tab-compta-btn"
            data-bs-toggle="tab" data-bs-target="#jh-tab-compta" type="button" role="tab"
            aria-controls="jh-tab-compta" aria-selected="<?= $_jhTab === 'compta' ? 'true' : 'false' ?>">
      <i class="fas fa-coins me-1" aria-hidden="true"></i><?= $GLOBAL['lastEntryCompta'] ?>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link<?= $_jhTab === 'suivi' ? ' active' : '' ?>" id="jh-tab-suivi-btn"
            data-bs-toggle="tab" data-bs-target="#jh-tab-suivi" type="button" role="tab"
            aria-controls="jh-tab-suivi" aria-selected="<?= $_jhTab === 'suivi' ? 'true' : 'false' ?>">
      <i class="fas fa-book-open me-1" aria-hidden="true"></i><?= $GLOBAL['lastEntrySuivi'] ?>
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade<?= $_jhPane('compta') ?>" id="jh-tab-compta" role="tabpanel" aria-labelledby="jh-tab-compta-btn">
    <?php $_jhRequireIsolated(__DIR__ . '/compta_last_entry.php'); ?>
  </div>

  <div class="tab-pane fade<?= $_jhPane('suivi') ?>" id="jh-tab-suivi" role="tabpanel" aria-labelledby="jh-tab-suivi-btn">
    <?php $_jhRequireIsolated(__DIR__ . '/suivi_last_entry.php'); ?>
  </div>
</div>

<script src="js/hub-tabs.js?v=<?= APP_VERSION ?>"></script>
<script>
// If the page lands on the Suivi tab (?tab=suivi) the Compta tab's
// DataTable initializes while hidden and mis-measures column widths.
document.getElementById('jh-tab-compta-btn')?.addEventListener('shown.bs.tab', function () {
  var t = window.jQuery && jQuery.fn.DataTable.isDataTable('.export') ? jQuery('.export').DataTable() : null;
  if (t) { t.columns.adjust(); }
});

// The Compta tab's year/type filters still point at its own standalone
// route (?view=lastEntryCompta) — rewrite them to stay inside the hub, and
// keep ?tab= in sync when switching tabs so the current tab is linkable.
caHubRewriteEmbeddedLinks('#jh-tab-compta', 'lastEntryCompta', 'journals', 'compta');
caHubEnableTabDeepLink('#jh-tab-compta-btn, #jh-tab-suivi-btn', /^jh-tab-(\w+)-btn$/);
</script>
