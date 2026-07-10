<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists members by their most recent follow-up (suivi) entry date,
 * merged with sent emails (from email_log where user_id IS NOT NULL).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

// Suivi entries (manual notes)
$stmtSuivi = $pdo->query(
    "SELECT u.id AS user_id, u.firstname, u.lastname, u.society,
            up.date AS ts, up.value AS content,
            'suivi' AS kind, NULL AS email_log_id
     FROM contact u
     JOIN contact_properties up ON u.id = up.user_id
     WHERE u.status = 1 AND up.parameter = 'suivi'"
);
$rows = $stmtSuivi->fetchAll(PDO::FETCH_OBJ);

// Sent emails linked to a member
$emailRows = [];
try {
    $stmtEmail = $pdo->query(
        "SELECT u.id AS user_id, u.firstname, u.lastname, u.society,
                UNIX_TIMESTAMP(el.created_at) AS ts, el.subject AS content,
                'email' AS kind, el.id AS email_log_id
         FROM email_log el
         JOIN contact u ON u.id = el.user_id
         WHERE el.user_id IS NOT NULL AND el.status = 'sent'"
    );
    $emailRows = $stmtEmail->fetchAll(PDO::FETCH_OBJ);
} catch (\Throwable $e) {
    // email_log.user_id column not yet migrated — skip silently
}

// Merge and sort by date desc
$allRows = array_merge($rows, $emailRows);
usort($allRows, fn($a, $b) => (int)$b->ts - (int)$a->ts);
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
<?php foreach ($allRows as $row):
    $date = timeStampToformatedDate($row->ts);
    $name = trim(($row->society ? htmlentities($row->society, ENT_COMPAT, $charset) . ' ' : '') .
                 htmlentities($row->lastname, ENT_COMPAT, $charset) . ' ' .
                 htmlentities($row->firstname, ENT_COMPAT, $charset));
    $isEmail = $row->kind === 'email';
?>
    <tr class="position-relative">
        <td class="text-nowrap"><?= htmlentities($date, ENT_COMPAT, $charset) ?></td>
        <td class="text-nowrap"><?= $name ?></td>
        <td>
            <?php if ($isEmail): ?>
            <i class="fas fa-envelope me-1 text-primary" aria-hidden="true" title="<?= $GLOBAL['emailSent'] ?>"></i>
            <?php endif ?>
            <!-- Legacy rows store entity-encoded text: decode first, then escape for output -->
            <?= htmlspecialchars(html_entity_decode($row->content, ENT_COMPAT, $charset), ENT_QUOTES, $charset) ?>
        </td>
        <td class="text-end" style="white-space:nowrap">
            <?php if ($isEmail && $row->email_log_id): ?>
            <a href="<?= appUrl() ?>?view=emailDetail&amp;emailid=<?= (int)$row->email_log_id ?>"
               class="stretched-link" hx-boost="false"
               title="<?= $GLOBAL['viewEmail'] ?>"></a>
            <?php else: ?>
            <a href="<?= appUrl() ?>?view=suivi&amp;userid=<?= (int)$row->user_id ?>"
               class="stretched-link" hx-boost="false"
               aria-label="<?= sprintf($GLOBAL['viewSuiviOf'], htmlspecialchars($row->firstname . ' ' . $row->lastname, ENT_QUOTES, $charset)) ?>"></a>
            <?php endif ?>
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
