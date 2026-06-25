<?php
$allTeams = $pdo->query("SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);
$saved = isset($_GET['saved']);
?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <div>
      <p class="form-section-title" style="margin-top:0">Réglages</p>

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
          <p class="text-muted mb-2" style="font-size:0.78rem">Utilisé comme référence pour les filtres cotisations (cotis non payées, etc.). Met à jour chaque année avec le groupe membres de l'année écoulée.</p>
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

    <div>
      <p class="form-section-title">Types de compta</p>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=manageComptaTypes" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-tags me-1" aria-hidden="true"></i>Gérer les types
      </a>
    </div>

  </div>
</div>
