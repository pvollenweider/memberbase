<?php
/**
 * Action handler for follow-up (suivi) entries: add and update.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addSuivi, updateSuivi

$action = $_REQUEST['action'];

if ($action == 'addSuivi') {
    $userProperty = new UserProperty();
    $userProperty->userId = $_REQUEST['userid'];
    $userProperty->parameter = $_REQUEST['parameter'];
    $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
    $userProperty->value = unquote(str_replace(',','.',$_REQUEST['value']));
    $userProperty->save();
    $_auSuiviU = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
    $_auSuiviU->execute([(int)$_REQUEST['userid']]);
    auditLog($pdo, 'addSuivi', "membre: " . ($_auSuiviU->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})", (int)$_REQUEST['userid']);

} elseif ($action == 'updateSuivi') {
    $userProperty = new UserProperty();
    $userProperty->lookupUserProperty($_REQUEST['suiviid']);
    $userProperty->date = formatedDateToTimeStamp($_REQUEST['date']);
    $userProperty->userId = $_REQUEST['userid'];
    $userProperty->parameter = $_REQUEST['parameter'];
    $userProperty->value = unquote($_REQUEST['value']);
    $userProperty->save();
    $_auSuiviU2 = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
    $_auSuiviU2->execute([(int)$_REQUEST['userid']]);
    auditLog($pdo, 'updateSuivi', "membre: " . ($_auSuiviU2->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | suivi#={$_REQUEST['suiviid']} | {$_REQUEST['parameter']}: {$_REQUEST['value']} ({$_REQUEST['date']})", (int)$_REQUEST['userid']);
}
