<?php 
$year = -1; // date("Y");
$type = "don";
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
$to = mktime(0, 0, 0, 1, 1, $year+1);
?>

<h2>DNA pour l'ann&eacute;e en cours
</h2>
<table class="list">
<!--
<tr class="title">
    <td><?=$GLOBAL['name']?></td>
    <td><?=$GLOBAL['sum']?></td>
</tr>
-->
<?php 
$query = "SELECT DISTINCT users.firstname,users.lastname,users.society,users.id,compta.type,compta.date,compta.libele,compta.id,compta.sum,compta.quittance,compta.user_id ".
         "FROM users,compta ".
         "WHERE users.id=compta.user_id  AND compta.type='don'";
if ($year != -2) {
    $query .= " AND compta.date > $from AND compta.date < $to ";
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
    $user = new User();
    $user->lookupUser($userId);
    $addUser = $user->isMemberOfTeam(7);
    if ($addUser) { 
    if ($previousiUserId == $userId) {
        $userTotal += $sum;
        $logger->debug("Uesr $userId is $sum -> total is $userTotal");
    } else {
        if (true) {
            $i++;
            $user = new User();
            $user->lookupUser($previousiUserId);
            $society = $user->getSociety();
            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();
	    $email = $user->getEmail();
	    $isActif = $user->isMemberOfTeam(6);
	    $sexe = $user->getSexe();
        if ($sexe == "na") { $sexe = "-"; }
        else if ($sexe == "f") { $sexe = "Madame"; }
        else if ($sexe == "m") { $sexe = "Monsieur"; }
        else if ($sexe == "hf") { $sexe = "Monsieur et Madame"; }
            ?>
            <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=$previousiUserId?>&year=<?=$year?>">
                <td><strong>
                <?php 
                  if ($society) {
                  ?><?=htmlentities($society,ENT_COMPAT,$charset)?> - <?php 
              }
              ?>
              <?=htmlentities($lastName,ENT_COMPAT,$charset)?></strong> <?=htmlentities($firstName,ENT_COMPAT,$charset)?>

              </td><td><?=$sexe?></td><td><?=$user->getFirstName()?></td><td><?=$user->getLastName()?></td><td><?=$email?></td><td><?=$isActif?></td><td><?=$user->getAddress()?></td><td><?=$user->getNpa()?></td><td style="text-align:right"><?=number_format($userTotal,2,'.','\'')?></td>
          </tr>
          <?php 
        }
        $userTotal = $sum;
        $logger->debug("Uesr $userId is $sum -> total is $userTotal");
    }
    $previousiUserId = $userId;
    }
}

?>
</table>
<span style="font-size:9px">Found <?=$i?> rows. </span>
