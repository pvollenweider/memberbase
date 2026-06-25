<?php
$_cpUser = authUser();
$_cpForced = $_cpUser->force_password_change;
$_cpError  = '';
$_cpOk     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_REQUEST['action'] ?? '') === 'changePassword') {
    // handled in manage_actions.inc — we won't reach this block directly
}
?>
<div class="d-flex justify-content-center align-items-start pt-4">
  <div class="card shadow-sm border-0" style="max-width:440px;width:100%">
    <div class="card-body p-4">
      <?php if ($_cpForced): ?>
      <div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-4" style="font-size:0.85rem">
        <i class="fas fa-key mt-1 flex-shrink-0" aria-hidden="true"></i>
        <span>Veuillez définir un nouveau mot de passe avant de continuer.</span>
      </div>
      <?php endif ?>
      <h5 class="card-title mb-4">Changer le mot de passe</h5>

      <?php if (!empty($_GET['pw_error'])): ?>
      <div class="alert alert-danger py-2 mb-3" style="font-size:0.875rem">
        <?= htmlspecialchars($_GET['pw_error'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif ?>

      <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
        <input type="hidden" name="action" value="changePassword">
        <?php if (!$_cpForced): ?>
        <div class="mb-3">
          <label for="pw_current" class="form-label" style="font-size:0.875rem">Mot de passe actuel</label>
          <input type="password" class="form-control" id="pw_current" name="pw_current"
                 autocomplete="current-password" required>
        </div>
        <?php endif ?>
        <div class="mb-3">
          <label for="pw_new" class="form-label" style="font-size:0.875rem">Nouveau mot de passe</label>
          <input type="password" class="form-control" id="pw_new" name="pw_new"
                 autocomplete="new-password" minlength="8" required>
          <div class="form-text">Minimum 8 caractères.</div>
        </div>
        <div class="mb-4">
          <label for="pw_confirm" class="form-label" style="font-size:0.875rem">Confirmation</label>
          <input type="password" class="form-control" id="pw_confirm" name="pw_confirm"
                 autocomplete="new-password" required>
        </div>
        <div class="d-flex gap-2">
          <?php if (!$_cpForced): ?>
          <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">Annuler</a>
          <?php endif ?>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>
