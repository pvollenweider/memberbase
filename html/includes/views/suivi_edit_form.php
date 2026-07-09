<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for editing an existing follow-up (suivi) entry.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$suiviid = $_REQUEST['suiviid'];
$userProperty = new UserProperty();
$userProperty->lookupUserProperty($suiviid);
$suivi_user = new Contact();
$suivi_user->lookupUser($userProperty->getUserId());
?>

<div class="row justify-content-center mt-3">
  <div class="col-md-7 col-lg-5">

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <h6 class="text-muted mb-0" style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em">
        <?= $GLOBAL['updateSuivi'] ?>
      </h6>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= $suivi_user->getId() ?>"
         class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= htmlentities($suivi_user->getFirstName(), ENT_COMPAT, $charset) ?> <?= htmlentities($suivi_user->getLastName(), ENT_COMPAT, $charset) ?>
      </a>
    </div>

    <form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="updateSuivi">
      <input type="hidden" name="suiviid"   value="<?= $userProperty->getId() ?>"/>
      <input type="hidden" name="parameter" value="<?= $userProperty->getParameter() ?>"/>
      <input type="hidden" name="action"    value="updateSuivi"/>
      <input type="hidden" name="view"      value="suivi"/>
      <input type="hidden" name="userid"    value="<?= $userProperty->getUserId() ?>"/>

      <div class="row mb-2 align-items-center">
        <label for="date" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['date'] ?></label>
        <div class="col-sm-9">
          <input type="text" id="date" name="date" class="form-control form-control-sm datepicker"
                 value="<?= timeStampToformatedDate($userProperty->getDate()) ?>" maxlength="255"/>
        </div>
      </div>

      <div class="row mb-3 align-items-start">
        <label for="comment" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['comment'] ?></label>
        <div class="col-sm-9">
          <textarea id="comment" name="value" rows="5"
                    class="form-control form-control-sm"><?= html_entity_decode($userProperty->getValue(), ENT_COMPAT, $charset) ?></textarea>
        </div>
      </div>

      <div class="row">
        <div class="col-sm-9 offset-sm-3 d-flex align-items-center gap-3">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?userid=<?= $userProperty->getUserId() ?>&amp;view=removeSuivi&amp;suiviid=<?= $suiviid ?>"
             class="btn btn-outline-danger btn-sm">
            <i class="fas fa-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
          </a>
        </div>
      </div>

    </form>
  </div>
</div>
