<?php
$minSum = 1;
if (isset($_REQUEST['minSum'])) {
    $minSum = $_REQUEST['minSum'];
}

$year = -1; // date("Y");
$type = "allTypes";
$sort = "compta.date";
if (isset($_REQUEST['sort'])) {
    $sort = $_REQUEST['sort'];
    if ($sort == "name"){
        $sort = "users.lastname,users.firstname,users.society";
    } else if ($sort == "date"){
        $sort = "compta.date";
    }
}
if (isset($_REQUEST['type'])) {
    $type = $_REQUEST['type'];
}
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
if ($year == -1) {
    $year = date("Y");
}
$from = mktime(0, 0, 0, 1, 0, $year);
$to = mktime(0, 0, 0, 1, 1, $year + 1);
?>
<div class="well">Dons &gt;= CHF <?= $minSum ?> (cotisation exclus)
    <div class="text-right"><i class="fas fa-filter" aria-hidden="true"></i>
        <a class="badge badge-pill badge-light"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=1&amp;type=<?= $type ?>&amp;year=<?= $year ?>">1</a>
        <a class="badge badge-pill badge-light"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=200&amp;type=<?= $type ?>&amp;year=<?= $year ?>">200</a>
        <a class="badge badge-pill badge-light"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=500&amp;type=<?= $type ?>&amp;year=<?= $year ?>">500</a>
        <a class="badge badge-pill badge-light"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=1000&amp;type=<?= $type ?>&amp;year=<?= $year ?>">1000</a>
    </div>
</div>


<div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <?= (isset($_REQUEST['year']) && $_REQUEST['year'] == -2) ? 'Année' : $year ?>
    </button>
    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
        <a class="dropdown-item<?php if ($_REQUEST['year'] == -2) { ?> active<?php } ?>"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;type=<?= $type ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a>
        <li class="divider"></li>
        <?php
        $currentYear = date("Y");
        for ($i = 0; $i < 10; $i++) {
            $y = $currentYear - $i;
            ?><a class="dropdown-item<?php if ($_REQUEST['year'] == $y) { ?> active<?php } ?>"
                 href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;minSum=<?= $minSum ?>&amp;type=<?= $type ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
        }
        ?>
    </div>
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
</tr>
</thead>
<?php
$query = "SELECT DISTINCT users.firstname,users.lastname,users.society,users.id,compta.type,compta.date,compta.libele,compta.id,compta.sum,compta.quittance,compta.user_id ".
         "FROM users,compta ".
         "WHERE users.id=compta.user_id  ";
if ($year != -2) {
    $query .= " AND compta.date > $from AND compta.date < $to AND compta.type <> 'coti'";
}
$query .= " ORDER BY users.lastname,users.firstname,users.society DESC";
$stmt = $pdo->query($query);
$i = 0;
$previousiUserId = 0;
$userTotal = 0;
while ($row = $stmt->fetchObject()) {
    $date = timeStampToformatedDate($row->date);
    $firstName = $row->firstname;
    $lastName = $row->lastname;
    $society = $row->society;
    $libele = $row->libele;
    $sum = $row->sum;
    $quittance = $row->quittance;
    $userId = $row->user_id;
    $type = $row->type;
    $comptaid = $row->id;
    if ($previousiUserId == $userId) {
        $userTotal += $sum;
        $logger->debug("Uesr $userId is $sum -> total is $userTotal");
    } else {
        if ($userTotal >= $minSum) {
            $i++;
            $user = new User();
            $user->lookupUser($previousiUserId);
            $society = $user->getSociety();
            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();
	    $email = $user->getEmail();
	    $isActif = $user->isMemberOfTeam(141) ? "<i class='fas fa-check'></i><span class='hidden'>1</span>" : "";
	    $sexe = $user->getSexe();
	    $sexe2 = $sexe;
        if ($sexe == "f") { $sexe = "<i class='fas fa-female s'></i>"; }
        else if ($sexe == "m") { $sexe = "<i class='fas fa-male s'></i>"; }
        else if ($sexe == "hf") { $sexe = "<i class='fas fa-male s'></i><i class='fas fa-female s'></i>"; }

        if ($sexe2 == "na") { $sexe2 = "-"; }
        else if ($sexe2 == "f") { $sexe2 = "Madame"; }
        else if ($sexe2 == "m") { $sexe2 = "Monsieur"; }
        else if ($sexe2 == "hf") { $sexe2 = "Monsieur et Madame"; }
            ?>
            <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=$previousiUserId?>&year=<?=$year?>">
                <td><?=htmlentities($society,ENT_COMPAT,$charset)?>
              </td><td><?=$sexe?><span class="hide"><?=$sexe2?></td><td><strong><?=htmlentities($lastName,ENT_COMPAT,$charset)?></strong></td><td><?=htmlentities($firstName,ENT_COMPAT,$charset)?></strong></td><td><?=$email?></td><td><?=$isActif?></td><td><?=$user->getAddress()?></td><td><?=$user->getNpa()?></td><td style="text-align:right"><?=number_format($userTotal,2,'.','\'')?></td>
          </tr>
          <?php
        }
        $userTotal = $sum;
        $logger->debug("Uesr $userId is $sum -> total is $userTotal");
    }
    $previousiUserId = $userId;
}

?>
</table>
<span style="font-size:9px">Found <?=$i?> rows. </span>
<script>
$(document).ready(function() {
    $('.export').DataTable(
 {
"order": [[ 2, "asc" ]],

        "paging":   false,
dom: 'Bfrtip',
        buttons: [
            'copy', 'excel', 'print'
        ]
    }
);
} );
</script>

