/**
 * Application-wide JavaScript: dirty-form guard, jQuery plugin
 * initialization after htmx swaps, and save/undo toast handling.
 *
 * Loaded at the end of <body> — the DOM (and jQuery) are available.
 * Localized toast messages are read from data attributes on #casaToast
 * (data-msg-saved, data-msg-segment-modified) rendered by index.php.
 *
 * Dirty-form contract (see CLAUDE.md): inline JS navigation must set
 * window.__dirtyOverride = true before changing window.location, and
 * navigation-only selects/inputs must carry data-no-dirty.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

// ---------------------------------------------------------------------------
// CSRF token propagation (#69)
// The token is rendered once in <meta name="csrf-token"> by index.php and
// stays in the DOM across htmx swaps (the <head> is never replaced).
// ---------------------------------------------------------------------------
(function () {
    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
    // Exposed for the few inline scripts that POST via raw fetch() to index.php.
    window.casaCsrfToken = csrfToken;

    // htmx requests (boosted form submits, links, hx-post/put/delete): send header.
    document.addEventListener('htmx:configRequest', function (e) {
        var t = csrfToken();
        if (t) e.detail.headers['X-CSRF-Token'] = t;
    });

    // Stamp every POST form with a hidden `csrf` field. Done proactively (not on
    // the submit event) so programmatic form.submit() calls — which do NOT fire
    // the submit event — still carry the token. Covers native submits
    // (hx-boost="false", multipart uploads) and is harmless for htmx submits.
    function stampForms(root) {
        var t = csrfToken();
        if (!t) return;
        (root || document).querySelectorAll('form[method="post" i]').forEach(function (form) {
            if (form.querySelector('input[name="csrf"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf';
            input.value = t;
            form.appendChild(input);
        });
    }

    stampForms(document);
    document.addEventListener('htmx:afterSwap', function (e) { stampForms(e.target); });
})();

// ---------------------------------------------------------------------------
// Dirty-form guard
// ---------------------------------------------------------------------------
(function () {
    var dirty = false;

    function markDirty(e) {
        var el = e.target;
        if (!el || !el.closest) return;
        if (el.classList.contains('mg-segment-cb')) return;
        if (el.id === 'includeAttestation') return;
        if (el.id === 'segment-filter-input') return;
        if (el.closest('[data-no-dirty]')) return;
        if (el.closest('.dt-search, .dataTables_filter')) return;
        if (el.closest('.modal')) return;
        if (el.closest('#bulk-form')) return;
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
            dirty = true;
        }
    }

    document.addEventListener('change', markDirty);
    document.addEventListener('input', markDirty);
    document.addEventListener('submit', function () { dirty = false; });

    // htmx navigation — warn before swap if dirty, but not on form submissions (save = intentional)
    document.addEventListener('htmx:beforeRequest', function (e) {
        if (!dirty || window.__dirtyOverride) return;
        var verb = (e.detail && e.detail.requestConfig && e.detail.requestConfig.verb) || 'get';
        if (verb === 'post') { dirty = false; return; } // form submit — let it through
        if (!confirm('Des modifications non sauvegardées seront perdues. Continuer ?')) {
            e.preventDefault();
        } else {
            dirty = false;
        }
    });

    // Reset dirty after htmx swap (new content loaded)
    document.addEventListener('htmx:afterSwap', function () {
        dirty = false;
        window.__dirtyOverride = false;
    });

    // Fallback for non-htmx navigation (browser back, manual URL)
    window.addEventListener('beforeunload', function (e) {
        if (!dirty || window.__dirtyOverride) return;
        e.preventDefault();
        e.returnValue = 'Des modifications non sauvegardées seront perdues. Continuer ?';
        return e.returnValue;
    });
})();

// ---------------------------------------------------------------------------
// Global navigation shortcuts: Option/Alt+Cmd/Meta+1/2/3 → dashboard,
// Membres & finances, Journaux. Routed through htmx.ajax (not
// window.location) so it behaves exactly like clicking a nav link: same
// SPA partial swap, same dirty-form confirm via htmx:beforeRequest.
// ---------------------------------------------------------------------------
(function () {
    var VIEW_BY_KEY = { '1': 'dashboard', '2': 'peopleFinance', '3': 'journals' };

    document.addEventListener('keydown', function (e) {
        if (!e.altKey || !e.metaKey) return;
        var view = VIEW_BY_KEY[e.key];
        if (!view) return;
        e.preventDefault();
        var scriptName = location.pathname.substring(location.pathname.lastIndexOf('/') + 1) || 'index.php';
        htmx.ajax('GET', scriptName + '?view=' + view, { target: '#main-content', swap: 'innerHTML', pushUrl: true });
    });
})();

// ---------------------------------------------------------------------------
// jQuery plugin bootstrapping (initial page load)
// ---------------------------------------------------------------------------
$('table').datahref();
$(function () {
    $('.datepicker').datetimepicker({
        format: 'L',
        locale: 'fr',
        widgetParent: 'body'
    });
    $.extend(true, $.fn.datetimepicker.defaults, {
        icons: {
            time: 'far fa-clock',
            date: 'far fa-calendar',
            up: 'fas fa-arrow-up',
            down: 'fas fa-arrow-down',
            previous: 'fas fa-chevron-left',
            next: 'fas fa-chevron-right',
            today: 'fas fa-calendar-check',
            clear: 'far fa-trash-can',
            close: 'far fa-circle-xmark'
        }
    });
});

// Destroy DataTables before htmx snapshots the DOM for history cache
// (prevents htmx restoring a DataTables-wrapped DOM that causes column mismatch on re-init)
document.addEventListener('htmx:beforeHistorySave', function () {
    if ($.fn.DataTable) {
        $.fn.DataTable.tables({ visible: true,  api: true }).destroy();
        $.fn.DataTable.tables({ visible: false, api: true }).destroy();
    }
});

// Re-initialize jQuery plugins after every htmx content swap
function casaInit(root) {
    root = root || document;

    // datepicker
    $(root).find('.datepicker').each(function () {
        if (!$(this).data('DateTimePicker')) {
            $(this).datetimepicker({ format: 'L', locale: 'fr', widgetParent: 'body' });
        }
    });

    // datahref click-to-row (plugin uses namespaced event — safe to call multiple times)
    if ($(root).find('table').datahref) { $(root).find('table').datahref(); }

    // destroy any orphaned DataTable instances from previous swap
    if ($.fn.DataTable) {
        $.fn.DataTable.tables({ visible: true,  api: true }).destroy();
        $.fn.DataTable.tables({ visible: false, api: true }).destroy();
    }
}

// ---------------------------------------------------------------------------
// Post-swap cleanup + save/undo toast
// ---------------------------------------------------------------------------
document.addEventListener('htmx:afterSwap', function (e) {
    // Clean up Bootstrap modal state left behind after htmx content swap
    document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    casaInit(e.target);
    var toastEl = document.getElementById('casaToast');
    if (!toastEl || !e.target.querySelector) return;
    var undoEl = document.getElementById('casaToastUndo');
    var msgEl  = document.getElementById('casaToastMsg');

    if (e.target.querySelector('#casa-save-ok')) {
        msgEl.textContent = toastEl.dataset.msgSaved || 'Enregistré';
        if (undoEl) undoEl.style.display = 'none';
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3000 }).show();
    }

    var membership = e.target.querySelector('#casa-membership-toast');
    if (membership) {
        var msg     = membership.dataset.msg     || toastEl.dataset.msgSegmentModified || '';
        var undoUrl = membership.dataset.undoUrl || '';
        msgEl.textContent = msg;
        if (undoEl) {
            if (undoUrl) {
                undoEl.href = undoUrl;
                undoEl.style.display = '';
                undoEl.onclick = function () {
                    bootstrap.Toast.getInstance(toastEl).hide();
                };
            } else {
                undoEl.style.display = 'none';
            }
        }
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 5000 }).show();
    }
});
