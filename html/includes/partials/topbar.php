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
<?php /* hx-boost="false": the topbar is rendered once on full page load and
         lives outside #main-content, so a boosted click here would swap the
         content but leave the settings-gear "active" state stale — force a
         real navigation so the topbar re-renders correctly. */ ?>
<nav class="ca-topbar" hx-boost="false">
    <button class="ca-icon-btn" type="button" id="sidebarToggle" aria-label="<?= $GLOBAL['toggleSidebar'] ?>">
        <i class="fas fa-bars"></i>
    </button>
    <a class="ca-brand" href="<?= appUrl() ?>?view=dashboard"><?= htmlspecialchars($appSettings['org_name'] ?: 'MemberBase', ENT_QUOTES, $charset) ?></a>

    <form class="ca-topbar-search me-auto d-none d-lg-block" role="search" action="<?= appUrl() ?>" method="get" data-no-dirty id="main-search-form">
        <input type="hidden" name="action" value="search"/>
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
        <li class="nav-item dropdown">
            <a class="nav-link" id="navbarDropdownUserImage" href="javascript:void(0);" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-circle-user fa-lg"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUserImage">
                <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars($__authUser->display_name, ENT_QUOTES, $charset) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= appUrl() ?>?view=changePassword"><?= $GLOBAL['changePassword'] ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= appUrl() ?>" data-no-dirty hx-boost="false">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="dropdown-item text-danger"><?= $GLOBAL['logout'] ?></button>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>
