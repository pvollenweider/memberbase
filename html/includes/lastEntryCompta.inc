<?php
$year = -1; // date("Y");
$type = "allTypes";
$sort = "compta.date";
if (isset($_REQUEST['sort'])) {
    $sortInput = $_REQUEST['sort'];
    if ($sortInput == "name") {
        $sort = "users.lastname,users.firstname,users.society";
    } elseif ($sortInput == "date") {
        $sort = "compta.date";
    }
}
$allowedTypes = ['allTypes','avocat','cd','evenementiel','reintegration','urgence','coti','don','dna','donInst','donEntreprise','donEtablissementdFinancier'];
if (isset($_REQUEST['type']) && in_array($_REQUEST['type'], $allowedTypes)) {
    $type = $_REQUEST['type'];
}
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
if (isset($_REQUEST['viewDetail'])) {
    $year = $_REQUEST['viewDetail'];
}

if ($year == -1) {
    $year = date("Y");
}
$from = mktime(0, 0, 0, 1, 0, $year);
$to = mktime(0, 0, 0, 1, 1, $year + 1);
$addMem = -1;
if (isset($_REQUEST['addMem'])) {
    $addMem = $_REQUEST['addMem'];
}
if ($addMem != -1) {
    ?>Add membership <?=$addMem?><?php
}
?>


<?php $filterType = $type; /* save before loop overwrites $type */ ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $GLOBAL['lastEntryCompta'] ?></span>

  <div class="dropdown ms-2">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $filterType === 'allTypes' ? 'Tous les types' : ($GLOBAL[$filterType] ?? htmlentities($filterType, ENT_COMPAT, $charset)) ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $filterType === 'allTypes' ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;year=<?= $year ?>"><?= $GLOBAL['allTypes'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $typeList = ['avocat','cd','evenementiel','reintegration','urgence','coti','don','dna','donInst','donEntreprise','donEtablissementdFinancier'];
      foreach ($typeList as $t): ?>
      <li><a class="dropdown-item<?= $filterType === $t ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type=<?= $t ?>&amp;year=<?= $year ?>"><?= $GLOBAL[$t] ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? 'Toutes les années' : $year ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type=<?= $filterType ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == $y) ? ' active' : '' ?>"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type=<?= $filterType ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
      }
      ?>
    </ul>
  </div>
</div>

<div class="table-responsive">
<table class="table  table-hover table-sm export">
<thead>
<tr>
    <th><?=$GLOBAL['date']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['society']?></th>
    <th><?=$GLOBAL['lastName']?></th>
    <th><?=$GLOBAL['firstName']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['address']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['npa']?></th>
    <th><?=$GLOBAL['email']?></th>
    <th><?=$GLOBAL['type']?></th>
    <th><?=$GLOBAL['libele']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['quittance']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['creationDate']?></th>

</tr>
</thead>
<?php
$query = "SELECT DISTINCT users.firstname,users.lastname,users.society,users.id,compta.type,compta.date,compta.libele,compta.id,compta.sum,compta.quittance,compta.user_id,users.address,users.npa,users.email,users.tel,users.portable ".
         "FROM users,compta ".
         "WHERE users.id=compta.user_id ";
if ($type != "allTypes") {
    $query .= "AND compta.type='$type'";
}
if ($year != -2) {
    $query .= " AND compta.date > $from AND compta.date < $to ";
}
$query2 = $query;
$query .= " ORDER BY $sort DESC LIMIT 0,20000";
$query2 .= " ORDER BY $sort ASC LIMIT 0,20000";
$stmt = $pdo->query($query);
$i = 0;
$total = 0.0;
while ($row = $stmt->fetchObject()) {
    $i++;
    $date = timeStampToformatedDate($row->date);
    $firstName = $row->firstname;
    $lastName = $row->lastname;
    $society = $row->society;
    $libele = $row->libele;
    $sum = (float) $row->sum;
    $quittance = $row->quittance;
    $userId = $row->user_id;
    $user = new User();
    $user->lookupUser($userId);
    $type = $row->type;
    $email=$row->email;
    $address = $row->address;
        $npa = $row->npa;
        $email = $row->email;

    $comptaid = $row->id;
    $total += $sum;
    $rowType = $row->type;
    $cssClass = match($rowType) {
        'coti'         => 'table-success',
        'don'          => 'text-secondary',
        'dna'          => 'text-dark bg-light',
        'donInst'      => 'table-warning',
        'evenementiel' => 'text-primary',
        default        => ''
    };
    
    if ($addMem != -1) {
        $user->addMembership($addMem);
    }
    ?>
    <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=$userId?>" class="<?=$rowType?> <?=$cssClass?>">
        <td><?= htmlentities($date, ENT_COMPAT, $charset) ?></td>
        <td class="d-none d-xl-table-cell"><div class="text-truncate" style="max-width:125px"><?= htmlentities($society, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($lastName, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($firstName, ENT_COMPAT, $charset) ?></div></td>
        <td class="d-none d-xl-table-cell "><div class="text-truncate" style="max-width:125px"><?= htmlentities($address, ENT_COMPAT, $charset) ?></div></td>
        <td class="d-none d-xl-table-cell text-nowrap"><div class="text-truncate" style="max-width:100px"><?= htmlentities($npa, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($email, ENT_COMPAT, $charset) ?></div></td>

        <td class="text-nowrap"><?= $GLOBAL[$rowType] ?? htmlentities($rowType, ENT_COMPAT, $charset) ?></td>
        <td><?= htmlentities($libele, ENT_COMPAT, $charset) ?></td>
        <td style="text-align:right;"><?= htmlentities(number_format($sum, 2, '.', ''), ENT_COMPAT, $charset) ?></td>
        <td class="d-none d-xl-table-cell">
            <?= htmlentities($quittance, ENT_COMPAT, $charset) ?>
            <?php
            if ($quittance != "") {
                ?>
                <a href="../quittancedon.php?userid=<?= $userId ?>&amp;comptaid=<?= $comptaid ?>"
                   title="<?= $GLOBAL['exportQuittance'] ?>">
                    <img src="../images/doc.gif" width="16" height="16" alt="doc" border="0" align="absmiddle"/>
                </a>
                <?php
            }
            ?>
        </td>
        <td class="d-none d-xl-table-cell"><?= timeStampToformatedDate($user->getCreationDate()) ?></td>

    </tr>
    <?php
}

?>
<tfoot>
<tr>
    <td colspan="9" class="text-end text-muted" style="font-size:0.8rem"><?= $GLOBAL['total'] ?></td>
    <td class="text-end"><strong><?= number_format($total, 2, '.', '\'') ?></strong></td>
    <td colspan="2"></td>
</tr>
</tfoot>
</table>
</div>
<script>
    $.fn.dataTable.moment('DD/MM/YYYY');

    $(document).ready(function () {
        $('.export').DataTable({
            order: [[0, 'desc']],
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
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns me-1" aria-hidden="true"></i>Colonnes',
                    className: 'btn btn-dt'
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

<div class="mt-4" style="position:relative;height:35vh">
  <canvas id="myChart"></canvas>
</div>

<script>

    var data = {
        datasets: [{
            label: 'Somme',
        steppedLine: true,

            data: [

<?php

$stmt = $pdo->query($query2);
$total=0;
while ($row = $stmt->fetchObject()) {
    $date = timeStampToformatedDate($row->date);
    $sum = (float) $row->sum;
    $total = $sum + $total;
   ?>                { x: moment('<?=$date?>','DD/MM/YYYY'), y: <?=$total?> },
<?php
}
?>
            ],
        },

        ]
    };
    moment.locale('fr');
    var ctx = document.getElementById("myChart");
    var myChart = new Chart(ctx, {
        type: 'line',
        data: data,
        responsive: true,
        options: {
            scales: {
                xAxes: [{
                    type: 'time',
                    scaleLabel: {
                        display: true,
                        labelString: "Date"
                    }
                }],
                yAxes: [{
                    display: true,
                    scaleLabel: {
                        display: true,
                        labelString: "Somme"
                    }
                }]
            }
        }
    });
</script>


