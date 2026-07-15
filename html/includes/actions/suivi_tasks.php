<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for tasks (suivi_task): add, update, close, delete.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: addTask, updateTask, closeTask, reopenTask, deleteTask,
//          generateUnpaidCotiTasks, generateComptaRecapTasks, bulkDeleteCompletedTasks

if (in_array($_REQUEST['action'], ['generateUnpaidCotiTasks', 'generateComptaRecapTasks'], true)) {
    if (!isAdmin()) { http_response_code(403); exit; }
} elseif ($_REQUEST['action'] === 'bulkDeleteCompletedTasks') {
    if (!isManager()) { http_response_code(403); exit; }
} elseif (!canWrite()) {
    http_response_code(403); exit;
}

$action = $_REQUEST['action'];
$_authUser = authUser();

if ($action == 'generateUnpaidCotiTasks') {
    $_genYear = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $_genResult = SuiviTask::generateUnpaidCotiTasks($_genYear, $appSettings, (int)($_authUser->id ?? 0));
    auditLog(db(), 'generateUnpaidCotiTasks', "année: $_genYear | créées: {$_genResult['created']} | closes (résolues ailleurs): {$_genResult['closed']}");
    $_genTarget = '?view=tasks&generated=' . $_genResult['created'] . '&closed=' . $_genResult['closed'];
    if (isset($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . appUrl() . $_genTarget); exit; }
    header('Location: ' . appUrl() . $_genTarget); exit;

} elseif ($action == 'generateComptaRecapTasks') {
    $_genYear = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $_genResult = SuiviTask::generateComptaRecapTasks($_genYear, (int)($_authUser->id ?? 0));
    auditLog(db(), 'generateComptaRecapTasks', "année: $_genYear | créées: {$_genResult['created']} | closes (résolues ailleurs): {$_genResult['closed']}");
    $_genTarget = '?view=tasks&generated=' . $_genResult['created'] . '&closed=' . $_genResult['closed'];
    if (isset($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . appUrl() . $_genTarget); exit; }
    header('Location: ' . appUrl() . $_genTarget); exit;

} elseif ($action == 'addTask') {
    $task = new SuiviTask();
    $task->setUserId(!empty($_REQUEST['userid']) ? (int)$_REQUEST['userid'] : null);
    $task->setCreatedBy((int)($_authUser->id ?? 0));
    $task->setTitle(unquote(trim($_REQUEST['title'] ?? '')));
    $task->setBody(unquote(trim($_REQUEST['body'] ?? '')));
    $task->setPriority((int)($_REQUEST['priority'] ?? SuiviTask::PRIORITY_NORMAL));
    $task->setDueDate(formatedDateToTimeStamp($_REQUEST['due_date'] ?? '') ?: null);
    $task->save();
    $_auTarget = $task->getUserId() ? Contact::getMemberName((int)$task->getUserId()) : 'global';
    auditLog(db(), 'addTask', "id={$task->getId()} | membre: {$_auTarget} | {$task->getTitle()}", $task->getUserId());

} elseif ($action == 'updateTask') {
    $task = new SuiviTask();
    $task->lookupTask((int)$_REQUEST['taskid']);
    $task->setTitle(unquote(trim($_REQUEST['title'] ?? '')));
    $task->setBody(unquote(trim($_REQUEST['body'] ?? '')));
    $task->setPriority((int)($_REQUEST['priority'] ?? SuiviTask::PRIORITY_NORMAL));
    $task->setDueDate(formatedDateToTimeStamp($_REQUEST['due_date'] ?? '') ?: null);
    $task->save();
    auditLog(db(), 'updateTask', "id={$task->getId()} | {$task->getTitle()}", $task->getUserId());

} elseif ($action == 'closeTask') {
    $task = new SuiviTask();
    $task->lookupTask((int)$_REQUEST['taskid']);
    $task->close();
    auditLog(db(), 'closeTask', "id={$task->getId()} | {$task->getTitle()}", $task->getUserId());

} elseif ($action == 'reopenTask') {
    $task = new SuiviTask();
    $task->lookupTask((int)$_REQUEST['taskid']);
    db()->prepare("UPDATE suivi_task SET done_at=NULL WHERE id=?")->execute([$task->getId()]);
    auditLog(db(), 'reopenTask', "id={$task->getId()} | {$task->getTitle()}", $task->getUserId());

} elseif ($action == 'bulkDeleteCompletedTasks') {
    $_bulkDeletedCount = SuiviTask::deleteAllCompleted();
    auditLog(db(), 'bulkDeleteCompletedTasks', "{$_bulkDeletedCount} tâche(s) terminée(s) supprimée(s)");
    $_bulkTarget = '?view=tasks&bulkDeleted=' . $_bulkDeletedCount;
    if (isset($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . appUrl() . $_bulkTarget); exit; }
    header('Location: ' . appUrl() . $_bulkTarget); exit;

} elseif ($action == 'deleteTask') {
    $task = new SuiviTask();
    $task->lookupTask((int)$_REQUEST['taskid']);
    $_delUserId = $task->getUserId();
    auditLog(db(), 'deleteTask', "id={$task->getId()} | {$task->getTitle()}", $_delUserId);
    $task->remove();
    $_delTarget = $_delUserId ? ('?view=memberTasks&userid=' . $_delUserId) : '?view=tasks';
    if (isset($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . appUrl() . $_delTarget); exit; }
    header('Location: ' . appUrl() . $_delTarget); exit;
}
