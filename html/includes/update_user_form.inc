<?php
if ($userid == -1) {
    if (isset($_REQUEST['userid'])) {
        $userid = $_REQUEST['userid'];
    } else {
        $userid = $_REQUEST['id'];
    }
}
$user = new User();
$user->lookupUser($userid);
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
    <a class="navbar-brand" href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;userid=<?= $user->getId() ?>"><?= htmlentities($user->getSociety(), ENT_COMPAT, $charset) ?> <?= htmlentities($user->getFirstName(), ENT_COMPAT, $charset) ?> <?= htmlentities($user->getLastName(), ENT_COMPAT, $charset) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto">
            <li class="nav-item<?php if ($view == "generalData") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;userid=<?= $user->getId() ?>"><i class="far fa-id-card"></i> <?= $GLOBAL['generalData'] ?></a>
            </li>
            <li class="nav-item<?php if ($view == "compta") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= $user->getId() ?>"><i class="fas fa-file-contract"></i> <?= $GLOBAL['compta'] ?></a>
            </li>
            <li class="nav-item<?php if ($view == "suivi") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= $user->getId() ?>"><i class="far fa-list-alt"></i> <?= $GLOBAL['suivi'] ?></a>
            </li>
        </ul>
    </div>
</nav>

<?php
if ($view == "compta") {

    //include "avocat.inc";
    //include "cd.inc";
    //include "coti.inc";
    //include "dons.inc";
    //include "reintegration.inc";
    include "compta_generic.inc";
} else if ($view == "suivi") {
    ?><?php include "suivi.inc"; ?><?php
} else {
    ?>
    <div class="row">
        <div class="col-md-8">
            <?php include "generalData.inc"; ?>
        </div>
        <div class="col-md-4 small">
            <?php include "memberOf.inc"; ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 d-flex justify-content-end">
            <a href="<?=$_SERVER['PHP_SELF']?>?view=deleteUser&amp;id=<?=$user->getId()?>" class="btn btn-danger"> <i class="fas fa-user-times l"></i> Delete user</a>
        </div>
    </div>
    <?php
}


?>
