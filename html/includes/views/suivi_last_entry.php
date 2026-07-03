<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members by their most recent follow-up (suivi) entry date.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$stmt = $pdo->query(
    "SELECT users.id, users.firstname, users.lastname, users.society,
            user_properties.date, user_properties.value
     FROM users
     JOIN user_properties ON users.id = user_properties.user_id
     WHERE users.status=1 AND user_properties.parameter = 'suivi'
     ORDER BY user_properties.date DESC"
);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $GLOBAL['lastEntrySuivi'] ?></span>
</div>

<table id="suivi-table" class="table table-sm table-striped table-hover mt-2">
<thead>
<tr>
    <th><?= $GLOBAL['date'] ?></th>
    <th><?= $GLOBAL['lastName'] ?></th>
    <th><?= $GLOBAL['suivi'] ?></th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
    $date      = timeStampToformatedDate($row->date);
    $name      = trim(($row->society ? htmlentities($row->society, ENT_COMPAT, $charset) . ' ' : '') .
                      htmlentities($row->lastname, ENT_COMPAT, $charset) . ' ' .
                      htmlentities($row->firstname, ENT_COMPAT, $charset));
?>
    <tr class="position-relative">
        <td><?= htmlentities($date, ENT_COMPAT, $charset) ?></td>
        <td class="text-nowrap"><?= $name ?></td>
        <td><?= html_entity_decode($row->value, ENT_COMPAT, $charset) ?></td>
        <td>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= (int)$row->id ?>"
               class="stretched-link" hx-boost="false"
               aria-label="<?= sprintf($GLOBAL['viewSuiviOf'], htmlspecialchars($row->firstname . ' ' . $row->lastname, ENT_QUOTES, $charset)) ?>"></a>
        </td>
    </tr>
<?php endforeach ?>
</tbody>
</table>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('#suivi-table').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        paging: true,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: '<?= $GLOBAL['export'] ?> <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i><?= $GLOBAL['copy'] ?>' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i><?= $GLOBAL['excel'] ?>' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i><?= $GLOBAL['print'] ?>' }
                ]
            }
        ],
        language: {
            info:           '<?= $GLOBAL['dtInfoEntries'] ?>',
            infoFiltered:   '<?= $GLOBAL['dtInfoFiltered'] ?>',
            search:         '',
            searchPlaceholder: '<?= $GLOBAL['filterPlaceholder'] ?>',
            paginate: {
                first:    '«',
                last:     '»',
                next:     '›',
                previous: '‹'
            }
        }
    });
});
</script>
