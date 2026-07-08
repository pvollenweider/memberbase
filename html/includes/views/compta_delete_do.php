<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Deletes an accounting entry (with audit log), then re-renders the member's compta tab.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$compta = new Compta();
$compta->lookupCompta($_REQUEST['comptaid']);
auditLog($pdo, 'deleteCompta', "compta#={$_REQUEST['comptaid']} | membre: " . User::getMemberName((int)$compta->userId) . " | {$compta->sum} CHF");
$compta->remove();
$view = "compta";
include __DIR__ . "/users_edit_form.php";
