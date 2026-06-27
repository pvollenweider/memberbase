<?php
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
$saved = isset($_GET['saved']);
$_activeTab = $_REQUEST['tab'] ?? null;
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <!-- Mobile: select replaces sidebar on small screens -->
    <div class="ca-settings-select-wrap">
      <select class="form-select form-select-sm" id="settings-select" aria-label="Section des réglages">
        <option value="#tab-groups">Groupes</option>
        <option value="#tab-categories">Catégories</option>
        <option value="#tab-filters">Métagroupes</option>
        <?php if (isAdmin()): ?>
        <option value="#tab-compta">Types compta</option>
        <option value="#tab-settings">Réglages</option>
        <option value="#tab-users">Utilisateurs</option>
        <option value="#tab-audit">Journal</option>
        <option value="#tab-integrity">Intégrité</option>
        <?php endif ?>
      </select>
    </div>

    <div class="ca-settings-layout">

      <!-- Sidebar nav -->
      <nav class="ca-settings-nav" aria-label="Sections des réglages">
        <ul role="tablist" aria-orientation="vertical" id="settings-tabs">
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-groups-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-groups"
                    type="button" role="tab"
                    aria-controls="tab-groups" aria-selected="false">
              <i class="fas fa-users fa-fw" aria-hidden="true"></i>Groupes
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-categories-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-categories"
                    type="button" role="tab"
                    aria-controls="tab-categories" aria-selected="false">
              <i class="fas fa-tag fa-fw" aria-hidden="true"></i>Catégories
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-filters-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-filters"
                    type="button" role="tab"
                    aria-controls="tab-filters" aria-selected="false">
              <i class="fas fa-layer-group fa-fw" aria-hidden="true"></i>Métagroupes
            </button>
          </li>
          <?php if (isAdmin()): ?>
          <li role="presentation" class="ca-settings-nav-divider" aria-hidden="true">Administration</li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-compta-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-compta"
                    type="button" role="tab"
                    aria-controls="tab-compta" aria-selected="false">
              <i class="fas fa-tags fa-fw" aria-hidden="true"></i>Types compta
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-settings-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-settings"
                    type="button" role="tab"
                    aria-controls="tab-settings" aria-selected="false">
              <i class="fas fa-sliders-h fa-fw" aria-hidden="true"></i>Réglages
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-users-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-users"
                    type="button" role="tab"
                    aria-controls="tab-users" aria-selected="false">
              <i class="fas fa-user-shield fa-fw" aria-hidden="true"></i>Utilisateurs
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-audit-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-audit"
                    type="button" role="tab"
                    aria-controls="tab-audit" aria-selected="false">
              <i class="fas fa-history fa-fw" aria-hidden="true"></i>Journal
            </button>
          </li>
          <li role="presentation">
            <button class="ca-settings-nav-btn" id="tab-integrity-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-integrity"
                    type="button" role="tab"
                    aria-controls="tab-integrity" aria-selected="false">
              <i class="fas fa-stethoscope fa-fw" aria-hidden="true"></i>Intégrité
            </button>
          </li>
          <li role="presentation">
            <a class="ca-settings-nav-btn" href="<?= $_SERVER['PHP_SELF'] ?>?view=inactiveUsers"
               style="text-decoration:none">
              <i class="fas fa-archive fa-fw" aria-hidden="true"></i>Archivés
            </a>
          </li>
          <?php endif ?>
        </ul>
      </nav>

      <!-- Content panels -->
      <div class="ca-settings-content">
        <div class="tab-content">

          <!-- Réglages -->
          <div class="tab-pane fade" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-btn">
            <?php if (!isAdmin()): ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i>Accès réservé aux administrateurs.</div>
            <?php else: ?>
            <div class="col-md-8">
            <div id="settings-save-msg"></div>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post"
                  hx-post="<?= $_SERVER['PHP_SELF'] ?>"
                  hx-target="#settings-save-msg"
                  hx-swap="innerHTML">
              <input type="hidden" name="action" value="saveSettings"/>
              <input type="hidden" name="view" value="settings"/>
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_default_team">Groupe affiché par défaut</label>
                <p class="text-muted mb-2" style="font-size:0.78rem">Groupe sélectionné à l'ouverture de la liste des membres. Choisir le groupe correspondant aux membres de l'année en cours (ex: «Membre 2026»). À mettre à jour chaque année.</p>
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
            <div class="mt-1">
            <?php include 'manage_groups.inc'; ?>
            </div>
          </div><!-- #tab-groups -->

          <!-- Catégories -->
          <div class="tab-pane fade" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories-btn">
            <div class="mt-1 col-md-9">
            <p class="form-section-title" style="margin-top:0"><i class="fas fa-tag me-1" aria-hidden="true"></i>Catégories</p>
            <?php include 'manage_categories.inc'; ?>
            </div>
          </div><!-- #tab-categories -->

          <!-- Métagroupes -->
          <div class="tab-pane fade" id="tab-filters" role="tabpanel" aria-labelledby="tab-filters-btn">
            <div class="mt-1 col-md-9">
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
            <div class="mt-1 col-md-10">
            <?php include 'manage_integrity.inc'; ?>
            </div>
          </div><!-- #tab-integrity -->
          <?php endif ?>

        </div><!-- .tab-content -->
      </div><!-- .ca-settings-content -->

    </div><!-- .ca-settings-layout -->

    <script>
    (function() {
      var STORAGE_KEY = 'admin_activeTab';
      var tabs = document.getElementById('settings-tabs');
      var sel  = document.getElementById('settings-select');
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
        'teams':      '#tab-groups',
      };
      var targetPane = urlTab && tabMap[urlTab] ? tabMap[urlTab]
                     : sessionStorage.getItem(STORAGE_KEY)
                     || '#tab-groups';

      var btn = tabs.querySelector('[data-bs-target="' + targetPane + '"]');
      if (btn) {
        bootstrap.Tab.getOrCreateInstance(btn).show();
      } else {
        var first = tabs.querySelector('[data-bs-toggle="tab"]');
        if (first) bootstrap.Tab.getOrCreateInstance(first).show();
      }

      // Sync select value to active pane
      function syncSelect(pane) {
        if (!sel) return;
        var opt = sel.querySelector('option[value="' + pane + '"]');
        if (opt) sel.value = pane;
      }

      tabs.addEventListener('shown.bs.tab', function(e) {
        var pane = e.target.getAttribute('data-bs-target');
        sessionStorage.setItem(STORAGE_KEY, pane);
        syncSelect(pane);
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

      // Mobile select → show corresponding tab
      if (sel) {
        sel.addEventListener('change', function() {
          var target = sel.value;
          var btn = tabs.querySelector('[data-bs-target="' + target + '"]');
          if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
        });
      }
    })();
    </script>

  </div>
</div>
