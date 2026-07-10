<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * SMTP configuration tab (Settings → Email).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { ?>
  <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
<?php return; } ?>

<div class="col-md-8">
<div id="smtp-save-msg"></div>
<form action="<?= appUrl() ?>" method="post"
      hx-post="<?= appUrl() ?>"
      hx-target="#smtp-save-msg"
      hx-swap="innerHTML">
  <input type="hidden" name="action" value="saveSmtp"/>
  <input type="hidden" name="view"   value="settings"/>

  <p class="form-section-title"><i class="fas fa-server me-1" aria-hidden="true"></i><?= $GLOBAL['smtpServer'] ?></p>

  <div class="row g-2 mb-3">
    <div class="col-sm-8">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_host"><?= $GLOBAL['smtpHost'] ?></label>
      <input type="text" name="smtp_host" id="s_smtp_host" class="form-control form-control-sm"
             placeholder="smtp.example.com"
             value="<?= htmlspecialchars($appSettings['smtp_host'] ?? '', ENT_QUOTES, $charset) ?>">
    </div>
    <div class="col-sm-4">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_port"><?= $GLOBAL['smtpPort'] ?></label>
      <input type="number" name="smtp_port" id="s_smtp_port" class="form-control form-control-sm"
             min="1" max="65535" placeholder="587"
             value="<?= htmlspecialchars($appSettings['smtp_port'] ?? '587', ENT_QUOTES, $charset) ?>">
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold" style="font-size:0.85rem"><?= $GLOBAL['smtpEncryption'] ?></label>
    <div class="d-flex gap-3 flex-wrap">
      <?php foreach (['none' => $GLOBAL['smtpEncNone'], 'starttls' => 'STARTTLS', 'ssl' => 'SSL/TLS'] as $val => $lbl): ?>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="smtp_encryption" id="smtp_enc_<?= $val ?>"
               value="<?= $val ?>" <?= ($appSettings['smtp_encryption'] ?? 'starttls') === $val ? 'checked' : '' ?>>
        <label class="form-check-label" for="smtp_enc_<?= $val ?>"><?= $lbl ?></label>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="smtp_auth" id="s_smtp_auth" value="1"
           <?= !empty($appSettings['smtp_auth']) ? 'checked' : '' ?>>
    <label class="form-check-label fw-semibold" for="s_smtp_auth" style="font-size:0.85rem"><?= $GLOBAL['smtpAuth'] ?></label>
  </div>

  <div class="row g-2 mb-4" id="smtp-auth-fields" <?= empty($appSettings['smtp_auth']) ? 'style="display:none"' : '' ?>>
    <div class="col-sm-6">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_user"><?= $GLOBAL['smtpUser'] ?></label>
      <input type="text" name="smtp_user" id="s_smtp_user" class="form-control form-control-sm"
             autocomplete="off"
             value="<?= htmlspecialchars($appSettings['smtp_user'] ?? '', ENT_QUOTES, $charset) ?>">
    </div>
    <div class="col-sm-6">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_password"><?= $GLOBAL['smtpPassword'] ?></label>
      <input type="password" name="smtp_password" id="s_smtp_password" class="form-control form-control-sm"
             autocomplete="new-password"
             placeholder="<?= !empty($appSettings['smtp_password']) ? $GLOBAL['smtpPasswordSet'] : '' ?>"
             value="">
      <div class="form-text"><?= $GLOBAL['smtpPasswordHelp'] ?></div>
    </div>
  </div>

  <p class="form-section-title"><i class="fas fa-envelope me-1" aria-hidden="true"></i><?= $GLOBAL['smtpSender'] ?></p>

  <div class="row g-2 mb-3">
    <div class="col-sm-6">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_from_name"><?= $GLOBAL['smtpFromName'] ?></label>
      <input type="text" name="smtp_from_name" id="s_smtp_from_name" class="form-control form-control-sm"
             value="<?= htmlspecialchars($appSettings['smtp_from_name'] ?? '', ENT_QUOTES, $charset) ?>">
    </div>
    <div class="col-sm-6">
      <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_from_email"><?= $GLOBAL['smtpFromEmail'] ?></label>
      <input type="email" name="smtp_from_email" id="s_smtp_from_email" class="form-control form-control-sm"
             value="<?= htmlspecialchars($appSettings['smtp_from_email'] ?? '', ENT_QUOTES, $charset) ?>">
    </div>
  </div>

  <div class="mb-4">
    <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_reply_to"><?= $GLOBAL['smtpReplyTo'] ?></label>
    <input type="email" name="smtp_reply_to" id="s_smtp_reply_to" class="form-control form-control-sm" style="max-width:280px"
           value="<?= htmlspecialchars($appSettings['smtp_reply_to'] ?? '', ENT_QUOTES, $charset) ?>">
    <div class="form-text"><?= $GLOBAL['smtpReplyToHelp'] ?></div>
  </div>

  <div class="d-flex gap-2 align-items-center flex-wrap">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fas fa-floppy-disk me-1" aria-hidden="true"></i><?= $GLOBAL['save'] ?>
    </button>
  </div>
</form>

<hr class="my-4">

<!-- Test email -->
<p class="form-section-title"><i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['smtpTest'] ?></p>
<div class="d-flex gap-2 align-items-end flex-wrap mb-2" style="max-width:440px">
  <div class="flex-grow-1">
    <label class="form-label fw-semibold" style="font-size:0.85rem" for="s_smtp_test_to"><?= $GLOBAL['smtpTestTo'] ?></label>
    <input type="email" id="s_smtp_test_to" class="form-control form-control-sm"
           value="<?= htmlspecialchars($_SESSION['app_user_email'] ?? '', ENT_QUOTES, $charset) ?>">
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-smtp-test"
          data-label-sending="<?= htmlspecialchars($GLOBAL['smtpTesting'], ENT_QUOTES, $charset) ?>">
    <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['smtpTestSend'] ?>
  </button>
</div>
<div id="smtp-test-result" style="font-size:0.85rem"></div>
</div>

<hr class="my-4">

<?php include __DIR__ . '/settings_email_log.php'; ?>

<hr class="my-4">

<p class="form-section-title"><i class="fas fa-file-alt me-1" aria-hidden="true"></i><?= $GLOBAL['emailTemplates'] ?></p>
<?php include __DIR__ . '/settings_email_templates.php'; ?>

<script>
(function () {
  // Toggle auth fields visibility
  document.getElementById('s_smtp_auth').addEventListener('change', function () {
    document.getElementById('smtp-auth-fields').style.display = this.checked ? '' : 'none';
  });

  // Test email button
  document.getElementById('btn-smtp-test').addEventListener('click', function () {
    var btn = this;
    var to  = document.getElementById('s_smtp_test_to').value.trim();
    var res = document.getElementById('smtp-test-result');
    if (!to) { res.innerHTML = '<span class="text-danger"><?= addslashes($GLOBAL['smtpTestMissingTo']) ?></span>'; return; }
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + btn.dataset.labelSending;
    res.innerHTML = '';
    fetch(<?= json_encode(appUrl()) ?>, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : ''
      },
      body: 'action=sendTestEmail&view=settings&to=' + encodeURIComponent(to)
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        res.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i><?= addslashes($GLOBAL['smtpTestOk']) ?></span>';
      } else {
        var html = '<span class="text-danger"><i class="fas fa-xmark me-1"></i>' + (data.error || '<?= addslashes($GLOBAL['smtpTestFail']) ?>') + '</span>';
        if (data.debug) {
          html += '<div class="mt-2"><a class="small text-muted" href="#" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display===\'none\'?\'\':\'none\';return false"><?= addslashes($GLOBAL['smtpDebugToggle']) ?></a>'
               + '<pre class="mt-1 p-2 border rounded small text-muted" style="white-space:pre-wrap;display:none;max-height:300px;overflow:auto">' + data.debug.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</pre></div>';
        }
        res.innerHTML = html;
      }
    })
    .catch(function(){ res.innerHTML = '<span class="text-danger"><?= addslashes($GLOBAL['smtpTestFail']) ?></span>'; })
    .finally(function(){ btn.disabled = false; btn.innerHTML = orig; });
  });
}());
</script>
