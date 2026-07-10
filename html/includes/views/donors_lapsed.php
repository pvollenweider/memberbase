<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists donors who gave in a prior year but not in the selected year.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

require_once __DIR__ . '/../lib/donor.php';
$rows  = mbGetLapsedDonors($pdo, $year);
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= appUrl() ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <?= sprintf($GLOBAL['lapsedDonorsTitle'], $year-1, $year) ?>
  </span>

  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lapsedDonors&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<button type="button" class="btn btn-outline-warning btn-sm mb-3"
        data-bs-toggle="modal" data-bs-target="#modal-create-lapsed-donors">
  <i class="fas fa-users me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['createSegmentLapsedDonors'], $year) ?>
</button>

<div class="modal fade" id="modal-create-lapsed-donors" tabindex="-1" aria-labelledby="modal-create-lapsed-donors-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-create-lapsed-donors-label"><?= $GLOBAL['createSegmentTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['confirmCreateLapsedDonorsSegment'], $year, $count) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= appUrl() ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"    value="createLapsedSegment">
          <input type="hidden" name="groupType" value="donors">
          <input type="hidden" name="year"      value="<?= $year ?>">
          <input type="hidden" name="view"      value="lapsedDonors">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-users me-1" aria-hidden="true"></i><?= $GLOBAL['create'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" role="status" style="font-size:0.85rem">
  <i class="fas fa-user-clock mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><?= sprintf($GLOBAL['lapsedDonorsCount'], $count, $count > 1 ? 's' : '', $year-1, $year) ?></span>
</div>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$dt_order      = [[6, 'desc']];
$extra_columns = [
    [
        'label'  => sprintf($GLOBAL['donYear'], $year - 1),
        'value'  => fn($row) => number_format((float)$row->total_prev, 2, '.', '\''),
        'style'  => 'text-align:right',
        'footer' => array_sum(array_map(fn($r) => (float)$r->total_prev, $rows)),
    ],
    [
        'label'  => $GLOBAL['lastDonation'],
        'value'  => fn($row) => timeStampToformatedDate($row->last_date),
        'style'  => '',
        'footer' => null,
    ],
];
$row_href = fn($row) => appUrl() . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
