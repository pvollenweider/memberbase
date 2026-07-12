<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Email templates editor tab (Settings → Email → Templates).
 *
 * @copyright 2026 Philippe Vollenweider
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
    $rows = db()->query("SELECT `key`, subject, body_text, body_html FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $dbTemplates[$r['key']] = $r;
    }
} catch (\Throwable $e) {
    // Table not yet migrated — fall back to defaults silently
}
$allTemplates = array_merge($defaults, $dbTemplates); // DB overrides defaults

// Map template key → display label
$tplLabels = [
    'tpl_compta_recap'        => $GLOBAL['emailTemplateComptaRecap'],
    'tpl_payment_receipt'     => $GLOBAL['emailTemplatePaymentReceipt'],
    'tpl_cotisation_reminder' => $GLOBAL['emailTemplateCotiReminder'],
    'tpl_attestation_don'     => $GLOBAL['emailTemplateAttestationDon'],
    'tpl_task_digest'         => $GLOBAL['emailTemplateTaskDigest'],
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
    <form action="<?= appUrl() ?>" method="post"
          hx-post="<?= appUrl() ?>"
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
          <div class="form-text px-1">
            <?= $GLOBAL['emailTemplateHelp'] ?>
            <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline"
                    data-bs-toggle="modal" data-bs-target="#tpl-vars-modal"
                    title="<?= $GLOBAL['emailTemplateVarsHelp'] ?? 'Aide sur les variables' ?>"
                    style="font-size:0.8rem;line-height:1">(?)</button>
          </div>
        </div>
        <div class="tab-pane fade" id="<?= $paneHtml ?>" role="tabpanel">
          <textarea class="form-control form-control-sm border-0" rows="10"
                    name="tpl_body_html"
                    style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($htmlVal, ENT_QUOTES, $charset) ?></textarea>
          <div class="form-text px-1">
            <?= $GLOBAL['emailTemplateHtmlHelp'] ?>
            <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline"
                    data-bs-toggle="modal" data-bs-target="#tpl-vars-modal"
                    style="font-size:0.8rem;line-height:1">(?)</button>
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-floppy-disk me-1" aria-hidden="true"></i><?= $GLOBAL['save'] ?>
        </button>
        <?php if (isset($dbTemplates[$key])): ?>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline" hx-boost="false">
          <input type="hidden" name="action"  value="resetEmailTemplate"/>
          <input type="hidden" name="view"    value="settings"/>
          <input type="hidden" name="tab"     value="email"/>
          <input type="hidden" name="tpl_key" value="<?= $safeKey ?>"/>
          <button type="submit" class="btn btn-outline-secondary btn-sm"
                  onclick="return confirm(<?= json_encode($GLOBAL['resetToDefaultConfirm']) ?>)">
            <i class="fas fa-rotate-left me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['resetToDefault'], ENT_QUOTES, $charset) ?>
          </button>
        </form>
        <?php endif ?>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>

</div>

<!-- Variables reference modal (shared by all template editors) -->
<div class="modal fade" id="tpl-vars-modal" tabindex="-1"
     aria-labelledby="tpl-vars-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tpl-vars-modal-label">
          <i class="fas fa-code me-2" aria-hidden="true"></i><?= $GLOBAL['emailTemplateVarsHelp'] ?? 'Variables disponibles' ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-sm table-hover mb-0" style="font-size:0.85rem">
          <thead class="table-light">
            <tr>
              <th style="width:30%"><?= $GLOBAL['variable'] ?? 'Variable' ?></th>
              <th><?= $GLOBAL['description'] ?? 'Description' ?></th>
              <th style="width:25%"><?= $GLOBAL['example'] ?? 'Exemple' ?></th>
            </tr>
          </thead>
          <tbody>
            <tr class="table-secondary"><td colspan="3" class="fw-semibold px-3 py-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em">Salutation</td></tr>
            <tr>
              <td><code>{{greeting}}</code></td>
              <td>Formule de salutation HTML — prénom+nom en gras si disponibles, sinon société, sinon "Bonjour," seul. À utiliser dans le corps HTML.</td>
              <td class="text-muted">Bonjour <strong>Jean Dupont</strong>,</td>
            </tr>
            <tr>
              <td><code>{{greeting_text}}</code></td>
              <td>Même logique, version texte brut. À utiliser dans le corps texte.</td>
              <td class="text-muted">Bonjour Jean Dupont,</td>
            </tr>
            <tr>
              <td><code>{{display_name}}</code></td>
              <td>Meilleur nom disponible : prénom+nom, sinon société, sinon vide.</td>
              <td class="text-muted">Jean Dupont</td>
            </tr>
            <tr class="table-secondary"><td colspan="3" class="fw-semibold px-3 py-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em">Contact</td></tr>
            <tr>
              <td><code>{{firstname}}</code></td>
              <td>Prénom du destinataire (peut être vide pour une société).</td>
              <td class="text-muted">Jean</td>
            </tr>
            <tr>
              <td><code>{{lastname}}</code></td>
              <td>Nom de famille (peut être vide pour une société).</td>
              <td class="text-muted">Dupont</td>
            </tr>
            <tr>
              <td><code>{{society}}</code></td>
              <td>Raison sociale (peut être vide pour un particulier).</td>
              <td class="text-muted">Entreprise SA</td>
            </tr>
            <tr>
              <td><code>{{email}}</code></td>
              <td>Adresse email du destinataire.</td>
              <td class="text-muted">jean@exemple.ch</td>
            </tr>
            <tr class="table-secondary"><td colspan="3" class="fw-semibold px-3 py-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em">Organisation</td></tr>
            <tr>
              <td><code>{{org_name}}</code></td>
              <td>Nom de l'organisation (Réglages → Général).</td>
              <td class="text-muted">Mon Association</td>
            </tr>
            <tr>
              <td><code>{{contact_email}}</code></td>
              <td>Email de contact (adresse reply-to SMTP, ou adresse d'expédition).</td>
              <td class="text-muted">contact@asso.ch</td>
            </tr>
            <tr>
              <td><code>{{org_address}}</code></td>
              <td>Adresse postale de l'organisation.</td>
              <td class="text-muted">Rue de la Paix 1</td>
            </tr>
            <tr>
              <td><code>{{org_city}}</code></td>
              <td>Ville de l'organisation.</td>
              <td class="text-muted">Genève</td>
            </tr>
            <tr>
              <td><code>{{org_web}}</code></td>
              <td>Site web de l'organisation.</td>
              <td class="text-muted">www.asso.ch</td>
            </tr>
          </tbody>
        </table>
        <p class="text-muted small px-3 pt-2 pb-1">
          <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
          Variables spécifiques à certains templates (reçu, récapitulatif…) : <code>{{type}}</code>, <code>{{amount}}</code>, <code>{{entry_date}}</code>, <code>{{entries_html}}</code>, <code>{{total}}</code>, <code>{{since_line}}</code> — voir les commentaires dans le template par défaut.
        </p>
      </div>
    </div>
  </div>
</div>
