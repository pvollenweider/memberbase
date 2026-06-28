<?php
if (!isAdmin()) { echo '<div class="alert alert-danger">Accès refusé.</div>'; return; }

$_auRows = $pdo->query(
    "SELECT id, username, display_name, email, role, is_active, force_password_change, created_at, last_login, reset_token, token_expires_at
     FROM app_users ORDER BY role DESC, username ASC"
)->fetchAll(PDO::FETCH_OBJ);
?>
<?php
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
    Lien d'invitation pour <strong><?= htmlspecialchars($__invUsername, ENT_QUOTES, $charset) ?></strong> (valable 7 jours) :<br>
    <code class="user-select-all" style="word-break:break-all"><?= htmlspecialchars($__invLink, ENT_QUOTES, $charset) ?></code>
    <br><small class="text-muted">Envoyez ce lien à l'utilisateur. Il définira lui-même son mot de passe.</small>
  </span>
</div>
<?php endif ?>
<?php
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
    Mot de passe temporaire pour <strong><?= htmlspecialchars($__resetUsername, ENT_QUOTES, $charset) ?></strong> :
    <code class="ms-1 user-select-all"><?= htmlspecialchars($__flash['pw'], ENT_QUOTES, $charset) ?></code>
    <br><small class="text-muted">Communiquez-le à l'utilisateur. Il devra le changer à la prochaine connexion.</small>
  </span>
</div>
<?php endif ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <i class="fas fa-user-shield me-1" aria-hidden="true"></i>Utilisateurs de l'application
  </span>
  <button type="button" class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modal-create-user">
    <i class="fas fa-plus me-1" aria-hidden="true"></i>Nouvel utilisateur
  </button>
</div>

<table class="table table-hover table-sm" style="font-size:0.875rem">
<thead>
<tr>
  <th>Identifiant</th>
  <th>Nom</th>
  <th>Email</th>
  <th>Rôle</th>
  <th>Statut</th>
  <th>Dernier login</th>
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
    <?php if ($isSelf): ?><span class="badge bg-secondary ms-1" style="font-size:0.65rem">vous</span><?php endif ?>
    <?php if (!empty($_au->reset_token) && strtotime($_au->token_expires_at) > time()): ?>
      <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem" title="Invitation en attente — lien non encore utilisé"><i class="fas fa-envelope me-1" aria-hidden="true"></i>invitation</span>
    <?php elseif ($_au->force_password_change): ?>
      <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem" title="Doit changer son mot de passe">clé</span>
    <?php endif ?>
  </td>
  <td><?= htmlspecialchars($_au->display_name ?? '', ENT_QUOTES, $charset) ?></td>
  <td><?= htmlspecialchars($_au->email ?? '', ENT_QUOTES, $charset) ?></td>
  <td>
    <?php if ($_au->role === 'admin'): ?>
      <span class="badge bg-danger" style="font-size:0.7rem">Admin</span>
    <?php else: ?>
      <span class="badge bg-secondary" style="font-size:0.7rem">Utilisateur</span>
    <?php endif ?>
  </td>
  <td>
    <?php if ($_au->is_active): ?>
      <span class="text-success" style="font-size:0.8rem"><i class="fas fa-circle" aria-hidden="true"></i> Actif</span>
    <?php else: ?>
      <span class="text-muted" style="font-size:0.8rem"><i class="far fa-circle" aria-hidden="true"></i> Inactif</span>
    <?php endif ?>
  </td>
  <td style="font-size:0.8rem;color:var(--ca-ink-muted)">
    <?= $_au->last_login ? date('d.m.Y H:i', strtotime($_au->last_login)) : '—' ?>
  </td>
  <td class="text-end" style="white-space:nowrap">
    <?php if (!$isSelf): ?>
    <form method="post" class="d-inline"
          onsubmit="return confirm('Réinitialiser le mot de passe de «<?= htmlspecialchars(addslashes($_au->username), ENT_QUOTES, $charset) ?>»?')">
      <input type="hidden" name="action"    value="resetUserPassword">
      <input type="hidden" name="target_id" value="<?= (int)$_au->id ?>">
      <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2" title="Réinitialiser mot de passe">
        <i class="fas fa-key" aria-hidden="true"></i>
      </button>
    </form>
    <form method="post" class="d-inline"
          onsubmit="return confirm('Supprimer l\'utilisateur «<?= htmlspecialchars(addslashes($_au->username), ENT_QUOTES, $charset) ?>»?')">
      <input type="hidden" name="action"    value="deleteAppUser">
      <input type="hidden" name="target_id" value="<?= (int)$_au->id ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Supprimer">
        <i class="fas fa-trash" aria-hidden="true"></i>
      </button>
    </form>
    <?php else: ?>
    <a href="<?= $_SERVER['PHP_SELF'] ?>?view=changePassword" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Changer mon mot de passe">
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
            <i class="fas fa-user-plus me-2" aria-hidden="true"></i>Nouvel utilisateur
          </h6>
          <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body" style="font-size:0.875rem">
          <?php if (!empty($_GET['au_error'])): ?>
          <div class="alert alert-danger py-2 mb-3" style="font-size:0.8rem">
            <?= htmlspecialchars($_GET['au_error'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif ?>
          <div class="mb-3">
            <label for="au_username" class="form-label">Identifiant <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="au_username" name="au_username"
                   required maxlength="100" pattern="[a-zA-Z0-9._\-]+" title="Lettres, chiffres, point, tiret, underscore">
          </div>
          <div class="mb-3">
            <label for="au_display_name" class="form-label">Nom affiché</label>
            <input type="text" class="form-control form-control-sm" id="au_display_name" name="au_display_name" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="au_email" class="form-label">Email</label>
            <input type="email" class="form-control form-control-sm" id="au_email" name="au_email" maxlength="200">
          </div>
          <div class="mb-3">
            <label for="au_role" class="form-label">Rôle</label>
            <select class="form-select form-select-sm" id="au_role" name="au_role">
              <option value="user">Utilisateur</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="au_password" class="form-label">Mot de passe temporaire</label>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control form-control-sm" id="au_password" name="au_password"
                     autocomplete="off" placeholder="changeme">
              <button type="button" class="btn btn-outline-secondary" id="btn-gen-pw" title="Générer un mot de passe aléatoire">
                <i class="fas fa-dice" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btn-copy-pw" title="Copier" style="display:none">
                <i class="fas fa-copy" aria-hidden="true"></i>
              </button>
            </div>
            <div class="form-text">Laisser vide pour utiliser <strong>changeme</strong> par défaut. L'utilisateur devra le changer à la première connexion.</div>
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
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary btn-sm">Créer</button>
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
