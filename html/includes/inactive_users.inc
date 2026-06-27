<?php
$inactiveUsers = $pdo->query(
    "SELECT id, firstName, lastName, society, email
     FROM users
     WHERE status=0
     ORDER BY lastName, firstName, society"
)->fetchAll(PDO::FETCH_OBJ);
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <div class="ca-settings-layout">

      <!-- Sidebar nav (same as settings_form.inc) -->
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
              <i class="fas fa-eye-slash fa-fw" aria-hidden="true"></i>Masqués
            </a>
          </li>
          <?php endif ?>
        </ul>
      </nav>

      <!-- Content -->
      <div class="ca-settings-content">

        <p class="form-section-title mb-1">
          <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Membres masqués
        </p>
        <p class="small text-muted mb-4">Profils désactivés. Ils ne sont plus visibles dans les listes.</p>

        <?php if (empty($inactiveUsers)): ?>
        <div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
          <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Aucun membre masqué.
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
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$u->id ?>" class="text-decoration-none">
                  <?= htmlspecialchars(trim($u->firstName . ' ' . $u->lastName ?: $u->society), ENT_QUOTES, $charset) ?>
                </a>
              </td>
              <td class="text-muted"><?= htmlspecialchars((string)$u->email, ENT_QUOTES, $charset) ?: '—' ?></td>
              <td class="text-center text-muted"><?= (int)$u->id ?></td>
              <td class="text-end">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" style="display:inline">
                  <input type="hidden" name="action"   value="reactivateUser">
                  <input type="hidden" name="id"       value="<?= (int)$u->id ?>">
                  <input type="hidden" name="redirect" value="inactiveUsers">
                  <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:0.75rem"
                          onclick="return confirm('Réactiver ce profil ?')">
                    <i class="fas fa-eye me-1" aria-hidden="true"></i>Réactiver
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>

      </div><!-- /.ca-settings-content -->
    </div><!-- /.ca-settings-layout -->
  </div>
</div>
