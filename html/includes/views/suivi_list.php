<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for adding a new follow-up (suivi) entry to a member's record.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
?>
<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['suivi'] ?></h2></div>
<div class="card-body">
<form action="<?=appUrl()?>" method="post" name="addSuivi">
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
// Merged suivi notes + sent emails for this member, most recent first —
// same merge as the global journals&tab=suivi / dashboard activity lists,
// scoped to this one member.
$_slSuiviRows = db()->prepare(
    "SELECT id, date AS ts, value AS content, 'suivi' AS kind, NULL AS email_log_id
     FROM contact_properties WHERE user_id = ? AND parameter = 'suivi'"
);
$_slSuiviRows->execute([(int)$user->getId()]);
$_slSuiviRows = $_slSuiviRows->fetchAll(PDO::FETCH_OBJ);
foreach ($_slSuiviRows as $_r) { $_r->ts = $_r->ts ? strtotime($_r->ts) : 0; }

$_slEmailRows = [];
try {
    $_slEmailStmt = db()->prepare(
        "SELECT id AS email_log_id, UNIX_TIMESTAMP(created_at) AS ts, subject AS content,
                status, error_msg, 'email' AS kind
         FROM email_log WHERE user_id = ?"
    );
    $_slEmailStmt->execute([(int)$user->getId()]);
    $_slEmailRows = $_slEmailStmt->fetchAll(PDO::FETCH_OBJ);
} catch (\Throwable $e) {
    // email_log.user_id column not yet migrated — skip silently
}

$_slRows = array_merge($_slSuiviRows, $_slEmailRows);
usort($_slRows, fn($a, $b) => (int)$b->ts - (int)$a->ts);

foreach ($_slRows as $row):
    if ($row->kind === 'email'):
    ?>
    <tr class="js-email-row-link<?= ($row->status ?? '') === 'error' ? ' table-danger' : '' ?>" style="cursor:pointer"
        data-email-id="<?= (int)$row->email_log_id ?>"
        tabindex="0" role="button" aria-label="<?= htmlspecialchars($GLOBAL['viewEmail'], ENT_QUOTES, $charset) ?>"
        <?php if (($row->status ?? '') === 'error' && !empty($row->error_msg)): ?>
        title="<?= htmlspecialchars($row->error_msg, ENT_QUOTES, $charset) ?>"
        <?php endif ?>>
        <td style="white-space:nowrap"><?= timeStampToformatedDate((int)$row->ts) ?></td>
        <td>
            <i class="fas fa-envelope me-1 text-primary" aria-hidden="true" title="<?= $GLOBAL['emailSent'] ?>"></i>
            <?= htmlspecialchars($row->content, ENT_QUOTES, $charset) ?>
            <?php if (($row->status ?? '') === 'error'): ?>
            <span class="badge bg-danger-subtle text-danger-emphasis ms-1"><?= $GLOBAL['emailLogStatusError'] ?></span>
            <?php endif ?>
        </td>
        <td>&nbsp;</td>
    </tr>
    <?php else: ?>
     <tr <?= canWrite() ? 'class="ca-row-link" data-suivi-href="' . appUrl() . '?view=updateSuivi&suiviid=' . (int)$row->id . '&userid=' . (int)$user->getId() . '" style="cursor:pointer" tabindex="0" role="button" aria-label="' . htmlspecialchars($GLOBAL['updateSuivi'], ENT_QUOTES, $charset) . '"' : '' ?>>
        <td><?=timeStampToformatedDate((int)$row->ts)?></td>
        <!-- Legacy rows store entity-encoded text: decode first, then escape for output -->
        <td><?= htmlspecialchars(html_entity_decode($row->content, ENT_COMPAT, $charset), ENT_QUOTES, $charset) ?></td>
        <td class="text-end" style="white-space:nowrap">
            <?php if (canWrite()): ?>
            <a href="<?=appUrl()?>?view=removeSuivi&amp;suiviid=<?=(int)$row->id?>&amp;userid=<?=(int)$user->getId()?>"
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
    endif;
endforeach;
?>
</table>
</div>
</form>
</div><!-- .card-body -->
</div><!-- .card -->

<!-- Suivi entry edit form, loaded on demand into this modal on row click
     instead of navigating to a separate page. -->
<div class="modal fade" id="suivi-edit-modal" tabindex="-1" aria-labelledby="suivi-edit-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="suivi-edit-modal-label"><?= $GLOBAL['updateSuivi'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body" id="suivi-edit-modal-body">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

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
document.addEventListener('DOMContentLoaded', function() {
    // Suivi rows: load the edit form into a modal instead of navigating away
    // (data-suivi-href avoids the global datahref jQuery plugin, which binds
    // any [data-href] element and would otherwise race with this handler).
    var tbody = document.querySelector('form[name="addSuivi"] tbody');
    var suiviModalEl   = document.getElementById('suivi-edit-modal');
    var suiviModalBody = document.getElementById('suivi-edit-modal-body');
    function openSuiviRow(tr) {
        suiviModalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>';
        bootstrap.Modal.getOrCreateInstance(suiviModalEl).show();
        fetch(tr.dataset.suiviHref + '&embedded=1', { headers: { 'HX-Request': 'true' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                suiviModalBody.innerHTML = html;
                suiviModalBody.querySelectorAll('script').forEach(function (old) {
                    var s = document.createElement('script');
                    if (old.src) { s.src = old.src; } else { s.textContent = old.textContent; }
                    old.replaceWith(s);
                });
                if (window.htmx) htmx.process(suiviModalBody);
                if (window.casaInit) casaInit(suiviModalBody);
            })
            .catch(function () {
                suiviModalBody.innerHTML = '<div class="alert alert-danger mb-0">' + <?= json_encode($GLOBAL['loadError']) ?> + '</div>';
            });
    }
    if (tbody && suiviModalEl && suiviModalBody) tbody.addEventListener('click', function(e) {
        var tr = e.target.closest('tr.ca-row-link');
        if (!tr) return;
        if (e.target.closest('a, button')) return;
        openSuiviRow(tr);
    });
    // Keyboard equivalent (role="button" + tabindex="0" on the row).
    if (tbody && suiviModalEl && suiviModalBody) tbody.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var tr = e.target.closest('tr.ca-row-link');
        if (!tr || e.target.closest('a, button')) return;
        e.preventDefault();
        openSuiviRow(tr);
    });

    // Email rows: load the preview into a modal instead of navigating away.
    var modalEl   = document.getElementById('email-detail-modal');
    var modalBody = document.getElementById('email-detail-modal-body');
    if (!modalEl || !modalBody) return;
    function openEmailRow(row) {
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        var url = <?= json_encode(appUrl()) ?> + '?view=emailDetail&emailid=' + encodeURIComponent(row.dataset.emailId) + '&embedded=1';
        fetch(url, { headers: { 'HX-Request': 'true' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                modalBody.innerHTML = html;
                // <script> tags set via innerHTML never execute — the
                // iframe-population script in the fetched fragment needs
                // to be manually re-created to actually run.
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
    }
    document.querySelectorAll('tr.js-email-row-link').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, button')) return;
            openEmailRow(row);
        });
        // Keyboard equivalent (role="button" + tabindex="0" on the row).
        row.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.target.closest('a, button')) return;
            e.preventDefault();
            openEmailRow(row);
        });
    });

    // Bootstrap sets aria-hidden="true" on hide before it moves focus back to
    // the trigger — if the row that opened the modal is now keyboard-focusable
    // (tabindex="0", added for a11y) and still holds focus at that moment,
    // the browser blocks it: "aria-hidden on an element because its descendant
    // retained focus". Blur proactively so hide() never fights the focused row.
    [suiviModalEl, modalEl].forEach(function (el) {
        if (!el) return;
        el.addEventListener('hide.bs.modal', function () {
            if (document.activeElement && el.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    });
});
</script>
