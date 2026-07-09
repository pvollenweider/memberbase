<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shows the full content of a sent email (from email_log).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isManager()) { ?>
  <div class="alert alert-danger" role="alert">
    <i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?>
  </div>
<?php return; }

$emailId = (int)($_GET['emailid'] ?? 0);
if ($emailId <= 0) { ?>
  <div class="alert alert-warning"><?= $GLOBAL['notFound'] ?></div>
<?php return; }

try {
    $stmt = $pdo->prepare(
        "SELECT el.id, el.created_at, el.to_email, el.subject, el.status,
                el.error_msg, el.body_text, el.body_html,
                u.id AS user_id, u.firstname, u.lastname
         FROM email_log el
         LEFT JOIN contact u ON u.id = el.user_id
         WHERE el.id = ? LIMIT 1"
    );
    $stmt->execute([$emailId]);
    $log = $stmt->fetchObject();
} catch (\Throwable $e) {
    $log = null;
}

if (!$log) { ?>
  <div class="alert alert-warning"><?= $GLOBAL['notFound'] ?></div>
<?php return; }

$sentAt  = date('d.m.Y H:i', strtotime($log->created_at));
$isHtml  = isset($log->body_html) && $log->body_html !== '';
?>

<div class="page-title-row mb-3">
  <h1 class="page-title">
    <i class="fas fa-envelope me-2" aria-hidden="true"></i><?= htmlspecialchars($log->subject, ENT_QUOTES, $charset) ?>
  </h1>
</div>

<div class="card mb-3" style="max-width:700px">
  <div class="card-body py-2 px-3" style="font-size:0.85rem">
    <div class="row g-1">
      <div class="col-sm-3 text-muted"><?= $GLOBAL['date'] ?></div>
      <div class="col-sm-9"><?= htmlspecialchars($sentAt, ENT_QUOTES, $charset) ?></div>
      <div class="col-sm-3 text-muted"><?= $GLOBAL['emailTo'] ?></div>
      <div class="col-sm-9">
        <?= htmlspecialchars($log->to_email, ENT_QUOTES, $charset) ?>
        <?php if ($log->user_id): ?>
          — <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&userid=<?= (int)$log->user_id ?>">
              <?= htmlspecialchars(trim($log->firstname . ' ' . $log->lastname), ENT_QUOTES, $charset) ?>
            </a>
        <?php endif ?>
      </div>
      <div class="col-sm-3 text-muted"><?= $GLOBAL['emailStatus'] ?></div>
      <div class="col-sm-9">
        <?php if ($log->status === 'sent'): ?>
          <span class="badge bg-success"><?= $GLOBAL['emailStatusSent'] ?></span>
        <?php else: ?>
          <span class="badge bg-danger"><?= $GLOBAL['emailStatusError'] ?></span>
          <?php if ($log->error_msg): ?>
            <span class="text-muted ms-2 small"><?= htmlspecialchars($log->error_msg, ENT_QUOTES, $charset) ?></span>
          <?php endif ?>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<?php if ($isHtml): ?>
<!-- HTML preview in sandboxed iframe -->
<div class="mb-2 d-flex gap-2 align-items-center">
  <span class="badge bg-light text-dark border">HTML</span>
  <a href="#email-plaintext" class="small text-muted" data-bs-toggle="collapse"><?= $GLOBAL['emailViewPlaintext'] ?></a>
</div>
<div style="border:1px solid #dee2e6;border-radius:6px;overflow:hidden;max-width:700px">
  <iframe id="email-html-frame" srcdoc="" style="width:100%;border:none;min-height:400px" sandbox="allow-same-origin"></iframe>
</div>
<div class="collapse mt-3" id="email-plaintext">
  <pre class="p-3 border rounded bg-light small" style="white-space:pre-wrap;max-width:700px"><?= htmlspecialchars($log->body_text ?? '', ENT_QUOTES, $charset) ?></pre>
</div>
<script>
(function () {
  var frame = document.getElementById('email-html-frame');
  var html  = <?= json_encode($log->body_html) ?>;
  var doc   = frame.contentDocument || frame.contentWindow.document;
  doc.open(); doc.write(html); doc.close();
  // Auto-resize iframe to content height
  frame.addEventListener('load', function () {
    try { frame.style.height = (frame.contentDocument.body.scrollHeight + 32) + 'px'; } catch(e) {}
  });
  // Trigger load for srcdoc
  frame.srcdoc = html;
}());
</script>
<?php else: ?>
<pre class="p-3 border rounded bg-light small" style="white-space:pre-wrap;max-width:700px"><?= htmlspecialchars($log->body_text ?? '', ENT_QUOTES, $charset) ?></pre>
<?php endif ?>

<div class="mt-3">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=lastEntrySuivi" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= $GLOBAL['back'] ?>
  </a>
</div>
