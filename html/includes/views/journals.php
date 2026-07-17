<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * "Journaux" hub — the sidebar nav (tab bar removed, same treatment as
 * "Membres & finances") navigates straight to a specific tab via ?tab=,
 * so this renders only the ONE matching pane per request instead of both.
 * Journal compta (compta_last_entry.php) and journal suivi
 * (suivi_last_entry.php) reuse their source view unchanged.
 *
 * Open to every logged-in role, same as the two routes it replaces.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_jhTab = in_array($_REQUEST['tab'] ?? '', ['compta', 'suivi'], true) ? $_REQUEST['tab'] : 'compta';

$_jhRequireIsolated = function (string $file, array $vars = []) use ($GLOBAL, $charset, $appSettings, $comptaTypes, $pdo) {
    extract($vars);
    require $file;
};

$_noOuterContainer = true;
$_phIcon = $_jhTab === 'suivi' ? 'fa-book-open' : 'fa-coins';
$_phTitle = $GLOBAL['journalsPageTitle'];
$_phSubtitle = $_jhTab === 'suivi' ? $GLOBAL['lastEntrySuivi'] : $GLOBAL['lastEntryCompta'];
include __DIR__ . '/../partials/page_header.php';
?>

<div class="container-xl px-4 ca-hero-overlap">
<div id="jh-tab-<?= $_jhTab ?>" class="jh-active-pane">
  <?php if ($_jhTab === 'suivi'): ?>
    <?php $_jhRequireIsolated(__DIR__ . '/suivi_last_entry.php', ['_jhEmbedded' => true]); ?>
  <?php else: ?>
    <?php $_jhRequireIsolated(__DIR__ . '/compta_last_entry.php', ['_jhEmbedded' => true]); ?>
  <?php endif ?>
</div>
</div>

<script src="js/hub-tabs.js?v=<?= APP_VERSION ?>"></script>
<script>
// The Compta pane's year/type filters still point at its own standalone
// route (?view=lastEntryCompta) — rewrite them to stay inside the hub.
caHubRewriteEmbeddedLinks('.jh-active-pane', 'lastEntryCompta', 'journals', 'compta');
</script>
