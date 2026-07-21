<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Contact type management — add/rename/delete custom types, per-type icon,
 * and the contact_type × compta_type matrix (issue #165).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/../lib/contact_type.php';
require_once __DIR__ . '/../lib/pure.php';

$_ctBuiltinCodes = [CONTACT_TYPE_PRIVATE, CONTACT_TYPE_INSTITUTION, CONTACT_TYPE_FINANCIAL, CONTACT_TYPE_COMPANY];

$_ctEmbedded = $_ctEmbedded ?? false;

$_ctRows = db()->query(
    "SELECT ct.id, ct.code, ct.label, ct.icon, ct.sort_order, COUNT(c.id) AS cnt
     FROM contact_type ct
     LEFT JOIN contact c ON c.contact_type_id = ct.id AND c.status = 1
     GROUP BY ct.id ORDER BY ct.sort_order"
)->fetchAll(PDO::FETCH_OBJ);
$_ctLabelSavedId = isset($_GET['contactTypeLabelSaved']) ? (int)$_GET['contactTypeLabelSaved'] : null;
$_ctMatrix       = mbContactTypeComptaMatrix(db());
$_ctComptaTypes  = db()->query(
    "SELECT id, label, is_archived FROM compta_type WHERE is_archived = 0 ORDER BY sort_order, label"
)->fetchAll(PDO::FETCH_OBJ);
$_ctDefaults     = array_column(
    db()->query("SELECT id, default_compta_type_id FROM contact_type")->fetchAll(PDO::FETCH_OBJ),
    'default_compta_type_id',
    'id'
);
if (!$_ctEmbedded):
    $_noOuterContainer = true;
    $_phIcon = 'fa-address-card';
    $_phTitle = $GLOBAL['administration'];
    include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">
<div class="row justify-content-center mt-4">
  <div class="col-lg-10">
<?php endif ?>

<?php if ($_ctLabelSavedId !== null): ?>
<div class="alert alert-success py-2" role="alert">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i><?= $GLOBAL['contactTypeLabelSavedMsg'] ?>
</div>
<?php endif ?>

<div class="card mb-4">
  <div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['newContactType'] ?></h2></div>
  <div class="card-body">
    <form id="add-contact-type-form" action="<?= appUrl() ?>" method="post" class="d-flex gap-3 align-items-end flex-wrap">
      <input type="hidden" name="action" value="addContactType">
      <input type="hidden" name="returnView" value="<?= $_ctEmbedded ? 'settings' : 'contactTypes' ?>">
      <div>
        <label class="form-label form-label-sm mb-1" style="font-size:0.8rem"><?= $GLOBAL['labelField'] ?></label>
        <input type="text" name="label" class="form-control form-control-sm" style="width:200px" required>
      </div>
      <div>
        <label class="form-label form-label-sm mb-1" style="font-size:0.8rem"><?= $GLOBAL['contactTypeIcon'] ?></label>
        <input type="text" name="icon" class="form-control form-control-sm" style="width:140px"
               placeholder="<?= htmlspecialchars($GLOBAL['contactTypeIconPlaceholder'], ENT_QUOTES, $charset) ?>"
               title="<?= htmlspecialchars($GLOBAL['contactTypeIconHelp'], ENT_QUOTES, $charset) ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['addBtn'] ?></button>
    </form>
    <p class="text-muted small mt-2 mb-0"><?= $GLOBAL['newContactTypeHelp'] ?></p>
  </div>
</div>

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['contactTypesTitle'] ?></h2></div>
<div class="card-body">
<div class="table-responsive">
<table id="contact-type-management-table" class="table table-sm table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th style="width:60px" class="text-center"><?= $GLOBAL['contactTypeIcon'] ?></th>
      <th><?= $GLOBAL['contactTypeLabel'] ?> / <?= $GLOBAL['contactTypeCode'] ?></th>
      <th class="text-end"><?= $GLOBAL['contactTypeCount'] ?></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($_ctRows as $_ct): $_ctIsBuiltin = in_array($_ct->code, $_ctBuiltinCodes, true); ?>
    <tr>
      <td class="text-center">
        <i class="fas fa-<?= htmlspecialchars($_ct->icon !== '' ? $_ct->icon : 'question', ENT_QUOTES, $charset) ?> ctm-icon-preview" style="font-size:1.1rem"></i>
      </td>
      <td>
        <form method="post" action="<?= appUrl() ?>" class="d-flex gap-2 align-items-center flex-wrap">
          <input type="hidden" name="action" value="updateContactTypeLabel">
          <input type="hidden" name="returnView" value="<?= $_ctEmbedded ? 'settings' : 'contactTypes' ?>">
          <input type="hidden" name="id" value="<?= (int)$_ct->id ?>">
          <input type="text" name="label" value="<?= htmlspecialchars($_ct->label, ENT_QUOTES, $charset) ?>"
                 class="form-control form-control-sm" style="max-width:220px" required>
          <input type="text" name="icon" value="<?= htmlspecialchars($_ct->icon, ENT_QUOTES, $charset) ?>"
                 class="form-control form-control-sm ctm-icon-input" style="max-width:140px"
                 placeholder="<?= htmlspecialchars($GLOBAL['contactTypeIconPlaceholder'], ENT_QUOTES, $charset) ?>"
                 title="<?= htmlspecialchars($GLOBAL['contactTypeIconHelp'], ENT_QUOTES, $charset) ?>">
          <?php if ($_ctIsBuiltin): ?>
          <code class="text-muted small" title="<?= htmlspecialchars($GLOBAL['contactTypeCodeBuiltinHelp'], ENT_QUOTES, $charset) ?>"><?= htmlspecialchars($_ct->code, ENT_QUOTES, $charset) ?></code>
          <?php else: ?>
          <input type="text" name="code" value="<?= htmlspecialchars($_ct->code, ENT_QUOTES, $charset) ?>"
                 class="form-control form-control-sm font-monospace" style="max-width:140px" maxlength="20"
                 pattern="[a-z0-9_]+" title="<?= htmlspecialchars($GLOBAL['contactTypeCodeHelp'], ENT_QUOTES, $charset) ?>">
          <?php endif ?>
          <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $GLOBAL['save'] ?></button>
        </form>
      </td>
      <td class="text-end text-muted" style="font-size:0.85rem"><?= (int)$_ct->cnt ?></td>
      <td class="text-end">
        <?php if ((int)$_ct->cnt === 0): ?>
        <button type="button" class="btn btn-outline-danger btn-sm py-0"
                data-bs-toggle="modal" data-bs-target="#modal-delete-contact-type"
                data-href="<?= htmlspecialchars(appUrl() . '?action=deleteContactType&id=' . (int)$_ct->id . '&returnView=' . urlencode($_ctEmbedded ? 'settings' : 'contactTypes') . '&csrf=' . urlencode(csrfToken()), ENT_QUOTES, $charset) ?>"><?= $GLOBAL['deleteShort'] ?></button>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<p class="text-muted small mt-1 mb-0">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
  <?= sprintf($GLOBAL['contactTypeIconFaHint'], '<a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener">fontawesome.com</a>') ?>
</p>
<script>
document.querySelectorAll('.ctm-icon-input').forEach(function (input) {
  input.addEventListener('input', function () {
    var preview = input.closest('tr').querySelector('.ctm-icon-preview');
    var name = input.value.trim().replace(/^fa-/, '');
    preview.className = 'fas fa-' + (name || 'question') + ' ctm-icon-preview';
  });
});
</script>
</div><!-- .table-responsive -->
</div><!-- .card-body -->
</div><!-- .card -->

<div class="modal fade" id="modal-delete-contact-type" tabindex="-1" aria-labelledby="modal-delete-contact-type-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-contact-type-label"><?= $GLOBAL['deleteContactTypeTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body"><?= $GLOBAL['deleteContactTypeConfirm'] ?></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <a id="modal-delete-contact-type-link" href="#" class="btn btn-danger"><?= $GLOBAL['delete'] ?></a>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('modal-delete-contact-type').addEventListener('show.bs.modal', function (e) {
    var href = e.relatedTarget ? e.relatedTarget.dataset.href : '';
    document.getElementById('modal-delete-contact-type-link').href = href || '#';
});
</script>

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['contactTypeMatrixTitle'] ?></h2></div>
<div class="card-body">
<p class="text-muted small"><?= $GLOBAL['contactTypeMatrixHelp'] ?></p>

<?php if (empty($_ctComptaTypes)): ?>
<p class="text-muted mb-0"><?= $GLOBAL['contactTypeMatrixNoComptaTypes'] ?></p>
<?php else: ?>
<div class="mb-0">
  <div class="table-responsive">
  <table id="contact-type-matrix-table" class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th><?= $GLOBAL['contactTypeMatrixComptaType'] ?></th>
        <?php foreach ($_ctRows as $_ct): ?>
        <th class="text-center">
          <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none ctm-col-toggle"
                  data-contact-type-id="<?= (int)$_ct->id ?>"
                  title="<?= htmlspecialchars(sprintf($GLOBAL['contactTypeMatrixToggleColumn'], $_ct->label), ENT_QUOTES, $charset) ?>">
            <?= htmlspecialchars($_ct->label, ENT_QUOTES, $charset) ?>
          </button>
        </th>
        <?php endforeach ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($_ctComptaTypes as $_compTy): ?>
      <tr>
        <td><?= htmlspecialchars($_compTy->label, ENT_QUOTES, $charset) ?></td>
        <?php foreach ($_ctRows as $_ct): ?>
        <?php
          $_restricted = $_ctMatrix[(int)$_ct->id] ?? [];
          $_checked = empty($_restricted) || in_array((int)$_compTy->id, $_restricted, true);
          $_isDefault = (int)($_ctDefaults[$_ct->id] ?? 0) === (int)$_compTy->id;
        ?>
        <td class="text-center">
          <input type="checkbox" class="form-check-input ctm-cell"
                 data-contact-type-id="<?= (int)$_ct->id ?>" value="<?= (int)$_compTy->id ?>"
                 <?= $_checked ? 'checked' : '' ?>
                 aria-label="<?= htmlspecialchars($_compTy->label . ' — ' . $_ct->label, ENT_QUOTES, $charset) ?>">
          <input type="radio" class="form-check-input ctm-default-cell ms-2"
                 name="ctm-default-<?= (int)$_ct->id ?>"
                 data-contact-type-id="<?= (int)$_ct->id ?>" value="<?= (int)$_compTy->id ?>"
                 <?= $_isDefault ? 'checked' : '' ?> <?= $_checked ? '' : 'disabled' ?>
                 title="<?= htmlspecialchars($GLOBAL['contactTypeMatrixDefaultRadioTitle'], ENT_QUOTES, $charset) ?>"
                 aria-label="<?= htmlspecialchars(sprintf($GLOBAL['contactTypeMatrixDefaultRadioLabel'], $_compTy->label, $_ct->label), ENT_QUOTES, $charset) ?>">
        </td>
        <?php endforeach ?>
      </tr>
    <?php endforeach ?>
    <tr class="table-light">
      <td class="text-muted small"><?= $GLOBAL['contactTypeMatrixDefaultNone'] ?></td>
      <?php foreach ($_ctRows as $_ct): ?>
      <td class="text-center">
        <input type="radio" class="form-check-input ctm-default-cell"
               name="ctm-default-<?= (int)$_ct->id ?>"
               data-contact-type-id="<?= (int)$_ct->id ?>" value=""
               <?= empty($_ctDefaults[$_ct->id]) ? 'checked' : '' ?>
               aria-label="<?= htmlspecialchars(sprintf($GLOBAL['contactTypeMatrixDefaultRadioLabel'], $GLOBAL['contactTypeMatrixDefaultNone'], $_ct->label), ENT_QUOTES, $charset) ?>">
      </td>
      <?php endforeach ?>
    </tr>
    </tbody>
  </table>
  </div>
  <p class="text-muted small mb-0"><?= $GLOBAL['contactTypeMatrixUncheckAllHelp'] ?></p>
  <p class="text-muted small mb-0"><?= $GLOBAL['contactTypeMatrixDefaultHelp'] ?></p>
  <div id="contact-type-matrix-status" class="small text-success mt-1" style="min-height:1.2em"></div>
</div>
<script>
(function () {
  var table = document.getElementById('contact-type-matrix-table');
  if (!table) return;
  var status  = document.getElementById('contact-type-matrix-status');
  var baseUrl = <?= json_encode(appUrl()) ?>;
  var savedMsg = <?= json_encode($GLOBAL['contactTypeMatrixSavedMsg']) ?>;
  var errMsg   = <?= json_encode($GLOBAL['loadError']) ?>;
  var statusTimer = null;

  function showStatus(text, isError) {
    clearTimeout(statusTimer);
    status.textContent = text;
    status.classList.toggle('text-danger', !!isError);
    status.classList.toggle('text-success', !isError);
    statusTimer = setTimeout(function () { status.textContent = ''; }, 2500);
  }

  function saveColumn(contactTypeId) {
    var checked = Array.from(table.querySelectorAll('.ctm-cell[data-contact-type-id="' + contactTypeId + '"]:checked'))
      .map(function (cb) { return cb.value; });
    var body = new URLSearchParams();
    body.append('action', 'updateContactTypeComptaMatrixColumn');
    body.append('contact_type_id', contactTypeId);
    checked.forEach(function (v) { body.append('compta_type_ids[]', v); });
    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true', 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { showStatus(data.ok ? savedMsg : errMsg, !data.ok); })
      .catch(function () { showStatus(errMsg, true); });
  }

  function saveDefault(contactTypeId, comptaTypeId) {
    var body = new URLSearchParams();
    body.append('action', 'updateContactTypeDefaultComptaType');
    body.append('contact_type_id', contactTypeId);
    body.append('compta_type_id', comptaTypeId || '');
    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true', 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { showStatus(data.ok ? savedMsg : errMsg, !data.ok); })
      .catch(function () { showStatus(errMsg, true); });
  }

  table.addEventListener('change', function (e) {
    if (e.target.classList.contains('ctm-default-cell')) {
      saveDefault(e.target.dataset.contactTypeId, e.target.value);
      return;
    }
    if (!e.target.classList.contains('ctm-cell')) return;
    var contactTypeId = e.target.dataset.contactTypeId;
    // Unchecking a cell disables its "default" radio; if it was the
    // selected default, fall back to "Aucun" and persist that.
    var radio = table.querySelector('.ctm-default-cell[data-contact-type-id="' + contactTypeId + '"][value="' + e.target.value + '"]');
    if (radio) {
      radio.disabled = !e.target.checked;
      if (!e.target.checked && radio.checked) {
        var noneRadio = table.querySelector('.ctm-default-cell[data-contact-type-id="' + contactTypeId + '"][value=""]');
        if (noneRadio) { noneRadio.checked = true; }
        radio.checked = false;
        saveDefault(contactTypeId, '');
      }
    }
    saveColumn(contactTypeId);
  });

  table.querySelectorAll('.ctm-col-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var contactTypeId = btn.dataset.contactTypeId;
      var boxes = table.querySelectorAll('.ctm-cell[data-contact-type-id="' + contactTypeId + '"]');
      var radios = table.querySelectorAll('.ctm-default-cell[data-contact-type-id="' + contactTypeId + '"]');
      var allChecked = Array.from(boxes).every(function (cb) { return cb.checked; });
      boxes.forEach(function (cb) { cb.checked = !allChecked; });
      radios.forEach(function (r) { r.disabled = allChecked && r.value !== ''; });
      if (allChecked) {
        // Column just got fully unchecked — clear whichever default was set.
        var noneRadio = table.querySelector('.ctm-default-cell[data-contact-type-id="' + contactTypeId + '"][value=""]');
        if (noneRadio && !noneRadio.checked) { noneRadio.checked = true; saveDefault(contactTypeId, ''); }
      }
      saveColumn(contactTypeId);
    });
  });
})();
</script>
<?php endif ?>
</div><!-- .card-body -->
</div><!-- .card -->

<?php if (!$_ctEmbedded): ?>
  </div>
</div>
</div>
<?php endif ?>
