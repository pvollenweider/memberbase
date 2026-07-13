<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members who paid a cotisation this year but not the year before
 * (new or returning-after-a-gap members).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }
$_pfEmbedded = $_pfEmbedded ?? false;
$_selfQuery  = !empty($_pfEmbedded) ? 'view=peopleFinance&tab=lapsed&cohort=new' : 'view=newMembers';

require_once __DIR__ . '/../lib/cotisation.php';
$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$rows        = mbGetNewMembers(db(), $year, $cotiTypeIds);
$count       = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <?php if (empty($_pfEmbedded)): ?>
  <a href="<?= appUrl() ?>?view=peopleFinance&amp;tab=members&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm" hx-boost="false">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToMemberOverview'] ?>
  </a>
  <?php endif ?>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <?= sprintf($GLOBAL['newMembersTitle'], $year) ?>
  </span>
  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= appUrl() ?>?<?= $_selfQuery ?>&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<?php if (empty($cotiTypeIds)): ?>
<div class="alert alert-secondary" style="font-size:0.85rem">
  <?= $GLOBAL['noComptaCotiType'] ?>
</div>
<?php else: ?>

<div class="alert d-flex align-items-start gap-2 py-2 mb-3" role="status"
     style="font-size:0.85rem;background:rgba(255,193,7,0.12);border:1px solid rgba(255,193,7,0.4);border-radius:6px">
  <i class="fas fa-star mt-1 flex-shrink-0" aria-hidden="true" style="color:var(--bs-warning)"></i>
  <span><?= sprintf($GLOBAL['newMembersCount'], $count, $count > 1 ? 's' : '', $year, $year - 1) ?></span>
</div>

<?php
$dt_order      = [[1, 'asc']];
$extra_columns = [];
$row_href      = fn($row) => appUrl() . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
<?php endif ?>
