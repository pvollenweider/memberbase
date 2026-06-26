<?php
$stmt = $pdo->query(
    "SELECT users.id, users.firstname, users.lastname, users.society,
            user_properties.date, user_properties.value
     FROM users
     JOIN user_properties ON users.id = user_properties.user_id
     WHERE user_properties.parameter = 'suivi'
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
               aria-label="Voir suivi de <?= htmlspecialchars($row->firstname . ' ' . $row->lastname, ENT_QUOTES, $charset) ?>"></a>
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
                text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' }
                ]
            }
        ],
        language: {
            info:           '_START_–_END_ sur _TOTAL_ entrées',
            infoFiltered:   '(filtrées sur _MAX_)',
            search:         '',
            searchPlaceholder: 'Filtrer…',
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
