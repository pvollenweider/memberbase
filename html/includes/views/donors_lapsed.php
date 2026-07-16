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
$_pfEmbedded = $_pfEmbedded ?? false;
$_selfQuery  = !empty($_pfEmbedded) ? 'view=peopleFinance&tab=lapsedDonors' : 'view=lapsedDonors';

require_once __DIR__ . '/../lib/donor.php';
$rows  = mbGetLapsedDonors(db(), $year);
$count = count($rows);
?>
<div class="card mb-4">
<div class="card-header d-flex align-items-center gap-2 flex-wrap">
  <?php if (empty($_pfEmbedded)): ?>
  <a href="<?= appUrl() ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
  <?php endif ?>
  <span class="me-2"><?= sprintf($GLOBAL['lapsedDonorsTitle'], $year-1, $year) ?></span>

  <div class="vr d-none d-sm-block mx-1" aria-hidden="true"></div>

  <div class="dropdown">
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

  <?php if (isManager()): ?>
  <button type="button" class="btn btn-outline-warning btn-sm ms-auto"
          data-bs-toggle="modal" data-bs-target="#modal-create-lapsed-donors">
    <i class="fas fa-users me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['createSegmentLapsedDonors'], $year) ?>
  </button>
  <?php endif ?>
</div><!-- .card-header -->
<div class="card-body">

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
          <input type="hidden" name="view"      value="peopleFinance">
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
        'value'  => fn($row) => timeStampToformatedDate($row->last_date ? strtotime($row->last_date) : 0),
        'style'  => '',
        'footer' => null,
    ],
];
$row_href = fn($row) => appUrl() . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
</div><!-- .card-body -->
</div><!-- .card -->
