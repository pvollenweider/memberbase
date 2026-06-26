<?php
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
        <a class="navbar-brand" href="<?= $_SERVER['PHP_SELF'] ?>"><i class="fas fa-home"></i> </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item<?= $view == 'list' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>"
                       title="<?= $GLOBAL['manageTeam'] ?>"><i class="fas fa-list"></i> <?= $GLOBAL['list'] ?></a>
                </li>
                <li class="nav-item<?= $view == 'lastEntryCompta' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta"><i class="fas fa-coins me-1" aria-hidden="true"></i>Compta</a>
                </li>
                <li class="nav-item<?= $view == 'lastEntrySuivi' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntrySuivi"><i class="fas fa-book-open me-1" aria-hidden="true"></i>Suivi</a>
                </li>
                <li class="nav-item<?= $view == 'resume' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=resume"><i class="fas fa-chart-pie me-1" aria-hidden="true"></i>Aperçu des dons</a>
                </li>
            </ul>

            <?php $__authUser = authUser(); ?>

            <!-- Settings cog (right side) -->
            <a class="nav-link text-white my-2 my-lg-0 me-2<?= $view === 'settings' ? ' active' : '' ?>"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=groups" title="Administration">
                <i class="fas fa-cog"></i>
            </a>

            <div class="dropdown my-2 my-lg-0 me-2">
              <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= htmlspecialchars($__authUser->display_name, ENT_QUOTES, $charset) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= $_SERVER['PHP_SELF'] ?>?view=changePassword">Mot de passe</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" data-no-dirty hx-boost="false">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="dropdown-item text-danger">Déconnexion</button>
                  </form>
                </li>
              </ul>
            </div>

            <form class="d-flex gap-2 my-2 my-lg-0" role="search" action="<?= $_SERVER['PHP_SELF'] ?>" method="get" data-no-dirty id="main-search-form">
                <input type="hidden" name="action" value="search"/>
                <input type="hidden" name="team" value="-3"/>
                <input class="form-control me-sm-2" id="search" type="search" aria-label="Search" placeholder="Chercher"
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

