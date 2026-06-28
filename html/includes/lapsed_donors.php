<?php
/**
 * Lists donors who gave in a prior year but not in the selected year.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

$kFrom  = mktime(0, 0, 0, 1, 0, $year);
$kTo    = mktime(0, 0, 0, 1, 1, $year + 1);
$kFrom1 = mktime(0, 0, 0, 1, 0, $year - 1);
$kTo1   = mktime(0, 0, 0, 1, 1, $year);

$excl = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";

// Donors in year-1 who did NOT donate in year
$sql = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           SUM(c.sum) AS total_prev,
           MAX(c.date) AS last_date
    FROM users u
    JOIN compta c ON u.id = c.user_id
    WHERE u.status=1 AND c.date > ? AND c.date < ?
      AND c.type_id NOT IN ($excl)
      AND u.id NOT IN (
          SELECT DISTINCT user_id FROM compta
          WHERE date > ? AND date < ?
            AND type_id NOT IN ($excl)
      )
    GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
    ORDER BY total_prev DESC, u.lastname, u.firstname
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo]);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Retour à l'aperçu des dons
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    Donateurs perdus <?= $year-1 ?> → <?= $year ?>
  </span>

  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedDonors&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<button type="button" class="btn btn-outline-warning btn-sm mb-3"
        data-bs-toggle="modal" data-bs-target="#modal-create-lapsed-donors">
  <i class="fas fa-users me-1" aria-hidden="true"></i>Créer groupe «Donateurs à relancer <?= $year ?>»
</button>

<div class="modal fade" id="modal-create-lapsed-donors" tabindex="-1" aria-labelledby="modal-create-lapsed-donors-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-create-lapsed-donors-label">Créer le groupe</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        Créer le groupe «Donateurs à relancer <?= $year ?>» avec <strong><?= $count ?></strong> personne(s)?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"    value="createLapsedGroup">
          <input type="hidden" name="groupType" value="donors">
          <input type="hidden" name="year"      value="<?= $year ?>">
          <input type="hidden" name="view"      value="lapsedDonors">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-users me-1" aria-hidden="true"></i>Créer
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" role="status" style="font-size:0.85rem">
  <i class="fas fa-user-clock mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><strong><?= $count ?> donateur<?= $count > 1 ? 's' : '' ?></strong> ont contribué en <strong><?= $year-1 ?></strong> mais pas en <strong><?= $year ?></strong>.</span>
</div>

<?php
$dt_order      = [[6, 'desc']];
$extra_columns = [
    [
        'label'  => 'Don ' . ($year - 1),
        'value'  => fn($row) => number_format((float)$row->total_prev, 2, '.', '\''),
        'style'  => 'text-align:right',
        'footer' => array_sum(array_map(fn($r) => (float)$r->total_prev, $rows)),
    ],
    [
        'label'  => 'Dernier don',
        'value'  => fn($row) => timeStampToformatedDate($row->last_date),
        'style'  => '',
        'footer' => null,
    ],
];
$row_href = fn($row) => $_SERVER['PHP_SELF'] . '?view=compta&userid=' . (int)$row->id;
include '_donor_table.php';
?>
