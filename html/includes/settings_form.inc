<?php
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
$saved = isset($_GET['saved']);
$_activeTab = $_GET['tab'] ?? null;
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" id="settings-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-groups-btn"
                data-bs-toggle="tab" data-bs-target="#tab-groups"
                type="button" role="tab"
                aria-controls="tab-groups" aria-selected="false">
          <i class="fas fa-users me-1" aria-hidden="true"></i>Groupes
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-categories-btn"
                data-bs-toggle="tab" data-bs-target="#tab-categories"
                type="button" role="tab"
                aria-controls="tab-categories" aria-selected="false">
          <i class="fas fa-tag me-1" aria-hidden="true"></i>Catégories
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-filters-btn"
                data-bs-toggle="tab" data-bs-target="#tab-filters"
                type="button" role="tab"
                aria-controls="tab-filters" aria-selected="false">
          <i class="fas fa-layer-group me-1" aria-hidden="true"></i>Métagroupes
        </button>
      </li>
      <?php if (isAdmin()): ?>
      <li class="nav-item" role="presentation" aria-label="Section administration">
        <span class="nav-link pe-none text-muted" style="opacity:0.4;cursor:default;font-size:0.75rem;padding-top:0.6rem">|</span>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-compta-btn"
                data-bs-toggle="tab" data-bs-target="#tab-compta"
                type="button" role="tab"
                aria-controls="tab-compta" aria-selected="false">
          <i class="fas fa-tags me-1" aria-hidden="true"></i>Types compta
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-settings-btn"
                data-bs-toggle="tab" data-bs-target="#tab-settings"
                type="button" role="tab"
                aria-controls="tab-settings" aria-selected="false">
          <i class="fas fa-sliders-h me-1" aria-hidden="true"></i>Réglages
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-users-btn"
                data-bs-toggle="tab" data-bs-target="#tab-users"
                type="button" role="tab"
                aria-controls="tab-users" aria-selected="false">
          <i class="fas fa-user-shield me-1" aria-hidden="true"></i>Utilisateurs
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-audit-btn"
                data-bs-toggle="tab" data-bs-target="#tab-audit"
                type="button" role="tab"
                aria-controls="tab-audit" aria-selected="false">
          <i class="fas fa-history me-1" aria-hidden="true"></i>Journal
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link text-nowrap" id="tab-integrity-btn"
                data-bs-toggle="tab" data-bs-target="#tab-integrity"
                type="button" role="tab"
                aria-controls="tab-integrity" aria-selected="false">
          <i class="fas fa-stethoscope me-1" aria-hidden="true"></i>Intégrité
        </button>
      </li>
      <?php endif ?>
    </ul>

    <div class="tab-content">

      <!-- Réglages -->
      <div class="tab-pane fade" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-btn">
        <?php if (!isAdmin()): ?>
        <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i>Accès réservé aux administrateurs.</div>
        <?php else: ?>
        <div class="col-md-8 mx-auto">
        <?php if ($saved): ?>
        <div class="alert alert-success py-2 px-3 mb-3" style="font-size:0.85rem" role="status">Réglages sauvegardés.</div>
        <?php endif ?>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
          <input type="hidden" name="action" value="saveSettings"/>
          <input type="hidden" name="view" value="settings"/>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_default_team">Groupe affiché par défaut</label>
            <p class="text-muted mb-2" style="font-size:0.78rem">Groupe sélectionné à l'ouverture de la liste des membres.</p>
            <select name="default_team" id="s_default_team" class="form-select form-select-sm" style="max-width:320px">
              <?php foreach ($allTeams as $t): ?>
              <option value="<?= (int)$t->id ?>" <?= (int)$appSettings['default_team'] === (int)$t->id ? 'selected' : '' ?>>
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' (masqué)' : '' ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_membre_team">Groupe membres (année de référence)</label>
            <p class="text-muted mb-2" style="font-size:0.78rem">Groupe membres de l'année en cours (ex: «Membre 2026»). Utilisé pour les filtres cotisations et affiché dans le tableau de bord Contributions avec comparaison à l'année précédente. À mettre à jour chaque année.</p>
            <select name="membre_team" id="s_membre_team" class="form-select form-select-sm" style="max-width:320px">
              <?php foreach ($allTeams as $t): ?>
              <option value="<?= (int)$t->id ?>" <?= (int)$appSettings['membre_team'] === (int)$t->id ? 'selected' : '' ?>>
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' (masqué)' : '' ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_archive_id">Groupe archive</label>
            <p class="text-muted mb-2" style="font-size:0.78rem">Ce groupe est exclu du filtre «Tout le monde sauf archives» et des mailings. Ses membres n'apparaissent pas dans les vues par défaut.</p>
            <select name="archive_id" id="s_archive_id" class="form-select form-select-sm" style="max-width:320px">
              <?php foreach ($allTeams as $t): ?>
              <option value="<?= (int)$t->id ?>" <?= ARCHIVE_ID === (int)$t->id ? 'selected' : '' ?>>
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' (masqué)' : '' ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
        </form>
        </div>
        <?php endif ?>
      </div><!-- #tab-settings -->

      <!-- Types compta (admin only) -->
      <div class="tab-pane fade" id="tab-compta" role="tabpanel" aria-labelledby="tab-compta-btn">
        <?php if (isAdmin()): $ctEmbedded = true; $ctReturnView = 'settings'; $ctReturnTab = 'compta'; include 'manage_compta_types.inc'; else: ?>
        <div class="alert alert-danger mt-3" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i>Accès réservé aux administrateurs.</div>
        <?php endif ?>
      </div><!-- #tab-compta -->

      <!-- Groupes -->
      <div class="tab-pane fade" id="tab-groups" role="tabpanel" aria-labelledby="tab-groups-btn">
        <div class="mt-3">
        <?php include 'manage_groups.inc'; ?>
        </div>
      </div><!-- #tab-groups -->

      <!-- Catégories -->
      <div class="tab-pane fade" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories-btn">
        <div class="mt-3 col-md-6">
        <p class="form-section-title" style="margin-top:0"><i class="fas fa-tag me-1" aria-hidden="true"></i>Catégories</p>
        <?php include 'manage_categories.inc'; ?>
        </div>
      </div><!-- #tab-categories -->

      <!-- Métagroupes -->
      <div class="tab-pane fade" id="tab-filters" role="tabpanel" aria-labelledby="tab-filters-btn">
        <div class="mt-3 col-md-6">
        <p class="form-section-title" style="margin-top:0"><i class="fas fa-layer-group me-1" aria-hidden="true"></i>Métagroupes</p>
        <?php include 'manage_filters.inc'; ?>
        </div>
      </div><!-- #tab-filters -->

      <?php if (isAdmin()): ?>
      <!-- Utilisateurs app -->
      <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="tab-users-btn">
        <?php include 'manage_app_users.inc'; ?>
      </div><!-- #tab-users -->

      <!-- Journal d'activité -->
      <div class="tab-pane fade" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
        <?php include 'audit_log.inc'; ?>
      </div><!-- #tab-audit -->

      <!-- Intégrité -->
      <div class="tab-pane fade" id="tab-integrity" role="tabpanel" aria-labelledby="tab-integrity-btn">
        <div class="mt-3 col-md-8">
        <?php include 'manage_integrity.inc'; ?>
        </div>
      </div><!-- #tab-integrity -->
      <?php endif ?>

    </div><!-- .tab-content -->

    <script>
    (function() {
      var STORAGE_KEY = 'admin_activeTab';
      var tabs = document.getElementById('settings-tabs');
      if (!tabs) return;

      var urlTab = <?= json_encode($_activeTab) ?>;
      var tabMap = {
        'settings':   '#tab-settings',
        'compta':     '#tab-compta',
        'groups':     '#tab-groups',
        'categories': '#tab-categories',
        'filters':    '#tab-filters',
        'users':      '#tab-users',
        'audit':      '#tab-audit',
        'integrity':  '#tab-integrity',
        // legacy aliases
        'teams':      '#tab-groups',
      };
      var targetPane = urlTab && tabMap[urlTab] ? tabMap[urlTab]
                     : sessionStorage.getItem(STORAGE_KEY)
                     || '#tab-compta';

      var btn = tabs.querySelector('[data-bs-target="' + targetPane + '"]');
      if (btn) {
        bootstrap.Tab.getOrCreateInstance(btn).show();
      } else {
        var first = tabs.querySelector('[data-bs-toggle="tab"]');
        if (first) bootstrap.Tab.getOrCreateInstance(first).show();
      }

      tabs.addEventListener('shown.bs.tab', function(e) {
        var pane = e.target.getAttribute('data-bs-target');
        sessionStorage.setItem(STORAGE_KEY, pane);
        var key = Object.keys(tabMap).find(function(k) { return tabMap[k] === pane && k !== 'teams'; });
        if (key && history.replaceState) {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', key);
          history.replaceState(null, '', url.toString());
        }
        if (pane === '#tab-audit' && window.auditLogDT) {
          window.auditLogDT.columns.adjust().draw(false);
        }
      });
    })();
    </script>

  </div>
</div>
