<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for metagroup management: create, update, delete, and category assignment.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: updateCategoryOrder, updateSegmentCategory, updateMetagroupTeams,
//          deleteMetagroup, addMetagroup, updateMetagroup

if (!isManager()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'updateCategoryOrder') {
    if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $stmt = $pdo->prepare("UPDATE metagroup SET sort_order=? WHERE id=? AND name IS NOT NULL AND is_filter=0");
        foreach ($_REQUEST['ids'] as $i => $id) {
            $stmt->execute([$i, (int)$id]);
        }
    }

} elseif ($action == 'updateSegmentCategory') {
    $segmentId = (int)$_REQUEST['id'];
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    $pdo->prepare("DELETE FROM metagroup WHERE segmentid=? AND id IN (
        SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS cats
    )")->execute([$segmentId]);
    if ($categoryId > 0) {
        $pdo->prepare("INSERT INTO metagroup (id, segmentid) VALUES (?, ?)")->execute([$categoryId, $segmentId]);
    }

} elseif ($action == 'updateMetagroupTeams') {
    $mgId = (int)$_REQUEST['id'];
    $selected = !empty($_REQUEST['teams']) && is_array($_REQUEST['teams'])
        ? array_map('intval', $_REQUEST['teams']) : [];
    $_mgIsFilterRow = $pdo->prepare("SELECT is_filter FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
    $_mgIsFilterRow->execute([$mgId]);
    $_mgIsCategory = ((int)$_mgIsFilterRow->fetchColumn() === 0);
    if ($_mgIsCategory && !empty($selected)) {
        $_stmtEvict = $pdo->prepare(
            "DELETE FROM metagroup WHERE segmentid=? AND id!=? AND id IN (SELECT id FROM (SELECT id FROM metagroup WHERE name IS NOT NULL AND is_filter=0) AS _cats)"
        );
        foreach ($selected as $segmentId) {
            if ($segmentId > 0) $_stmtEvict->execute([$segmentId, $mgId]);
        }
    }
    $pdo->prepare("DELETE FROM metagroup WHERE id=? AND segmentid IS NOT NULL")->execute([$mgId]);
    $stmt = $pdo->prepare("INSERT INTO metagroup (id, segmentid) VALUES (?, ?)");
    foreach ($selected as $segmentId) {
        if ($segmentId > 0) $stmt->execute([$mgId, $segmentId]);
    }
    $_auMgName = $pdo->prepare("SELECT name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
    $_auMgName->execute([$mgId]);
    $_auMgNameVal = $_auMgName->fetchColumn() ?: "id=$mgId";
    $selectedStr = empty($selected) ? 'aucun' : implode(', ', $selected);
    auditLog($pdo, 'updateMetagroupTeams', "groupe filtre: {$_auMgNameVal} (id={$mgId}) | " . count($selected) . " groupes: [{$selectedStr}]");

} elseif ($action == 'deleteMetagroup') {
    $mgId = (int)$_REQUEST['id'];
    $_auMgDel = $pdo->prepare("SELECT name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
    $_auMgDel->execute([$mgId]);
    auditLog($pdo, 'deleteMetagroup', "id=$mgId | " . ($_auMgDel->fetchColumn() ?: ''));
    $pdo->prepare("DELETE FROM metagroup WHERE id=?")->execute([$mgId]);

} elseif ($action == 'addMetagroup') {
    $mg = new Metagroup();
    $mg->name = $_REQUEST['name'];
    $mg->save();
    $newMgId = $mg->id;
    $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 0 ? 0 : 1) : 1;
    if ($newMgId) {
        $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, $newMgId]);
    }
    auditLog($pdo, 'addMetagroup', "id=$newMgId | {$_REQUEST['name']} | filtre: " . ($isFilter ? 'oui' : 'non'));
    $_amUrl = appUrl() . '?view=updateMetagroup&id=' . $newMgId . '&created=1';
    if ($isHtmx) { header('HX-Location: ' . $_amUrl); } else { echo '<script>window.location.replace(' . json_encode($_amUrl) . ');</script>'; }
    exit;

} elseif ($action == 'updateMetagroup') {
    $mg = new Metagroup();
    $mg->lookupMetagroup((int)$_REQUEST['id']);
    $_auMgOldName = $mg->name;
    $mg->name = $_REQUEST['name'];
    $mg->save();
    $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 1 ? 1 : 0) : 1;
    $_auMgOldRow = $pdo->prepare("SELECT is_filter FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
    $_auMgOldRow->execute([(int)$_REQUEST['id']]);
    $_auMgOldFilter = (int)$_auMgOldRow->fetchColumn();
    $pdo->prepare("UPDATE metagroup SET is_filter=? WHERE id=? AND name IS NOT NULL")->execute([$isFilter, (int)$_REQUEST['id']]);
    $_auMgChanges = [];
    if ($_auMgOldName !== $mg->name) $_auMgChanges[] = "nom: «{$_auMgOldName}» → «{$mg->name}»";
    if ($_auMgOldFilter !== $isFilter) $_auMgChanges[] = "filtre: " . ($_auMgOldFilter ? 'oui' : 'non') . " → " . ($isFilter ? 'oui' : 'non');
    $auMgDetail = "id={$_REQUEST['id']} | {$mg->name}" . (count($_auMgChanges) ? ' | ' . implode(', ', $_auMgChanges) : ' | aucun changement');
    auditLog($pdo, 'updateMetagroup', $auMgDetail);
    $_mgRedirectTab = $isFilter ? 'filters' : 'categories';
    $_umUrl = appUrl() . '?view=settings&tab=' . $_mgRedirectTab;
    if ($isHtmx) { header('HX-Location: ' . $_umUrl); } else { header('Location: ' . $_umUrl); }
    exit;
}
