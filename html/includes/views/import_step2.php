<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Import wizard — step 2: column mapping.
 *
 * @copyright 2026 Philippe Vollenweider
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

// Segments (teams) and categories for the "add to segment" section
$_segTeams  = $pdo->query("SELECT id, name FROM segment WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
$_segCats   = $pdo->query("SELECT id, name FROM metagroup WHERE name IS NOT NULL AND is_filter = 0 GROUP BY id, name ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
$_segAutoName = sprintf($GLOBAL['importSegmentName'], date('d.m.Y H:i'));

require_once __DIR__ . '/../lib/import_fields.php';
$_memberFields = ['' => $GLOBAL['ignoreField']] + importFieldLabels();

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
      <i class="fas fa-file-import me-1" aria-hidden="true"></i><?= $GLOBAL['importContacts'] ?>
    </p>
    <p class="small text-muted mb-4">
      <?= $GLOBAL['importStep2Subtitle'] ?><br>
      <span class="text-muted"><?= sprintf($GLOBAL['rowsDetected'], count($_rows), count($_rows) > 1 ? 's' : '', count($_rows) > 1 ? 's' : '') ?></span>
    </p>

    <?php if (!empty($_SESSION['_import_truncated'])): ?>
    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>
      <?= $GLOBAL['importTruncatedWarning'] ?>
    </div>
    <?php endif ?>

    <?php if (($_GET['err'] ?? '') === 'nomap'): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem">
      <i class="fas fa-triangle-exclamation me-1"></i>
      <?= $GLOBAL['importErrNoMapping'] ?>
    </div>
    <?php endif ?>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" value="importApply">
      <input type="hidden" name="view"   value="importStep2">

      <table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
        <thead>
          <tr>
            <th style="width:22%"><?= $GLOBAL['fileColumn'] ?></th>
            <th style="width:28%"><?= $GLOBAL['memberField'] ?></th>
            <th><?= $GLOBAL['examples'] ?></th>
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

      <p class="form-section-title mt-4"><?= $GLOBAL['addContactsToSegment'] ?></p>
      <div x-data="{ mode: 'auto' }" class="mb-4" style="max-width:560px">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="segment_mode" id="seg-auto" value="auto" x-model="mode" data-no-dirty checked>
          <label class="form-check-label" for="seg-auto" style="font-size:0.85rem">
            <?= sprintf($GLOBAL['createSegmentNamed'], htmlspecialchars($_segAutoName, ENT_QUOTES, $charset)) ?>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="segment_mode" id="seg-existing" value="existing" x-model="mode" data-no-dirty <?= empty($_segTeams) ? 'disabled' : '' ?>>
          <label class="form-check-label" for="seg-existing" style="font-size:0.85rem"><?= $GLOBAL['addToExistingSegment'] ?></label>
          <div class="mt-1" x-show="mode === 'existing'" x-cloak>
            <select name="segment_existing_id" class="form-select form-select-sm" data-no-dirty style="max-width:320px">
              <?php foreach ($_segTeams as $_t): ?>
              <option value="<?= (int)$_t->id ?>"><?= htmlspecialchars($_t->name, ENT_QUOTES, $charset) ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="segment_mode" id="seg-new" value="new" x-model="mode" data-no-dirty>
          <label class="form-check-label" for="seg-new" style="font-size:0.85rem"><?= $GLOBAL['createNewSegment'] ?></label>
          <div class="mt-1 d-flex flex-column gap-2" x-show="mode === 'new'" x-cloak style="max-width:320px">
            <input type="text" name="segment_new_name" class="form-control form-control-sm" data-no-dirty
                   placeholder="<?= $GLOBAL['teamName'] ?>" maxlength="64">
            <?php if (!empty($_segCats)): ?>
            <select name="segment_new_category" class="form-select form-select-sm" data-no-dirty>
              <option value="0"><?= $GLOBAL['noCategoryOption'] ?></option>
              <?php foreach ($_segCats as $_c): ?>
              <option value="<?= (int)$_c->id ?>"><?= htmlspecialchars($_c->name, ENT_QUOTES, $charset) ?></option>
              <?php endforeach ?>
            </select>
            <?php endif ?>
          </div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="segment_mode" id="seg-none" value="none" x-model="mode" data-no-dirty>
          <label class="form-check-label" for="seg-none" style="font-size:0.85rem"><?= $GLOBAL['doNotAddToSegment'] ?></label>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-arrow-right me-1" aria-hidden="true"></i><?= $GLOBAL['import'] ?>
        </button>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=importStep1" class="btn btn-outline-secondary btn-sm"><?= $GLOBAL['back'] ?></a>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary btn-sm ms-auto"><?= $GLOBAL['cancel'] ?></a>
      </div>
    </form>

  </div>
</div>
