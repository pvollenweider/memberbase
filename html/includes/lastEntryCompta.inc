<?php
$year = -1; // date("Y");
$type = "allTypes";
$sort = "c.date";
if (isset($_REQUEST['sort'])) {
    $sortInput = $_REQUEST['sort'];
    if ($sortInput == "name") {
        $sort = "u.lastname,u.firstname,u.society";
    } elseif ($sortInput == "date") {
        $sort = "c.date";
    }
}
$filterTypeId = 0;
if (isset($_REQUEST['type_id']) && (int)$_REQUEST['type_id'] > 0 && isset($comptaTypes[(int)$_REQUEST['type_id']])) {
    $filterTypeId = (int)$_REQUEST['type_id'];
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


<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $GLOBAL['lastEntryCompta'] ?></span>

  <div class="dropdown ms-2">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $filterTypeId === 0 ? 'Tous les types' : htmlentities($comptaTypes[$filterTypeId]->label ?? '', ENT_COMPAT, $charset) ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $filterTypeId === 0 ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;year=<?= $year ?>"><?= $GLOBAL['allTypes'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php foreach ($comptaTypes as $ct): ?>
      <li><a class="dropdown-item<?= $filterTypeId === (int)$ct->id ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type_id=<?= (int)$ct->id ?>&amp;year=<?= $year ?>">
             <?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>
      </a></li>
      <?php endforeach ?>
    </ul>
  </div>

  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? 'Toutes les années' : $year ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= (isset($_REQUEST['year']) && $_REQUEST['year'] == $y) ? ' active' : '' ?>"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
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
    <th><?=$GLOBAL['address']?></th>
    <th><?=$GLOBAL['npa']?></th>
    <th><?=$GLOBAL['email']?></th>
    <th><?=$GLOBAL['type']?></th>
    <th><?=$GLOBAL['libele']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th><?=$GLOBAL['comment']?></th>
    <th><?=$GLOBAL['creationDate']?></th>

</tr>
</thead>
<?php
$query = "SELECT DISTINCT u.firstname, u.lastname, u.society, u.id, c.type_id, c.date, c.libele, c.id AS comptaid, c.sum, c.quittance, c.user_id, u.address, u.npa, u.email, u.tel, u.portable, ct.label AS ct_label, ct.color AS ct_color FROM users u JOIN compta c ON u.id = c.user_id LEFT JOIN compta_type ct ON ct.id = c.type_id WHERE 1=1";
if ($filterTypeId > 0) {
    $query .= " AND c.type_id = " . (int)$filterTypeId;
}
if ($year != -2) {
    $query .= " AND c.date > $from AND c.date < $to";
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
    $email   = $row->email;
    $address = $row->address;
    $npa     = $row->npa;
    $comptaid = $row->comptaid;
    $total += $sum;
    $ctColor = $row->ct_color ?? '';
    static $bgVarMap = [
        'bg-primary-subtle'   => 'var(--bs-primary-bg-subtle)',
        'bg-secondary-subtle' => 'var(--bs-secondary-bg-subtle)',
        'bg-success-subtle'   => 'var(--bs-success-bg-subtle)',
        'bg-danger-subtle'    => 'var(--bs-danger-bg-subtle)',
        'bg-warning-subtle'   => 'var(--bs-warning-bg-subtle)',
        'bg-info-subtle'      => 'var(--bs-info-bg-subtle)',
        'bg-light'            => 'var(--bs-light)',
        'bg-dark-subtle'      => 'var(--bs-dark-bg-subtle)',
    ];
    $rowStyle = isset($bgVarMap[$ctColor]) ? '--bs-table-bg:' . $bgVarMap[$ctColor] : '';

    if ($addMem != -1) {
        $user->addMembership($addMem);
    }
    ?>
    <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=$userId?>" style="<?= htmlentities($rowStyle, ENT_COMPAT, $charset) ?>">
        <td><?= htmlentities($date, ENT_COMPAT, $charset) ?></td>
        <td class="d-none d-xl-table-cell"><div class="text-truncate" style="max-width:125px"><?= htmlentities($society, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($lastName, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($firstName, ENT_COMPAT, $charset) ?></div></td>
        <td><div class="text-truncate" style="max-width:125px"><?= htmlentities($address, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:100px"><?= htmlentities($npa, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($email, ENT_COMPAT, $charset) ?></div></td>

        <td class="text-nowrap"><?= htmlentities($row->ct_label ?? '', ENT_COMPAT, $charset) ?></td>
        <td><?= htmlentities($libele, ENT_COMPAT, $charset) ?></td>
        <td style="text-align:right;"><?= number_format($sum, 2, '.', '\'') ?></td>
        <td>
            <?= htmlentities($quittance, ENT_COMPAT, $charset) ?>
            <?php
            if ($quittance != "") {
                ?>
                <a href="../quittancedon.php?userid=<?= $userId ?>&amp;comptaid=<?= $comptaid ?>"
                   title="<?= $GLOBAL['exportQuittance'] ?>"
                   onclick="event.stopPropagation()">
                    <img src="../images/doc.gif" width="16" height="16" alt="doc" border="0" align="absmiddle"/>
                </a>
                <?php
            }
            ?>
        </td>
        <td><?= timeStampToformatedDate($user->getCreationDate()) ?></td>

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
            columnDefs: [
                { targets: [4, 5, 6, 11], visible: false }
            ],
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

<?php
$_frM = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
$_monthly = [];
$_stmt2 = $pdo->query($query2);
while ($_r = $_stmt2->fetchObject()) {
    $_ts  = (int)$_r->date;
    $_mk  = date('Y-m', $_ts);
    $_lbl = $_frM[(int)date('n', $_ts)] . ' ' . date('Y', $_ts);
    if (!isset($_monthly[$_mk])) $_monthly[$_mk] = ['label' => $_lbl, 'sum' => 0.0];
    $_monthly[$_mk]['sum'] += (float)$_r->sum;
}
ksort($_monthly);
$_labels = array_values(array_column($_monthly, 'label'));
$_monthly_data = array_values(array_map(fn($v) => round($v['sum'], 2), $_monthly));
$_cumul = 0.0;
$_cumul_data = array_values(array_map(function($v) use (&$_cumul) {
    $_cumul += $v['sum'];
    return round($_cumul, 2);
}, $_monthly));
?>
<div class="mt-4" style="position:relative;height:35vh">
  <canvas id="myChart"></canvas>
</div>
<script>
new Chart(document.getElementById('myChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($_labels) ?>,
        datasets: [
            {
                type: 'bar',
                label: 'Mensuel',
                data: <?= json_encode($_monthly_data) ?>,
                backgroundColor: 'rgba(13,110,253,0.55)',
                borderColor: 'rgba(13,110,253,1)',
                borderWidth: 1,
                yAxisID: 'y1'
            },
            {
                type: 'line',
                label: 'Cumulé',
                data: <?= json_encode($_cumul_data) ?>,
                borderColor: 'rgba(220,53,69,1)',
                backgroundColor: 'rgba(220,53,69,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                fill: true,
                yAxisID: 'y2'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            yAxes: [
                {
                    id: 'y1',
                    position: 'left',
                    ticks: { callback: function(v) { return v.toLocaleString('fr-CH'); } },
                    gridLines: { drawOnChartArea: false }
                },
                {
                    id: 'y2',
                    position: 'right',
                    ticks: { callback: function(v) { return v.toLocaleString('fr-CH'); } }
                }
            ]
        }
    }
});
</script>


