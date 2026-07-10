<?php
/**
 * Application entry point — renders the full page or an htmx partial fragment.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

define('APP_ENTRY', true);

ob_start();
$charset = "UTF-8";

// Auth — must run before any output
require_once __DIR__ . '/includes/lib/auth.php';
requireLogin();
requirePasswordChange();

header("Content-Type: text/html; charset=$charset");

// Load core dependencies before any output (needed for appSettings in page title)
require_once __DIR__ . "/includes/lib/locale.php";
mbLoadLocale($_SESSION['app_user_locale'] ?? null);
include __DIR__ . "/includes/lib/bootstrap.php";
include "classes/contact_class.php";
include "classes/segment_class.php";
include "classes/compta_class.php";
include "classes/property_class.php";
include "classes/combined_segment_class.php";
include "classes/member_filter_class.php";

// htmx partial response — skip layout, return only content fragment
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);
if ($isHtmx) {
    $userid = -1;
    $view = $_REQUEST['view'] ?? 'list';
    include __DIR__ . "/includes/routing/actions.php";
    include __DIR__ . "/includes/routing/views.php";
    ob_end_flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($GLOBAL['currentLocale'] ?? 'fr', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, $charset) ?>">
    <title><?= htmlspecialchars($appSettings['org_name'] ?: $GLOBAL['memberManagement'], ENT_QUOTES, $charset) ?></title>
    <?php
    function getMicroTime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    $start = getMicroTime();
    ?>

    <?php $v = APP_VERSION; ?>
    <!-- Inter (self-hosted) -->
    <link rel="stylesheet" href="css/vendor/inter.css?v=<?= $v ?>">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="css/vendor/bootstrap.min.css?v=<?= $v ?>">

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <link rel="stylesheet" href="css/vendor/dataTables.bootstrap5.min.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/vendor/buttons.bootstrap5.min.css?v=<?= $v ?>">

    <!-- Datetimepicker -->
    <link rel="stylesheet" href="css/bootstrap-datetimepicker.min.css?v=<?= $v ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/vendor/font-awesome.min.css?v=<?= $v ?>">

    <!-- Custom -->
    <link rel="stylesheet" href="css/custom.css?v=<?= $v ?>">

    <!-- jQuery (required by DataTables, datetimepicker) -->
    <script src="js/vendor/jquery-3.7.1.min.js?v=<?= $v ?>"></script>

    <!-- Bootstrap 5 bundle (includes Popper) -->
    <script src="js/vendor/bootstrap.bundle.min.js?v=<?= $v ?>"></script>

    <!-- Moment.js (required by datetimepicker) -->
    <script src="js/vendor/moment.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/fr.js?v=<?= $v ?>"></script>

    <!-- Datetimepicker -->
    <script src="js/vendor/bootstrap-datetimepicker.min.js?v=<?= $v ?>"></script>

    <!-- Highlight + datahref -->
    <script src="js/vendor/jquery.highlight.js?v=<?= $v ?>"></script>
    <script src="js/vendor/datahref2.jquery.js?v=<?= $v ?>"></script>

    <!-- DataTables 1.13.x + Bootstrap 5 -->
    <script src="js/vendor/jquery.dataTables.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/dataTables.bootstrap5.min.js?v=<?= $v ?>"></script>

    <!-- DataTables Buttons -->
    <script src="js/vendor/dataTables.buttons.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/buttons.bootstrap5.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/jszip.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/pdfmake.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/vfs_fonts.js?v=<?= $v ?>"></script>
    <script src="js/vendor/buttons.html5.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/buttons.print.min.js?v=<?= $v ?>"></script>
    <script src="js/vendor/buttons.colVis.min.js?v=<?= $v ?>"></script>

    <!-- DataTables moment sorting plugin -->
    <script src="js/vendor/datetime-moment.js?v=<?= $v ?>"></script>

    <!-- Shared DataTables defaults -->
    <script src="js/dt_defaults.js?v=<?= $v ?>"></script>

    <!-- Chart.js -->
    <script src="js/vendor/Chart.bundle.min.js?v=<?= $v ?>"></script>

    <!-- htmx + Alpine.js -->
    <script src="js/vendor/htmx.min.js?v=<?= $v ?>"></script>
    <!-- Alpine components (CSP 'self' compatible, loaded before Alpine init) -->
    <script src="js/member-general-form.js?v=<?= $v ?>"></script>
    <script defer src="js/vendor/alpine.min.js?v=<?= $v ?>"></script>
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
    <script type="module" src="js/tiptap-editor.js?v=<?= filemtime(__DIR__ . '/js/tiptap-editor.js') ?>"></script>
</head>

<body hx-boost="true" hx-target="#main-content" hx-swap="innerHTML" hx-push-url="true">

<?php
include __DIR__ . "/includes/partials/menu.php";

// Bandeau d'alerte : migrations DB en attente (visible admin uniquement).
$_pendingMigrations = isAdmin() ? pendingMigrations($pdo) : [];
if ($_pendingMigrations):
?>
<div class="container mt-2">
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">
        <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
        <div>
            <strong><?= count($_pendingMigrations) ?>
                <?= sprintf($GLOBAL['pendingDbMigrationsLabel'], count($_pendingMigrations) > 1 ? 's' : '') ?></strong>
            (<?= htmlspecialchars(implode(', ', $_pendingMigrations), ENT_QUOTES, $charset) ?>).
            <?= sprintf($GLOBAL['pendingMigrationsBannerBody'], count($_pendingMigrations) > 1 ? 's' : '', appUrl()) ?>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="container mt-2">
    <div class="row">
        <div class="col-12" id="main-content">
            <?php
            $userid = -1;
            include __DIR__ . "/includes/routing/actions.php";
            include __DIR__ . "/includes/routing/views.php";
            $end = getMicroTime();
            ?>
        </div>
    </div>
</div>
<!-- Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100" aria-live="polite" aria-atomic="true">
    <div id="casaToast" class="toast text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true"
         data-msg-saved="<?= htmlspecialchars($GLOBAL['saved'], ENT_QUOTES, $charset) ?>"
         data-msg-group-modified="<?= htmlspecialchars($GLOBAL['groupModified'], ENT_QUOTES, $charset) ?>">
        <div class="d-flex align-items-center px-3 py-2 gap-2">
            <i class="fas fa-check-circle flex-shrink-0" aria-hidden="true"></i>
            <span id="casaToastMsg" class="flex-grow-1"><?= $GLOBAL['saved'] ?></span>
            <a id="casaToastUndo" href="#" class="btn btn-sm btn-outline-light py-0 px-2 flex-shrink-0" style="display:none;font-size:0.78rem"><?= $GLOBAL['cancel'] ?></a>
            <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="toast" aria-label="<?= $GLOBAL['close'] ?>"></button>
        </div>
    </div>
</div>

<hr/>
<footer class="bs-footer" role="contentinfo">
    <div class="container">
        <small>Process time: [<?= (int)(($end - $start) * 1000) ?>
            ms]. Date: [<?= date("d.m.Y H:i", time()) ?>]<br/>
            <a href="https://pvollenweider.github.io/memberbase/" target="_blank" rel="noopener" class="text-muted">MemberBase v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, $charset) ?></a>
            &middot; <a href="https://pvollenweider.github.io/memberbase/docs/" target="_blank" rel="noopener" class="text-muted"><?= htmlspecialchars($GLOBAL['documentation'], ENT_QUOTES, $charset) ?></a>
        </small>
    </div>
</footer>

<script src="js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>"></script>

</body>
</html>
<?php
ob_end_flush();
?>
