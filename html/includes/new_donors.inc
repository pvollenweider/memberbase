<?php
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

$kFrom  = mktime(0, 0, 0, 1, 0, $year);
$kTo    = mktime(0, 0, 0, 1, 1, $year + 1);
$kFrom1 = mktime(0, 0, 0, 1, 0, $year - 1);
$kTo1   = mktime(0, 0, 0, 1, 1, $year);

$excl = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";

// Donors in year who did NOT donate in year-1 (nouveaux)
$sql = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           SUM(c.sum) AS total_curr,
           MIN(c.date) AS first_date
    FROM users u
    JOIN compta c ON u.id = c.user_id
    WHERE c.date > ? AND c.date < ?
      AND c.type_id NOT IN ($excl)
      AND u.id NOT IN (
          SELECT DISTINCT user_id FROM compta
          WHERE date > ? AND date < ?
            AND type_id NOT IN ($excl)
      )
    GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
    ORDER BY total_curr DESC, u.lastname, u.firstname
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$kFrom, $kTo, $kFrom1, $kTo1]);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Retour Contributions
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    Nouveaux donateurs <?= $year ?>
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
  <span><strong><?= $count ?> nouveau<?= $count > 1 ? 'x' : '' ?> donateur<?= $count > 1 ? 's' : '' ?></strong> — ont contribué en <strong><?= $year ?></strong> sans donation en <strong><?= $year-1 ?></strong>.</span>
</div>

<table class="table table-hover table-sm export">
<thead>
<tr>
  <th><?= $GLOBAL['society'] ?></th>
  <th><?= $GLOBAL['lastName'] ?></th>
  <th><?= $GLOBAL['firstName'] ?></th>
  <th><?= $GLOBAL['email'] ?></th>
  <th><?= $GLOBAL['address'] ?></th>
  <th><?= $GLOBAL['npa'] ?></th>
  <th>Don <?= $year ?></th>
  <th>Premier don</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
  $society   = htmlentities($row->society   ?? '', ENT_COMPAT, $charset);
  $lastName  = htmlentities($row->lastname  ?? '', ENT_COMPAT, $charset);
  $firstName = htmlentities($row->firstname ?? '', ENT_COMPAT, $charset);
  $email     = htmlentities($row->email     ?? '', ENT_COMPAT, $charset);
  $address   = htmlentities($row->address   ?? '', ENT_COMPAT, $charset);
  $npa       = htmlentities($row->npa       ?? '', ENT_COMPAT, $charset);
?>
<tr data-href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&userid=<?= (int)$row->id ?>&year=<?= $year ?>">
  <td><?= $society ?></td>
  <td><strong><?= $lastName ?></strong></td>
  <td><?= $firstName ?></td>
  <td><?= $email ?></td>
  <td><?= $address ?></td>
  <td><?= $npa ?></td>
  <td style="text-align:right"><?= number_format((float)$row->total_curr, 2, '.', '\'') ?></td>
  <td><?= timeStampToformatedDate($row->first_date) ?></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr>
  <td colspan="6" class="text-end text-muted" style="font-size:0.8rem">Total</td>
  <td style="text-align:right"><strong><?= number_format(array_sum(array_map(fn($r) => (float)$r->total_curr, $rows)), 2, '.', '\'') ?></strong></td>
  <td></td>
</tr>
</tfoot>
</table>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('.export').DataTable({
        order: [[6, 'desc']],
        paging: false,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [{
            extend: 'collection',
            text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
            className: 'btn btn-dt',
            buttons: [
                { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' },
                { extend: 'pdf',   text: '<i class="fas fa-file-pdf me-2" aria-hidden="true"></i>PDF' }
            ]
        }],
        language: {
            info: '_TOTAL_ entrées',
            infoFiltered: '(filtrées sur _MAX_)',
            search: '',
            searchPlaceholder: 'Filtrer…'
        }
    });
});
</script>
