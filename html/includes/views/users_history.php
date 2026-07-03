<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin-only view of a member's full activity and change history.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { echo '<div class="alert alert-danger">' . $GLOBAL['accessDenied'] . '</div>'; return; }
$memberId = (int)$user->getId();

$histRows = $pdo->prepare("
    SELECT created_at, username, action, detail
    FROM audit_log
    WHERE subject_user_id = ?
    ORDER BY created_at DESC
");
$histRows->execute([$memberId]);
$history = $histRows->fetchAll(PDO::FETCH_OBJ);
?>

<p class="form-section-title mb-1">
  <i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i><?= $GLOBAL['changeHistory'] ?>
</p>
<p class="small text-muted mb-3"><?= $GLOBAL['changeHistoryHint'] ?></p>

<?php if (empty($history)): ?>
<div class="alert alert-secondary py-2 px-3" style="font-size:0.85rem">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['noJournalEntriesForMember'] ?>
</div>
<?php else: ?>
<table id="userHistoryTable" class="table table-sm table-striped table-hover">
  <thead>
    <tr>
      <th style="white-space:nowrap"><?= $GLOBAL['date'] ?></th>
      <th class="d-none d-sm-table-cell"><?= $GLOBAL['user'] ?></th>
      <th><?= $GLOBAL['action'] ?></th>
      <th><?= $GLOBAL['detail'] ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($history as $r): ?>
    <tr>
      <td data-order="<?= htmlspecialchars($r->created_at) ?>" style="white-space:nowrap"><?= htmlspecialchars($r->created_at) ?></td>
      <td class="d-none d-sm-table-cell"><?= htmlspecialchars($r->username ?? '') ?></td>
      <td><code><?= htmlspecialchars($r->action) ?></code></td>
      <td class="text-muted small"><?= htmlspecialchars($r->detail ?? '') ?></td>
    </tr>
    <?php endforeach ?>
  </tbody>
</table>

<script>
$(function () {
    $('#userHistoryTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [{ targets: [1], visible: window.innerWidth >= 576 }],
        language: {
            search: <?= json_encode($GLOBAL['dtSearch'], JSON_UNESCAPED_UNICODE) ?>,
            lengthMenu: <?= json_encode($GLOBAL['dtLengthMenu'], JSON_UNESCAPED_UNICODE) ?>,
            info: <?= json_encode($GLOBAL['dtInfo'], JSON_UNESCAPED_UNICODE) ?>,
            infoFiltered: <?= json_encode($GLOBAL['dtInfoFiltered'], JSON_UNESCAPED_UNICODE) ?>,
            paginate: { previous: <?= json_encode($GLOBAL['dtPrevious'], JSON_UNESCAPED_UNICODE) ?>, next: <?= json_encode($GLOBAL['dtNext'], JSON_UNESCAPED_UNICODE) ?> },
            emptyTable: <?= json_encode($GLOBAL['dtEmptyTable'], JSON_UNESCAPED_UNICODE) ?>
        }
    });
});
</script>
<?php endif ?>
