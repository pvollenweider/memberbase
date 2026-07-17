<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Fixed top bar — sidebar toggle, brand, and search. Tasks badge and
 * settings gear moved out (both duplicated the always-present sidebar
 * entries) — search now takes their place on the right.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// $view and $_navOpenTaskCount are no longer read in this file itself, but
// sidebar_nav.php depends on both without computing them itself — topbar.php
// is included first in the normal (non-OOB) render path, so this is their
// only source there.
$view = $_REQUEST['view'] ?? 'list';
$searchString = isset($_REQUEST['searchString']) ? trim($_REQUEST['searchString']) : '';
$_navOpenTaskCount = isManager() ? SuiviTask::openCount() : 0;
$__authUser = authUser();
?>
<?php /* Boosted now: index.php's htmx branch re-renders this whole nav as an
         out-of-band swap (id="ca-topbar" + hx-swap-oob) on every boosted
         request, so it stays in sync without forcing a full reload (the
         previous fix, before OOB swapping). */ ?>
<nav class="ca-topbar" id="ca-topbar"<?= !empty($_snOob) ? ' hx-swap-oob="true"' : '' ?>>
    <button class="ca-icon-btn" type="button" id="sidebarToggle" aria-label="<?= $GLOBAL['toggleSidebar'] ?>">
        <i class="fas fa-bars"></i>
    </button>
    <a class="ca-brand" href="<?= appUrl() ?>?view=dashboard"><?= htmlspecialchars($appSettings['org_name'] ?: 'MemberBase', ENT_QUOTES, $charset) ?></a>

    <form class="ca-topbar-search ms-auto d-none d-lg-block" role="search" action="<?= appUrl() ?>" method="get" data-no-dirty id="main-search-form">
        <input type="hidden" name="action" value="search"/>
        <input type="hidden" name="view" value="peopleFinance"/>
        <input type="hidden" name="tab" value="members"/>
        <input type="hidden" name="segment" value="<?= FILTER_ALL_EXCEPT_ARCHIVES ?>"/>
        <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-magnifying-glass"></i></span>
            <input class="form-control border-start-0" id="search" type="search" placeholder="<?= $GLOBAL['search'] ?>" aria-label="<?= $GLOBAL['search'] ?>"
                   name="searchString" value="<?= htmlentities($searchString, ENT_COMPAT, $charset) ?>" autocomplete="off">
        </div>
    </form>
</nav>
