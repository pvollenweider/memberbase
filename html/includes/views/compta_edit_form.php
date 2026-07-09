<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for creating or editing an accounting entry.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if ($userid == -1) {
    if (isset($_REQUEST['userid'])) {
        $userid = $_REQUEST['userid'];
    } else {
        $userid = $_REQUEST['id'];
    }
}
$user = new Contact();
$user->lookupUser($userid);

$comptaid = $_REQUEST['comptaid'];
$compta = new Compta();
$compta->lookupCompta($comptaid);
$typeId = $compta->getTypeId();
$_isCotiType = isset($comptaTypes[(int)$typeId]) && (int)$comptaTypes[(int)$typeId]->is_cotisation === 1;
$_cotiTypeIdsEdit = array_values(array_map('intval',
    array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1))
));
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-7 col-lg-5">

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <h6 class="text-muted mb-0" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em">
        <?= $GLOBAL['editCompta'] ?>
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
        <label for="type" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['type'] ?></label>
        <div class="col-8 col-sm-9">
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
        <label for="date" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['date'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" value="<?= timeStampToformatedDate($compta->getDate()) ?>"
                 class="form-control form-control-sm datepicker" id="date" name="date">
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="libele" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['libele'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" class="form-control form-control-sm" id="libele" name="libele"
                 value="<?= htmlentities($compta->getLibele(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="sum" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['sum'] ?></label>
        <div class="col-8 col-sm-5">
          <input type="text" class="form-control form-control-sm" id="sum" name="sum"
                 inputmode="decimal" pattern="^[0-9]+([.,][0-9]+)?$" title="<?= $GLOBAL['numericAmountHint'] ?>"
                 required oninvalid="this.setCustomValidity(this.validity.valueMissing ? <?= json_encode($GLOBAL['sumRequired']) ?> : <?= json_encode($GLOBAL['numericAmountHint']) ?>)"
                 oninput="this.setCustomValidity('')"
                 value="<?= htmlentities($compta->getSum(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div class="row mb-2 align-items-center">
        <label for="quittance" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['quittance'] ?></label>
        <div class="col-8 col-sm-9">
          <input type="text" class="form-control form-control-sm" id="quittance" name="quittance"
                 value="<?= htmlentities($compta->getQuittance(), ENT_COMPAT, $charset) ?>">
        </div>
      </div>

      <div id="ca-coti-year-row" class="row mb-2 align-items-center"<?= $_isCotiType ? '' : ' style="display:none"' ?>>
        <label for="cotisation_year" class="col-4 col-sm-3 col-form-label col-form-label-sm text-end text-sm-end" style="font-size:0.82rem"><?= $GLOBAL['cotisationYearLabel'] ?></label>
        <div class="col-4 col-sm-3">
          <?php
          $_ceSelYear = $compta->getCotisationYear() ?? (int)date('Y', (int)$compta->getDate());
          $_ceNow = (int)date('Y');
          ?>
          <select class="form-control form-control-sm" id="cotisation_year" name="cotisation_year">
            <?php for ($_cey = $_ceNow + 1; $_cey >= $_ceNow - 10; $_cey--): ?>
            <option value="<?= $_cey ?>"<?= $_cey === (int)$_ceSelYear ? ' selected' : '' ?>><?= $_cey ?></option>
            <?php endfor ?>
          </select>
        </div>
      </div>

      <div class="row mb-3 align-items-center">
        <div class="col-8 offset-4 col-sm-9 offset-sm-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="wants_attestation" name="wants_attestation" value="1"
                   <?= $compta->getWantsAttestation() ? 'checked' : '' ?>>
            <label class="form-check-label" for="wants_attestation">
              <?= $GLOBAL['wantsAttestationLabel'] ?>
            </label>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-8 offset-4 col-sm-9 offset-sm-3 d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?userid=<?= $user->getId() ?>&amp;view=removeCompta&amp;comptaid=<?= $comptaid ?>"
             class="btn btn-outline-danger btn-sm">
            <i class="fas fa-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
          </a>
        </div>
      </div>

    </form>
  </div>
</div>
<script>
(function() {
    var cotiIds = <?= json_encode($_cotiTypeIdsEdit) ?>;
    var typeSelect = document.getElementById('type_id');
    var cotiRow = document.getElementById('ca-coti-year-row');
    var cotiInput = document.getElementById('cotisation_year');
    function toggleCotiYear() {
        if (!typeSelect || !cotiRow) return;
        var isCoti = cotiIds.indexOf(parseInt(typeSelect.value, 10)) !== -1;
        cotiRow.style.display = isCoti ? '' : 'none';
        if (cotiInput) cotiInput.name = isCoti ? 'cotisation_year' : '';
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', toggleCotiYear);
        toggleCotiYear();
    }
})();
</script>
