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

$sort = "user_properties.date";
if (isset($_REQUEST['sort'])) {
    $sort = $_REQUEST['sort'];
    if ($sort == "name"){
        $sort = "users.lastname,users.firstname,users.society";
    } else if ($sort == "date"){
        $sort = "user_properties.date";
    }
}

?>




<nav class="navbar navbar-expand-lg navbar-light bg-light mb-2">
    <a class="navbar-brand" href="#"><?=$GLOBAL['lastEntrySuivi']?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto">

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <?=$_REQUEST['year'] == -2 ? 'Ann&eacute;e' : $year?>
                </a>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item<?php if ($_REQUEST['year'] == -2) { ?> active<?php } ?>"
                       href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntrySuivi&amp;type=<?= $type ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a>
                    <div class="dropdown-divider"></div>
                    <?php
                    $currentYear = date("Y");
                    for ($i = 0; $i < 10; $i++) {
                        $y = $currentYear - $i;
                        ?><a class="dropdown-item <?php if ($_REQUEST['year'] == $y) { ?> active<?php } ?>"
                             href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntrySuivi&amp;type=<?= $type ?>&amp;year=<?= $y ?>"><?= $y ?></a><?php
                    }
                    ?>
                </div>
            </li>


        </ul>
    </div>
</nav>

<table class="table table-condensed table-striped table-hover p mt-2">
<thead>
<tr>
    <th><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntrySuivi&amp;sort=date"><?=$GLOBAL['date']?></a></th>
    <th><a href="<?=$_SERVER['PHP_SELF']?>?view=lastEntrySuivi&amp;sort=name"><?=$GLOBAL['lastName']?></a></th>
    <th><?=$GLOBAL['suivi']?></th>
</tr>
</thead>
<?php
$query = "SELECT DISTINCT users.id,users.firstname,users.lastname,users.society,users.id,user_properties.date,user_properties.value ".
         "FROM users,user_properties ".
         "WHERE users.id=user_properties.user_id ".
         "AND user_properties.parameter='suivi' ";

if ($year != -2) {
    $query .= " AND user_properties.date > $from AND user_properties.date < $to ";
}
$query .= "ORDER by $sort DESC";
$stmt = $pdo->query($query);
$i = 0;
while ($row = $stmt->fetchObject()) {
    $i++;
    $date = timeStampToformatedDate($row->date);
    $firstName = $row->firstname;
    $lastName = $row->lastname;
    $society = $row->society;
    $userId = $row->id;
    $suivi = $row->value;
    ?>
    <tr data-href="<?=$_SERVER['PHP_SELF']?>?view=suivi&userid=<?=$userId?>" class="<?=$type?>">
        <td><?=htmlentities($date,ENT_COMPAT,$charset)?></td>
        <td class="text-nowrap">
            <?php
            if ($society) {
                ?><?=htmlentities($society,ENT_COMPAT,$charset)?><?php
            }
            ?>
            <?=htmlentities($lastName,ENT_COMPAT,$charset)?> <?=htmlentities($firstName,ENT_COMPAT,$charset)?>
        </td>
        <td><?=html_entity_decode($suivi,ENT_COMPAT,$charset)?></td>
    </tr>
    <?php
}
?>
</table>
<span style="font-size:9px">Found <?=$i?> rows. </span>
