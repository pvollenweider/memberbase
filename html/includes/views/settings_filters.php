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
try {
    $allFilters = db()->query("SELECT m.id, m.name, COUNT(mm.segment_id) AS team_count FROM combined_segment m LEFT JOIN combined_segment_member mm ON mm.combined_segment_id=m.id WHERE m.is_filter=1 GROUP BY m.id, m.name ORDER BY m.name")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $allFilters = [];
}
?>
<?php if (count($allFilters) > 0): ?>
<table class="table table-sm table-hover align-middle mb-3" style="font-size:0.82rem">
  <tbody>
  <?php foreach ($allFilters as $mg): ?>
    <tr>
      <td>
        <a href="<?= appUrl() ?>?combinedSegment=<?= (int)$mg->id ?>" class="text-decoration-none">
          <?= htmlentities($mg->name, ENT_COMPAT, $charset) ?>
        </a>
      </td>
      <td class="text-muted" style="font-size:0.75rem;width:5rem"><?= sprintf($GLOBAL['segmentCount'], (int)$mg->team_count, $mg->team_count != 1 ? 's' : '') ?></td>
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
