<form action="<?=$_SERVER['PHP_SELF']?>" method="post" name="addSuivi">
<input type="hidden" name="action" value="addSuivi"/>
<input type="hidden" name="view" value="suivi"/>
<input type="hidden" name="userid" value="<?=$user->getId()?>"/>
<input type="hidden" name="parameter" value="suivi"/>
<div class="table-responsive">
<table class="table table-striped table-hover p">
<thead>
<tr class="title">
    <th><?=$GLOBAL['date']?></th>
    <th><?=$GLOBAL['comment']?></th>
    <th>&nbsp;</th>
</tr>
</thead>
<tr>
    <td>
        <input type="text" name="date" id="date" class="form-control datepicker" maxlength="30" value="<?=date("d/m/Y")?>"/>
    </td>
    <td><textarea name="value"class="form-control"  rows="3" id="comment"></textarea></td>
    <td><button type="submit" class="btn btn-primary"><?=$GLOBAL['add']?></button></td>
</tr>
<?php
$query = "SELECT id,user_id,parameter,date,value FROM user_properties ".
         "WHERE user_id=" . $user->getId() . " " .
         "AND parameter='suivi' ".
         "ORDER BY date DESC";
$stmt = $pdo->query($query);
while ($row = $stmt->fetchObject()) {
    $id = $row->id;
    $userid = $row->user_id;
    $date = $row->date;
    $parameter = $row->parameter;
    $value = $row->value;
    ?>
     <tr class="position-relative">
        <td><?=timeStampToformatedDate($date)?></td>
        <td><?=html_entity_decode($value,ENT_COMPAT,$charset)?></td>
        <td class="text-end position-relative" style="white-space:nowrap">
            <a href="<?=$_SERVER['PHP_SELF']?>?view=removeSuivi&amp;suiviid=<?=(int)$id?>&amp;userid=<?=(int)$userid?>"
               class="btn btn-sm py-0 px-1 text-muted position-relative"
               title="Supprimer cette entrée"
               aria-label="Supprimer">
                <i class="fas fa-trash-alt" style="font-size:0.75rem" aria-hidden="true"></i>
            </a>
            <a href="<?=$_SERVER['PHP_SELF']?>?view=updateSuivi&amp;suiviid=<?=(int)$id?>&amp;userid=<?=(int)$userid?>"
               class="stretched-link" hx-boost="false"
               aria-label="Modifier cette entrée de suivi"></a>
        </td>
    </tr>
    <?php
}

?>
</table>
</div>
</form>
