<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Import wizard — step 1: file upload.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_err = $_GET['err'] ?? '';
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-md-7 col-lg-5">

    <p class="form-section-title mb-1">
      <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer des contacts
    </p>
    <p class="small text-muted mb-4">Étape 1 sur 3 — Sélectionnez un fichier CSV ou TSV.</p>

    <?php if ($_err === 'upload'): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>Erreur lors de l'envoi du fichier. Vérifiez que le fichier est bien sélectionné.
    </div>
    <?php elseif ($_err === 'toobig'): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>Fichier trop volumineux (maximum 5 MB).
    </div>
    <?php elseif ($_err === 'empty'): ?>
    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>Le fichier semble vide ou ne contient pas de données valides.
    </div>
    <?php elseif ($_err === 'session'): ?>
    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>Session expirée — veuillez recommencer l'import.
    </div>
    <?php endif ?>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="importUpload">
      <input type="hidden" name="view"   value="importStep1">

      <div class="mb-4">
        <label for="csv-file" class="form-label" style="font-size:0.85rem">Fichier CSV / TSV</label>
        <input type="file" id="csv-file" name="csv" class="form-control form-control-sm"
               accept=".csv,.tsv,.txt" required data-no-dirty>
        <div class="form-text">
          Formats acceptés : CSV (virgule ou point-virgule), TSV (tabulation).<br>
          Encodage UTF-8 ou Latin-1. Première ligne = en-têtes de colonnes.<br>
          Limite : 5 000 lignes par import.
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-arrow-right me-1" aria-hidden="true"></i>Suivant
        </button>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Annuler</a>
      </div>
    </form>

  </div>
</div>
