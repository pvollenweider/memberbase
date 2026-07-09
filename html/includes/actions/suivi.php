<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for follow-up (suivi) entries: add and update.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addSuivi, updateSuivi

if (!canWrite()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'addSuivi') {
    $userProperty = new UserProperty();
    $userProperty->userId = (int)$_REQUEST['userid'];
    $userProperty->parameter = $_REQUEST['parameter'];
    $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
    $userProperty->value = unquote(str_replace(',','.',$_REQUEST['value']));
    $userProperty->save();
    auditLog($pdo, 'addSuivi', "membre: " . Contact::getMemberName((int)$_REQUEST['userid']) . " | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})", (int)$_REQUEST['userid']);

} elseif ($action == 'updateSuivi') {
    $userProperty = new UserProperty();
    $userProperty->lookupUserProperty((int)$_REQUEST['suiviid']);
    $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
    $userProperty->userId = (int)$_REQUEST['userid'];
    $userProperty->parameter = $_REQUEST['parameter'];
    $userProperty->value = unquote($_REQUEST['value']);
    $userProperty->save();
    auditLog($pdo, 'updateSuivi', "membre: " . Contact::getMemberName((int)$_REQUEST['userid']) . " | suivi#={$_REQUEST['suiviid']} | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})", (int)$_REQUEST['userid']);
}
