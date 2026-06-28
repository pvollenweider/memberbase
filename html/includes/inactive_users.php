<?php
$inactiveUsers = $pdo->query(
    "SELECT id, firstName, lastName, society, email
     FROM users
     WHERE status=0
     ORDER BY society, lastName, firstName"
)->fetchAll(PDO::FETCH_OBJ);
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <div class="ca-settings-layout">

      <!-- Sidebar nav (same as settings_form.php) -->
      <nav class="ca-settings-nav" aria-label="Sections des réglages">
        <ul role="tablist" aria-orientation="vertical" id="settings-tabs">
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=groups"
               style="text-decoration:none">
              <i class="fas fa-users fa-fw" aria-hidden="true"></i>Groupes
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=categories"
               style="text-decoration:none">
              <i class="fas fa-tag fa-fw" aria-hidden="true"></i>Catégories
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=filters"
               style="text-decoration:none">
              <i class="fas fa-layer-group fa-fw" aria-hidden="true"></i>Métagroupes
            </a>
          </li>
          <?php if (isAdmin()): ?>
          <li role="presentation" class="ca-settings-nav-divider" aria-hidden="true">Administration</li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=compta"
               style="text-decoration:none">
              <i class="fas fa-tags fa-fw" aria-hidden="true"></i>Types compta
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=settings"
               style="text-decoration:none">
              <i class="fas fa-sliders-h fa-fw" aria-hidden="true"></i>Réglages
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=users"
               style="text-decoration:none">
              <i class="fas fa-user-shield fa-fw" aria-hidden="true"></i>Utilisateurs
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=audit"
               style="text-decoration:none">
              <i class="fas fa-history fa-fw" aria-hidden="true"></i>Journal
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&tab=integrity"
               style="text-decoration:none">
              <i class="fas fa-stethoscope fa-fw" aria-hidden="true"></i>Intégrité
            </a>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn ca-settings-nav-btn--active" href="<?= $_SERVER['PHP_SELF'] ?>?view=inactiveUsers"
               style="text-decoration:none" aria-current="page">
              <i class="fas fa-archive fa-fw" aria-hidden="true"></i>Archivés
            </a>
          </li>
          <?php endif ?>
        </ul>
      </nav>

      <!-- Content -->
      <div class="ca-settings-content">

        <p class="form-section-title mb-1">
          <i class="fas fa-archive me-1" aria-hidden="true"></i>Membres archivés
        </p>
        <p class="small text-muted mb-4">Profils archivés. Ils ne sont plus visibles dans les listes.</p>

        <?php if (empty($inactiveUsers)): ?>
        <div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
          <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Aucun membre archivé.
        </div>
        <?php else: ?>
        <table class="table table-sm align-middle" style="font-size:0.82rem;max-width:720px">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Email</th>
              <th style="width:3rem" class="text-center">ID</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($inactiveUsers as $u): ?>
            <tr>
              <td>
                <?php $_uLabel = htmlspecialchars(trim(trim($u->lastName . ' ' . $u->firstName) ?: $u->society), ENT_QUOTES, $charset) ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$u->id ?>" class="text-decoration-none">
                  <?= $_uLabel ?: '<span class="text-muted fst-italic">Sans nom</span>' ?>
                </a>
              </td>
              <td class="text-muted"><?= htmlspecialchars((string)$u->email, ENT_QUOTES, $charset) ?: '—' ?></td>
              <td class="text-center text-muted"><?= (int)$u->id ?></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                        style="font-size:0.75rem;white-space:nowrap"
                        data-unarchive-id="<?= (int)$u->id ?>"
                        data-unarchive-name="<?= $_uLabel ?: 'Sans nom #' . (int)$u->id ?>"
                        onclick="unarchiveConfirm(this)">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i>Désarchiver
                </button>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>

      </div><!-- /.ca-settings-content -->

      <!-- Modal désarchivage (réutilisé pour toutes les lignes) -->
      <div class="modal fade" id="unarchive-modal" tabindex="-1" aria-labelledby="unarchive-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
          <div class="modal-content">
            <div class="modal-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--ca-ink-muted)">
                <i class="fas fa-box-open" aria-hidden="true"></i>
              </div>
              <h6 class="mb-1" id="unarchive-modal-label">Désarchiver ce membre&nbsp;?</h6>
              <p class="text-muted mb-1" style="font-size:0.83rem" id="unarchive-modal-name"></p>
              <p class="text-muted mb-4" style="font-size:0.83rem">Le profil réapparaîtra dans toutes les listes.</p>
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-success" id="unarchive-confirm-btn">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i>Désarchiver
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" id="unarchive-form" data-no-dirty>
        <input type="hidden" name="action"   value="reactivateUser">
        <input type="hidden" name="id"       id="unarchive-id" value="">
        <input type="hidden" name="redirect" value="inactiveUsers">
      </form>

      <script>
      function unarchiveConfirm(btn) {
        var id   = btn.dataset.unarchiveId;
        var name = btn.dataset.unarchiveName;
        document.getElementById('unarchive-id').value = id;
        document.getElementById('unarchive-modal-name').textContent = name;
        new bootstrap.Modal(document.getElementById('unarchive-modal')).show();
      }
      document.getElementById('unarchive-confirm-btn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('unarchive-modal')).hide();
        document.getElementById('unarchive-form').submit();
      });
      </script>
    </div><!-- /.ca-settings-layout -->
  </div>
</div>
