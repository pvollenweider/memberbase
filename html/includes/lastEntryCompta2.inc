<div class="tab">
<ul>
    <li<?php  if ($view == "lastEntryCompta2") { ?> class="selected" <?php  } ?>><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2" title="<?=$GLOBAL['generalData']?>"><span><?=$GLOBAL['lastEntryCompta']?></span></a></li>
    <li<?php  if ($view == "lastEntrySuivi") { ?> class="selected" <?php  } ?>><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntrySuivi" title="<?=$GLOBAL['lastEntrySuivi']?>"><span><?=$GLOBAL['lastEntrySuivi']?></span></a></li>
</ul>
</div>
<?php
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
<div class="legende">
    <br/>&nbsp;<br/>
    <span class="allTypes"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['allTypes']?></a></span>
    <span class="avocat"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=avocat&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['avocat']?></a></span>
    <span class="cd"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=cd&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['cd']?></a></span>
    <span class="evenementiel"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=evenementiel&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['evenementiel']?></a></span>
    <span class="reintegration"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=reintegration&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['reintegration']?></a></span>
    <span class="urgence"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=urgence&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['urgence']?></a></span>
<br/><br/>
    <span class="coti"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=coti&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['coti']?></a></span>
    <span class="don"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=don&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['don']?></a></span>
    <span class="donInst"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=donInst&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['donInst']?></a></span>
    <span class="donEntreprise"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=donEntreprise&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['donEntreprise']?></a></span>
    <span class="donEtablissementdFinancier"><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=donEtablissementdFinancier&amp;year=<?=$year?>&amp;sort=<?=$sort?>"><?=$GLOBAL['donEtablissementdFinancier']?></a></span><br/><br/>

    <br/><br/>
</div>

<form action="<?=$_SERVER['PHP_SELF']?>?view=lastEntryCompta2&amp;type=<?=$type?>" method="post" name="changeYear">
<?=$GLOBAL['compta']?>
    <select name="year" onchange="document.changeYear.submit();">
        <option value="-2"<?php  if ($year==-1){?> selected="selected"<?php }?>><?=$GLOBAL['allYear']?></option>
        <?php
        $currentYear = date("Y");
        for ($i=0;$i<10;$i++) {
            $y = $currentYear - $i;
            ?><option value="<?=$y?>"<?php  if ($year==$y){?> selected="selected"<?php }?>><?=$y?></option><?php
        }
        ?>
    </select>
</form>
<table class="list">
<?php
$query = "SELECT DISTINCT users.firstname,users.lastname,users.address,users.sexe,users.society,users.npa,users.email,users.society,users.id,compta.type,compta.date,compta.libele,compta.id,compta.sum,compta.quittance,compta.user_id ".
         "FROM users,compta ".
         "WHERE users.id=compta.user_id ";
if ($type != "allTypes") {
    $query .= "AND compta.type='$type'";
}
if ($year != -2) {
    $query .= " AND compta.date > $from AND compta.date < $to ";
}
$query .= " ORDER BY $sort DESC LIMIT 0,1000";
$stmt = $pdo->query($query);
$i = 0;
$total = 0;
while ($row = $stmt->fetchObject()) {
    $date = timeStampToformatedDate($row->date);
        $firstName = $row->firstname;
        $lastName = $row->lastname;
        $society = $row->society;
        $sexe = $row->sexe;
        if ($sexe == "na") { $sexe = "-"; }
        else if ($sexe == "f") { $sexe = "Madame"; }
        else if ($sexe == "m") { $sexe = "Monsieur"; }
        else if ($sexe == "hf") { $sexe = ""; }
        $address = $row->address;
        $npa = $row->npa;
        $email = $row->email;
        $firstName = htmlentities($firstName,ENT_COMPAT,$charset);
        $lastName = htmlentities($lastName,ENT_COMPAT,$charset);
        $address = htmlentities($address,ENT_COMPAT,$charset);
        $npa = htmlentities($npa,ENT_COMPAT,$charset);
        $emailStr = htmlentities($email,ENT_COMPAT,$charset);

    $libele = $row->libele;
    $sum = $row->sum;
    $quittance = $row->quittance;
    $userId = $row->user_id;
    $type = $row->type;
    $comptaid = $row->id;
    $total += $sum;
    if ($quittance != "") {
    $i++;

    ?>
    <tr  data-href="<?=$_SERVER['PHP_SELF']?>?view=compta&userid=<?=$userId?>" class="<?=$type?>">
        <td><?=htmlentities($date,ENT_COMPAT,$charset)?></td>
            <td><?=$sexe?></td>
            <td class="bold"><?=$society?></td>
            <td class="text-nowrap"><?=$lastName?> </td>
            <td class="text-nowrap"><?=$firstName?></td>
            <td><?=$address?></td>
            <td><?=$npa?></td>
            <td><a href="mailto:<?=$email?>"><?=$emailStr?></a></td>

        <td><?=htmlentities($libele,ENT_COMPAT,$charset)?></td>
        <td style="text-align:right;"><?=htmlentities($sum,ENT_COMPAT,$charset)?></td>
        <td>
            <?=htmlentities($quittance,ENT_COMPAT,$charset)?>
            <?php
            if ($quittance != "") {
                ?>
                <a href="../quittancedon.php?userid=<?=$userId?>&amp;comptaid=<?=$comptaid?>" title="<?=$GLOBAL['exportQuittance']?>">
                    <img src="../images/doc.gif" width="16" height="16" alt="doc" border="0" align="absmiddle"/>
                </a>
                <?php
            }
            ?>
        </td>
    </tr>
    <?php
    }
}

?>
</table>
<span style="font-size:9px">Found <?=$i?> rows. </span>
