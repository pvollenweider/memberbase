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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $view == 'lastEntryCompta' ? ' active' : '' ?><?= $view == 'lastEntrySuivi' ? ' active' : '' ?><?= $view == 'resume' ? ' active' : '' ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-chart-line"></i> <?= $GLOBAL['lastEntry'] ?>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item<?= $view == 'lastEntryCompta' ? ' active' : '' ?>" href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta"><?= $GLOBAL['lastEntryCompta'] ?></a>
                        <a class="dropdown-item<?= $view == 'lastEntrySuivi' ? ' active' : '' ?>" href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntrySuivi"><?= $GLOBAL['lastEntrySuivi'] ?></a>
                        <a class="dropdown-item<?= $view == 'resume' ? ' active' : '' ?>" href="<?= $_SERVER['PHP_SELF'] ?>?view=resume">Contributions</a>
                    </div>
                </li>
                <li class="nav-item<?= in_array($view, ['manageTeam','manageMetagroups','updateMetagroup','updateTeam']) ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=manageTeam"
                       title="Groupes"><i class="fas fa-users"></i> Groupes</a>
                </li>
                <li class="nav-item<?= $view == 'settings' ? ' active' : '' ?>">
                    <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings" title="Réglages"><i class="fas fa-cog"></i></a>
                </li>
            </ul>
            <form class="d-flex gap-2 my-2 my-lg-0" role="search" action="<?= $_SERVER['PHP_SELF'] ?>" method="get">
                <input type="hidden" name="action" value="search"/>
                <input type="hidden" name="team" value="-3"/>
                <input class="form-control me-sm-2" id="search" type="search" aria-label="Search" placeholder="Chercher"
                       name="searchString" value="<?= htmlentities($searchString, ENT_COMPAT, $charset) ?>">
            </form>
        </div>
    </div>
</nav>

