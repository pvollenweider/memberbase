<?php
$charset = "UTF-8";
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

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    <!-- Datetimepicker -->
    <link rel="stylesheet" href="css/bootstrap-datetimepicker.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/font-awesome/css/all.min.css">

    <!-- Custom -->
    <link rel="stylesheet" href="css/custom.css">

    <!-- jQuery (required by DataTables, CKEditor, datetimepicker) -->
    <script src="js/jquery-3.3.1.min.js"></script>

    <!-- Bootstrap 5 bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Moment.js (required by datetimepicker) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.21.0/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.21.0/locale/fr.js"></script>

    <!-- Datetimepicker -->
    <script src="js/bootstrap-datetimepicker.min.js"></script>

    <!-- CKEditor -->
    <script src="plugins/ckeditor/ckeditor.js"></script>
    <script src="plugins/ckeditor/adapters/jquery.js"></script>

    <!-- Highlight + datahref -->
    <script src="js/jquery.highlight.js"></script>
    <script src="js/datahref2.jquery.js"></script>

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

    <!-- DataTables moment sorting plugin -->
    <script src="https://cdn.datatables.net/plug-ins/1.13.7/sorting/datetime-moment.js"></script>

    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.min.js"></script>

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
            Casa Members revision 226.
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
?>
