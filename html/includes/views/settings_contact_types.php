<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Contact type classification review tool (issue #165) — suggests
 * private/institution/financial/company for every active contact based on
 * their compta entries' type flags (+ society field for "company"), shown
 * for admin review before any write. Nothing is applied without an explicit
 * selection + submit — see includes/actions/settings.php's
 * applyContactTypes handler.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/../lib/contact_type.php';

$_ctEmbedded = $_ctEmbedded ?? false;
$_ctAppliedCount = isset($_GET['contactTypesApplied']) ? (int)$_GET['contactTypesApplied'] : null;

$_ctFlaggedTypes = (int)db()->query(
    "SELECT COUNT(*) FROM compta_type WHERE is_institutional=1 OR is_financial_institution=1 OR is_company=1"
)->fetchColumn();

$_ctSuggestions = $_ctFlaggedTypes > 0 ? mbSuggestContactTypes(db()) : [];
$_ctDiffs = array_filter($_ctSuggestions, fn($r) => $r->suggested_type_id !== (int)$r->current_type_id);
?>
<?php if (!$_ctEmbedded): ?>
<div class="row justify-content-center mt-4">
  <div class="col-lg-10">
<?php endif ?>

<p class="form-section-title" style="margin-top:0"><?= $GLOBAL['contactTypesTitle'] ?></p>
<p class="text-muted small"><?= $GLOBAL['contactTypesHelp'] ?></p>

<?php if ($_ctAppliedCount !== null): ?>
<div class="alert alert-success py-2" role="alert">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
  <?= sprintf($GLOBAL['contactTypesAppliedMsg'], $_ctAppliedCount) ?>
</div>
<?php endif ?>

<?php if ($_ctFlaggedTypes === 0): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 py-2" role="alert">
  <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
  <div><?= $GLOBAL['contactTypesNoFlaggedTypes'] ?>
    <a href="<?= appUrl() ?>?view=settings&amp;tab=compta"><?= $GLOBAL['comptaTypes'] ?></a>.
  </div>
</div>
<?php elseif (empty($_ctDiffs)): ?>
<p class="text-muted"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['contactTypesNoDiffs'] ?></p>
<?php else: ?>

<form method="post" action="<?= appUrl() ?>">
  <input type="hidden" name="action" value="applyContactTypes">
  <input type="hidden" name="returnView" value="<?= $_ctEmbedded ? 'settings' : 'contactTypes' ?>">
  <input type="hidden" name="returnTab" value="contactTypes">

  <div class="d-flex align-items-center justify-content-between mb-2">
    <span class="text-muted small"><?= sprintf($GLOBAL['contactTypesDiffCount'], count($_ctDiffs)) ?></span>
    <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['contactTypesApplySelection'] ?></button>
  </div>

  <div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:32px">
          <input type="checkbox" id="ct-check-all" class="form-check-input" checked
                 aria-label="<?= htmlspecialchars($GLOBAL['selectAll'], ENT_QUOTES, $charset) ?>">
        </th>
        <th><?= $GLOBAL['society'] ?></th>
        <th><?= $GLOBAL['lastName'] ?></th>
        <th><?= $GLOBAL['firstName'] ?></th>
        <th><?= $GLOBAL['contactTypeCurrent'] ?></th>
        <th><?= $GLOBAL['contactTypeSuggested'] ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_ctDiffs as $_r): ?>
      <tr>
        <td>
          <input type="checkbox" class="form-check-input ct-row-check" checked
                 name="apply[<?= (int)$_r->id ?>]" value="<?= (int)$_r->suggested_type_id ?>"
                 aria-label="<?= htmlspecialchars(trim($_r->lastname . ' ' . $_r->firstname), ENT_QUOTES, $charset) ?>">
        </td>
        <td class="text-nowrap"><?= htmlspecialchars($_r->society ?? '', ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($_r->lastname, ENT_QUOTES, $charset) ?></td>
        <td class="text-nowrap"><?= htmlspecialchars($_r->firstname, ENT_QUOTES, $charset) ?></td>
        <td class="text-muted"><?= htmlspecialchars($_r->current_label, ENT_QUOTES, $charset) ?></td>
        <td><span class="fw-semibold"><?= htmlspecialchars($_r->suggested_label, ENT_QUOTES, $charset) ?></span></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
</form>

<script>
document.getElementById('ct-check-all')?.addEventListener('change', function () {
  document.querySelectorAll('.ct-row-check').forEach((cb) => { cb.checked = this.checked; });
});
</script>

<?php endif ?>

<?php if (!$_ctEmbedded): ?>
  </div>
</div>
<?php endif ?>
