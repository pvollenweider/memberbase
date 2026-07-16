<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin UI for managing navigation filters (combined segments).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
?>
<p class="small text-muted mb-3"><?= $GLOBAL['filtersHelp'] ?></p>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$_filtersLoadError = false;
$_filterMembers = [];
try {
    $allFilters = db()->query("SELECT m.id, m.name, COUNT(mm.segment_id) AS segment_count FROM combined_segment m LEFT JOIN combined_segment_member mm ON mm.combined_segment_id=m.id WHERE m.is_filter=1 GROUP BY m.id, m.name ORDER BY m.name")->fetchAll(PDO::FETCH_OBJ);
    // Segment composition per filter, for inline display (avoids a click-through to "Edit" just to see what's inside).
    $_filterMemberRows = db()->query("
        SELECT mm.combined_segment_id, s.name
        FROM combined_segment_member mm
        JOIN segment s ON s.id = mm.segment_id
        JOIN combined_segment m ON m.id = mm.combined_segment_id AND m.is_filter = 1
        ORDER BY mm.combined_segment_id, s.name
    ")->fetchAll(PDO::FETCH_OBJ);
    foreach ($_filterMemberRows as $_fmr) {
        $_filterMembers[(int)$_fmr->combined_segment_id][] = $_fmr->name;
    }
} catch (PDOException $e) {
    $allFilters = [];
    $_filtersLoadError = true;
}
?>
<?php if ($_filtersLoadError): ?>
<div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem">
  <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['loadError'] ?>
</div>
<?php elseif (count($allFilters) > 0): ?>
<table class="table table-sm table-hover align-middle mb-3" style="font-size:0.82rem">
  <tbody>
  <?php foreach ($allFilters as $mg): ?>
    <tr>
      <td>
        <a href="<?= appUrl() ?>?combinedSegment=<?= (int)$mg->id ?>" class="text-decoration-none">
          <?= htmlentities($mg->name, ENT_COMPAT, $charset) ?>
        </a>
        <?php $_members = $_filterMembers[(int)$mg->id] ?? []; if (!empty($_members)): ?>
        <div class="mt-1">
          <?php foreach ($_members as $_mName): ?>
          <span class="badge text-bg-light border me-1 mb-1" style="font-size:0.68rem;font-weight:500"><?= htmlentities($_mName, ENT_COMPAT, $charset) ?></span>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </td>
      <td class="text-muted" style="font-size:0.75rem;width:5rem"><?= sprintf($GLOBAL['segmentCount'], (int)$mg->segment_count, $mg->segment_count != 1 ? 's' : '') ?></td>
      <td class="text-end" style="width:2rem">
        <a href="<?= appUrl() ?>?view=updateCombinedSegment&amp;id=<?= (int)$mg->id ?>" class="text-decoration-none text-muted" title="<?= $GLOBAL['edit'] ?>">
          <i class="fas fa-pen" style="font-size:0.75rem"></i>
        </a>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php else: ?>
<p class="text-muted small mb-3"><?= $GLOBAL['noFilters'] ?></p>
<?php endif ?>

<form action="<?= appUrl() ?>" method="post">
  <input type="hidden" name="action" value="addCombinedSegment"/>
  <input type="hidden" name="view" value="settings"/>
  <input type="hidden" name="tab" value="filters"/>
  <input type="hidden" name="is_filter" value="1"/>
  <div class="d-flex align-items-center gap-2">
    <input type="text" class="form-control form-control-sm" name="name" placeholder="<?= $GLOBAL['filterNamePlaceholder'] ?>" maxlength="255" required style="max-width:240px"/>
    <button type="submit" class="btn btn-outline-primary btn-sm flex-shrink-0"><?= $GLOBAL['addBtn'] ?></button>
  </div>
</form>
