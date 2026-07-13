<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shown instead of the compta-types / contact-types panes when their
 * required migrations (0035-0037) haven't been applied yet — those panes
 * query columns/tables (is_financial_institution, contact_type…) that don't
 * exist pre-migration, and since every settings tab pane renders
 * unconditionally on every page load, letting them fatal there would also
 * take down the Santé tab needed to apply the migration. See
 * settings_general.php's $_ctSchemaPending guard.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_ctPendingCount = count(pendingMigrations($pdo));
?>
<div class="alert alert-warning d-flex align-items-start gap-2 mt-3" role="alert">
  <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
  <div>
    <strong><?= $_ctPendingCount ?>
        <?= sprintf($GLOBAL['pendingDbMigrationsLabel'], $_ctPendingCount > 1 ? 's' : '') ?></strong>.
    <?php if (isAdmin()): ?>
    <?= sprintf($GLOBAL['pendingMigrationsBannerBody'], $_ctPendingCount > 1 ? 's' : '', appUrl()) ?>
    <?php endif ?>
  </div>
</div>
