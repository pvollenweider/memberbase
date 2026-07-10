<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for combined segment management: create, update, delete, and category assignment.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: updateCategoryOrder, updateSegmentCategory, updateCombinedSegmentMembers,
//          deleteCombinedSegment, addCombinedSegment, updateCombinedSegment

if (!isManager()) { http_response_code(403); exit; }

$action = $_REQUEST['action'];

if ($action == 'updateCategoryOrder') {
    if (!empty($_REQUEST['ids']) && is_array($_REQUEST['ids'])) {
        $stmt = db()->prepare("UPDATE combined_segment SET sort_order=? WHERE id=? AND is_filter=0");
        foreach ($_REQUEST['ids'] as $i => $id) {
            $stmt->execute([$i, (int)$id]);
        }
    }

} elseif ($action == 'updateSegmentCategory') {
    $segmentId = (int)$_REQUEST['id'];
    $categoryId = (int)($_REQUEST['categoryId'] ?? 0);
    db()->prepare("DELETE FROM combined_segment_member WHERE segment_id=? AND combined_segment_id IN (
        SELECT id FROM combined_segment WHERE is_filter=0
    )")->execute([$segmentId]);
    if ($categoryId > 0) {
        db()->prepare("INSERT IGNORE INTO combined_segment_member (combined_segment_id, segment_id) VALUES (?, ?)")->execute([$categoryId, $segmentId]);
    }

} elseif ($action == 'updateCombinedSegmentMembers') {
    $mgId = (int)$_REQUEST['id'];
    $selected = !empty($_REQUEST['segments']) && is_array($_REQUEST['segments'])
        ? array_map('intval', $_REQUEST['segments']) : [];
    $_mgIsFilterRow = db()->prepare("SELECT is_filter FROM combined_segment WHERE id=? LIMIT 1");
    $_mgIsFilterRow->execute([$mgId]);
    $_mgIsCategory = ((int)$_mgIsFilterRow->fetchColumn() === 0);
    if ($_mgIsCategory && !empty($selected)) {
        $_stmtEvict = db()->prepare(
            "DELETE FROM combined_segment_member WHERE segment_id=? AND combined_segment_id!=? AND combined_segment_id IN (SELECT id FROM combined_segment WHERE is_filter=0)"
        );
        foreach ($selected as $segmentId) {
            if ($segmentId > 0) $_stmtEvict->execute([$segmentId, $mgId]);
        }
    }
    db()->prepare("DELETE FROM combined_segment_member WHERE combined_segment_id=?")->execute([$mgId]);
    $stmt = db()->prepare("INSERT IGNORE INTO combined_segment_member (combined_segment_id, segment_id) VALUES (?, ?)");
    foreach ($selected as $segmentId) {
        if ($segmentId > 0) $stmt->execute([$mgId, $segmentId]);
    }
    $_auMgName = db()->prepare("SELECT name FROM combined_segment WHERE id=? LIMIT 1");
    $_auMgName->execute([$mgId]);
    $_auMgNameVal = $_auMgName->fetchColumn() ?: "id=$mgId";
    $selectedStr = empty($selected) ? 'aucun' : implode(', ', $selected);
    auditLog(db(), 'updateCombinedSegmentMembers', "segment combiné: {$_auMgNameVal} (id={$mgId}) | " . count($selected) . " segments: [{$selectedStr}]");

} elseif ($action == 'deleteCombinedSegment') {
    $mgId = (int)$_REQUEST['id'];
    $_auMgDel = db()->prepare("SELECT name FROM combined_segment WHERE id=? LIMIT 1");
    $_auMgDel->execute([$mgId]);
    auditLog(db(), 'deleteCombinedSegment', "id=$mgId | " . ($_auMgDel->fetchColumn() ?: ''));
    db()->prepare("DELETE FROM combined_segment_member WHERE combined_segment_id=?")->execute([$mgId]);
    db()->prepare("DELETE FROM combined_segment WHERE id=?")->execute([$mgId]);

} elseif ($action == 'addCombinedSegment') {
    $mg = new CombinedSegment();
    $mg->name = $_REQUEST['name'];
    $mg->save();
    $newMgId = $mg->id;
    $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 0 ? 0 : 1) : 1;
    if ($newMgId) {
        db()->prepare("UPDATE combined_segment SET is_filter=? WHERE id=?")->execute([$isFilter, $newMgId]);
    }
    auditLog(db(), 'addCombinedSegment', "id=$newMgId | {$_REQUEST['name']} | filtre: " . ($isFilter ? 'oui' : 'non'));
    $_amUrl = appUrl() . '?view=updateCombinedSegment&id=' . $newMgId . '&created=1';
    if ($isHtmx) { header('HX-Location: ' . $_amUrl); } else { echo '<script>window.location.replace(' . json_encode($_amUrl) . ');</script>'; }
    exit;

} elseif ($action == 'updateCombinedSegment') {
    $mg = new CombinedSegment();
    $mg->lookupCombinedSegment((int)$_REQUEST['id']);
    $_auMgOldName = $mg->name;
    $mg->name = $_REQUEST['name'];
    $mg->save();
    $isFilter = isset($_REQUEST['is_filter']) ? ((int)$_REQUEST['is_filter'] === 1 ? 1 : 0) : 1;
    $_auMgOldRow = db()->prepare("SELECT is_filter FROM combined_segment WHERE id=? LIMIT 1");
    $_auMgOldRow->execute([(int)$_REQUEST['id']]);
    $_auMgOldFilter = (int)$_auMgOldRow->fetchColumn();
    db()->prepare("UPDATE combined_segment SET is_filter=? WHERE id=?")->execute([$isFilter, (int)$_REQUEST['id']]);
    $_auMgChanges = [];
    if ($_auMgOldName !== $mg->name) $_auMgChanges[] = "nom: «{$_auMgOldName}» → «{$mg->name}»";
    if ($_auMgOldFilter !== $isFilter) $_auMgChanges[] = "filtre: " . ($_auMgOldFilter ? 'oui' : 'non') . " → " . ($isFilter ? 'oui' : 'non');
    $auMgDetail = "id={$_REQUEST['id']} | {$mg->name}" . (count($_auMgChanges) ? ' | ' . implode(', ', $_auMgChanges) : ' | aucun changement');
    auditLog(db(), 'updateCombinedSegment', $auMgDetail);
    $_mgRedirectTab = $isFilter ? 'filters' : 'categories';
    $_umUrl = appUrl() . '?view=settings&tab=' . $_mgRedirectTab;
    if ($isHtmx) { header('HX-Location: ' . $_umUrl); } else { header('Location: ' . $_umUrl); }
    exit;
}
