<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Fixed left sidebar navigation. Same routes/guards as the previous plain
 * navbar (see git history for menu.php): dashboard, peopleFinance, journals,
 * tasks (isManager), settings (isManager).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Mirrors people_finance.php's own $_pfTab resolution (independent copy: this
// partial renders on every page, not just when view=peopleFinance is active).
$_snPfTab = in_array($_REQUEST['tab'] ?? '', ['members', 'recap', 'lapsed', 'lapsedDonors', 'dons'], true) ? $_REQUEST['tab'] : 'members';
$_snSegmentsActive = in_array($view, ['peopleFinance', 'list'], true) && $_snPfTab === 'members';
$_snJournalsTab    = in_array($_REQUEST['tab'] ?? '', ['compta', 'suivi'], true) ? $_REQUEST['tab'] : 'compta';
$_snJournalComptaActive = ($view === 'journals' && $_snJournalsTab === 'compta') || $view === 'lastEntryCompta';
$_snJournalSuiviActive  = ($view === 'journals' && $_snJournalsTab === 'suivi') || $view === 'lastEntrySuivi';
$_snFinancesActive  = $_snJournalComptaActive || (in_array($view, ['peopleFinance', 'comptaRecap', 'resume'], true) && in_array($_snPfTab, ['recap', 'dons'], true));
$_snEvolutionActive = $view === 'peopleFinance' && in_array($_snPfTab, ['lapsed', 'lapsedDonors'], true);

// Tab resolution for the "Administration" group's active-state highlighting.
$_snSettingsTab = $_REQUEST['tab'] ?? 'groups';
if ($_snSettingsTab === 'teams' || $_snSettingsTab === 'segments') { $_snSettingsTab = 'groups'; }
$_snAdminActive = in_array($view, ['settings', 'updateSegment', 'updateCombinedSegment', 'manageComptaTypes', 'contactTypes', 'manageAppUsers', 'auditLog', 'inactiveUsers'], true);
?>
<div class="ca-sidebar-col" id="ca-sidebar-col"<?= !empty($_snOob) ? ' hx-swap-oob="true"' : '' ?>>
    <?php /* Boosted now: index.php's htmx branch re-renders this whole column
             as an out-of-band swap (id="ca-sidebar-col" + hx-swap-oob) on
             every boosted request, so "active" stays in sync without forcing
             a full reload (the previous fix, before OOB swapping). */ ?>
    <nav class="ca-sidebar-panel d-flex flex-column flex-shrink-0 p-3 bg-body-tertiary">
        <div class="ca-sidebar-body">
            <div class="nav accordion" id="ca-sidebar-accordion">
                <a class="nav-link<?= $view === 'dashboard' ? '' : ' collapsed' ?>" href="<?= appUrl() ?>?view=dashboard">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-gauge"></i></span>
                    <?= $GLOBAL['dashboardPageTitle'] ?>
                </a>
                <a class="nav-link<?= $_snSegmentsActive ? '' : ' collapsed' ?>" href="<?= appUrl() ?>?view=peopleFinance&tab=members">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-users"></i></span>
                    <?= $GLOBAL['peopleFinanceTabMembers'] ?>
                </a>
                <a class="nav-link<?= $_snFinancesActive ? '' : ' collapsed' ?>" href="javascript:void(0);" data-bs-toggle="collapse"
                   data-bs-target="#collapsePfFinances" aria-expanded="<?= $_snFinancesActive ? 'true' : 'false' ?>" aria-controls="collapsePfFinances">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-hand-holding-dollar"></i></span>
                    <?= $GLOBAL['peopleFinanceGroupFinances'] ?>
                    <span class="ca-sidebar-caret"><i class="fas fa-angle-down"></i></span>
                </a>
                <div class="collapse<?= $_snFinancesActive ? ' show' : '' ?>" id="collapsePfFinances">
                    <nav class="ca-sidebar-submenu nav">
                        <a class="nav-link<?= $_snJournalComptaActive ? ' active' : '' ?>" href="<?= appUrl() ?>?view=journals&tab=compta"><?= $GLOBAL['lastEntryCompta'] ?></a>
                        <?php if (isManager()): ?>
                        <a class="nav-link<?= $_snPfTab === 'recap' && $view === 'peopleFinance' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&tab=recap"><?= $GLOBAL['peopleFinanceTabRecap'] ?></a>
                        <?php endif ?>
                        <a class="nav-link<?= $_snPfTab === 'dons' && $view === 'peopleFinance' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&tab=dons"><?= $GLOBAL['peopleFinanceTabDons'] ?></a>
                    </nav>
                </div>

                <a class="nav-link<?= $_snEvolutionActive ? '' : ' collapsed' ?>" href="javascript:void(0);" data-bs-toggle="collapse"
                   data-bs-target="#collapsePfEvolution" aria-expanded="<?= $_snEvolutionActive ? 'true' : 'false' ?>" aria-controls="collapsePfEvolution">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-arrow-right-arrow-left"></i></span>
                    <?= $GLOBAL['peopleFinanceGroupEvolution'] ?>
                    <span class="ca-sidebar-caret"><i class="fas fa-angle-down"></i></span>
                </a>
                <div class="collapse<?= $_snEvolutionActive ? ' show' : '' ?>" id="collapsePfEvolution">
                    <nav class="ca-sidebar-submenu nav">
                        <a class="nav-link<?= $_snPfTab === 'lapsed' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&tab=lapsed"><?= $GLOBAL['peopleFinanceTabLapsed'] ?></a>
                        <a class="nav-link<?= $_snPfTab === 'lapsedDonors' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=peopleFinance&tab=lapsedDonors"><?= $GLOBAL['peopleFinanceTabLapsedDonors'] ?></a>
                    </nav>
                </div>
                <a class="nav-link<?= $_snJournalSuiviActive ? '' : ' collapsed' ?>" href="<?= appUrl() ?>?view=journals&tab=suivi">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-book-open"></i></span>
                    <?= $GLOBAL['lastEntrySuivi'] ?>
                </a>

                <?php if (isManager()): ?>
                <a class="nav-link<?= $view === 'tasks' ? '' : ' collapsed' ?>" href="<?= appUrl() ?>?view=tasks">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-list-check"></i></span>
                    <?= $GLOBAL['tasks'] ?>
                    <?php if ($_navOpenTaskCount > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1"><?= $_navOpenTaskCount ?></span>
                    <?php endif ?>
                </a>
                <?php endif ?>
                <?php if (isManager()): ?>
                <a class="nav-link<?= $_snAdminActive ? '' : ' collapsed' ?>" href="javascript:void(0);" data-bs-toggle="collapse"
                   data-bs-target="#collapseAdmin" aria-expanded="<?= $_snAdminActive ? 'true' : 'false' ?>" aria-controls="collapseAdmin">
                    <span class="ca-sidebar-link-icon"><i class="fas fa-gear"></i></span>
                    <?= $GLOBAL['administration'] ?>
                    <span class="ca-sidebar-caret"><i class="fas fa-angle-down"></i></span>
                </a>
                <div class="collapse<?= $_snAdminActive ? ' show' : '' ?>" id="collapseAdmin">
                    <nav class="ca-sidebar-submenu nav">
                        <?php
                        // Two sub-groups, split by role rather than technical theme: everything
                        // a manager can already reach (Segments) vs. admin-only settings (the old
                        // Application/Diagnostics split was two admin-only groups side by side —
                        // same role, no reason to keep them apart).
                        $_snAdminSegmentsActive = $_snAdminActive && in_array($_snSettingsTab, ['groups', 'categories', 'filters', 'compta'], true);
                        $_snAdminAppActive      = $_snAdminActive && in_array($_snSettingsTab, ['settings', 'email', 'users', 'health', 'contactTypes', 'audit', 'integrity'], true) || $view === 'inactiveUsers';
                        ?>
                        <a class="nav-link<?= $_snAdminSegmentsActive ? '' : ' collapsed' ?>" href="javascript:void(0);" data-bs-toggle="collapse"
                           data-bs-target="#collapseAdminSegments" aria-expanded="<?= $_snAdminSegmentsActive ? 'true' : 'false' ?>" aria-controls="collapseAdminSegments">
                            <?= $GLOBAL['adminGroupSegments'] ?>
                            <span class="ca-sidebar-caret"><i class="fas fa-angle-down"></i></span>
                        </a>
                        <div class="collapse<?= $_snAdminSegmentsActive ? ' show' : '' ?>" id="collapseAdminSegments">
                            <nav class="ca-sidebar-submenu nav">
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'groups' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=groups"><?= $GLOBAL['groups'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'filters' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=filters"><?= $GLOBAL['combinedSegments'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'categories' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=categories"><?= $GLOBAL['categories'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'compta' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=compta"><?= $GLOBAL['comptaTypes'] ?></a>
                            </nav>
                        </div>

                        <?php if (isAdmin()): ?>
                        <a class="nav-link<?= $_snAdminAppActive ? '' : ' collapsed' ?>" href="javascript:void(0);" data-bs-toggle="collapse"
                           data-bs-target="#collapseAdminApp" aria-expanded="<?= $_snAdminAppActive ? 'true' : 'false' ?>" aria-controls="collapseAdminApp">
                            <?= $GLOBAL['adminGroupApplication'] ?>
                            <span class="ca-sidebar-caret"><i class="fas fa-angle-down"></i></span>
                        </a>
                        <div class="collapse<?= $_snAdminAppActive ? ' show' : '' ?>" id="collapseAdminApp">
                            <nav class="ca-sidebar-submenu nav">
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'settings' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=settings"><?= $GLOBAL['settings'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'contactTypes' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=contactTypes"><?= $GLOBAL['contactTypesTitle'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'email' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=email"><?= $GLOBAL['smtpSettings'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'users' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=users"><?= $GLOBAL['users'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'health' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=health"><?= $GLOBAL['health'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'audit' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=audit"><?= $GLOBAL['journal'] ?></a>
                                <a class="nav-link<?= $_snAdminActive && $_snSettingsTab === 'integrity' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=settings&tab=integrity"><?= $GLOBAL['integrity'] ?></a>
                                <a class="nav-link<?= $view === 'inactiveUsers' ? ' active' : '' ?>" href="<?= appUrl() ?>?view=inactiveUsers"><?= $GLOBAL['archived'] ?></a>
                            </nav>
                        </div>
                        <?php endif ?>
                    </nav>
                </div>
                <?php endif ?>
            </div>
        </div>
        <div class="ca-sidebar-footer dropup">
            <a href="javascript:void(0);" class="ca-sidebar-footer-toggle d-flex align-items-center text-decoration-none dropdown-toggle"
               id="sidebarUserMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-circle-user fa-lg me-2" aria-hidden="true"></i>
                <span class="flex-grow-1 text-truncate">
                    <span class="ca-sidebar-footer-org d-block"><?= htmlspecialchars($appSettings['org_name'] ?? '', ENT_QUOTES, $charset) ?></span>
                    <span class="ca-sidebar-footer-name d-block"><?= htmlspecialchars($__authUser->display_name, ENT_QUOTES, $charset) ?></span>
                </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end w-100" aria-labelledby="sidebarUserMenu">
                <li><a class="dropdown-item" href="<?= appUrl() ?>?view=changePassword"><i class="fas fa-user-gear me-2" aria-hidden="true"></i><?= $GLOBAL['myAccountLabel'] ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= appUrl() ?>" data-no-dirty hx-boost="false">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-right-from-bracket me-2" aria-hidden="true"></i><?= $GLOBAL['logout'] ?></button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>
</div>
