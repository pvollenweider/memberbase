<?php
$year = -1; // date("Y");
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
if ($year == -1) {
    $year = date("Y");
}
$from = mktime(0, 0, 0, 1, 0, $year);
$to = mktime(0, 0, 0, 1, 1, $year + 1);
?>
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">

  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?=$year == -2 ? $GLOBAL['allYear'] : $year?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $year == -2 ? ' active' : '' ?>"
             href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=-2"><?=$GLOBAL['allYear']?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= $year == $y ? ' active' : '' ?>"
               href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=<?=$y?>"><?=$y?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <?php if ($year != -2): ?>
  <div class="dropdown ms-auto">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>Attestation
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <?php for ($i = 0; $i < 10; $i++): $y = $currentYear - $i; ?>
      <li><a class="dropdown-item<?= $year == $y ? ' fw-semibold' : '' ?>"
             href="/attestation_don.php?userid=<?=$user->getId()?>&amp;year=<?=$y?>"
             target="_blank">
          <?= $y ?><?= $year == $y ? ' (année affichée)' : '' ?>
      </a></li>
      <?php endfor ?>
    </ul>
  </div>
  <?php endif ?>

</div>


<form action="<?=$_SERVER['PHP_SELF']?>" method="post" name="addCompta">
<input type="hidden" name="action" value="addCompta"/>
<input type="hidden" name="view" value="compta"/>
<input type="hidden" name="userid" value="<?=$user->getId()?>"/>
<table class="table table-condensed table-striped table-hover p mt-2">
<thead>
<tr>
    <th><?=$GLOBAL['type']?></th>
    <th><?=$GLOBAL['date']?></th>
    <th><?=$GLOBAL['libele']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th><?=$GLOBAL['quittance']?></th>
    <th>&nbsp;</td>
</tr>
</thead>
<tr>
    <td>
        <select name="type_id" class="form-control">
            <?php foreach ($comptaTypes as $ct): ?>
            <option value="<?= (int)$ct->id ?>"><?= htmlentities($ct->label, ENT_COMPAT, $charset) ?></option>
            <?php endforeach ?>
        </select>
    </td>
    <td>
        <input type="text" name="date" id="date" class="form-control datepicker" maxlength="30" value="<?=date("d/m/Y")?>" />

    </td>
    <td><input type="text" name="libele" class="form-control" maxlength="255"/></td>
    <td><input type="text" name="sum" size="10" class="form-control "maxlength="64"/></td>
    <td><input type="text" name="quittance" size="10" class="form-control" maxlength="64"/></td>
    <td><button type="submit" class="btn btn-primary"><?=$GLOBAL['add']?></button></td>
</tr>
<?php
$query = "SELECT c.id, c.user_id, c.type_id, c.date, c.libele, c.sum, c.quittance, ct.label AS ct_label, ct.color AS ct_color FROM compta c LEFT JOIN compta_type ct ON ct.id = c.type_id WHERE c.user_id=" . $user->getId() . " ";
if ($year != -2) {
    $query .= " AND c.date > $from AND c.date < $to ";
}
$query2 = $query;
$query .= "ORDER BY c.date DESC";
$query2 .= "ORDER BY c.date ASC";
$stmt = $pdo->query($query);
$total = 0;
while ($row = $stmt->fetchObject()) {
    $id = $row->id;
    $date = $row->date;
    $libele = $row->libele;
    $sum = (float)($row->sum ?? 0);
    $quittance = $row->quittance;
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
    ?>
     <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=updateCompta&comptaid=<?=$id?>&userid=<?=$user->getId()?>" style="<?= htmlentities($rowStyle, ENT_COMPAT, $charset) ?>">
        <td><?= htmlentities($row->ct_label ?? '', ENT_COMPAT, $charset) ?></td>
        <td><?=timeStampToformatedDate($date)?></td>
        <td><?=htmlentities($libele,ENT_COMPAT,$charset)?></td>
        <td style="text-align:right;"><?=number_format($sum,2,'.','\'')?></td>
        <td >
            <?=$quittance?>
            <?php
            if ($quittance != "") {
                ?>
                <a href="quittancedon.php?userid=<?=$user->getId()?>&amp;comptaid=<?=$id?>" title="<?=$GLOBAL['exportQuittance']?>">
                <i class="fas fa-arrow-circle-o-down l"></i></a>
                <?php
            }
            ?>
        </td>
        <td class="text-end">
            <a href="<?=$_SERVER['PHP_SELF']?>?view=updateCompta&comptaid=<?=$id?>&userid=<?=$user->getId()?>"
               class="btn btn-outline-secondary btn-sm py-0 px-1">
                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
            </a>
        </td>
    </tr>
    <?php
}

?>
<tfoot>
<tr>
    <td></td>
    <td><strong><?=$GLOBAL['total']?></strong></td>
    <td></td>
    <td style="text-align:right;"><strong><?=number_format($total,2,'.','\'')?></strong></td>
    <td></td>
    <td></td>
</tr>
</tfoot>
</table>
</form>
<canvas id="myChart" style="position: relative; height:40vh; width:80vw"></canvas>

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
    $sum = $row->sum;
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

