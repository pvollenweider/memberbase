/**
 * Generic preview/send modal controller — shared by the compta recap
 * (compta_recap.php) and the per-row attestation send (donors_summary.php)
 * flows (#152). Both render the modal markup via
 * includes/partials/preview_send_modal.php and wire it up by calling
 * initPreviewSendModal() with view-specific options.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
(function (global) {
    'use strict';

    function getCsrf() {
        return global.casaCsrfToken ? global.casaCsrfToken() : '';
    }

    /**
     * @param {Object} opts
     * @param {string} opts.id                 Element id prefix (modal = "<id>-modal", send button = "btn-<id>-send", ...)
     * @param {string} opts.baseUrl             appUrl() of the current app
     * @param {string} opts.triggerSelector     CSS selector for the elements that open the modal
     * @param {string} opts.previewAction       action= value for the preview POST
     * @param {string} opts.sendAction          action= value for the send POST
     * @param {function(Element):string} opts.getMetaText     text shown under the modal title while loading
     * @param {function(Element):string} opts.getPreviewParams  extra "key=val&..." body params for the preview POST
     * @param {function(Element):string} opts.getSendParams     extra "key=val&..." body params for the send POST
     * @param {function(Object,Element,bootstrap.Modal):void} opts.onSettled  called with the send response, whether ok or not
     * @param {string} opts.sendBtnHtml         idle HTML of the send button
     * @param {string} opts.sendingText         text shown next to the spinner while sending
     * @param {string} opts.genericErrorText    fallback error text
     * @param {boolean} [opts.offSeasonGate]    whether an off-season confirmation checkbox gates the send button
     * @param {boolean} [opts.bcc]              whether a BCC checkbox is present and should append &bcc=1 when checked
     */
    function initPreviewSendModal(opts) {
        var triggers = document.querySelectorAll(opts.triggerSelector);
        if (!triggers.length) return;

        var modalEl = document.getElementById(opts.id + '-modal');
        if (!modalEl) return;

        var modal       = new bootstrap.Modal(modalEl);
        var loadingEl   = document.getElementById(opts.id + '-modal-loading');
        var errorEl     = document.getElementById(opts.id + '-modal-error');
        var frame       = document.getElementById(opts.id + '-modal-frame');
        var metaEl      = document.getElementById(opts.id + '-modal-meta');
        var subjectEl   = document.getElementById(opts.id + '-modal-subject');
        var sendBtn     = document.getElementById('btn-' + opts.id + '-send');
        var offSeasonCb = opts.offSeasonGate ? document.getElementById(opts.id + '-off-season-confirm') : null;
        var bccCb       = opts.bcc ? document.getElementById(opts.id + '-bcc') : null;

        var currentTrigger = null;
        var previewOk = false;

        function syncSendEnabled() {
            sendBtn.disabled = !previewOk || (offSeasonCb ? !offSeasonCb.checked : false);
        }
        if (offSeasonCb) { offSeasonCb.addEventListener('change', syncSendEnabled); }

        function resetSendBtn() {
            sendBtn.innerHTML = opts.sendBtnHtml;
            syncSendEnabled();
        }

        triggers.forEach(function (el) {
            el.addEventListener('click', function () {
                currentTrigger = el;
                previewOk = false;

                loadingEl.style.display = '';
                errorEl.style.display   = 'none';
                frame.style.display     = 'none';
                metaEl.textContent      = opts.getMetaText(el);
                subjectEl.textContent   = '';
                if (offSeasonCb) { offSeasonCb.checked = false; }
                resetSendBtn();
                sendBtn.disabled = true;
                modal.show();

                fetch(opts.baseUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                    body: 'action=' + opts.previewAction + '&' + opts.getPreviewParams(el)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loadingEl.style.display = 'none';
                    if (!data.ok) {
                        errorEl.textContent = data.error || opts.genericErrorText;
                        errorEl.style.display = '';
                        return;
                    }
                    subjectEl.textContent = data.subject;
                    frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
                    frame.style.display = '';
                    frame.addEventListener('load', function () {
                        try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch (e) {}
                    }, { once: true });
                    frame.style.height = '500px';
                    // Only offer to send when the row has an email address (data-email
                    // set by the caller) — some views list rows without one (e.g. the
                    // compta recap "already sent" table isn't filtered on email presence).
                    previewOk = el.dataset.email === undefined || el.dataset.email !== '';
                    syncSendEnabled();
                })
                .catch(function () {
                    loadingEl.style.display = 'none';
                    errorEl.textContent = opts.genericErrorText;
                    errorEl.style.display = '';
                });
            });
        });

        sendBtn.addEventListener('click', function () {
            if (!currentTrigger) return;
            var trigger = currentTrigger;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + opts.sendingText;

            var body = 'action=' + opts.sendAction + '&' + opts.getSendParams(trigger);
            if (bccCb && bccCb.checked) { body += '&bcc=1'; }

            fetch(opts.baseUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    resetSendBtn();
                    errorEl.textContent = data.error || opts.genericErrorText;
                    errorEl.style.display = '';
                }
                opts.onSettled(data, trigger, modal);
            })
            .catch(function () {
                resetSendBtn();
            });
        });
    }

    global.initPreviewSendModal = initPreviewSendModal;
})(window);
