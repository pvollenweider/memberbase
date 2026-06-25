<?php
ob_start();
$charset = "UTF-8";

// Auth — must run before any output
require_once __DIR__ . '/includes/auth.inc';
requireLogin();
requirePasswordChange();

header("Content-Type: text/html; charset=$charset");
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

<body>

<?php
include "includes/menu.inc";
?>
<div class="container mt-2">
    <div class="row">
        <div class="col-12">
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
            Casa Members v2.2.0
        </small>
    </div>
</footer>

<script>
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
</script>

</body>
</html>
<?php
Logger::shutdown();
ob_end_flush();
?>
