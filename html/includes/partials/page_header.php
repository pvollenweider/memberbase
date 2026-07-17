<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shared "hero" page-title band, used by every top-level view so the
 * app-wide layout stays consistent instead of duplicating the same markup
 * ~30 times. Caller sets $_phTitle (required), $_phIcon (Font Awesome
 * class, defaults to fa-file) and $_phSubtitle (optional — defaults to the
 * active second-level sidebar item, e.g. Administration > Groupes, when one
 * applies) before including this file, then sets $_noOuterContainer = true
 * and opens its own
 * <div class="container-xl px-4 ca-hero-overlap"> for the rest of its
 * content (this partial does not open/close that div itself, since some
 * callers need extra markup — like a search box — inside the header row
 * before the content container starts).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_phIcon = $_phIcon ?? 'fa-file';

// Default subtitle: current second-level sidebar item, when the active
// top-level entry is a submenu (e.g. Administration > Groupes) — sidebar_nav.php
// runs earlier in the request and leaves $_snSettingsTab/$_snAdminActive set
// in this same global scope. Callers that already set $_phSubtitle (e.g. the
// peopleFinance/journals hubs, which pick their own tab label) keep it as-is.
if (!isset($_phSubtitle) || $_phSubtitle === '') {
    $_phSubtitle = ($view ?? '') === 'inactiveUsers' ? $GLOBAL['archived']
        : ((!empty($_snAdminActive) && isset($_snSettingsTab)) ? match ($_snSettingsTab) {
            'groups'       => $GLOBAL['groups'],
            'filters'      => $GLOBAL['combinedSegments'],
            'categories'   => $GLOBAL['categories'],
            'compta'       => $GLOBAL['comptaTypes'],
            'contactTypes' => $GLOBAL['contactTypesTitle'],
            'settings'     => $GLOBAL['settings'],
            'email'        => $GLOBAL['smtpSettings'],
            'users'        => $GLOBAL['users'],
            'health'       => $GLOBAL['health'],
            'audit'        => $GLOBAL['journal'],
            'integrity'    => $GLOBAL['integrity'],
            default        => '',
        } : '');
}
?>
<header class="ca-hero">
  <div class="container-xl px-4">
    <div class="row align-items-center justify-content-between">
      <div class="col-auto">
        <h1 class="ca-hero-title">
          <span class="ca-hero-icon"><i class="fas <?= htmlspecialchars($_phIcon, ENT_QUOTES, $charset) ?>"></i></span>
          <?= $_phTitle ?>
        </h1>
        <?php if (!empty($_phSubtitle)): ?>
        <div class="ca-hero-subtitle"><?= $_phSubtitle ?></div>
        <?php endif ?>
      </div>
    </div>
  </div>
</header>
