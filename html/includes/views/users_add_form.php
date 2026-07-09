<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for searching and adding a new member.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$searchString = "";
if (isset($_REQUEST["searchString"])) {
    $searchString = trim($_REQUEST["searchString"]);
}
$fromTeam     = (int)($_REQUEST['fromTeam'] ?? 0);
$fromTeamName = '';
if ($fromTeam > 0) {
    $_ft = $pdo->prepare("SELECT name FROM segment WHERE id = ?");
    $_ft->execute([$fromTeam]);
    $fromTeamName = (string)($_ft->fetchColumn() ?: '');
    unset($_ft);
}
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-9 col-lg-7">

    <h6 class="form-section-title" style="margin-top:0"><?= $GLOBAL['addUser'] ?></h6>

    <form action="<?= $_SERVER['PHP_SELF'] ?>?action=addUser&amp;view=updateUser" method="post" id="addUser">
      <?php if ($fromTeam > 0): ?>
      <input type="hidden" name="fromTeam" value="<?= $fromTeam ?>">
      <?php endif ?>

      <p class="form-section-title"><?= $GLOBAL['contactInfo'] ?></p>

      <div class="row mb-2 align-items-center">
        <label for="society" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['society'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="society" name="society" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['society'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="lastName" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['lastName'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="lastName" name="lastName" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['lastName'], ENT_COMPAT, $charset) ?>"
                 value="<?= htmlentities(ucfirst($searchString), ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="firstName" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['firstName'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="firstName" name="firstName" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['firstName'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="sexe" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['sexe'] ?></label>
        <div class="col-8 col-sm-9">
          <select id="sexe" name="sexe" class="form-select form-select-sm">
            <option value="na"><?= $GLOBAL['na'] ?></option>
            <option value="hf"><?= $GLOBAL['hf'] ?></option>
            <option value="f"><?= $GLOBAL['f'] ?></option>
            <option value="m"><?= $GLOBAL['m'] ?></option>
          </select>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="title" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['title'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="title" name="title" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['title'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="address" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['address'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="address" name="address" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['address'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="npa" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['npa'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="npa" name="npa" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['npa'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="email" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-envelope" aria-hidden="true"></i> <?= $GLOBAL['email'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="email" name="email" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['email'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="emailAlt" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-envelope" aria-hidden="true"></i> E-mail alt.
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="emailAlt" name="emailAlt" class="form-control form-control-sm"
                 placeholder="Adresse alternative / historique"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="web" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-globe" aria-hidden="true"></i> <?= $GLOBAL['web'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="web" name="web" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['web'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>

      <p class="form-section-title"><?= $GLOBAL['additionalInfo'] ?></p>

      <div class="row mb-2 align-items-center">
        <label for="telProf" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['telProf'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="telProf" name="telProf" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['telProf'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="tel" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-phone" aria-hidden="true"></i> <?= $GLOBAL['tel'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="tel" name="tel" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['tel'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="portable" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-mobile-screen-button" aria-hidden="true"></i> <?= $GLOBAL['portable'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="portable" name="portable" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['portable'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="fax" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem">
          <i class="fas fa-print" aria-hidden="true"></i> <?= $GLOBAL['fax'] ?>
        </label>
        <div class="col-8 col-sm-9">
          <input type="text" id="fax" name="fax" class="form-control form-control-sm"
                 placeholder="<?= htmlentities($GLOBAL['fax'], ENT_COMPAT, $charset) ?>"/>
        </div>
      </div>
      <div class="row mb-2 align-items-center">
        <label for="birthDay" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['birthDay'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" id="birthDay" name="birthDay" class="form-control form-control-sm datepicker"
                 placeholder="01/01/1970"/>
        </div>
      </div>
      <div class="row mb-3 align-items-center">
        <label for="comment" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end" style="font-size:0.82rem"><?= $GLOBAL['compet'] ?></label>
        <div class="col-8 col-sm-9">
          <textarea id="comment" rows="4" name="comment" class="form-control form-control-sm"
                    placeholder="<?= htmlentities($GLOBAL['compet'], ENT_COMPAT, $charset) ?>"></textarea>
        </div>
      </div>

      <?php if ($fromTeam > 0 && $fromTeamName): ?>
      <div class="row mb-3">
        <div class="col-8 offset-4 col-sm-9 offset-sm-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="addToFromTeam" name="addToFromTeam" value="1">
            <label class="form-check-label" for="addToFromTeam" style="font-size:0.85rem">
              Ajouter au segment <strong><?= htmlspecialchars($fromTeamName, ENT_QUOTES, $charset) ?></strong>
            </label>
          </div>
        </div>
      </div>
      <?php endif ?>

      <div class="row">
        <div class="col-8 offset-4 col-sm-9 offset-sm-3">
          <button type="submit" class="btn btn-success btn-sm"><?= $GLOBAL['add'] ?></button>
        </div>
      </div>

    </form>
  </div>
</div>
