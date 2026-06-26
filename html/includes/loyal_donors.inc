<?php
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

$kFrom  = mktime(0, 0, 0, 1, 0, $year);
$kTo    = mktime(0, 0, 0, 1, 1, $year + 1);
$kFrom1 = mktime(0, 0, 0, 1, 0, $year - 1);
$kTo1   = mktime(0, 0, 0, 1, 1, $year);

$excl = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";

// Donors in year who also donated in year-1 (fidèles / récurrents)
$sql = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           SUM(c.sum) AS total_curr,
           (SELECT COALESCE(SUM(c2.sum),0) FROM compta c2
            WHERE c2.user_id = u.id AND c2.date > ? AND c2.date < ?
              AND c2.type_id NOT IN ($excl)) AS total_prev
    FROM users u
    JOIN compta c ON u.id = c.user_id
    WHERE c.date > ? AND c.date < ?
      AND c.type_id NOT IN ($excl)
      AND u.id IN (
          SELECT DISTINCT user_id FROM compta
          WHERE date > ? AND date < ?
            AND type_id NOT IN ($excl)
      )
    GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
    ORDER BY total_curr DESC, u.lastname, u.firstname
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo, $kFrom1, $kTo1]);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Retour à l'aperçu des dons
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    Donateurs fidèles <?= $year ?>
  </span>
  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=loyalDonors&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<div class="alert d-flex align-items-start gap-2 py-2 mb-3" role="status"
     style="font-size:0.85rem;background:rgba(25,135,84,0.1);border:1px solid rgba(25,135,84,0.3);border-radius:6px">
  <i class="fas fa-rotate mt-1 flex-shrink-0" aria-hidden="true" style="color:var(--bs-success)"></i>
  <span><strong><?= $count ?> donateur<?= $count > 1 ? 's' : '' ?> fidèles</strong> — ont contribué à la fois en <strong><?= $year-1 ?></strong> et en <strong><?= $year ?></strong>.</span>
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
  <th>Don <?= $year-1 ?></th>
  <th></th>
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
<tr class="position-relative">
  <td><?= $society ?></td>
  <td><strong><?= $lastName ?></strong></td>
  <td><?= $firstName ?></td>
  <td><?= $email ?></td>
  <td><?= $address ?></td>
  <td><?= $npa ?></td>
  <td style="text-align:right"><?= number_format((float)$row->total_curr, 2, '.', '\'') ?></td>
  <td style="text-align:right"><?= number_format((float)$row->total_prev, 2, '.', '\'') ?></td>
  <td>
    <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= (int)$row->id ?>&amp;year=<?= $year ?>"
       class="stretched-link"
       aria-label="Voir compta de <?= htmlspecialchars($row->firstname . ' ' . $row->lastname, ENT_QUOTES, $charset) ?>"></a>
  </td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot>
<tr>
  <td colspan="6" class="text-end text-muted" style="font-size:0.8rem">Total</td>
  <td style="text-align:right"><strong><?= number_format(array_sum(array_map(fn($r) => (float)$r->total_curr, $rows)), 2, '.', '\'') ?></strong></td>
  <td style="text-align:right"><strong><?= number_format(array_sum(array_map(fn($r) => (float)$r->total_prev, $rows)), 2, '.', '\'') ?></strong></td>
</tr>
</tfoot>
</table>

<script>
$(document).ready(function() {
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
