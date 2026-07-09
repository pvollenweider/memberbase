<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Legacy direct-link confirm — treat as deactivate for safety.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$user = new User();
$user->lookupUser((int)$_REQUEST['id']);
$pdo->prepare("UPDATE contact SET status=0 WHERE id=?")->execute([(int)$_REQUEST['id']]);
auditLog($pdo, 'deactivateUser', "id={$_REQUEST['id']} {$user->firstName} {$user->lastName}");
if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF']); exit; }
header('Location: ' . $_SERVER['PHP_SELF']); exit;
