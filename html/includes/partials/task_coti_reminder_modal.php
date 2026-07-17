<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Preview/send modal for the "Envoyer le rappel" button on cotisation-reminder
 * tasks (rule_key unpaid_coti_current_*). Reuses the existing
 * previewCotisationReminder/sendCotisationReminderOne actions
 * (includes/actions/cotisation_reminder.php) — same email/QR-bill flow as the
 * Membres perdus view. On success, closes the linked task (task_id) too.
 *
 * Included from tasks_list.php and tasks_global.php when at least one visible
 * row has a .js-task-send-coti button.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
?>
<div class="modal fade" id="taskCotiPreviewModal" tabindex="-1" aria-labelledby="taskCotiPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="taskCotiPreviewModalLabel"><?= htmlspecialchars($GLOBAL['sendCotiRemindersBtnOne'], ENT_QUOTES, $charset) ?></h5>
          <div class="text-muted small" id="task-coti-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body p-0" style="min-height:300px">
        <div id="task-coti-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="task-coti-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="task-coti-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin"></iframe>
      </div>
      <div class="modal-footer gap-2">
        <div class="me-auto small text-muted" id="task-coti-modal-subject"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($GLOBAL['cancel'], ENT_QUOTES, $charset) ?></button>
        <button type="button" class="btn btn-primary" id="btn-task-coti-send" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['sendCotiRemindersBtnOne'], ENT_QUOTES, $charset) ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
    var rowBtns = document.querySelectorAll('.js-task-send-coti');
    if (!rowBtns.length) return;

    var baseUrl = <?= json_encode(appUrl()) ?>;
    function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }

    var modal      = new bootstrap.Modal(document.getElementById('taskCotiPreviewModal'));
    var loadingEl   = document.getElementById('task-coti-modal-loading');
    var errorEl     = document.getElementById('task-coti-modal-error');
    var frame       = document.getElementById('task-coti-modal-frame');
    var metaEl      = document.getElementById('task-coti-modal-meta');
    var subjectEl   = document.getElementById('task-coti-modal-subject');
    var sendBtn     = document.getElementById('btn-task-coti-send');
    var pendingBtn  = null;
    var previewOk   = false;

    rowBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingBtn = btn;
            loadingEl.style.display = '';
            errorEl.style.display   = 'none';
            frame.style.display     = 'none';
            metaEl.textContent      = btn.dataset.confirm;
            subjectEl.textContent   = '';
            previewOk               = false;
            sendBtn.disabled        = true;
            modal.show();

            fetch(baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                body: 'action=previewCotisationReminder&user_id=' + encodeURIComponent(btn.dataset.userId) + '&year=' + encodeURIComponent(btn.dataset.year)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loadingEl.style.display = 'none';
                if (!data.ok) {
                    errorEl.textContent = data.error || '?';
                    errorEl.style.display = '';
                    return;
                }
                subjectEl.textContent = data.subject;
                frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
                frame.style.display = '';
                frame.addEventListener('load', function () {
                    try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch(e){}
                }, { once: true });
                frame.style.height = '500px';
                previewOk = true;
                sendBtn.disabled = false;
            })
            .catch(function () {
                loadingEl.style.display = 'none';
                errorEl.textContent = '?';
                errorEl.style.display = '';
            });
        });
    });

    sendBtn.addEventListener('click', function () {
        var btn = pendingBtn;
        if (!btn || !previewOk) return;
        modal.hide();
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
        fetch(baseUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
            body: 'action=sendCotisationReminderOne&user_id=' + encodeURIComponent(btn.dataset.userId)
                + '&year=' + encodeURIComponent(btn.dataset.year)
                + '&task_id=' + encodeURIComponent(btn.dataset.taskId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                // Reload rather than remove the <tr> by hand: the global view's
                // table is DataTables-managed, and direct DOM removal desyncs
                // its internal row model (pagination/search break silently).
                window.__dirtyOverride = true;
                window.location.reload();
            } else {
                btn.innerHTML = '<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' + btn.dataset.msgFail;
                btn.classList.replace('btn-outline-primary', 'btn-outline-danger');
                btn.disabled = false;
            }
        })
        .catch(function () {
            btn.innerHTML = orig;
            btn.disabled = false;
        });
    });
})();
</script>
