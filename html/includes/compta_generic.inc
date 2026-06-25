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
<div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <?=$year == -2 ? 'Année' : $year?>
    </button>
    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item<?php if ($year == -2) {?> active<?php  } ?>" href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=-2"><?=$GLOBAL['allYear']?></a>
        <div class="dropdown-divider"></div>
        <?php
        $currentYear = date("Y");
        for ($i=0;$i<10;$i++) {
            $y = $currentYear - $i;
            ?><a class="dropdown-item<?php if ($year == $y) {?> active<?php  } ?>" href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=<?=$y?>"><?=$y?></a><?php
        }
        ?>
    </div>
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
        <select name="type" class="form-control">
            <option value="avocat"><?=$GLOBAL['avocat']?></option>
            <option value="cd"><?=$GLOBAL['cd']?></option>
            <option value="coti"><?=$GLOBAL['coti']?></option>
            <option value="donEntreprise"><?=$GLOBAL['donEntreprise']?></option>
            <option value="donEtablissementdFinancier"><?=$GLOBAL['donEtablissementdFinancier']?></option>
            <option value="don"><?=$GLOBAL['don']?></option>
            <option value="dna" selected="selected"><?=$GLOBAL['dna']?></option>
            <option value="donInst"><?=$GLOBAL['donInst']?></option>
            <option value="evenementiel"><?=$GLOBAL['evenementiel']?></option>
            <option value="reintegration"><?=$GLOBAL['reintegration']?></option>
            <option value="urgence"><?=$GLOBAL['urgence']?></option>

<!--            <option value="vente"><?=$GLOBAL['vente']?></option>-->
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
$query = "SELECT id,user_id,type,date,libele,sum,quittance FROM compta ".
         "WHERE user_id=" . $user->getId() . " ";
if ($year != -2) {
    $query .= " AND date > $from AND date < $to ";
}
$query2 = $query;
$query .= "ORDER BY date DESC";
$query2 .= "ORDER BY date ASC";
$stmt = $pdo->query($query);
$total = 0;
while ($row = $stmt->fetchObject()) {
    $id = $row->id;
    $date = $row->date;
    $libele = $row->libele;
    $sum = $row->sum;
    $quittance = $row->quittance;
    $type = $row->type;
    $total += $sum;
    $cssClass = "";
    if ($type=="coti") { $cssClass="table-success"; }
    elseif ($type=="don") { $cssClass="table-danger"; }
    elseif ($type=="dna") { $cssClass="table-info"; }
    elseif ($type=="donInst") { $cssClass="table-warning"; }
    elseif ($type=="evenementiel") { $cssClass="table-active"; }

    ?>
     <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=updateCompta&comptaid=<?=$id?>&userid=<?=$user->getId()?>" class="<?=$type?> <?=$cssClass?>">
        <td><?=$GLOBAL[$type]?></td>
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
        <td>
            &nbsp;
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

