<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Fixed top bar — sidebar toggle, brand, search, and user/settings menus.
 * Mirrors the previous plain navbar's search box and dropdowns, adapted to
 * the sidebar layout.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$view = $_REQUEST['view'] ?? 'list';
$searchString = isset($_REQUEST['searchString']) ? trim($_REQUEST['searchString']) : '';
$_navOpenTaskCount = isManager() ? SuiviTask::openCount() : 0;
$__authUser = authUser();
?>
<?php /* Boosted now: index.php's htmx branch re-renders this whole nav as an
         out-of-band swap (id="ca-topbar" + hx-swap-oob) on every boosted
         request, so the "active" settings-gear state stays in sync without
         forcing a full reload (the previous fix, before OOB swapping). */ ?>
<nav class="ca-topbar" id="ca-topbar"<?= !empty($_snOob) ? ' hx-swap-oob="true"' : '' ?>>
    <button class="ca-icon-btn" type="button" id="sidebarToggle" aria-label="<?= $GLOBAL['toggleSidebar'] ?>">
        <i class="fas fa-bars"></i>
    </button>
    <a class="ca-brand" href="<?= appUrl() ?>?view=dashboard"><?= htmlspecialchars($appSettings['org_name'] ?: 'MemberBase', ENT_QUOTES, $charset) ?></a>

    <form class="ca-topbar-search me-auto d-none d-lg-block" role="search" action="<?= appUrl() ?>" method="get" data-no-dirty id="main-search-form">
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

    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <?php if ($_navOpenTaskCount > 0 && isManager()): ?>
        <li class="nav-item me-2">
            <a class="nav-link position-relative" href="<?= appUrl() ?>?view=tasks" title="<?= $GLOBAL['tasks'] ?>">
                <i class="fas fa-list-check"></i>
                <span class="badge rounded-pill bg-danger ms-1"><?= $_navOpenTaskCount ?></span>
            </a>
        </li>
        <?php endif ?>
        <?php if (isManager()): ?>
        <li class="nav-item me-2">
            <a class="nav-link<?= $view === 'settings' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=groups" title="<?= $GLOBAL['administration'] ?>">
                <i class="fas fa-gear"></i>
            </a>
        </li>
        <?php endif ?>
    </ul>
</nav>
