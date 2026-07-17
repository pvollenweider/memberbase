<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Import wizard — step 3: results and duplicate resolution.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_created    = (int)($_SESSION['_import_created']    ?? 0);
$_duplicates = $_SESSION['_import_duplicates'] ?? [];
$_segment    = $_SESSION['_import_segment']    ?? null;

if (!isset($_SESSION['_import_created'])) {
    header('Location: ' . appUrl() . '?view=importStep1&err=session');
    exit;
}

require_once __DIR__ . '/../lib/import_fields.php';
$_fieldLabels = importFieldLabels();
$_noOuterContainer = true;
$_phIcon = 'fa-file-import';
$_phTitle = $GLOBAL['import'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">
<div class="row justify-content-center mt-4">
  <div class="col-12 col-xl-10">

    <p class="form-section-title mb-1">
      <i class="fas fa-file-import me-1" aria-hidden="true"></i><?= $GLOBAL['importContacts'] ?>
    </p>
    <p class="small text-muted mb-4"><?= $GLOBAL['importStep3Subtitle'] ?></p>

    <!-- Created summary -->
    <div class="alert <?= $_created > 0 ? 'alert-success' : 'alert-secondary' ?> py-2 px-3 mb-4" style="font-size:0.85rem">
      <i class="fas <?= $_created > 0 ? 'fa-circle-check' : 'fa-circle-info' ?> me-1"></i>
      <?php if ($_created > 0): ?>
        <?= sprintf($GLOBAL['contactsCreated'], $_created, $_created > 1 ? 's' : '', $_created > 1 ? 's' : '') ?>
      <?php else: ?>
        <?= $GLOBAL['noNewContacts'] ?>
      <?php endif ?>
      <?php if (!empty($_duplicates)): ?>
        &nbsp;·&nbsp;<?= sprintf($GLOBAL['duplicatesDetectedCount'], count($_duplicates), count($_duplicates) > 1 ? 's' : '', count($_duplicates) > 1 ? 's' : '') ?>
      <?php endif ?>
    </div>

    <?php if ($_segment): ?>
    <div class="alert alert-info py-2 px-3 mb-4" style="font-size:0.85rem">
      <i class="fas fa-users me-1" aria-hidden="true"></i>
      <?= sprintf($GLOBAL['contactsAddedToSegment'], (int)$_segment['added'], (int)$_segment['added'] > 1 ? 's' : '', (int)$_segment['added'] > 1 ? 's' : '') ?>
      <a href="<?= appUrl() ?>?segment=<?= (int)$_segment['id'] ?>" class="alert-link"><?= htmlspecialchars($_segment['name'], ENT_QUOTES, $charset) ?></a>.
    </div>
    <?php endif ?>

    <?php if (empty($_duplicates)): ?>
    <!-- No duplicates — done -->
    <div class="d-flex gap-2">
      <a href="<?= appUrl() ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-list me-1" aria-hidden="true"></i><?= $GLOBAL['viewMemberList'] ?>
      </a>
    </div>
    <?php unset($_SESSION['_import_created'], $_SESSION['_import_duplicates'], $_SESSION['_import_segment']); ?>

    <?php else: ?>
    <!-- Duplicate resolution form -->
    <p class="form-section-title mb-1"><?= $GLOBAL['duplicatesDetected'] ?></p>
    <p class="small text-muted mb-3">
      <?= $GLOBAL['duplicateResolutionHint'] ?>
    </p>

    <form action="<?= appUrl() ?>" method="post">
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
              ?: ($dup['data']['society'] ?? $GLOBAL['noName']), ENT_QUOTES, $charset) ?></strong>
          <span class="text-muted ms-1" style="font-size:0.75rem">
            <?= $GLOBAL['duplicateOf'] ?>
            <a href="<?= appUrl() ?>?view=updateUser&id=<?= (int)$dup['existingId'] ?>" target="_blank"
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
              <input type="radio" name="choice[<?= $i ?>]" value="ignore" checked data-no-dirty>
              <span><?= $GLOBAL['ignore'] ?></span>
            </label>
            <label class="d-flex align-items-center gap-1" style="cursor:pointer">
              <input type="radio" name="choice[<?= $i ?>]" value="fill" data-no-dirty>
              <span><?= $GLOBAL['fillEmptyFields'] ?></span>
            </label>
            <label class="d-flex align-items-center gap-1" style="cursor:pointer">
              <input type="radio" name="choice[<?= $i ?>]" value="overwrite" data-no-dirty>
              <span class="text-danger"><?= $GLOBAL['overwrite'] ?></span>
            </label>
          </div>
        </div>
      </div>
      <?php endforeach ?>

      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-check me-1" aria-hidden="true"></i><?= $GLOBAL['applyChoices'] ?>
        </button>
        <a href="<?= appUrl() ?>" class="btn btn-outline-secondary btn-sm"><?= $GLOBAL['finishWithoutApplying'] ?></a>
      </div>
    </form>
    <?php endif ?>

  </div>
</div>
</div>
