<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Renders the main navigation menu with active-view highlighting.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$view = "list";
if (isset($_REQUEST['view'])) {
    $view = $_REQUEST['view'];
}
$searchString = "";
if (isset ($_REQUEST["searchString"])) {
    $searchString = trim($_REQUEST["searchString"]);
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-2">
    <div class="container">
        <!-- Mobile icon bar — replaces hamburger -->
        <div class="d-flex d-lg-none align-items-center justify-content-between w-100">
            <!-- Left: navigation views -->
            <div class="d-flex align-items-center gap-1">
                <a class="nav-link text-white px-2<?= in_array($view, ['list','']) ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>" title="<?= $GLOBAL['list'] ?>" aria-label="<?= $GLOBAL['list'] ?>">
                    <i class="fas fa-list"></i>
                </a>
                <a class="nav-link text-white px-2<?= $view === 'lastEntryCompta' ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>?view=lastEntryCompta" title="Compta" aria-label="Compta">
                    <i class="fas fa-coins"></i>
                </a>
                <a class="nav-link text-white px-2<?= $view === 'lastEntrySuivi' ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>?view=lastEntrySuivi" title="Suivi" aria-label="Suivi">
                    <i class="fas fa-book-open"></i>
                </a>
                <a class="nav-link text-white px-2<?= $view === 'tasks' ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>?view=tasks" title="<?= $GLOBAL['tasks'] ?>" aria-label="<?= $GLOBAL['tasks'] ?>">
                    <i class="fas fa-list-check"></i>
                </a>
                <a class="nav-link text-white px-2<?= $view === 'resume' ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>?view=resume" title="<?= $GLOBAL['donationOverview'] ?>" aria-label="<?= $GLOBAL['donationOverview'] ?>">
                    <i class="fas fa-chart-pie"></i>
                </a>
            </div>
            <!-- Right: search, settings, user -->
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm text-white border-0 px-2 opacity-75" type="button"
                        id="mobile-search-toggle" aria-label="Rechercher" aria-expanded="false"
                        aria-controls="mobile-search-bar">
                    <i class="fas fa-magnifying-glass"></i>
                </button>
                <?php if (isManager()): ?>
                <a class="nav-link text-white px-2<?= $view === 'settings' ? ' opacity-100' : ' opacity-75' ?>"
                   href="<?= appUrl() ?>?view=settings&tab=groups" title="<?= $GLOBAL['administration'] ?>" aria-label="<?= $GLOBAL['administration'] ?>">
                    <i class="fas fa-gear"></i>
                </a>
                <?php endif ?>
                <?php $__authUser = authUser(); ?>
                <div class="dropdown">
                    <button class="btn btn-sm text-white border-0 px-2 opacity-75" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu utilisateur">
                        <i class="fas fa-circle-user"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
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
                </div>
            </div>
        </div>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item<?= $view == 'list' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>"
                       title="<?= $GLOBAL['manageSegments'] ?>"><i class="fas fa-list"></i> <?= $GLOBAL['list'] ?></a>
                </li>
                <li class="nav-item<?= $view == 'lastEntryCompta' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>?view=lastEntryCompta"><i class="fas fa-coins me-1" aria-hidden="true"></i><?= $GLOBAL['compta'] ?></a>
                </li>
                <?php if (isManager()): ?>
                <li class="nav-item<?= $view == 'comptaRecap' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>?view=comptaRecap"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapTitle'] ?></a>
                </li>
                <?php endif ?>
                <li class="nav-item<?= $view == 'lastEntrySuivi' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>?view=lastEntrySuivi"><i class="fas fa-book-open me-1" aria-hidden="true"></i><?= $GLOBAL['suivi'] ?></a>
                </li>
                <li class="nav-item<?= $view == 'tasks' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>?view=tasks"><i class="fas fa-list-check me-1" aria-hidden="true"></i><?= $GLOBAL['tasks'] ?></a>
                </li>
                <li class="nav-item<?= $view == 'resume' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= appUrl() ?>?view=resume"><i class="fas fa-chart-pie me-1" aria-hidden="true"></i><?= $GLOBAL['donationOverview'] ?></a>
                </li>
            </ul>

            <?php $__authUser = authUser(); ?>

            <!-- Settings cog (right side) -->
            <?php if (isManager()): ?>
            <a class="nav-link text-white my-2 my-lg-0 me-2<?= $view === 'settings' ? ' active' : '' ?>"
               href="<?= appUrl() ?>?view=settings&tab=groups" title="<?= $GLOBAL['administration'] ?>">
                <i class="fas fa-gear"></i>
            </a>
            <?php endif ?>

            <div class="dropdown my-2 my-lg-0 me-2">
              <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= htmlspecialchars($__authUser->display_name, ENT_QUOTES, $charset) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= appUrl() ?>?view=changePassword">Mot de passe</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="post" action="<?= appUrl() ?>" data-no-dirty hx-boost="false">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="dropdown-item text-danger"><?= $GLOBAL['logout'] ?></button>
                  </form>
                </li>
              </ul>
            </div>

            <form class="d-flex gap-2 my-2 my-lg-0" role="search" action="<?= appUrl() ?>" method="get" data-no-dirty id="main-search-form">
                <input type="hidden" name="action" value="search"/>
                <input type="hidden" name="segment" value="<?= FILTER_ALL_EXCEPT_ARCHIVES ?>"/>
                <input class="form-control me-sm-2" id="search" type="search" aria-label="<?= $GLOBAL['search'] ?>" placeholder="<?= $GLOBAL['search'] ?>"
                       name="searchString" value="<?= htmlentities($searchString, ENT_COMPAT, $charset) ?>"
                       autocomplete="off">
            </form>
            <script>
            (function () {
              var inp = document.getElementById('search');
              var frm = document.getElementById('main-search-form');
              if (!inp || !frm) return;
              var timer = null;
              inp.addEventListener('input', function () {
                clearTimeout(timer);
                var val = inp.value;
                if (val.length === 0 || val.length >= 3) {
                  timer = setTimeout(function () { frm.requestSubmit(); }, 400);
                }
              });
              document.addEventListener('keydown', function (e) {
                if (e.key === '/' && document.activeElement !== inp &&
                    !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
                  e.preventDefault();
                  inp.focus();
                  inp.select();
                }
              });
            })();
            </script>
        </div>
    </div>
</nav>

<!-- Mobile search bar — hidden by default, toggled by loupe button -->
<div id="mobile-search-bar" class="d-lg-none bg-primary px-3 pb-2" style="display:none!important">
    <form class="d-flex" role="search" action="<?= appUrl() ?>" method="get" data-no-dirty id="mobile-search-form">
        <input type="hidden" name="action" value="search"/>
        <input type="hidden" name="segment" value="-3"/>
        <input class="form-control form-control-sm me-2" id="mobile-search" type="search" aria-label="Rechercher"
               placeholder="Chercher…" name="searchString"
               value="<?= htmlentities($searchString, ENT_COMPAT, $charset) ?>"
               autocomplete="off" autocorrect="off" autocapitalize="off">
        <button class="btn btn-sm btn-light" type="submit"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></button>
    </form>
</div>
<script>
(function () {
    var btn = document.getElementById('mobile-search-toggle');
    var bar = document.getElementById('mobile-search-bar');
    var inp = document.getElementById('mobile-search');
    if (!btn || !bar) return;
    btn.addEventListener('click', function () {
        var open = bar.style.display !== 'none' && bar.style.display !== '';
        if (open) {
            bar.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        } else {
            bar.style.display = 'block';
            btn.setAttribute('aria-expanded', 'true');
            if (inp) { inp.focus(); inp.select(); }
        }
    });
    // auto-submit on 3+ chars
    if (inp) {
        var frm = document.getElementById('mobile-search-form');
        var timer = null;
        inp.addEventListener('input', function () {
            clearTimeout(timer);
            var val = inp.value;
            if (val.length === 0 || val.length >= 3) {
                timer = setTimeout(function () { frm.requestSubmit(); }, 400);
            }
        });
    }
})();
</script>

