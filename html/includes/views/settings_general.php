<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Application settings form covering groups, filters, and accounting types.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
$saved = isset($_GET['saved']);
$_activeTab = $_REQUEST['tab'] ?? null;
$_settingsDrillDown = in_array($_REQUEST['view'] ?? '', ['updateTeam', 'updateMetagroup']);

$_paneClass = function(string $tab) use ($_activeTab): string {
    $active = $_activeTab ?? '';
    if ($active === 'teams') $active = 'groups';
    return $active === $tab ? ' show active' : '';
};

function _settings_nav_item(string $tab, string $icon, string $label, string $activeTab, bool $drillDown, string $self): void {
    if ($activeTab === 'teams') $activeTab = 'groups';
    $isActive   = $activeTab === $tab;
    $activeClass = $isActive ? ' active' : '';
    if ($drillDown) {
        $url = htmlspecialchars($self . '?view=settings&tab=' . $tab, ENT_QUOTES);
        echo '<li role="presentation">'
           . '<a class="ca-settings-nav-btn' . $activeClass . '" href="' . $url . '" style="text-decoration:none">'
           . '<i class="' . $icon . ' fa-fw" aria-hidden="true"></i>' . htmlspecialchars($label)
           . '</a></li>';
    } else {
        $ariaSelected = $isActive ? 'true' : 'false';
        echo '<li role="presentation">'
           . '<button class="ca-settings-nav-btn' . $activeClass . '" id="tab-' . $tab . '-btn"'
           . ' data-bs-toggle="tab" data-bs-target="#tab-' . $tab . '"'
           . ' type="button" role="tab" aria-controls="tab-' . $tab . '" aria-selected="' . $ariaSelected . '">'
           . '<i class="' . $icon . ' fa-fw" aria-hidden="true"></i>' . htmlspecialchars($label)
           . '</button></li>';
    }
}
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
          <?php
          $_navSelf = $_SERVER['PHP_SELF'];
          $_navActive = $_activeTab ?? 'groups';
          _settings_nav_item('groups',     'fas fa-users',       'Groupes',      $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('categories', 'fas fa-tag',         'Catégories',   $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('filters',    'fas fa-layer-group', 'Métagroupes',  $_navActive, $_settingsDrillDown, $_navSelf);
          if (isAdmin()):
          ?>
          <li role="presentation" class="ca-settings-nav-divider" aria-hidden="true"><?= $GLOBAL['administration'] ?></li>
          <?php
          _settings_nav_item('compta',     'fas fa-tags',        'Types compta', $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('settings',   'fas fa-sliders',   'Réglages',     $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('users',      'fas fa-user-shield', 'Utilisateurs', $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('audit',      'fas fa-clock-rotate-left',     'Journal',      $_navActive, $_settingsDrillDown, $_navSelf);
          _settings_nav_item('integrity',  'fas fa-stethoscope', 'Intégrité',    $_navActive, $_settingsDrillDown, $_navSelf);
          ?>
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
          <div class="tab-pane fade<?= $_paneClass('settings') ?>" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-btn">
            <?php if (!isAdmin()): ?>
            <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
            <?php else: ?>
            <div class="col-md-8">
            <div id="settings-save-msg"></div>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post"
                  hx-post="<?= $_SERVER['PHP_SELF'] ?>"
                  hx-target="#settings-save-msg"
                  hx-swap="innerHTML">
              <input type="hidden" name="action" value="saveSettings"/>
              <input type="hidden" name="view" value="settings"/>
              <p class="form-section-title"><i class="fas fa-building me-1" aria-hidden="true"></i>Organisation</p>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_name">Nom de l'organisation</label>
                <input type="text" name="org_name" id="s_org_name" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_name'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_address">Adresse</label>
                <input type="text" name="org_address" id="s_org_address" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_address'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="row g-2 mb-3" style="max-width:320px">
                <div class="col-4">
                  <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_npa">NPA</label>
                  <input type="text" name="org_npa" id="s_org_npa" class="form-control form-control-sm"
                         value="<?= htmlspecialchars($appSettings['org_npa'] ?? '', ENT_QUOTES, $charset) ?>">
                </div>
                <div class="col-8">
                  <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_city">Ville</label>
                  <input type="text" name="org_city" id="s_org_city" class="form-control form-control-sm"
                         value="<?= htmlspecialchars($appSettings['org_city'] ?? '', ENT_QUOTES, $charset) ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_org_country">Pays</label>
                <input type="text" name="org_country" id="s_org_country" class="form-control form-control-sm" style="max-width:320px"
                       value="<?= htmlspecialchars($appSettings['org_country'] ?? '', ENT_QUOTES, $charset) ?>">
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_membre_team_prefix">Préfixe des groupes membres</label>
                <p class="text-muted mb-2" style="font-size:0.78rem">Préfixe utilisé pour retrouver les groupes membres des années précédentes (ex: «Membre» pour les groupes «Membre 2025», «Membre 2026»…).</p>
                <input type="text" name="membre_team_prefix" id="s_membre_team_prefix" class="form-control form-control-sm" style="max-width:200px"
                       value="<?= htmlspecialchars($appSettings['membre_team_prefix'] ?? 'Membre', ENT_QUOTES, $charset) ?>">
              </div>
              <p class="form-section-title"><i class="fas fa-sliders me-1" aria-hidden="true"></i>Groupes</p>
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
              <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_member_no_coti_team">Groupe membres sans cotisation</label>
                <p class="text-muted mb-2" style="font-size:0.78rem">Membres considérés comme actifs sans payer de cotisation (bénévoles, comité…). Exclus du filtre «Aucune cotisation ces 3 dernières années». Laisser vide si non applicable.</p>
                <select name="member_no_coti_team" id="s_member_no_coti_team" class="form-select form-select-sm" style="max-width:320px">
                  <option value="0" <?= empty($appSettings['member_no_coti_team']) ? 'selected' : '' ?>>— Aucun —</option>
                  <?php foreach ($allTeams as $t): ?>
                  <option value="<?= (int)$t->id ?>" <?= (int)($appSettings['member_no_coti_team'] ?? 0) === (int)$t->id ? 'selected' : '' ?>>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $t->hidden ? ' (masqué)' : '' ?>
                  </option>
                  <?php endforeach ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['save'] ?></button>
            </form>
            </div>
            <?php endif ?>
          </div><!-- #tab-settings -->

          <!-- Types compta (admin only) -->
          <div class="tab-pane fade<?= $_paneClass('compta') ?>" id="tab-compta" role="tabpanel" aria-labelledby="tab-compta-btn">
            <?php if (isAdmin()): $ctEmbedded = true; $ctReturnView = 'settings'; $ctReturnTab = 'compta'; include __DIR__ . '/settings_compta_types.php'; else: ?>
            <div class="alert alert-danger mt-3" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
            <?php endif ?>
          </div><!-- #tab-compta -->

          <!-- Groupes -->
          <div class="tab-pane fade<?= $_paneClass('groups') ?>" id="tab-groups" role="tabpanel" aria-labelledby="tab-groups-btn">
            <div class="mt-1">
            <?php if (($_REQUEST['view'] ?? '') === 'updateTeam'): include __DIR__ . '/settings_group_edit.php'; else: include __DIR__ . '/settings_groups.php'; endif; ?>
            </div>
          </div><!-- #tab-groups -->

          <!-- Catégories -->
          <div class="tab-pane fade<?= $_paneClass('categories') ?>" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories-btn">
            <div class="mt-1 col-md-9">
            <p class="form-section-title" style="margin-top:0"><i class="fas fa-tag me-1" aria-hidden="true"></i>Catégories</p>
            <?php include __DIR__ . '/settings_categories.php'; ?>
            </div>
          </div><!-- #tab-categories -->

          <!-- Métagroupes -->
          <div class="tab-pane fade<?= $_paneClass('filters') ?>" id="tab-filters" role="tabpanel" aria-labelledby="tab-filters-btn">
            <div class="mt-1 col-md-9">
            <?php if (($_REQUEST['view'] ?? '') === 'updateMetagroup'): include __DIR__ . '/settings_filter_edit.php'; else: ?>
            <p class="form-section-title" style="margin-top:0"><i class="fas fa-layer-group me-1" aria-hidden="true"></i>Métagroupes</p>
            <?php include __DIR__ . '/settings_filters.php'; endif; ?>
            </div>
          </div><!-- #tab-filters -->

          <?php if (isAdmin()): ?>
          <!-- Utilisateurs app -->
          <div class="tab-pane fade<?= $_paneClass('users') ?>" id="tab-users" role="tabpanel" aria-labelledby="tab-users-btn">
            <?php include __DIR__ . '/settings_app_users.php'; ?>
          </div><!-- #tab-users -->

          <!-- Journal d'activité -->
          <div class="tab-pane fade<?= $_paneClass('audit') ?>" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
            <?php include __DIR__ . '/settings_audit_log.php'; ?>
          </div><!-- #tab-audit -->

          <!-- Intégrité -->
          <div class="tab-pane fade<?= $_paneClass('integrity') ?>" id="tab-integrity" role="tabpanel" aria-labelledby="tab-integrity-btn">
            <div class="mt-1 col-md-10">
            <?php include __DIR__ . '/settings_integrity.php'; ?>
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
        if (first) {
          bootstrap.Tab.getOrCreateInstance(first).show();
        } else {
          // drill-down mode: no tab buttons — activate pane directly via PHP show active
          var pane = document.querySelector(targetPane);
          if (pane) { pane.classList.add('show', 'active'); }
        }
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
