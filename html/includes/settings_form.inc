<?php
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
$saved = isset($_GET['saved']);
?>
<div class="row justify-content-center mt-4">
  <div class="col-md-8">

    <ul class="nav nav-tabs mb-3" id="settings-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-settings-btn"
                data-bs-toggle="tab" data-bs-target="#tab-settings"
                type="button" role="tab"
                aria-controls="tab-settings" aria-selected="true">
          <i class="fas fa-cog me-1" aria-hidden="true"></i>Réglages
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-compta-btn"
                data-bs-toggle="tab" data-bs-target="#tab-compta"
                type="button" role="tab"
                aria-controls="tab-compta" aria-selected="false">
          <i class="fas fa-tags me-1" aria-hidden="true"></i>Types de compta
        </button>
      </li>
    </ul>

    <div class="tab-content">

    <div class="tab-pane fade show active" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings-btn">

      <?php if ($saved): ?>
      <div class="alert alert-success py-2 px-3 mb-3" style="font-size:0.85rem" role="status">Réglages sauvegardés.</div>
      <?php endif ?>

      <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
        <input type="hidden" name="action" value="saveSettings"/>
        <input type="hidden" name="view" value="settings"/>

        <!-- Default team -->
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

        <!-- Membre team -->
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

        <!-- Archive ID -->
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

    </div><!-- #tab-settings -->

    <div class="tab-pane fade" id="tab-compta" role="tabpanel" aria-labelledby="tab-compta-btn">
      <?php $ctEmbedded = true; $ctReturnView = 'settings'; include 'manage_compta_types.inc'; ?>
    </div><!-- #tab-compta -->

    </div><!-- .tab-content -->

    <script>
    (function() {
      var STORAGE_KEY = 'settings_activeTab';
      var tabs = document.getElementById('settings-tabs');
      if (!tabs) return;
      var saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        var btn = tabs.querySelector('[data-bs-target="' + saved + '"]');
        if (btn) { bootstrap.Tab.getOrCreateInstance(btn).show(); }
      }
      tabs.addEventListener('shown.bs.tab', function(e) {
        sessionStorage.setItem(STORAGE_KEY, e.target.getAttribute('data-bs-target'));
      });
    })();
    </script>

  </div>
</div>
