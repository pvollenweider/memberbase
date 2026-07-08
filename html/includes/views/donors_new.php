<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists first-time donors for a given year.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

require_once __DIR__ . '/../lib/donor.php';
$rows  = mbGetNewDonors($pdo, $year);
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <?= sprintf($GLOBAL['newDonorsTitle'], $year) ?>
  </span>
  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=newDonors&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<div class="alert d-flex align-items-start gap-2 py-2 mb-3" role="status"
     style="font-size:0.85rem;background:rgba(255,193,7,0.12);border:1px solid rgba(255,193,7,0.4);border-radius:6px">
  <i class="fas fa-star mt-1 flex-shrink-0" aria-hidden="true" style="color:var(--bs-warning)"></i>
  <span><?= sprintf($GLOBAL['newDonorsCount'], $count, $count > 1 ? 'x' : '', $count > 1 ? 's' : '', $year, $year-1) ?></span>
</div>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$dt_order      = [[6, 'desc']];
$extra_columns = [
    [
        'label'  => sprintf($GLOBAL['donYear'], $year),
        'value'  => fn($row) => number_format((float)$row->total_curr, 2, '.', '\''),
        'style'  => 'text-align:right',
        'footer' => array_sum(array_map(fn($r) => (float)$r->total_curr, $rows)),
    ],
    [
        'label'  => $GLOBAL['firstDonation'],
        'value'  => fn($row) => timeStampToformatedDate($row->first_date),
        'style'  => '',
        'footer' => null,
    ],
];
$row_href = fn($row) => $_SERVER['PHP_SELF'] . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
