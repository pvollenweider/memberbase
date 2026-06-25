<?php
$showAll = isset($_REQUEST['showAll']) && $_REQUEST['showAll'] == '1';
$minSum  = $showAll ? 0 : max(0, (int)(isset($_REQUEST['minSum']) ? $_REQUEST['minSum'] : 1));
if (!$showAll && !in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year === -1) { $year = (int)date("Y"); }
$type = "allTypes";
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    <i class="fas fa-filter me-1" aria-hidden="true"></i>Min CHF
  </span>
  <?php foreach ([1, 100, 200, 500, 1000] as $_ms): ?>
  <a class="ca-filter-btn<?= (!$showAll && $minSum == $_ms) ? ' active' : '' ?>"
     href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $_ms ?>&amp;type=<?= $type ?>&amp;year=<?= $year ?>"><?= $_ms ?></a>
  <?php endforeach ?>
  <a class="ca-filter-btn<?= $showAll ? ' active' : '' ?>"
     href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;showAll=1&amp;type=<?= $type ?>&amp;year=<?= $year ?>">Toutes entrées</a>

  <div class="dropdown ms-2">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? $GLOBAL['allYear'] : $year ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == $y) ? ' active' : '' ?>"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <?php if ($year != -2): ?>
  <a href="/attestation_bulk.php?year=<?= (int)$year ?>&amp;minSum=<?= (int)$minSum ?>"
     class="ca-filter-btn ms-auto"
     target="_blank"
     title="Générer toutes les attestations en un seul PDF">
    <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Toutes les attestations <?= (int)$year ?>
  </a>
  <?php endif ?>

</div>
<table class="table table-striped table-hover export">
<thead>
<tr>
    <th><?=$GLOBAL['society']?></th>
    <th><?=$GLOBAL['sexe']?></th>
    <th><?=$GLOBAL['lastName']?></th>
    <th><?=$GLOBAL['firstName']?></th>
    <th><?=$GLOBAL['email']?></th>
    <th>Actif?</th>
    <th><?=$GLOBAL['address']?></th>
    <th><?=$GLOBAL['npa']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th title="Souhaite attestation"><i class="fas fa-file-pdf" aria-hidden="true"></i></th>
    <th></th>
</tr>
</thead>
<?php
$params = [];
$baseSelect = "
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
           SUM(c.sum) AS total,
           MAX(c.wants_attestation) AS wants_attestation,
           EXISTS(
               SELECT 1 FROM user_properties up
               WHERE up.user_id = u.id AND up.parameter = 'team_141' AND up.value = 'true'
           ) AS is_actif
    FROM users u
    JOIN compta c ON u.id = c.user_id
";

if ($showAll) {
    $sql = $baseSelect . " WHERE 1=1";
} else {
    $sql = $baseSelect . " WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)";
}

if ($year != -2) {
    $from = mktime(0, 0, 0, 1, 0, $year);
    $to   = mktime(0, 0, 0, 1, 1, $year + 1);
    $sql .= " AND c.date > ? AND c.date < ?";
    $params[] = $from;
    $params[] = $to;
}
$sql .= " GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email";
if (!$showAll) {
    $sql .= " HAVING SUM(c.sum) >= ?";
    $params[] = $minSum;
}
$sql .= " ORDER BY u.lastname, u.firstname, u.society";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
$i = count($rows);

foreach ($rows as $row):
    $society = htmlentities($row->society ?? '', ENT_COMPAT, $charset);
    $lastName = htmlentities($row->lastname ?? '', ENT_COMPAT, $charset);
    $firstName = htmlentities($row->firstname ?? '', ENT_COMPAT, $charset);
    $email = htmlentities($row->email ?? '', ENT_COMPAT, $charset);
    $address = htmlentities($row->address ?? '', ENT_COMPAT, $charset);
    $npa = htmlentities($row->npa ?? '', ENT_COMPAT, $charset);
    $isActif = $row->is_actif ? "<i class='fas fa-check'></i><span class='hidden'>1</span>" : "";
    $sexeRaw = $row->sexe;
    $sexe2 = match($sexeRaw) { 'f' => 'Madame', 'm' => 'Monsieur', 'hf' => 'Monsieur et Madame', default => '-' };
    $sexe = match($sexeRaw) {
        'f'  => "<i class='fas fa-female s'></i>",
        'm'  => "<i class='fas fa-male s'></i>",
        'hf' => "<i class='fas fa-male s'></i><i class='fas fa-female s'></i>",
        default => ''
    };
    ?>
    <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=(int)$row->id?>&year=<?=$year?>">
        <td><?=$society?></td>
        <td><?=$sexe?><span class="hide"><?=$sexe2?></span></td>
        <td><strong><?=$lastName?></strong></td>
        <td><?=$firstName?></td>
        <td><?=$email?></td>
        <td><?=$isActif?></td>
        <td><?=$address?></td>
        <td><?=$npa?></td>
        <td style="text-align:right"><?=number_format((float)$row->total, 2, '.', '\'')?></td>
        <td class="text-center">
            <?php if ($row->wants_attestation): ?>
            <i class="fas fa-check text-success" aria-label="Souhaite attestation"></i>
            <?php endif ?>
        </td>
        <td class="text-end">
            <?php if ($year != -2): ?>
            <a href="/attestation_don.php?userid=<?=(int)$row->id?>&amp;year=<?=(int)$year?>"
               class="btn btn-outline-secondary btn-sm py-0 px-1"
               title="Attestation de dons <?=(int)$year?>"
               target="_blank"
               onclick="event.stopPropagation()">
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
            </a>
            <?php endif ?>
        </td>
    </tr>
    <?php
endforeach;
?>
</table>
<span style="font-size:9px">Found <?=$i?> rows. </span>
<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' },
                    { extend: 'pdf',   text: '<i class="fas fa-file-pdf me-2" aria-hidden="true"></i>PDF' }
                ]
            }
        ],
        language: {
            info: '_TOTAL_ entrées',
            infoFiltered: '(filtrées sur _MAX_)',
            search: '',
            searchPlaceholder: 'Filtrer…'
        }
    });
});
</script>

