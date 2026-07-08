<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Reusable email log section — shows sent emails for a given member.
 * Expects $user to be set in the calling scope.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_elMemberId = (int)$user->getId();

$_emailLogs = [];
try {
    $s = $pdo->prepare(
        "SELECT id, created_at, subject, status, error_msg
         FROM email_log
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 200"
    );
    $s->execute([$_elMemberId]);
    $_emailLogs = $s->fetchAll(PDO::FETCH_OBJ);
} catch (\Throwable $_e) {
    // Table may not exist yet
}
?>

<p class="form-section-title mt-4 mb-1">
  <i class="fas fa-envelope-open-text me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogSection'] ?>
</p>
<p class="small text-muted mb-3"><?= $GLOBAL['emailLogHint'] ?></p>

<?php if (empty($_emailLogs)): ?>
<div class="alert alert-secondary py-2 px-3" style="font-size:0.85rem">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $GLOBAL['emailLogNoEntries'] ?>
</div>
<?php else: ?>
<table class="table table-sm table-hover" style="font-size:0.85rem">
  <thead class="table-light">
    <tr>
      <th style="white-space:nowrap"><?= $GLOBAL['date'] ?></th>
      <th><?= $GLOBAL['emailTemplateSubject'] ?></th>
      <th style="width:90px"><?= $GLOBAL['emailLogStatus'] ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($_emailLogs as $_el): ?>
    <tr<?= $_el->status === 'error' ? ' class="table-danger"' : '' ?>
        <?php if ($_el->status === 'error' && $_el->error_msg): ?>
        title="<?= htmlspecialchars($_el->error_msg, ENT_QUOTES, $charset) ?>"
        <?php endif ?>>
      <td style="white-space:nowrap"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($_el->created_at)), ENT_QUOTES, $charset) ?></td>
      <td><?= htmlspecialchars($_el->subject, ENT_QUOTES, $charset) ?></td>
      <td>
        <?php if ($_el->status === 'sent'): ?>
          <span class="badge bg-success-subtle text-success-emphasis"><?= $GLOBAL['emailLogStatusSent'] ?></span>
        <?php else: ?>
          <span class="badge bg-danger-subtle text-danger-emphasis" title="<?= htmlspecialchars($_el->error_msg ?? '', ENT_QUOTES, $charset) ?>">
            <?= $GLOBAL['emailLogStatusError'] ?>
          </span>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
