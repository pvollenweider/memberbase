<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for adding a new follow-up (suivi) entry to a member's record.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
?>
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
<?php if (canWrite()): ?>
<tr>
    <td>
        <input type="text" name="date" id="date" class="form-control datepicker" maxlength="30" value="<?=date("d/m/Y")?>"/>
    </td>
    <td><textarea name="value"class="form-control"  rows="3" id="comment"></textarea></td>
    <td><button type="submit" class="btn btn-primary"><?=$GLOBAL['add']?></button></td>
</tr>
<?php endif ?>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
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
     <tr <?= canWrite() ? 'class="ca-row-link" data-href="' . $_SERVER['PHP_SELF'] . '?view=updateSuivi&suiviid=' . (int)$id . '&userid=' . (int)$userid . '" style="cursor:pointer"' : '' ?>>
        <td><?=timeStampToformatedDate($date)?></td>
        <td><?=html_entity_decode($value,ENT_COMPAT,$charset)?></td>
        <td class="text-end" style="white-space:nowrap">
            <?php if (canWrite()): ?>
            <a href="<?=$_SERVER['PHP_SELF']?>?view=removeSuivi&amp;suiviid=<?=(int)$id?>&amp;userid=<?=(int)$userid?>"
               class="btn btn-sm py-0 px-1 text-muted"
               style="position:relative;z-index:2"
               title="<?= $GLOBAL['deleteThisEntry'] ?>"
               aria-label="<?= $GLOBAL['delete'] ?>">
                <i class="fas fa-trash-can" style="font-size:0.75rem" aria-hidden="true"></i>
            </a>
            <?php endif ?>
        </td>
    </tr>
    <?php
}

?>
</table>
</div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.querySelector('form[name="addSuivi"] tbody');
    if (tbody) tbody.addEventListener('click', function(e) {
        var tr = e.target.closest('tr.ca-row-link');
        if (!tr) return;
        if (e.target.closest('a, button')) return;
        window.location.href = tr.dataset.href;
    });
});
</script>
