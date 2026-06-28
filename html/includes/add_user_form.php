<?php
$searchString = "";
if (isset($_REQUEST["searchString"])) {
    $searchString = trim($_REQUEST["searchString"]);
}
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-9 col-lg-7">

    <h6 class="form-section-title" style="margin-top:0"><?= $GLOBAL['addUser'] ?></h6>

    <form action="<?= $_SERVER['PHP_SELF'] ?>?action=addUser&amp;view=updateUser" method="post" id="addUser">

      <p class="form-section-title">Coordonnées</p>

      <div class="row mb-2">
        <label for="society" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['society'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="society" name="society" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['society'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="lastName" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['lastName'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="lastName" name="lastName" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['lastName'], ENT_COMPAT, $charset) ?>"
                 value="<?= htmlentities(ucfirst($searchString), ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="firstName" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['firstName'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="firstName" name="firstName" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['firstName'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="sexe" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['sexe'] ?></label>
        <div class="col-sm-9">
          <select id="sexe" name="sexe" class="form-select form-select-sm">
            <option value="na"><?= $GLOBAL['na'] ?></option>
            <option value="hf"><?= $GLOBAL['hf'] ?></option>
            <option value="f"><?= $GLOBAL['f'] ?></option>
            <option value="m"><?= $GLOBAL['m'] ?></option>
          </select>
        </div>
      </div>
      <div class="row mb-2">
        <label for="title" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['title'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="title" name="title" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['title'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="address" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['address'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="address" name="address" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['address'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="npa" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['npa'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="npa" name="npa" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['npa'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="email" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-envelope" aria-hidden="true"></i> <?= $GLOBAL['email'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="email" name="email" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['email'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="web" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-globe" aria-hidden="true"></i> <?= $GLOBAL['web'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="web" name="web" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['web'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>

      <p class="form-section-title">Infos complémentaires</p>

      <div class="row mb-2">
        <label for="telProf" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['telProf'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="telProf" name="telProf" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['telProf'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="tel" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['tel'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="tel" name="tel" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['tel'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="portable" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-mobile-alt" aria-hidden="true"></i> <?= $GLOBAL['portable'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="portable" name="portable" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['portable'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="fax" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">
          <i class="fas fa-print" aria-hidden="true"></i> <?= $GLOBAL['fax'] ?>
        </label>
        <div class="col-sm-9">
          <input type="text" id="fax" name="fax" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['fax'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2">
        <label for="birthDay" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['birthDay'] ?></label>
        <div class="col-sm-9">
          <div class="input-group input-group-sm" id="datetimepicker1">
            <input type="text" id="birthDay" name="birthDay" class="form-control datepicker"
                   placeholder="01/01/1970"/>
          </div>
        </div>
      </div>
      <div class="row mb-3">
        <label for="comment" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['compet'] ?></label>
        <div class="col-sm-9">
          <textarea id="comment" rows="4" name="comment" class="form-control form-control-sm"
                    placeholder="<?= htmlentities($GLOBAL['compet'], ENT_COMPAT, $charset) ?>"></textarea>
        </div>
      </div>

      <div class="row">
        <div class="col-sm-9 offset-sm-3">
          <button type="submit" class="btn btn-success btn-sm"><?= $GLOBAL['add'] ?></button>
        </div>
      </div>

    </form>
  </div>
</div>
