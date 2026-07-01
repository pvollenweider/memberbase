<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Import wizard — step 2: column mapping.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_headers = $_SESSION['_import_headers'] ?? [];
$_rows    = $_SESSION['_import_rows']    ?? [];

if (empty($_headers) || empty($_rows)) {
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=importStep1&err=session');
    exit;
}

// Scan up to 25 rows for examples — the first row is often sparse
$_preview = array_slice($_rows, 0, 25);

require_once __DIR__ . '/../lib/import_fields.php';
$_memberFields = ['' => '— ignorer —'] + importFieldLabels();

// Auto-detect mapping by matching header labels (case-insensitive)
$_autoMap = [
    'nom'         => 'lastName',  'name'        => 'lastName',  'lastname'    => 'lastName',
    'prénom'      => 'firstName', 'prenom'      => 'firstName', 'firstname'   => 'firstName',
    'société'     => 'society',   'societe'     => 'society',   'company'     => 'society', 'organisation' => 'society',
    'genre'       => 'sexe',      'civilité'    => 'sexe',      'civilite'    => 'sexe',    'sexe'  => 'sexe',
    'titre'       => 'title',
    'email'       => 'email',     'e-mail'      => 'email',     'courriel'    => 'email',
    'email alt'   => 'emailAlt',  'email2'      => 'emailAlt',  'email alt.'  => 'emailAlt',
    'tel'         => 'tel',       'téléphone'   => 'tel',       'telephone'   => 'tel',     'phone' => 'tel',
    'tel. prof'   => 'telProf',   'tel prof'    => 'telProf',   'tél. prof'   => 'telProf',
    'mobile'      => 'portable',  'portable'    => 'portable',  'gsm'         => 'portable',
    'fax'         => 'fax',
    'adresse'     => 'address',   'address'     => 'address',   'rue'         => 'address',
    'npa'         => 'npa',       'cp'          => 'npa',       'code postal' => 'npa',     'ville' => 'npa',
    'npa / localité' => 'npa',    'npa / localite' => 'npa',    'localité'    => 'npa',     'localite' => 'npa',
    'web'         => 'web',       'site'        => 'web',       'url'         => 'web',
    'naissance'   => 'birthDay',  'birthday'    => 'birthDay',  'date naissance' => 'birthDay',
    'remarques'   => 'comment',   'comment'     => 'comment',   'note'        => 'comment', 'notes' => 'comment',
];
?>
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <p class="form-section-title mb-1">
      <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer des contacts
    </p>
    <p class="small text-muted mb-4">
      Étape 2 sur 3 — Associez chaque colonne du fichier à un champ membre.<br>
      <span class="text-muted"><?= count($_rows) ?> ligne<?= count($_rows) > 1 ? 's' : '' ?> détectée<?= count($_rows) > 1 ? 's' : '' ?>.</span>
    </p>

    <?php if (!empty($_SESSION['_import_truncated'])): ?>
    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>
      Le fichier dépasse la limite de 5 000 lignes — seules les 5 000 premières seront importées.
      Les lignes suivantes sont <strong>ignorées</strong>. Découpez le fichier pour importer le reste.
    </div>
    <?php endif ?>

    <?php if (($_GET['err'] ?? '') === 'nomap'): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>
      Aucune colonne n'est associée à un champ membre — sélectionnez au moins un champ.
    </div>
    <?php endif ?>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" value="importApply">
      <input type="hidden" name="view"   value="importStep2">

      <table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
        <thead>
          <tr>
            <th style="width:22%">Colonne fichier</th>
            <th style="width:28%">Champ membre</th>
            <th>Exemples</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($_headers as $i => $header):
          $_normalKey = mb_strtolower(trim($header));
          $_autoField = $_autoMap[$_normalKey] ?? '';
        ?>
          <tr>
            <td class="text-muted"><?= htmlspecialchars($header, ENT_QUOTES, $charset) ?></td>
            <td>
              <select name="mapping[<?= $i ?>]" class="form-select form-select-sm" data-no-dirty>
                <?php foreach ($_memberFields as $val => $label): ?>
                <option value="<?= $val ?>" <?= $val === $_autoField ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, $charset) ?></option>
                <?php endforeach ?>
              </select>
            </td>
            <td class="text-muted" style="font-size:0.75rem">
              <?php
              $samples = [];
              foreach ($_preview as $r) {
                  $v = trim($r[$i] ?? '');
                  if ($v !== '' && !in_array($v, $samples, true)) $samples[] = $v;
                  if (count($samples) >= 3) break;
              }
              echo htmlspecialchars(implode(' · ', $samples), ENT_QUOTES, $charset);
              ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-arrow-right me-1" aria-hidden="true"></i>Importer
        </button>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=importStep1" class="btn btn-outline-secondary btn-sm">Retour</a>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm ms-auto">Annuler</a>
      </div>
    </form>

  </div>
</div>
