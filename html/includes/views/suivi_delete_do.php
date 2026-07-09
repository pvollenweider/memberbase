<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Deletes a follow-up (suivi) entry (with audit log), then re-renders the member's suivi tab.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$userProperty = new UserProperty();
$userProperty->lookupUserProperty($_REQUEST['suiviid']);
auditLog($pdo, 'deleteSuivi', "suivi#={$_REQUEST['suiviid']} | membre: " . Contact::getMemberName((int)$_REQUEST['userid']) . " | {$userProperty->parameter}: {$userProperty->getValue()}");
$userProperty->remove();
$view = "suivi";
include __DIR__ . "/users_edit_form.php";
