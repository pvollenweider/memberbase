<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members whose membership lapsed compared to the prior year.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

// Members who paid a cotisation for year-1 but not for year (by cotisation_year).
$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$rows = [];
if (!empty($cotiTypeIds)) {
    $ph = implode(',', array_fill(0, count($cotiTypeIds), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        FROM users u
        WHERE u.status = 1
          AND EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
          )
          AND NOT EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
          )
        ORDER BY u.lastname, u.firstname, u.society
    ");
    $params = array_merge(
        array_values($cotiTypeIds), [$year - 1],
        array_values($cotiTypeIds), [$year]
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$count = count($rows);
$prevTeamId = 1; // non-zero so the table renders
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['backToDonationOverview'] ?>
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <?= sprintf($GLOBAL['lapsedMembersTitle'], $year-1, $year) ?>
  </span>

  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedMembers&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<?php if (empty($cotiTypeIds)): ?>
<div class="alert alert-secondary" style="font-size:0.85rem">
  <?= $GLOBAL['noComptaCotiType'] ?>
</div>
<?php else: ?>

<button type="button" class="btn btn-outline-warning btn-sm mb-3"
        data-bs-toggle="modal" data-bs-target="#modal-create-lapsed-members">
  <i class="fas fa-users me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['createSegmentLapsedMembers'], $year) ?>
</button>

<div class="modal fade" id="modal-create-lapsed-members" tabindex="-1" aria-labelledby="modal-create-lapsed-members-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-create-lapsed-members-label"><?= $GLOBAL['createSegmentTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['confirmCreateLapsedMembersSegment'], $year, $count) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"    value="createLapsedGroup">
          <input type="hidden" name="groupType" value="members">
          <input type="hidden" name="year"      value="<?= $year ?>">
          <input type="hidden" name="view"      value="lapsedMembers">
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
  <span><?= sprintf($GLOBAL['lapsedMembersCount'], $count, $count > 1 ? 's' : '', $year-1, $year) ?></span>
</div>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$dt_order      = [[1, 'asc']];
$extra_columns = [];
$row_href      = fn($row) => $_SERVER['PHP_SELF'] . '?view=compta&userid=' . (int)$row->id;
include __DIR__ . '/../partials/donor_table.php';
?>
<?php endif ?>
