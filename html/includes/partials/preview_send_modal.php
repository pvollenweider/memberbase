<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Shared preview/send modal markup (#152) — paired with js/preview-send-modal.js.
 * Used by compta_recap.php and donors_summary.php's per-row attestation send.
 *
 * Expected variables, set by the caller before require:
 *   $psmId            string  element id prefix (e.g. "recap", "attest-row")
 *   $psmTitle         string  modal title
 *   $psmCancelLabel   string  dismiss button label
 *   $psmSendLabel     string  send button label (also the idle button HTML content)
 *   $psmOffSeasonGate bool    show the off-season confirmation checkbox (default false)
 *   $psmBcc           bool    show the BCC checkbox (default false — caller must also
 *                             check appSettings['smtp_reply_to'] before setting this)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$psmOffSeasonGate = $psmOffSeasonGate ?? false;
$psmBcc           = $psmBcc ?? false;
?>
<div class="modal fade" id="<?= $psmId ?>-modal" tabindex="-1" aria-labelledby="<?= $psmId ?>-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="<?= $psmId ?>-modal-title"><?= htmlspecialchars($psmTitle, ENT_QUOTES, $charset) ?></h5>
          <div class="text-muted small" id="<?= $psmId ?>-modal-meta"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
      </div>
      <div class="modal-body p-0" style="min-height:300px">
        <div id="<?= $psmId ?>-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="<?= $psmId ?>-modal-error" class="alert alert-danger m-3" style="display:none"></div>
        <iframe id="<?= $psmId ?>-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
      <?php if ($psmOffSeasonGate): ?>
      <div class="alert alert-warning d-flex align-items-start gap-2 mx-3 mb-0 py-2" role="alert" style="font-size:0.85rem">
        <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
        <div>
          <div><?= $GLOBAL['attestationOffSeasonWarning'] ?></div>
          <div class="form-check mt-1 mb-0">
            <input class="form-check-input" type="checkbox" id="<?= $psmId ?>-off-season-confirm">
            <label class="form-check-label" for="<?= $psmId ?>-off-season-confirm"><?= $GLOBAL['attestationOffSeasonConfirm'] ?></label>
          </div>
        </div>
      </div>
      <?php endif ?>
      <?php if ($psmBcc): ?>
      <div class="px-3 pt-2">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="<?= $psmId ?>-bcc">
          <label class="form-check-label small" for="<?= $psmId ?>-bcc"><?= sprintf($GLOBAL['sendBccCopyLabel'], htmlspecialchars($appSettings['smtp_reply_to'], ENT_QUOTES, $charset)) ?></label>
        </div>
      </div>
      <?php endif ?>
      <div class="modal-footer gap-2">
        <div class="me-auto small text-muted" id="<?= $psmId ?>-modal-subject"></div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($psmCancelLabel, ENT_QUOTES, $charset) ?></button>
        <button type="button" class="btn btn-primary" id="btn-<?= $psmId ?>-send" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($psmSendLabel, ENT_QUOTES, $charset) ?>
        </button>
      </div>
    </div>
  </div>
</div>
