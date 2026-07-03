<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form and logic for changing the authenticated user's password.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_cpUser = authUser();
$_cpForced = $_cpUser->force_password_change;
$_cpError  = '';
$_cpOk     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_REQUEST['action'] ?? '') === 'changePassword') {
    // handled in manage_actions.inc — we won't reach this block directly
}
?>
<div class="d-flex flex-column align-items-center pt-4 gap-3">
  <div class="card shadow-sm border-0" style="max-width:440px;width:100%">
    <div class="card-body p-4">
      <?php if ($_cpForced): ?>
      <div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-4" style="font-size:0.85rem">
        <i class="fas fa-key mt-1 flex-shrink-0" aria-hidden="true"></i>
        <span><?= $GLOBAL['forcePasswordChangeNotice'] ?></span>
      </div>
      <?php endif ?>
      <h5 class="card-title mb-4"><?= $GLOBAL['changePasswordTitle'] ?></h5>

      <?php if (!empty($_GET['pw_error'])): ?>
      <div class="alert alert-danger py-2 mb-3" style="font-size:0.875rem">
        <?= htmlspecialchars($_GET['pw_error'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif ?>

      <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="hidden" name="action" value="changePassword">
        <?php if (!$_cpForced): ?>
        <div class="mb-3">
          <label for="pw_current" class="form-label" style="font-size:0.875rem"><?= $GLOBAL['currentPassword'] ?></label>
          <input type="password" class="form-control" id="pw_current" name="pw_current"
                 autocomplete="current-password" required>
        </div>
        <?php endif ?>
        <div class="mb-3">
          <label for="pw_new" class="form-label" style="font-size:0.875rem"><?= $GLOBAL['newPassword'] ?></label>
          <input type="password" class="form-control" id="pw_new" name="pw_new"
                 autocomplete="new-password" minlength="8" required>
          <div class="form-text"><?= $GLOBAL['minPasswordChars'] ?></div>
        </div>
        <div class="mb-4">
          <label for="pw_confirm" class="form-label" style="font-size:0.875rem"><?= $GLOBAL['confirmationLabel'] ?></label>
          <input type="password" class="form-control" id="pw_confirm" name="pw_confirm"
                 autocomplete="new-password" required>
        </div>
        <div class="d-flex gap-2">
          <?php if (!$_cpForced): ?>
          <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
          <?php endif ?>
          <button type="submit" class="btn btn-primary"><?= $GLOBAL['saveButton'] ?></button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!$_cpForced): ?>
  <div class="card shadow-sm border-0" style="max-width:440px;width:100%">
    <div class="card-body p-4">
      <h5 class="card-title mb-3"><?= $GLOBAL['language'] ?></h5>
      <?php /* hx-boost off: a locale change must fully reload the page so the
               <html lang> attribute and every layout string refresh. */ ?>
      <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" hx-boost="false">
        <input type="hidden" name="action" value="changeLocale">
        <div class="mb-3">
          <label for="locale" class="form-label" style="font-size:0.875rem"><?= $GLOBAL['interfaceLanguage'] ?></label>
          <select class="form-select" id="locale" name="locale" data-no-dirty>
            <?php $_cpLocale = $_SESSION['app_user_locale'] ?? 'fr';
            foreach (mbAvailableLocales() as $_cpCode => $_cpName): ?>
            <option value="<?= $_cpCode ?>"<?= $_cpCode === $_cpLocale ? ' selected' : '' ?>><?= htmlspecialchars($_cpName, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach ?>
          </select>
          <div class="form-text"><?= $GLOBAL['interfaceLanguageHelp'] ?></div>
        </div>
        <button type="submit" class="btn btn-primary"><?= $GLOBAL['saveButton'] ?></button>
      </form>
    </div>
  </div>
  <?php endif ?>
</div>
