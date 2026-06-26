<?php
ob_start();
$charset = "UTF-8";

// Auth — must run before any output
require_once __DIR__ . '/includes/auth.inc';
requireLogin();
requirePasswordChange();

header("Content-Type: text/html; charset=$charset");

// htmx partial response — skip layout, return only content fragment
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);
if ($isHtmx) {
    include "locales/resources_fr.inc";
    include "includes/declarations.inc";
    include "classes/user_class.inc";
    include "classes/team_class.inc";
    include "classes/compta_class.inc";
    include "classes/property_class.inc";
    include "classes/metagroup_class.inc";
    $userid = -1;
    $view = $_REQUEST['view'] ?? 'list';
    include "includes/manage_actions.inc";
    include "includes/manage_views.inc";
    ob_end_flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casa Alianza - fichier des membres</title>
    <?php
    include "locales/resources_fr.inc";
    include "includes/declarations.inc";
    include "classes/user_class.inc";
    include "classes/team_class.inc";
    include "classes/compta_class.inc";
    include "classes/property_class.inc";
    include "classes/metagroup_class.inc";
    function getMicroTime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    $start = getMicroTime();
    ?>

    <!-- Inter (self-hosted) -->
    <link rel="stylesheet" href="css/vendor/inter.css">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="css/vendor/bootstrap.min.css">

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <link rel="stylesheet" href="css/vendor/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="css/vendor/buttons.bootstrap5.min.css">

    <!-- Datetimepicker -->
    <link rel="stylesheet" href="css/bootstrap-datetimepicker.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/font-awesome/css/all.min.css">

    <!-- Custom -->
    <link rel="stylesheet" href="css/custom.css">

    <!-- jQuery (required by DataTables, CKEditor, datetimepicker) -->
    <script src="js/jquery-3.3.1.min.js"></script>

    <!-- Bootstrap 5 bundle (includes Popper) -->
    <script src="js/vendor/bootstrap.bundle.min.js"></script>

    <!-- Moment.js (required by datetimepicker) -->
    <script src="js/vendor/moment.min.js"></script>
    <script src="js/vendor/fr.js"></script>

    <!-- Datetimepicker -->
    <script src="js/bootstrap-datetimepicker.min.js"></script>

    <!-- Highlight + datahref -->
    <script src="js/jquery.highlight.js"></script>
    <script src="js/datahref2.jquery.js"></script>

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <script src="js/vendor/jquery.dataTables.min.js"></script>
    <script src="js/vendor/dataTables.bootstrap5.min.js"></script>

    <!-- DataTables Buttons -->
    <script src="js/vendor/dataTables.buttons.min.js"></script>
    <script src="js/vendor/buttons.bootstrap5.min.js"></script>
    <script src="js/vendor/jszip.min.js"></script>
    <script src="js/vendor/pdfmake.min.js"></script>
    <script src="js/vendor/vfs_fonts.js"></script>
    <script src="js/vendor/buttons.html5.min.js"></script>
    <script src="js/vendor/buttons.print.min.js"></script>
    <script src="js/vendor/buttons.colVis.min.js"></script>

    <!-- DataTables moment sorting plugin -->
    <script src="js/vendor/datetime-moment.js"></script>

    <!-- Chart.js -->
    <script src="js/vendor/Chart.bundle.min.js"></script>

    <!-- htmx + Alpine.js -->
    <script src="js/vendor/htmx.min.js"></script>
    <script defer src="js/vendor/alpine.min.js"></script>
    <meta name="htmx-config" content='{"scrollIntoViewOnBoost": false, "defaultSwapStyle": "innerHTML"}'>

    <style>
        .tiptap-wrap { background: #fff; }
        .tt-btn {
            border: none; background: transparent; border-radius: 4px;
            padding: 2px 6px; color: #495057; cursor: pointer; font-size: 0.8rem;
            line-height: 1.4;
        }
        .tt-btn:hover { background: #e9ecef; }
        .tt-btn.is-active { background: #d3d8de; color: #212529; }
        .tt-sep { width: 1px; background: #dee2e6; margin: 2px 2px; }
        .tiptap-body .ProseMirror { outline: none; min-height: 70px; }
        .tiptap-body .ProseMirror p { margin-bottom: 0.25rem; }
        .tiptap-body .ProseMirror ul,
        .tiptap-body .ProseMirror ol { padding-left: 1.4rem; margin-bottom: 0.25rem; }
    </style>

    <!-- TipTap rich text editor -->
    <script type="module">
        import { Editor } from 'https://esm.sh/@tiptap/core@2';
        import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2';

        function initTiptap(root) {
            var el = (root && root.querySelector) ? root.querySelector('#tiptap-comment') : document.getElementById('tiptap-comment');
            if (!el || el._tt) return;
            var hidden = document.getElementById('comment');
            var editor = new Editor({
                element: el,
                extensions: [StarterKit],
                content: hidden ? hidden.value : '',
                onUpdate: function(_ref) {
                    if (hidden) hidden.value = _ref.editor.getHTML();
                },
                onSelectionUpdate: function(_ref) { updateToolbar(_ref.editor); },
                onTransaction: function(_ref) { updateToolbar(_ref.editor); },
            });
            el._tt = editor;

            // Toolbar button wiring
            var wrap = el.closest('.tiptap-wrap');
            if (wrap) {
                wrap.querySelectorAll('.tt-btn').forEach(function(btn) {
                    btn.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        var cmd = btn.dataset.tt;
                        if      (cmd === 'bold')        editor.chain().focus().toggleBold().run();
                        else if (cmd === 'italic')      editor.chain().focus().toggleItalic().run();
                        else if (cmd === 'bulletList')  editor.chain().focus().toggleBulletList().run();
                        else if (cmd === 'orderedList') editor.chain().focus().toggleOrderedList().run();
                        else if (cmd === 'undo')        editor.chain().focus().undo().run();
                        else if (cmd === 'redo')        editor.chain().focus().redo().run();
                    });
                });
            }
        }

        function updateToolbar(editor) {
            var wrap = document.querySelector('.tiptap-wrap');
            if (!wrap) return;
            wrap.querySelectorAll('.tt-btn[data-tt]').forEach(function(btn) {
                var cmd = btn.dataset.tt;
                var active = false;
                if (cmd === 'bold')        active = editor.isActive('bold');
                else if (cmd === 'italic') active = editor.isActive('italic');
                else if (cmd === 'bulletList')  active = editor.isActive('bulletList');
                else if (cmd === 'orderedList') active = editor.isActive('orderedList');
                btn.classList.toggle('is-active', active);
            });
        }

        function destroyTiptap(root) {
            var el = (root && root.querySelector) ? root.querySelector('#tiptap-comment') : document.getElementById('tiptap-comment');
            if (el && el._tt) { el._tt.destroy(); el._tt = null; }
        }

        initTiptap(document);
        document.addEventListener('htmx:beforeSwap', function(e) { destroyTiptap(e.detail.target); });
        document.addEventListener('htmx:afterSwap',  function(e) { initTiptap(e.detail.target); });
    </script>

    <script>
        sfFocus = function () {
            var sfEls = document.getElementsByTagName("INPUT");
            for (var i = 0; i < sfEls.length; i++) {
                sfEls[i].onfocus = function () {
                    this.className += " sffocus";
                }
                sfEls[i].onblur = function () {
                    this.className = this.className.replace(new RegExp(" sffocus\\b"), "");
                }
            }
        }
        if (window.attachEvent) window.attachEvent("onload", sfFocus);

        function viewUser(id) {
            var url = "<?=$_SERVER['PHP_SELF']?>?view=generalData&id=" + id;
            location = url;
        }
    </script>
</head>

<body hx-boost="true" hx-target="#main-content" hx-swap="innerHTML" hx-push-url="true">

<?php
include "includes/menu.inc";
?>
<div class="container mt-2">
    <div class="row">
        <div class="col-12" id="main-content">
            <?php
            $userid = -1;
            include "includes/manage_actions.inc";
            include "includes/manage_views.inc";
            $end = getMicroTime();
            ?>
        </div>
    </div>
</div>
<!-- Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100" aria-live="polite" aria-atomic="true">
    <div id="casaToast" class="toast text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex align-items-center px-3 py-2 gap-2">
            <i class="fas fa-check-circle flex-shrink-0" aria-hidden="true"></i>
            <span id="casaToastMsg" class="flex-grow-1">Enregistré.</span>
            <a id="casaToastUndo" href="#" class="btn btn-sm btn-outline-light py-0 px-2 flex-shrink-0" style="display:none;font-size:0.78rem">Annuler</a>
            <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
    </div>
</div>

<hr/>
<footer class="bs-footer" role="contentinfo">
    <div class="container">
        <small>Process time: [<?= (int)(($end - $start) * 1000) ?>
            ms]. Date: [<?= date("d.m.Y H:i", time()) ?>]<br/>
            Casa Members v3.0.0
        </small>
    </div>
</footer>

<script>
(function () {
    var dirty = false;

    function markDirty(e) {
        var el = e.target;
        if (!el || !el.closest) return;
        if (el.classList.contains('mg-team-cb')) return;
        if (el.id === 'includeAttestation') return;
        if (el.closest('form[data-no-dirty]')) return;
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

    $('table').datahref();
    $(function () {
        $('.datepicker').datetimepicker({
            format:'L',
            locale: 'fr'
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
                clear: 'far fa-trash-alt',
                close: 'far fa-times-circle'
            }
        });
    });

    // Re-initialize jQuery plugins after every htmx content swap
    function casaInit(root) {
        root = root || document;

        // datepicker
        $(root).find('.datepicker').each(function () {
            if (!$(this).data('DateTimePicker')) {
                $(this).datetimepicker({ format: 'L', locale: 'fr' });
            }
        });

        // datahref click-to-row
        $(root).find('table[data-href], table').datahref && $(root).find('table').datahref();

    }

    document.addEventListener('htmx:afterSwap', function (e) {
        casaInit(e.target);
        var toastEl = document.getElementById('casaToast');
        if (!toastEl || !e.target.querySelector) return;
        var undoEl = document.getElementById('casaToastUndo');
        var msgEl  = document.getElementById('casaToastMsg');

        if (e.target.querySelector('#casa-save-ok')) {
            msgEl.textContent = 'Enregistré.';
            if (undoEl) undoEl.style.display = 'none';
            bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3000 }).show();
        }

        var membership = e.target.querySelector('#casa-membership-toast');
        if (membership) {
            var msg     = membership.dataset.msg     || 'Groupe modifié.';
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
</script>

</body>
</html>
<?php
ob_end_flush();
?>
