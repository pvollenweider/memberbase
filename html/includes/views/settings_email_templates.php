<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Email templates editor tab (Settings → Email → Templates).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { ?>
  <div class="alert alert-danger" role="alert"><i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?></div>
<?php return; }

require_once __DIR__ . '/../lib/mailer.php';

// Load existing templates from DB (merged with defaults so all keys are always present)
$defaults   = mbDefaultTemplates();
$dbTemplates = [];
try {
    $rows = $pdo->query("SELECT `key`, subject, body_text, body_html FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $dbTemplates[$r['key']] = $r;
    }
} catch (\Throwable $e) {
    // Table not yet migrated — fall back to defaults silently
}
$allTemplates = array_merge($defaults, $dbTemplates); // DB overrides defaults

// Map template key → display label
$tplLabels = [
    'tpl_welcome'             => $GLOBAL['emailTemplateWelcome'],
    'tpl_payment_receipt'     => $GLOBAL['emailTemplatePaymentReceipt'],
    'tpl_cotisation_reminder' => $GLOBAL['emailTemplateCotiReminder'],
    'tpl_attestation_don'     => $GLOBAL['emailTemplateAttestationDon'],
];
?>

<div class="col-md-10">

<div id="tpl-save-msg"></div>

<!-- Template editors -->
<?php foreach ($tplLabels as $key => $label):
    $tpl        = $allTemplates[$key] ?? $defaults[$key];
    $subjectVal = $tpl['subject']   ?? '';
    $bodyVal    = $tpl['body_text'] ?? '';
    $htmlVal    = $tpl['body_html'] ?? '';
    $safeKey    = htmlspecialchars($key, ENT_QUOTES, $charset);
    $tabText    = 'tab-text-' . $safeKey;
    $tabHtml    = 'tab-html-' . $safeKey;
    $paneText   = 'pane-text-' . $safeKey;
    $paneHtml   = 'pane-html-' . $safeKey;
?>
<div class="card mb-3">
  <div class="card-header fw-semibold" style="font-size:0.9rem">
    <?= htmlspecialchars($label, ENT_QUOTES, $charset) ?>
  </div>
  <div class="card-body">
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post"
          hx-post="<?= $_SERVER['PHP_SELF'] ?>"
          hx-target="#tpl-save-msg"
          hx-swap="innerHTML">
      <input type="hidden" name="action"   value="saveEmailTemplate"/>
      <input type="hidden" name="view"     value="settings"/>
      <input type="hidden" name="tpl_key"  value="<?= $safeKey ?>"/>

      <div class="mb-2">
        <label class="form-label fw-semibold" style="font-size:0.85rem"
               for="tpl_subject_<?= $safeKey ?>">
          <?= $GLOBAL['emailTemplateSubject'] ?>
        </label>
        <input type="text" class="form-control form-control-sm"
               id="tpl_subject_<?= $safeKey ?>"
               name="tpl_subject"
               value="<?= htmlspecialchars($subjectVal, ENT_QUOTES, $charset) ?>">
      </div>

      <!-- Text / HTML tabs -->
      <ul class="nav nav-tabs mb-0" style="font-size:0.82rem" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="<?= $tabText ?>" data-bs-toggle="tab"
                  data-bs-target="#<?= $paneText ?>" type="button" role="tab">
            <?= $GLOBAL['emailTemplateBodyText'] ?>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="<?= $tabHtml ?>" data-bs-toggle="tab"
                  data-bs-target="#<?= $paneHtml ?>" type="button" role="tab">
            <?= $GLOBAL['emailTemplateBodyHtml'] ?>
          </button>
        </li>
      </ul>
      <div class="tab-content border border-top-0 rounded-bottom p-2 mb-2">
        <div class="tab-pane fade show active" id="<?= $paneText ?>" role="tabpanel">
          <textarea class="form-control form-control-sm border-0" rows="6"
                    name="tpl_body"
                    style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($bodyVal, ENT_QUOTES, $charset) ?></textarea>
          <div class="form-text px-1"><?= $GLOBAL['emailTemplateHelp'] ?></div>
        </div>
        <div class="tab-pane fade" id="<?= $paneHtml ?>" role="tabpanel">
          <textarea class="form-control form-control-sm border-0" rows="10"
                    name="tpl_body_html"
                    style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($htmlVal, ENT_QUOTES, $charset) ?></textarea>
          <div class="form-text px-1"><?= $GLOBAL['emailTemplateHtmlHelp'] ?></div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-floppy-disk me-1" aria-hidden="true"></i><?= $GLOBAL['save'] ?>
      </button>
    </form>
  </div>
</div>
<?php endforeach ?>

</div>
