<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Import wizard — step 3: results and duplicate resolution.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_created    = (int)($_SESSION['_import_created']    ?? 0);
$_duplicates = $_SESSION['_import_duplicates'] ?? [];

if (!isset($_SESSION['_import_created'])) {
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=importStep1&err=session');
    exit;
}

$_fieldLabels = [
    'lastName'  => 'Nom',        'firstName' => 'Prénom',    'society'   => 'Société',
    'email'     => 'Email',      'emailAlt'  => 'Email alt.','tel'       => 'Tél. fixe',
    'telProf'   => 'Tél. prof.', 'portable'  => 'Mobile',    'fax'       => 'Fax',
    'address'   => 'Adresse',    'npa'       => 'NPA',       'web'       => 'Web',
    'birthDay'  => 'Naissance',  'comment'   => 'Remarques',
];
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <p class="form-section-title mb-1">
      <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer des contacts
    </p>
    <p class="small text-muted mb-4">Étape 3 sur 3 — Résultats de l'import.</p>

    <!-- Created summary -->
    <div class="alert <?= $_created > 0 ? 'alert-success' : 'alert-secondary' ?> py-2 px-3 mb-4" style="font-size:0.85rem">
      <i class="fas <?= $_created > 0 ? 'fa-circle-check' : 'fa-circle-info' ?> me-1"></i>
      <?php if ($_created > 0): ?>
        <strong><?= $_created ?></strong> contact<?= $_created > 1 ? 's' : '' ?> créé<?= $_created > 1 ? 's' : '' ?> avec succès.
      <?php else: ?>
        Aucun nouveau contact créé.
      <?php endif ?>
      <?php if (!empty($_duplicates)): ?>
        &nbsp;·&nbsp;<strong><?= count($_duplicates) ?></strong> doublon<?= count($_duplicates) > 1 ? 's' : '' ?> détecté<?= count($_duplicates) > 1 ? 's' : '' ?> — à traiter ci-dessous.
      <?php endif ?>
    </div>

    <?php if (empty($_duplicates)): ?>
    <!-- No duplicates — done -->
    <div class="d-flex gap-2">
      <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-list me-1" aria-hidden="true"></i>Voir la liste des membres
      </a>
    </div>
    <?php
    unset($_SESSION['_import_headers'], $_SESSION['_import_rows'], $_SESSION['_import_delimiter'],
          $_SESSION['_import_created'], $_SESSION['_import_duplicates']);
    ?>

    <?php else: ?>
    <!-- Duplicate resolution form -->
    <p class="form-section-title mb-1">Doublons détectés</p>
    <p class="small text-muted mb-3">
      Pour chaque doublon, choisissez l'action à effectuer sur le contact existant.
    </p>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" value="importResolveDuplicates">
      <input type="hidden" name="view"   value="importStep3">

      <?php foreach ($_duplicates as $i => $dup):
        $importedFields = array_filter($dup['data'], fn($v) => $v !== '');
      ?>
      <div class="card mb-3 border" style="font-size:0.82rem">
        <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:var(--bs-light)">
          <i class="fas fa-user-clock text-warning" aria-hidden="true"></i>
          <strong><?= htmlspecialchars(
              trim(($dup['data']['firstName'] ?? '') . ' ' . ($dup['data']['lastName'] ?? ''))
              ?: ($dup['data']['society'] ?? 'Sans nom'), ENT_QUOTES, $charset) ?></strong>
          <span class="text-muted ms-1" style="font-size:0.75rem">
            doublon de
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$dup['existingId'] ?>" target="_blank"
               class="text-muted">#<?= (int)$dup['existingId'] ?> <?= htmlspecialchars($dup['existingName'], ENT_QUOTES, $charset) ?></a>
          </span>
        </div>
        <div class="card-body py-2 px-3">
          <?php if (!empty($importedFields)): ?>
          <div class="mb-2" style="font-size:0.78rem;color:var(--ca-ink-muted)">
            <?php foreach ($importedFields as $field => $val): ?>
            <span class="me-3"><span class="text-muted"><?= $_fieldLabels[$field] ?? $field ?>:</span> <?= htmlspecialchars($val, ENT_QUOTES, $charset) ?></span>
            <?php endforeach ?>
          </div>
          <?php endif ?>
          <div class="d-flex gap-3">
            <label class="d-flex align-items-center gap-1" style="cursor:pointer">
              <input type="radio" name="choice[<?= $i ?>]" value="ignore" checked>
              <span>Ignorer</span>
            </label>
            <label class="d-flex align-items-center gap-1" style="cursor:pointer">
              <input type="radio" name="choice[<?= $i ?>]" value="fill">
              <span>Compléter les champs vides</span>
            </label>
            <label class="d-flex align-items-center gap-1" style="cursor:pointer">
              <input type="radio" name="choice[<?= $i ?>]" value="overwrite">
              <span class="text-danger">Écraser</span>
            </label>
          </div>
        </div>
      </div>
      <?php endforeach ?>

      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-check me-1" aria-hidden="true"></i>Appliquer les choix
        </button>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm">Terminer sans appliquer</a>
      </div>
    </form>
    <?php endif ?>

  </div>
</div>
