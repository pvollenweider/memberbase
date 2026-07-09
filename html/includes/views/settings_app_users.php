<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin panel for managing application user accounts.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { echo '<div class="alert alert-danger">' . $GLOBAL['accessDenied'] . '</div>'; return; }

$_auRows = $pdo->query(
    "SELECT id, username, display_name, email, role, is_active, force_password_change, created_at, last_login, reset_token, token_expires_at
     FROM app_users ORDER BY role DESC, username ASC"
)->fetchAll(PDO::FETCH_OBJ);
?>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
// Flash: invitation token just generated
$__invFlash = $_SESSION['invite_token_flash'] ?? null;
unset($_SESSION['invite_token_flash']);
if ($__invFlash):
  $__invUser = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
  $__invUser->execute([(int)$__invFlash['uid']]);
  $__invUsername = $__invUser->fetchColumn() ?: '?';
  $__invLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/set-password.php?token=' . urlencode($__invFlash['token']);
?>
<div class="alert alert-success d-flex gap-2 align-items-start py-2 mb-3" style="font-size:0.875rem">
  <i class="fas fa-link mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span>
    <?= sprintf($GLOBAL['inviteLinkFor'], '<strong>' . htmlspecialchars($__invUsername, ENT_QUOTES, $charset) . '</strong>') ?><br>
    <code class="user-select-all" style="word-break:break-all"><?= htmlspecialchars($__invLink, ENT_QUOTES, $charset) ?></code>
    <br><small class="text-muted"><?= $GLOBAL['inviteLinkHelp'] ?></small>
  </span>
</div>
<?php endif ?>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
// Flash: reset password (classic)
$__flash = $_SESSION['reset_pw_flash'] ?? null;
unset($_SESSION['reset_pw_flash']);
if ($__flash):
  $__resetUser = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
  $__resetUser->execute([(int)$__flash['uid']]);
  $__resetUsername = $__resetUser->fetchColumn() ?: '?';
?>
<div class="alert alert-success d-flex gap-2 align-items-start py-2 mb-3" style="font-size:0.875rem">
  <i class="fas fa-key mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span>
    <?= sprintf($GLOBAL['tempPasswordFor'], '<strong>' . htmlspecialchars($__resetUsername, ENT_QUOTES, $charset) . '</strong>') ?>
    <code class="ms-1 user-select-all"><?= htmlspecialchars($__flash['pw'], ENT_QUOTES, $charset) ?></code>
    <br><small class="text-muted"><?= $GLOBAL['tempPasswordHelp'] ?></small>
  </span>
</div>
<?php endif ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <i class="fas fa-user-shield me-1" aria-hidden="true"></i><?= $GLOBAL['appUsersTitle'] ?>
  </span>
  <button type="button" class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modal-create-user">
    <i class="fas fa-plus me-1" aria-hidden="true"></i><?= $GLOBAL['newUser'] ?>
  </button>
</div>

<table class="table table-hover table-sm" style="font-size:0.875rem">
<thead>
<tr>
  <th><?= $GLOBAL['username'] ?></th>
  <th><?= $GLOBAL['name'] ?></th>
  <th><?= $GLOBAL['email'] ?></th>
  <th><?= $GLOBAL['role'] ?></th>
  <th><?= $GLOBAL['status'] ?></th>
  <th><?= $GLOBAL['lastLogin'] ?></th>
  <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($_auRows as $_au):
  $isSelf = $_au->id === (int)$_SESSION['app_user_id'];
?>
<tr>
  <td>
    <strong><?= htmlspecialchars($_au->username, ENT_QUOTES, $charset) ?></strong>
    <?php if ($isSelf): ?><span class="badge bg-secondary ms-1" style="font-size:0.65rem"><?= $GLOBAL['youBadge'] ?></span><?php endif ?>
    <?php if (!empty($_au->reset_token) && strtotime($_au->token_expires_at) > time()): ?>
      <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem" title="<?= $GLOBAL['invitePendingTooltip'] ?>"><i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['inviteBadge'] ?></span>
    <?php elseif ($_au->force_password_change): ?>
      <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem" title="<?= $GLOBAL['mustChangePasswordTooltip'] ?>"><?= $GLOBAL['keyBadge'] ?></span>
    <?php endif ?>
  </td>
  <td><?= htmlspecialchars($_au->display_name ?? '', ENT_QUOTES, $charset) ?></td>
  <td><?= htmlspecialchars($_au->email ?? '', ENT_QUOTES, $charset) ?></td>
  <td>
    <?php match($_au->role) {
      'admin'    => print('<span class="badge bg-danger"   style="font-size:0.7rem">' . $GLOBAL['roleAdmin'] . '</span>'),
      'manager'  => print('<span class="badge bg-warning text-dark" style="font-size:0.7rem">' . $GLOBAL['roleManager'] . '</span>'),
      'readonly' => print('<span class="badge bg-light text-dark border" style="font-size:0.7rem">' . $GLOBAL['roleReadonly'] . '</span>'),
      default    => print('<span class="badge bg-secondary" style="font-size:0.7rem">' . $GLOBAL['user'] . '</span>'),
    } ?>
  </td>
  <td>
    <?php if ($_au->is_active): ?>
      <span class="text-success" style="font-size:0.8rem"><i class="fas fa-circle" aria-hidden="true"></i> <?= $GLOBAL['active'] ?></span>
    <?php else: ?>
      <span class="text-muted" style="font-size:0.8rem"><i class="far fa-circle" aria-hidden="true"></i> <?= $GLOBAL['inactive'] ?></span>
    <?php endif ?>
  </td>
  <td style="font-size:0.8rem;color:var(--ca-ink-muted)">
    <?= $_au->last_login ? date('d.m.Y H:i', strtotime($_au->last_login)) : '—' ?>
  </td>
  <td class="text-end" style="white-space:nowrap">
    <?php if (!$isSelf): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="<?= $GLOBAL['edit'] ?>"
            data-bs-toggle="modal" data-bs-target="#modal-edit-app-user"
            data-user-id="<?= (int)$_au->id ?>"
            data-username="<?= htmlspecialchars($_au->username, ENT_QUOTES, $charset) ?>"
            data-display-name="<?= htmlspecialchars($_au->display_name ?? '', ENT_QUOTES, $charset) ?>"
            data-email="<?= htmlspecialchars($_au->email ?? '', ENT_QUOTES, $charset) ?>"
            data-role="<?= htmlspecialchars($_au->role, ENT_QUOTES, $charset) ?>"
            data-is-active="<?= $_au->is_active ? '1' : '0' ?>">
      <i class="fas fa-pen" aria-hidden="true"></i>
    </button>
    <button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" title="<?= $GLOBAL['resetPasswordShort'] ?>"
            data-bs-toggle="modal" data-bs-target="#modal-reset-app-user"
            data-user-id="<?= (int)$_au->id ?>"
            data-username="<?= htmlspecialchars($_au->username, ENT_QUOTES, $charset) ?>">
      <i class="fas fa-key" aria-hidden="true"></i>
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" title="<?= $GLOBAL['delete'] ?>"
            data-bs-toggle="modal" data-bs-target="#modal-delete-app-user"
            data-user-id="<?= (int)$_au->id ?>"
            data-username="<?= htmlspecialchars($_au->username, ENT_QUOTES, $charset) ?>">
      <i class="fas fa-trash" aria-hidden="true"></i>
    </button>
    <?php else: ?>
    <a href="<?= $_SERVER['PHP_SELF'] ?>?view=changePassword" class="btn btn-sm btn-outline-secondary py-0 px-2" title="<?= $GLOBAL['changeMyPassword'] ?>">
      <i class="fas fa-key" aria-hidden="true"></i>
    </a>
    <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<!-- Modal: créer utilisateur -->
<div class="modal fade" id="modal-create-user" tabindex="-1" aria-labelledby="modal-create-user-title" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="createAppUser">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modal-create-user-title" style="font-size:0.9rem">
            <i class="fas fa-user-plus me-2" aria-hidden="true"></i><?= $GLOBAL['newUser'] ?>
          </h6>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
        </div>
        <div class="modal-body" style="font-size:0.875rem">
          <?php if (!empty($_GET['au_error'])): ?>
          <div class="alert alert-danger py-2 mb-3" style="font-size:0.8rem">
            <?= htmlspecialchars($_GET['au_error'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif ?>
          <div class="mb-3">
            <label for="au_username" class="form-label"><?= $GLOBAL['username'] ?> <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="au_username" name="au_username"
                   required maxlength="100" pattern="[a-zA-Z0-9._\-]+" title="<?= $GLOBAL['usernamePatternHint'] ?>">
          </div>
          <div class="mb-3">
            <label for="au_display_name" class="form-label"><?= $GLOBAL['displayName'] ?></label>
            <input type="text" class="form-control form-control-sm" id="au_display_name" name="au_display_name" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="au_email" class="form-label"><?= $GLOBAL['email'] ?></label>
            <input type="email" class="form-control form-control-sm" id="au_email" name="au_email" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="au_role" class="form-label">
              <?= $GLOBAL['role'] ?>
              <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline text-decoration-none"
                      data-bs-toggle="collapse" data-bs-target="#role-matrix"
                      aria-expanded="false" aria-controls="role-matrix"
                      title="<?= $GLOBAL['viewRightsMatrix'] ?>">
                <i class="fas fa-circle-question" aria-hidden="true"></i>
                <span class="visually-hidden"><?= $GLOBAL['viewRightsMatrix'] ?></span>
              </button>
            </label>
            <select class="form-select form-select-sm" id="au_role" name="au_role">
              <option value="readonly"><?= $GLOBAL['roleReadonly'] ?></option>
              <option value="user" selected><?= $GLOBAL['user'] ?></option>
              <option value="manager"><?= $GLOBAL['roleManager'] ?></option>
              <option value="admin"><?= $GLOBAL['roleAdmin'] ?></option>
            </select>
            <div class="collapse mt-2" id="role-matrix">
              <style>
                .ca-rights-matrix th, .ca-rights-matrix td { padding:.2rem .35rem; border-bottom:1px solid rgba(0,0,0,.08); }
                .ca-rights-matrix tbody tr:last-child td { border-bottom:0; }
              </style>
              <div class="border rounded p-2 bg-light">
                <table class="ca-rights-matrix table-sm mb-0 align-middle text-center" style="font-size:0.72rem;width:100%;border-collapse:collapse">
                  <thead>
                    <tr>
                      <th class="text-start" style="font-weight:600"><?= $GLOBAL['rightLabel'] ?></th>
                      <th><?= $GLOBAL['roleReadonlyWrapped'] ?></th>
                      <th><?= $GLOBAL['roleUserWrapped'] ?></th>
                      <th><?= $GLOBAL['roleManager'] ?></th>
                      <th><?= $GLOBAL['roleAdmin'] ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    // ✓ = autorisé, – = refusé. Reflète auth.php (canRead/canWrite/isManager/isAdmin)
                    // et les gardes des actions (import & gestion = manager+ ; suppression/anonymisation & comptes = admin).
                    $_matrix = [
                      $GLOBAL['rightViewData']         => [1,1,1,1],
                      $GLOBAL['rightEditData']         => [0,1,1,1],
                      $GLOBAL['rightImportContacts']   => [0,0,1,1],
                      $GLOBAL['rightManageSettings']   => [0,0,1,1],
                      $GLOBAL['rightMergeArchive']     => [0,0,1,1],
                      $GLOBAL['rightDeleteAnonymize']  => [0,0,0,1],
                      $GLOBAL['rightManageAccounts']   => [0,0,0,1],
                    ];
                    foreach ($_matrix as $_label => $_cells): ?>
                    <tr>
                      <td class="text-start"><?= htmlspecialchars($_label, ENT_QUOTES, $charset) ?></td>
                      <?php foreach ($_cells as $_c): ?>
                      <td><?= $_c
                            ? '<i class="fas fa-check text-success" aria-label="' . $GLOBAL['yesLower'] . '"></i>'
                            : '<span class="text-muted" aria-label="' . $GLOBAL['noLower'] . '">–</span>' ?></td>
                      <?php endforeach ?>
                    </tr>
                    <?php endforeach ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label for="au_password" class="form-label"><?= $GLOBAL['tempPassword'] ?></label>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control form-control-sm" id="au_password" name="au_password"
                     autocomplete="off" placeholder="changeme">
              <button type="button" class="btn btn-outline-secondary" id="btn-gen-pw" title="<?= $GLOBAL['generateRandomPassword'] ?>">
                <i class="fas fa-dice" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btn-copy-pw" title="<?= $GLOBAL['copy'] ?>" style="display:none">
                <i class="fas fa-copy" aria-hidden="true"></i>
              </button>
            </div>
            <div class="form-text"><?= $GLOBAL['tempPasswordDefaultHelp'] ?></div>
          </div>
          <script>
          (function() {
            var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
            function genPw(len) {
              var arr = new Uint32Array(len);
              crypto.getRandomValues(arr);
              return Array.from(arr).map(function(v) { return chars[v % chars.length]; }).join('');
            }
            var inp = document.getElementById('au_password');
            var btnGen = document.getElementById('btn-gen-pw');
            var btnCopy = document.getElementById('btn-copy-pw');
            btnGen.addEventListener('click', function() {
              inp.value = genPw(12);
              inp.type = 'text';
              btnCopy.style.display = '';
            });
            btnCopy.addEventListener('click', function() {
              navigator.clipboard.writeText(inp.value).then(function() {
                var icon = btnCopy.querySelector('i');
                icon.className = 'fas fa-check';
                setTimeout(function() { icon.className = 'fas fa-copy'; }, 1500);
              });
            });
          })();
          </script>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['create'] ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if (!empty($_GET['au_error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('modal-create-user')).show();
});
</script>
<?php endif ?>

<div class="modal fade" id="modal-edit-app-user" tabindex="-1" aria-labelledby="modal-edit-app-user-label" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" hx-boost="false">
        <input type="hidden" name="action"    value="updateAppUser">
        <input type="hidden" name="target_id" id="modal-edit-target-id" value="">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="modal-edit-app-user-label" style="font-size:0.9rem">
            <i class="fas fa-pen me-2" aria-hidden="true"></i><?= sprintf($GLOBAL['editUserTitle'], '<strong id="modal-edit-username"></strong>') ?>
          </h6>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
        </div>
        <div class="modal-body" style="font-size:0.875rem">
          <div class="mb-3">
            <label for="modal-edit-display-name" class="form-label"><?= $GLOBAL['displayName'] ?></label>
            <input type="text" class="form-control form-control-sm" id="modal-edit-display-name" name="au_display_name" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="modal-edit-email" class="form-label"><?= $GLOBAL['email'] ?></label>
            <input type="email" class="form-control form-control-sm" id="modal-edit-email" name="au_email" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="modal-edit-role" class="form-label"><?= $GLOBAL['role'] ?></label>
            <select class="form-select form-select-sm" id="modal-edit-role" name="au_role">
              <option value="readonly"><?= $GLOBAL['roleReadonly'] ?></option>
              <option value="user"><?= $GLOBAL['user'] ?></option>
              <option value="manager"><?= $GLOBAL['roleManager'] ?></option>
              <option value="admin"><?= $GLOBAL['roleAdmin'] ?></option>
            </select>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="modal-edit-is-active" name="au_is_active" value="1">
            <label class="form-check-label" for="modal-edit-is-active"><?= $GLOBAL['accountActive'] ?></label>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['saveButton'] ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-reset-app-user" tabindex="-1" aria-labelledby="modal-reset-app-user-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-reset-app-user-label"><?= $GLOBAL['resetPasswordTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['resetPasswordConfirm'], '<strong id="modal-reset-username"></strong>') ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" class="d-inline" id="modal-reset-form" hx-boost="false">
          <input type="hidden" name="action"    value="resetUserPassword">
          <input type="hidden" name="target_id" id="modal-reset-target-id" value="">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-key me-1" aria-hidden="true"></i><?= $GLOBAL['reset'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-delete-app-user" tabindex="-1" aria-labelledby="modal-delete-app-user-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-app-user-label"><?= $GLOBAL['deleteUserTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['deleteUserConfirm'], '<strong id="modal-delete-app-username"></strong>') ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" class="d-inline" id="modal-delete-app-form" hx-boost="false">
          <input type="hidden" name="action"    value="deleteAppUser">
          <input type="hidden" name="target_id" id="modal-delete-app-target-id" value="">
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('modal-reset-app-user').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('modal-reset-username').textContent  = btn.dataset.username;
    document.getElementById('modal-reset-target-id').value       = btn.dataset.userId;
});
document.getElementById('modal-delete-app-user').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('modal-delete-app-username').textContent = btn.dataset.username;
    document.getElementById('modal-delete-app-target-id').value      = btn.dataset.userId;
});
document.getElementById('modal-edit-app-user').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('modal-edit-target-id').value        = btn.dataset.userId;
    document.getElementById('modal-edit-username').textContent   = btn.dataset.username;
    document.getElementById('modal-edit-display-name').value     = btn.dataset.displayName;
    document.getElementById('modal-edit-email').value            = btn.dataset.email;
    document.getElementById('modal-edit-role').value             = btn.dataset.role;
    document.getElementById('modal-edit-is-active').checked      = btn.dataset.isActive === '1';
});
</script>
