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
            <?php if (isAdmin()): ?>
            <li class="nav-item<?php if ($view == "userHistory") { ?> active<?php } ?>">
                <a class="nav-link" href="<?= $_SERVER['PHP_SELF'] ?>?view=userHistory&amp;userid=<?= $user->getId() ?>"><i class="fas fa-history"></i> Historique</a>
            </li>
            <?php endif ?>
        </ul>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex align-items-center ms-auto" data-no-dirty id="status-toggle-form">
          <input type="hidden" name="action"   value="<?= $user->status ? 'deactivateUser' : 'reactivateUser' ?>">
          <input type="hidden" name="id"      value="<?= (int)$user->getId() ?>">
          <input type="hidden" name="redirect" value="updateUser">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="status-toggle"
                   <?= $user->status ? 'checked' : '' ?>
                   onchange="if(!this.checked && !confirm('Désactiver ce membre ?')){this.checked=true;return;}document.getElementById('status-toggle-form').submit()">
            <label class="form-check-label" for="status-toggle"><?= $user->status ? 'Actif' : 'Inactif' ?></label>
          </div>
        </form>
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
} else if ($view == "userHistory") {
    ?><?php include "user_history.inc"; ?><?php
} else {
    ?>
    <?php if (!$user->status): ?>
    <div class="ca-inactive-banner alert alert-warning d-flex align-items-center gap-2 mb-3 py-2" role="alert">
      <i class="fas fa-eye-slash" aria-hidden="true"></i>
      <span>Ce profil est <strong>désactivé</strong> — il n'apparaît dans aucune liste.</span>
    </div>
    <?php endif ?>
    <div class="position-relative <?= !$user->status ? 'ca-inactive-wrap' : '' ?>">
      <?php if (!$user->status): ?>
      <div class="ca-inactive-overlay" aria-hidden="true"></div>
      <?php endif ?>
      <div class="row">
          <div class="col-md-8">
              <?php include "generalData.inc"; ?>
          </div>
          <div class="col-md-4 small">
              <?php include "memberOf.inc"; ?>
          </div>
      </div>
    </div>
    <?php
    $_stCompta = $pdo->prepare("SELECT COUNT(*) FROM compta WHERE user_id=?");
    $_stCompta->execute([$user->getId()]);
    $_hasCompta = (int)$_stCompta->fetchColumn() > 0;
    ?>
    <div class="row mt-3">
      <div class="col-md-12 d-flex align-items-center justify-content-end gap-3">

        <?php if (!$user->status): ?>
          <?php if ($_hasCompta): ?>
          <!-- Has compta → offer anonymize, not delete -->
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=anonymizeUser&amp;id=<?= (int)$user->getId() ?>"
             class="btn btn-outline-danger btn-sm"
             title="Ce profil a des données comptables — la suppression est impossible. L'anonymisation efface les données personnelles tout en conservant l'historique comptable.">
            <i class="fas fa-user-secret me-1" aria-hidden="true"></i>Anonymiser
          </a>
          <?php else: ?>
          <!-- No compta → allow delete -->
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=deleteUser&amp;id=<?= (int)$user->getId() ?>"
             class="btn btn-danger btn-sm">
            <i class="fas fa-user-times me-1" aria-hidden="true"></i>Supprimer
          </a>
          <?php endif ?>
        <?php endif ?>

      </div>
    </div>
    <?php
}


?>
