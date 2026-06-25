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

$comptaid = $_REQUEST['comptaid'];
$compta = new Compta();
$compta->lookupCompta($comptaid);
$typeId = $compta->getTypeId();
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-7 col-lg-5">

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <h6 class="text-muted mb-0" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em">
        Modifier entrée compta
      </h6>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= $user->getId() ?>"
         class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= htmlentities($user->getFirstName(), ENT_COMPAT, $charset) ?> <?= htmlentities($user->getLastName(), ENT_COMPAT, $charset) ?>
      </a>
    </div>

    <form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" name="updateCompta" method="post">
      <input type="hidden" name="comptaid" value="<?= $compta->getId() ?>"/>
      <input type="hidden" name="action"   value="updateCompta"/>
      <input type="hidden" name="view"     value="compta"/>
      <input type="hidden" name="userid"   value="<?= htmlentities($_REQUEST['userid'], ENT_COMPAT, $charset) ?>"/>

      <div class="row mb-2 align-items-center">
        <label for="type" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['type'] ?></label>
        <div class="col-sm-9">
          <select name="type_id" class="form-select form-select-sm" id="type_id">
            <?php foreach ($comptaTypes as $ct): ?>
            <option value="<?= (int)$ct->id ?>" <?= $typeId == $ct->id ? 'selected' : '' ?>>
              <?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="date" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['date'] ?></label>
        <div class="col-sm-9">
          <input type="text" value="<?= timeStampToformatedDate($compta->getDate()) ?>"
                 class="form-control form-control-sm datepicker" id="date" name="date">
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="libele" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['libele'] ?></label>
        <div class="col-sm-9">
          <input type="text" class="form-control form-control-sm" id="libele" name="libele"
                 value="<?= htmlentities($compta->getLibele(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="sum" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['sum'] ?></label>
        <div class="col-sm-5">
          <input type="text" class="form-control form-control-sm" id="sum" name="sum"
                 value="<?= htmlentities($compta->getSum(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div class="row mb-3 align-items-center">
        <label for="quittance" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['quittance'] ?></label>
        <div class="col-sm-9">
          <input type="text" class="form-control form-control-sm" id="quittance" name="quittance"
                 value="<?= htmlentities($compta->getQuittance(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div class="row">
        <div class="col-sm-9 offset-sm-3 d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?userid=<?= $user->getId() ?>&amp;view=removeCompta&amp;comptaid=<?= $comptaid ?>"
             class="btn btn-outline-danger btn-sm">
            <i class="fas fa-times me-1" aria-hidden="true"></i>Supprimer
          </a>
        </div>
      </div>

    </form>
  </div>
</div>
