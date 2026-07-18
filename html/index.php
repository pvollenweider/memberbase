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
include "classes/suivi_task_class.php";

// htmx partial response — skip layout, return only content fragment
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);
if ($isHtmx) {
    $userid = -1;
    $view = mbDefaultView($_REQUEST);
    include __DIR__ . "/includes/routing/actions.php";
    include __DIR__ . "/includes/routing/views.php";
    // Real htmx-boosted navigation (sidebar/topbar/hub links, all targeting
    // #main-content per the hx-target on <body>) sends HX-Target: main-content.
    // Many existing modal-loading flows (compta_list.php, suivi_list.php,
    // email/attestation previews, task modals…) spoof HX-Request: true on a
    // raw fetch() to reach this same fast-path and get a bare view fragment
    // for innerHTML injection into a .modal-body — they never set HX-Target.
    // Appending the OOB sidebar/topbar unconditionally used to leak into
    // those modal fragments (duplicated markup, and htmx.process() on the
    // modal body would then actually swap the page's real nav out-of-band).
    if (($_SERVER['HTTP_HX_TARGET'] ?? '') === 'main-content') {
        // The sidebar/topbar live outside #main-content (htmx's default boost
        // target), so a plain boosted swap would leave their "active" nav-link
        // stale after navigating — re-render both here as out-of-band swaps so
        // every boosted click keeps them in sync, instead of forcing a full
        // reload via hx-boost="false" on the two navs (the previous fix).
        $__authUser = authUser();
        $_navOpenTaskCount = isManager() ? SuiviTask::openCount() : 0;
        $_snOob = true;
        include __DIR__ . "/includes/partials/topbar.php";
        include __DIR__ . "/includes/partials/sidebar_nav.php";
    }
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
    <!-- Vendor + custom CSS, concat+minified by build/dist.mjs (npm run dist)
         into a single committed bundle — see that file for the exact source
         list/order (cascade order matters: bootstrap before custom.css). -->
    <link rel="stylesheet" href="css/dist/app.min.css?v=<?= $v ?>">

    <!-- jQuery, Bootstrap, DataTables+Buttons, Chart.js, htmx… concat+minified
         by build/dist.mjs into a single committed bundle (same source order
         as before, just fewer requests). Alpine (defer) and the small
         member-general-form.js stay separate below: bundling either into
         this blocking script would change their load timing. -->
    <script src="js/dist/vendor.min.js?v=<?= $v ?>"></script>

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

<body<?= !empty($_SESSION['ca_sidebar_collapsed']) ? ' class="ca-sidebar-collapsed"' : '' ?> hx-boost="true" hx-target="#main-content" hx-swap="innerHTML" hx-push-url="true">

<?php include __DIR__ . "/includes/partials/topbar.php"; ?>

<div class="ca-app-shell">
<?php include __DIR__ . "/includes/partials/sidebar_nav.php"; ?>
<div class="ca-app-content">
<main>

<?php
// Bandeau d'alerte : migrations DB en attente (visible admin uniquement).
$_pendingMigrations = isAdmin() ? pendingMigrations($pdo) : [];
if ($_pendingMigrations):
?>
<div class="container-xl px-4 mt-4">
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

<?php
// Bandeau d'alerte : hôte local mais SMTP pointant sur un vrai serveur (pas Mailpit).
// Évite d'envoyer de vrais emails à de vrais membres depuis un poste de dev (#131).
$_hostHeader   = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$_isLocalHost  = (bool)preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $_hostHeader);
$_smtpHost     = trim((string)($appSettings['smtp_host'] ?? ''));
$_smtpIsRealServer = $_smtpHost !== '' && !str_contains(strtolower($_smtpHost), 'mailpit');
if ($_isLocalHost && $_smtpIsRealServer && isManager()):
?>
<div class="container-xl px-4 mt-4">
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-0" role="alert">
        <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
        <div>
            <strong><?= $GLOBAL['localSmtpWarningTitle'] ?></strong>
            <?= sprintf($GLOBAL['localSmtpWarningBody'], htmlspecialchars($_smtpHost, ENT_QUOTES, $charset)) ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
// A view can set $_noOuterContainer = true (before/during its own render) to
// render full-bleed instead of being boxed in the generic container-xl below
// — needed for the hero-header pattern, where the header must span
// edge-to-edge under the topbar and the view owns its own container-xl
// internally. Buffered so actions.php can still header()/exit as usual
// (nested inside the file's top-level ob_start()).
$_noOuterContainer = false;
ob_start();
$userid = -1;
include __DIR__ . "/includes/routing/actions.php";
include __DIR__ . "/includes/routing/views.php";
$_mainContentHtml = ob_get_clean();
$end = getMicroTime();
?>
<?php if ($_noOuterContainer): ?>
<div id="main-content"><?= $_mainContentHtml ?></div>
<?php else: ?>
<div class="container-xl px-4 mt-4">
    <div class="row">
        <div class="col-12" id="main-content"><?= $_mainContentHtml ?></div>
    </div>
</div>
<?php endif ?>
<!-- Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100" aria-live="polite" aria-atomic="true">
    <div id="casaToast" class="toast text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true"
         data-msg-saved="<?= htmlspecialchars($GLOBAL['saved'], ENT_QUOTES, $charset) ?>"
         data-msg-segment-modified="<?= htmlspecialchars($GLOBAL['segmentModified'], ENT_QUOTES, $charset) ?>">
        <div class="d-flex align-items-center px-3 py-2 gap-2">
            <i class="fas fa-check-circle flex-shrink-0" aria-hidden="true"></i>
            <span id="casaToastMsg" class="flex-grow-1"><?= $GLOBAL['saved'] ?></span>
            <a id="casaToastUndo" href="#" class="btn btn-sm btn-outline-light py-0 px-2 flex-shrink-0" style="display:none;font-size:0.78rem"><?= $GLOBAL['cancel'] ?></a>
            <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="toast" aria-label="<?= $GLOBAL['close'] ?>"></button>
        </div>
    </div>
</div>

</main>
<footer class="ca-app-footer mt-auto">
    <div class="container-xl px-4">
        <div class="row">
            <div class="col-md-6 small">Process time: [<?= (int)(($end - $start) * 1000) ?>
                ms]. Date: [<?= date("d.m.Y H:i", time()) ?>]</div>
            <div class="col-md-6 text-md-end small">
                <a href="https://pvollenweider.github.io/memberbase/" target="_blank" rel="noopener">MemberBase v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, $charset) ?></a>
                &middot; <a href="https://pvollenweider.github.io/memberbase/docs/" target="_blank" rel="noopener"><?= htmlspecialchars($GLOBAL['documentation'], ENT_QUOTES, $charset) ?></a>
            </div>
        </div>
    </div>
</footer>
</div>
</div>

<!-- app.js + sidebar-nav.js, concat+minified by build/dist.mjs -->
<script src="js/dist/app.min.js?v=<?= $v ?>"></script>

</body>
</html>
<?php
ob_end_flush();
?>
