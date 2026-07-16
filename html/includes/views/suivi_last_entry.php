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
$stmtSuivi = db()->query(
    "SELECT u.id AS user_id, u.firstname, u.lastname, u.society,
            up.date AS ts, up.value AS content,
            'suivi' AS kind, NULL AS email_log_id
     FROM contact u
     JOIN contact_properties up ON u.id = up.user_id
     WHERE u.status = 1 AND up.parameter = 'suivi'"
);
$rows = $stmtSuivi->fetchAll(PDO::FETCH_OBJ);
// contact_properties.date is converted in PHP, not via SQL UNIX_TIMESTAMP() — that
// function uses MySQL's session timezone, which differs from PHP's hardcoded
// Europe/Zurich (bootstrap.php) and would silently shift the date (see #143).
foreach ($rows as $r) {
    $r->ts = $r->ts ? strtotime($r->ts) : 0;
}

// Sent emails linked to a member
$emailRows = [];
try {
    $stmtEmail = db()->query(
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

if (empty($_jhEmbedded)) {
    $_noOuterContainer = true;
    $_phIcon = 'fa-book-open';
    $_phTitle = $GLOBAL['lastEntrySuivi'];
    include __DIR__ . '/../partials/page_header.php';
    echo '<div class="container-xl px-4 ca-hero-overlap">';
}
?>

<div class="card mb-4">
<div class="card-header"><?= $GLOBAL['suiviActivityListTitle'] ?></div>
<div class="card-body">
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
    $date = date('d/m/Y H:i', (int)$row->ts);
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
               class="stretched-link js-email-row-link" hx-boost="false"
               data-email-id="<?= (int)$row->email_log_id ?>"
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
</div><!-- .card-body -->
</div><!-- .card -->
<?php if (empty($_jhEmbedded)): ?></div><?php endif ?>

<!-- Sent-email detail, loaded on demand into this modal on row click instead
     of navigating to a separate page. -->
<div class="modal fade" id="email-detail-modal" tabindex="-1" aria-labelledby="email-detail-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="email-detail-modal-label"><?= $GLOBAL['viewEmail'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body" id="email-detail-modal-body">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var modalEl   = document.getElementById('email-detail-modal');
  var modalBody = document.getElementById('email-detail-modal-body');
  if (!modalEl || !modalBody) return;
  document.querySelectorAll('.js-email-row-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>';
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      var url = <?= json_encode(appUrl()) ?> + '?view=emailDetail&emailid=' + encodeURIComponent(link.dataset.emailId) + '&embedded=1';
      fetch(url, { headers: { 'HX-Request': 'true' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          modalBody.innerHTML = html;
          // <script> tags set via innerHTML never execute — the
          // iframe-population script in the fetched fragment needs to be
          // manually re-created to actually run.
          modalBody.querySelectorAll('script').forEach(function (old) {
              var s = document.createElement('script');
              if (old.src) { s.src = old.src; } else { s.textContent = old.textContent; }
              old.replaceWith(s);
          });
          if (window.htmx) htmx.process(modalBody);
          if (window.casaInit) casaInit(modalBody);
        })
        .catch(function () {
          modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + <?= json_encode($GLOBAL['loadError']) ?> + '</div>';
        });
    });
  });
})();
</script>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY HH:mm');
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
