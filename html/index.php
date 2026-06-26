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

    <!-- CKEditor -->
    <script src="plugins/ckeditor/ckeditor.js"></script>
    <script src="plugins/ckeditor/adapters/jquery.js"></script>

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

    <script type="text/javascript">
        $(function () {
            var config = {
                toolbar: [
                    ['Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Unlink'],
                    ['UIColor']
                ]
            };
            $('.ck').ckeditor(config);
        });
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
<hr/>
<footer class="bs-footer" role="contentinfo">
    <div class="container">
        <small>Process time: [<?= (int)(($end - $start) * 1000) ?>
            ms]. Date: [<?= date("d.m.Y H:i", time()) ?>]<br/>
            Casa Members v2.2.2
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

        // CKEditor — destroy stale instances, re-attach to .ck textareas
        if (typeof CKEDITOR !== 'undefined') {
            $(root).find('textarea.ck').each(function () {
                var name = this.name || this.id;
                if (name && CKEDITOR.instances[name]) {
                    CKEDITOR.instances[name].destroy(true);
                }
                $(this).ckeditor({
                    toolbar: [['Bold','Italic','-','NumberedList','BulletedList','-','Link','Unlink'],['UIColor']]
                });
            });
        }
    }

    document.addEventListener('htmx:afterSwap', function (e) {
        casaInit(e.target);
    });
</script>

</body>
</html>
<?php
ob_end_flush();
?>
