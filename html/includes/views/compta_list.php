<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Generic accounting entries view with year and type filters.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = -2; // default: all years
if (isset($_REQUEST['year'])) {
    $year = (int)$_REQUEST['year']; // int cast: echoed in HTML/JS and used in queries
}
$donsOnly = !empty($_REQUEST['dons_only']);
$filterTypeId = isset($_REQUEST['type_id']) ? (int)$_REQUEST['type_id'] : 0;
$from = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year));
$to = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year + 1));
?>
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">

  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?=$year == -2 ? $GLOBAL['allYear'] : $year?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $year == -2 ? ' active' : '' ?>"
             href="<?=appUrl()?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=-2"><?=$GLOBAL['allYear']?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= $year == $y ? ' active' : '' ?>"
               href="<?=appUrl()?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=<?=$y?>"><?=$y?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <div class="form-check form-switch mb-0 ms-2" title="<?= $GLOBAL['hideNonDonationEntries'] ?>">
    <input class="form-check-input" type="checkbox" role="switch" id="dons-only-toggle"
           <?= $donsOnly ? 'checked' : '' ?>
           data-no-dirty
           onchange="window.__dirtyOverride=true;window.location='<?= appUrl() ?>?view=compta&amp;userid=<?= $user->getId() ?>&amp;year=<?= $year ?>'+(this.checked?'&amp;dons_only=1':'')">
    <label class="form-check-label small" for="dons-only-toggle"><?= $GLOBAL['donationsOnly'] ?></label>
  </div>

  <?php if ($year != -2): ?>
  <?php
  // Count excluded-from-donation entries in the current year view (without type filter to give full picture)
  $_exclStmt = db()->prepare(
      "SELECT COUNT(*) FROM compta c LEFT JOIN compta_type ct ON ct.id = c.type_id
       WHERE c.user_id = ? AND c.date > ? AND c.date < ?
         AND COALESCE(ct.is_excluded_from_donation,0) = 1 AND c.sum <> 0"
  );
  $_exclStmt->execute([(int)$user->getId(), $from, $to]);
  $_exclCount = (int)$_exclStmt->fetchColumn();
  ?>
  <div class="dropdown ms-auto">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-file-pdf me-1" aria-hidden="true"></i><?= $GLOBAL['attestation'] ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" style="min-width:15rem">
      <li class="px-3 py-1">
        <div class="form-check form-check-sm mb-0">
          <input class="form-check-input" type="checkbox" id="attest-include-stamp" data-no-dirty>
          <label class="form-check-label small" for="attest-include-stamp"><?= $GLOBAL['includeStampSignature'] ?></label>
        </div>
      </li>
      <li><hr class="dropdown-divider"></li>
      <?php for ($i = 0; $i < 10; $i++): $y = $currentYear - $i; ?>
      <li class="d-flex align-items-center">
        <a class="dropdown-item attest-year-link<?= $year == $y ? ' fw-semibold' : '' ?>"
           data-href="/attestation_don.php?userid=<?=$user->getId()?>&amp;year=<?=$y?>"
           href="/attestation_don.php?userid=<?=$user->getId()?>&amp;year=<?=$y?>"
           target="_blank" style="flex:1 1 auto">
            <?= $y ?><?= $year == $y ? ' ' . $GLOBAL['displayedYear'] : '' ?>
        </a>
        <?php if (isManager() && trim($user->getEmail()) !== ''): ?>
        <button type="button" class="btn btn-link btn-sm text-muted js-preview-attest-one py-0 px-2"
                data-year="<?= $y ?>"
                data-name="<?= htmlspecialchars(trim($user->getFirstName() . ' ' . $user->getLastName()), ENT_QUOTES, $charset) ?>"
                data-email="<?= htmlspecialchars($user->getEmail(), ENT_QUOTES, $charset) ?>"
                title="<?= $GLOBAL['sendAttestationBtn'] ?>">
          <i class="fas fa-paper-plane" aria-hidden="true"></i>
        </button>
        <?php endif ?>
      </li>
      <?php endfor ?>
    </ul>
  </div>

  <!-- Preview modal for individual attestation send -->
  <div class="modal fade" id="attestPreviewModal" tabindex="-1" aria-labelledby="attestPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0" id="attestPreviewModalLabel"><?= htmlspecialchars($GLOBAL['sendAttestationBtn'], ENT_QUOTES, $charset) ?></h5>
            <div class="text-muted small" id="attest-modal-meta"></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($GLOBAL['close'], ENT_QUOTES, $charset) ?>"></button>
        </div>
        <div class="modal-body p-0" style="min-height:300px">
          <div id="attest-modal-loading" style="display:flex;align-items:center;justify-content:center;padding:3rem 0">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
          </div>
          <div id="attest-modal-error" class="alert alert-danger m-3" style="display:none"></div>
          <iframe id="attest-modal-frame" style="width:100%;border:none;min-height:500px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
        </div>
        <?php if ((int)date('n') !== 1): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 mx-3 mb-0 py-2" role="alert" style="font-size:0.85rem">
          <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0" aria-hidden="true"></i>
          <div>
            <div><?= $GLOBAL['attestationOffSeasonWarning'] ?></div>
            <div class="form-check mt-1 mb-0">
              <input class="form-check-input" type="checkbox" id="attest-off-season-confirm">
              <label class="form-check-label" for="attest-off-season-confirm"><?= $GLOBAL['attestationOffSeasonConfirm'] ?></label>
            </div>
          </div>
        </div>
        <?php endif ?>
        <?php if (trim($appSettings['smtp_reply_to'] ?? '') !== ''): ?>
        <div class="px-3 pt-2">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="attest-one-bcc">
            <label class="form-check-label small" for="attest-one-bcc"><?= sprintf($GLOBAL['sendBccCopyLabel'], htmlspecialchars($appSettings['smtp_reply_to'], ENT_QUOTES, $charset)) ?></label>
          </div>
        </div>
        <?php endif ?>
        <div class="modal-footer gap-2">
          <div class="me-auto small text-muted" id="attest-modal-subject"></div>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($GLOBAL['cancel'], ENT_QUOTES, $charset) ?></button>
          <button type="button" class="btn btn-primary" id="btn-attest-send-one" disabled>
            <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= htmlspecialchars($GLOBAL['sendAttestationBtn'], ENT_QUOTES, $charset) ?>
          </button>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function () {
      var stampCb = document.getElementById('attest-include-stamp');
      function syncStampParam() {
          document.querySelectorAll('.attest-year-link').forEach(function (a) {
              var base = a.dataset.href;
              a.href = base + (stampCb.checked ? '&stamp=1' : '');
          });
      }
      stampCb.addEventListener('change', syncStampParam);
      syncStampParam();

      var baseUrl  = <?= json_encode(appUrl()) ?>;
      var userId   = <?= (int)$user->getId() ?>;
      function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }

      var modal        = new bootstrap.Modal(document.getElementById('attestPreviewModal'));
      var loadingEl     = document.getElementById('attest-modal-loading');
      var errorEl       = document.getElementById('attest-modal-error');
      var frame         = document.getElementById('attest-modal-frame');
      var metaEl        = document.getElementById('attest-modal-meta');
      var subjectEl     = document.getElementById('attest-modal-subject');
      var sendBtn       = document.getElementById('btn-attest-send-one');
      var offSeasonCb   = document.getElementById('attest-off-season-confirm');
      var currentYear   = null;
      var previewOk     = false;

      function syncSendEnabled() {
          sendBtn.disabled = !previewOk || (offSeasonCb && !offSeasonCb.checked);
      }
      if (offSeasonCb) { offSeasonCb.addEventListener('change', syncSendEnabled); }

      document.querySelectorAll('.js-preview-attest-one').forEach(function (btn) {
          btn.addEventListener('click', function () {
              currentYear = btn.dataset.year;
              loadingEl.style.display = '';
              errorEl.style.display   = 'none';
              frame.style.display     = 'none';
              metaEl.textContent      = btn.dataset.name + ' <' + btn.dataset.email + '> — ' + currentYear;
              subjectEl.textContent   = '';
              previewOk               = false;
              if (offSeasonCb) { offSeasonCb.checked = false; }
              syncSendEnabled();
              modal.show();

              fetch(baseUrl, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
                  body: 'action=previewAttestation&user_id=' + userId + '&year=' + encodeURIComponent(currentYear)
              })
              .then(function (r) { return r.json(); })
              .then(function (data) {
                  loadingEl.style.display = 'none';
                  if (!data.ok) {
                      errorEl.textContent = data.error || '?';
                      errorEl.style.display = '';
                      return;
                  }
                  subjectEl.textContent = data.subject;
                  frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
                  frame.style.display = '';
                  frame.addEventListener('load', function () {
                      try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch(e){}
                  }, { once: true });
                  frame.style.height = '500px';
                  previewOk = true;
                  syncSendEnabled();
              })
              .catch(function () {
                  loadingEl.style.display = 'none';
                  errorEl.textContent = '?';
                  errorEl.style.display = '';
              });
          });
      });

      sendBtn.addEventListener('click', function () {
          sendBtn.disabled = true;
          sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1" aria-hidden="true"></i>' + sendBtn.textContent.trim();
          fetch(baseUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
              body: 'action=sendAttestationOne&user_id=' + userId + '&year=' + encodeURIComponent(currentYear)
                  + (document.getElementById('attest-one-bcc') && document.getElementById('attest-one-bcc').checked ? '&bcc=1' : '')
          })
          .then(function (r) { return r.json(); })
          .then(function (data) {
              modal.hide();
              var yearBtn = document.querySelector('.js-preview-attest-one[data-year="' + currentYear + '"]');
              if (yearBtn) {
                  yearBtn.innerHTML = data.ok
                      ? '<i class="fas fa-check text-success" aria-hidden="true"></i>'
                      : '<i class="fas fa-triangle-exclamation text-danger" aria-hidden="true"></i>';
              }
          })
          .catch(function () { modal.hide(); });
      });
  })();
  </script>
  <?php endif ?>
  <?php if ($filterTypeId > 0 && isset($comptaTypes[$filterTypeId])): ?>
  <a href="<?= appUrl() ?>?view=compta&amp;userid=<?= $user->getId() ?>&amp;year=<?= $year ?>"
     class="ca-filter-btn text-decoration-none" data-no-dirty
     title="<?= $GLOBAL['removeTypeFilter'] ?>">
    <?= htmlentities($comptaTypes[$filterTypeId]->label, ENT_COMPAT, $charset) ?> <span aria-hidden="true">×</span>
  </a>
  <?php endif ?>

  <?php if (isManager() && trim($user->getEmail()) !== ''): ?>
  <button type="button" class="btn btn-outline-secondary btn-sm ms-auto"
          data-bs-toggle="modal" data-bs-target="#modal-send-recap-user"
          data-no-dirty>
    <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapSendUserBtn'] ?>
  </button>
  <?php endif ?>

</div>

<?php if (($year ?? -2) != -2 && ($_exclCount ?? 0) > 0): ?>
<div class="alert alert-warning py-2 d-flex align-items-start gap-2" style="font-size:0.85rem" role="alert">
  <i class="fas fa-circle-info mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><?= sprintf($GLOBAL['attestationExclNote'], $_exclCount) ?></span>
</div>
<?php endif ?>

<form action="<?=appUrl()?>" method="post" name="addCompta">
<input type="hidden" name="action" value="addCompta"/>
<input type="hidden" name="view" value="compta"/>
<input type="hidden" name="userid" value="<?=$user->getId()?>"/>
<div class="table-responsive">
<table class="table table-condensed table-striped table-hover p mt-2">
<thead>
<tr>
    <th><?=$GLOBAL['type']?></th>
    <th><?=$GLOBAL['date']?></th>
    <th><?=$GLOBAL['libele']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th class="d-none d-sm-table-cell"><?=$GLOBAL['quittance']?></th>
    <th class="d-none d-sm-table-cell" title="<?= $GLOBAL['wantsAttestation'] ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i></th>
    <th class="d-none d-sm-table-cell" title="<?= $GLOBAL['sendReceiptLabel'] ?>"><i class="fas fa-envelope" aria-hidden="true"></i></th>
    <th>&nbsp;</td>
</tr>
</thead>
<?php
// JSON map of cotisation type IDs for JS visibility toggle
$_cotiTypeIds = array_values(array_map('intval',
    array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1))
));
// Per-type default entry label for the autofill (only types that define one)
$_ctDefaultLibeles = [];
foreach ($comptaTypes as $_ctId => $_ctObj) {
    if (trim((string)($_ctObj->default_libele ?? '')) !== '') {
        $_ctDefaultLibeles[(int)$_ctId] = trim((string)$_ctObj->default_libele);
    }
}
?>
<?php if (canWrite()): ?>
<tr>
    <td>
        <select name="type_id" id="ca-add-type" class="form-control">
            <?php foreach ($comptaTypes as $ct): ?>
            <option value="<?= (int)$ct->id ?>"><?= htmlentities($ct->label, ENT_COMPAT, $charset) ?></option>
            <?php endforeach ?>
        </select>
    </td>
    <td>
        <input type="text" name="date" id="date" class="form-control datepicker" maxlength="30" value="<?=date("d/m/Y")?>" />
        <select name="cotisation_year" id="ca-coti-year"
                class="form-control form-control-sm mt-1" style="display:none;width:90px"
                title="<?= $GLOBAL['cotisationYearLabel'] ?>">
            <?php
            $_cyNow = (int)date('Y');
            for ($_cy = $_cyNow + 1; $_cy >= $_cyNow - 10; $_cy--):
            ?>
            <option value="<?= $_cy ?>"<?= $_cy === $_cyNow ? ' selected' : '' ?>><?= $_cy ?></option>
            <?php endfor ?>
        </select>
    </td>
    <td><input type="text" name="libele" class="form-control" maxlength="255"/></td>
    <td><input type="text" name="sum" size="10" class="form-control" maxlength="64"
             inputmode="decimal" pattern="^[0-9]+([.,][0-9]+)?$" title="<?= $GLOBAL['numericAmountHint'] ?>"
             required oninvalid="this.setCustomValidity(this.validity.valueMissing ? <?= json_encode($GLOBAL['sumRequired']) ?> : <?= json_encode($GLOBAL['numericAmountHint']) ?>)"
             oninput="this.setCustomValidity('')"/></td>
    <td class="d-none d-sm-table-cell"><input type="text" name="quittance" size="10" class="form-control" maxlength="64"/></td>
    <td class="d-none d-sm-table-cell text-center"><input type="checkbox" name="wants_attestation" value="1" /></td>
    <td class="d-none d-sm-table-cell text-center">
      <?php if ($user->getEmail()): ?>
      <input type="checkbox" name="send_receipt" value="1" title="<?= $GLOBAL['sendReceiptLabel'] ?>" />
      <?php else: ?>
      <span class="text-muted" title="<?= $GLOBAL['sendReceiptNoEmail'] ?>">—</span>
      <?php endif ?>
    </td>
    <td><button type="submit" class="btn btn-primary"><?=$GLOBAL['add']?></button></td>
</tr>
<?php endif ?>
<?php
$_showZero = isset($_REQUEST['showZero']);
// Filter clauses are static SQL; every user-influenced value goes through a
// bound parameter.
$_baseWhere  = "FROM compta c LEFT JOIN compta_type ct ON ct.id = c.type_id WHERE c.user_id = ? ";
$_baseParams = [(int)$user->getId()];
if ($year != -2) {
    $_baseWhere .= " AND c.date > ? AND c.date < ? ";
    $_baseParams[] = $from;
    $_baseParams[] = $to;
}
if ($donsOnly) {
    $_baseWhere .= " AND COALESCE(ct.is_excluded_from_donation,0) = 0 ";
}
if ($filterTypeId > 0) {
    $_baseWhere .= " AND c.type_id = ? ";
    $_baseParams[] = $filterTypeId;
}
// Count zero-sum entries so we can offer a "show all" toggle
$_zeroStmt = db()->prepare("SELECT COUNT(*) " . $_baseWhere . " AND c.sum = 0");
$_zeroStmt->execute($_baseParams);
$_zeroCount = (int)$_zeroStmt->fetchColumn();
$_selectCols = "SELECT c.id, c.user_id, c.type_id, c.date, c.libele, c.sum, c.quittance, c.wants_attestation, c.cotisation_year, ct.label AS ct_label, ct.color AS ct_color, COALESCE(ct.is_excluded_from_donation,0) AS ct_excl, COALESCE(ct.is_cotisation,0) AS ct_coti ";
$query  = $_selectCols . $_baseWhere;
$query2 = $_selectCols . $_baseWhere;
if (!$_showZero) {
    $query  .= " AND c.sum <> 0 ";
    $query2 .= " AND c.sum <> 0 ";
}
$query  .= " ORDER BY c.date DESC";
$query2 .= " ORDER BY c.date ASC";
$stmt = db()->prepare($query);
$stmt->execute($_baseParams);
$total = 0;
while ($row = $stmt->fetchObject()) {
    $id = $row->id;
    $date = $row->date ? strtotime($row->date) : 0;
    $libele = $row->libele;
    $sum = (float)($row->sum ?? 0);
    $quittance = $row->quittance;
    $total += $sum;
    $ctColor = $row->ct_color ?? '';
    static $bgVarMap = [
        'bg-primary-subtle'   => 'var(--bs-primary-bg-subtle)',
        'bg-secondary-subtle' => 'var(--bs-secondary-bg-subtle)',
        'bg-success-subtle'   => 'var(--bs-success-bg-subtle)',
        'bg-danger-subtle'    => 'var(--bs-danger-bg-subtle)',
        'bg-warning-subtle'   => 'var(--bs-warning-bg-subtle)',
        'bg-info-subtle'      => 'var(--bs-info-bg-subtle)',
        'bg-light'            => 'var(--bs-light)',
        'bg-dark-subtle'      => 'var(--bs-dark-bg-subtle)',
        'ca-orange-subtle'    => 'rgba(253,126,20,0.18)',
        'ca-teal-subtle'      => 'rgba(32,201,151,0.18)',
        'ca-pink-subtle'      => 'rgba(214,51,132,0.18)',
        'ca-purple-subtle'    => 'rgba(111,66,193,0.18)',
        'ca-indigo-subtle'    => 'rgba(102,16,242,0.18)',
        'ca-lime-subtle'      => 'rgba(128,189,64,0.18)',
    ];
    $rowStyle = isset($bgVarMap[$ctColor]) ? '--bs-table-bg:' . $bgVarMap[$ctColor] : '';
    ?>
     <tr <?= canWrite() ? 'class="ca-row-link" data-href="' . appUrl() . '?view=updateCompta&comptaid=' . (int)$id . '&userid=' . (int)$user->getId() . '" style="cursor:pointer;' . htmlentities($rowStyle, ENT_COMPAT, $charset) . '"' : 'style="' . htmlentities($rowStyle, ENT_COMPAT, $charset) . '"' ?>>
        <td>
            <?= htmlentities($row->ct_label ?? '', ENT_COMPAT, $charset) ?>
            <?php if ($row->ct_coti && $row->cotisation_year): ?>
            <?php $_payYear = $row->date ? (int)date('Y', strtotime($row->date)) : 0; ?>
            <?php if ((int)$row->cotisation_year !== $_payYear): ?>
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem" title="<?= $GLOBAL['cotisationYearLabel'] ?>"><?= (int)$row->cotisation_year ?></span>
            <?php else: ?>
            <span class="text-muted ms-1" style="font-size:0.72rem"><?= (int)$row->cotisation_year ?></span>
            <?php endif ?>
            <?php endif ?>
            <?php if ($row->ct_excl): ?>
            <span class="ms-1 text-muted" style="font-size:0.65rem;opacity:0.55" title="<?= $GLOBAL['notCountedAsDonation'] ?>"><?= $GLOBAL['nonDonation'] ?></span>
            <?php endif ?>
        </td>
        <td><?=timeStampToformatedDate($date)?></td>
        <td><?=htmlentities($libele,ENT_COMPAT,$charset)?></td>
        <td style="text-align:right;"><?=number_format($sum,2,'.','\'')?></td>
        <td class="d-none d-sm-table-cell"><?= htmlspecialchars($quittance, ENT_QUOTES, $charset) ?></td>
        <td class="d-none d-sm-table-cell text-center">
            <?php if ($row->wants_attestation): ?>
                <i class="fas fa-check text-success" aria-label="<?= $GLOBAL['wantsAttestation'] ?>" title="<?= $GLOBAL['wantsAttestation'] ?>"></i>
            <?php endif ?>
        </td>
        <td>
            <?php if (canWrite()): ?>
            <a href="<?=appUrl()?>?view=updateCompta&comptaid=<?=$id?>&userid=<?=$user->getId()?>"
               class="ca-row-link-anchor" hx-boost="false" tabindex="-1" aria-hidden="true"></a>
            <?php endif ?>
        </td>
    </tr>
    <?php
}

?>
<tfoot>
<tr>
    <td></td>
    <td><strong><?=$GLOBAL['total']?></strong></td>
    <td></td>
    <td style="text-align:right;"><strong><?=number_format($total,2,'.','\'')?></strong></td>
    <td class="d-none d-sm-table-cell"></td>
    <td class="d-none d-sm-table-cell"></td>
    <td></td>
</tr>
</tfoot>
</table>
</div>
</form>
<?php if ($_zeroCount > 0): ?>
<p class="text-muted small mt-1 mb-0">
  <?php
  // Build the toggle URL preserving all current query params except showZero
  $_qp = $_GET;
  unset($_qp['showZero']);
  if ($_showZero) {
      $GLOBAL['__toggleZeroUrl'] = appUrl() . '?' . http_build_query($_qp);
      $GLOBAL['__toggleZeroLabel'] = sprintf($GLOBAL['hideZeroEntries'], $_zeroCount);
  } else {
      $_qp['showZero'] = '1';
      $GLOBAL['__toggleZeroUrl'] = appUrl() . '?' . http_build_query($_qp);
      $GLOBAL['__toggleZeroLabel'] = sprintf($GLOBAL['showZeroEntries'], $_zeroCount);
  }
  ?>
  <a href="<?= htmlspecialchars($GLOBAL['__toggleZeroUrl'], ENT_QUOTES, $charset) ?>" data-no-dirty>
    <?= $GLOBAL['__toggleZeroLabel'] ?>
  </a>
</p>
<?php endif ?>
<script>
function _comptaListInit() {
    // Row click navigation
    var tbody = document.querySelector('form[name="addCompta"] tbody');
    if (tbody) tbody.addEventListener('click', function(e) {
        var tr = e.target.closest('tr.ca-row-link');
        if (!tr) return;
        if (e.target.closest('a, button')) return;
        window.location.href = tr.dataset.href;
    });

    // Show cotisation_year field only for cotisation types
    var cotiIds = <?= json_encode($_cotiTypeIds) ?>;
    var typeSelect = document.getElementById('ca-add-type');
    var cotiYearField = document.getElementById('ca-coti-year');
    function isCotiType() {
        return typeSelect && cotiIds.indexOf(parseInt(typeSelect.value, 10)) !== -1;
    }
    function toggleCotiYear() {
        if (!typeSelect || !cotiYearField) return;
        var isCoti = isCotiType();
        cotiYearField.style.display = isCoti ? '' : 'none';
        cotiYearField.name = isCoti ? 'cotisation_year' : '';
    }

    // Default entry label per type. For cotisation types the selected year is
    // appended ("Cotisation 2026"). The field is only overwritten while it is
    // empty or still holds the previous auto-filled value — a hand-edited
    // label is never touched.
    var defaultLibeles = <?= json_encode($_ctDefaultLibeles) ?>;
    var libeleInput = document.querySelector('form[name="addCompta"] input[name="libele"]');
    var lastAutoLibele = '';
    function computedDefaultLibele() {
        if (!typeSelect) return '';
        var def = defaultLibeles[typeSelect.value] || '';
        if (def && isCotiType() && cotiYearField && cotiYearField.value) {
            def += ' ' + cotiYearField.value;
        }
        return def;
    }
    function applyDefaultLibele() {
        if (!libeleInput) return;
        var cur = libeleInput.value.trim();
        if (cur !== '' && cur !== lastAutoLibele) return; // user-edited
        lastAutoLibele = computedDefaultLibele();
        libeleInput.value = lastAutoLibele;
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            toggleCotiYear();
            applyDefaultLibele();
        });
        if (cotiYearField) cotiYearField.addEventListener('change', applyDefaultLibele);
        toggleCotiYear();
        applyDefaultLibele();
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _comptaListInit);
} else {
    _comptaListInit();
}
</script>

<?php if (isManager() && trim($user->getEmail()) !== ''): ?>
<!-- Recap send modal for this user -->
<div class="modal fade" id="modal-send-recap-user" tabindex="-1"
     aria-labelledby="modal-send-recap-user-label" aria-hidden="true">
  <div class="modal-dialog modal-xl" style="--bs-modal-height:85vh">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="modal-send-recap-user-label"><?= $GLOBAL['comptaRecapModalTitle'] ?></h5>
          <div class="text-muted small"><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, $charset) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body" style="overflow:visible;min-height:500px">
        <!-- Controls -->
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
          <div class="dropdown">
            <button class="ca-filter-btn dropdown-toggle" type="button" id="recap-user-year-btn"
                    data-bs-toggle="dropdown" aria-expanded="false">
              <?= (int)date('Y') ?>
            </button>
            <ul class="dropdown-menu" id="recap-user-year-menu">
              <?php for ($i = 0; $i < 10; $i++): $y = (int)date('Y') - $i; ?>
              <li><a class="dropdown-item recap-user-year-item<?= $i === 0 ? ' active' : '' ?>"
                     data-year="<?= $y ?>" href="#"><?= $y ?></a></li>
              <?php endfor ?>
            </ul>
          </div>
          <div id="recap-scope-toggle-wrap" class="form-check form-switch mb-0" style="font-size:0.875rem">
            <input class="form-check-input" type="checkbox" role="switch" id="recap-scope-all" data-no-dirty>
            <label class="form-check-label text-muted" for="recap-scope-all"><?= $GLOBAL['comptaRecapScopeAll'] ?></label>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btn-recap-user-preview">
            <i class="fas fa-eye me-1" aria-hidden="true"></i><?= $GLOBAL['preview'] ?? 'Prévisualiser' ?>
          </button>
        </div>
        <!-- Preview area -->
        <div id="recap-user-loading" style="display:none;align-items:center;justify-content:center;padding:3rem 0"></div>
        <div id="recap-user-error" class="alert alert-danger m-3" style="display:none"></div>
        <div id="recap-user-empty" class="alert alert-info m-3" style="display:none">
          <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
          <span id="recap-user-empty-msg"></span>
        </div>
        <div id="recap-user-subject" class="text-muted small mb-2" style="display:none"></div>
        <iframe id="recap-user-frame" style="width:100%;border:none;min-height:400px;display:none" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
      <div class="modal-footer gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-primary" id="btn-recap-user-send" disabled>
          <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['comptaRecapSendOne'] ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function getCsrf() { return window.casaCsrfToken ? window.casaCsrfToken() : ''; }
  var baseUrl    = <?= json_encode(appUrl()) ?>;
  var userId     = <?= (int)$user->getId() ?>;
  var recapYear  = <?= (int)date('Y') ?>;
  var currentYear = recapYear;

  function isForceScope() {
    var el = document.getElementById('recap-scope-all');
    // Hidden (past year) → always force
    return el.parentElement.style.display === 'none' || el.checked;
  }

  function updateScopeToggle() {
    var wrap = document.getElementById('recap-scope-toggle-wrap');
    if (recapYear < currentYear) {
      // Past year: hide toggle, force is implicit
      wrap.style.display = 'none';
    } else {
      // Current year: show toggle, default unchecked
      wrap.style.display = '';
      document.getElementById('recap-scope-all').checked = false;
    }
  }

  // Year picker
  document.querySelectorAll('.recap-user-year-item').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      recapYear = parseInt(this.dataset.year, 10);
      document.getElementById('recap-user-year-btn').textContent = this.dataset.year;
      document.querySelectorAll('.recap-user-year-item').forEach(function (el) { el.classList.remove('active'); });
      this.classList.add('active');
      updateScopeToggle();
      // Reset preview
      document.getElementById('recap-user-frame').style.display   = 'none';
      document.getElementById('recap-user-subject').style.display = 'none';
      document.getElementById('recap-user-empty').style.display   = 'none';
      document.getElementById('btn-recap-user-send').disabled     = true;
    });
  });

  function showRecapUserLoading(on) {
    var el = document.getElementById('recap-user-loading');
    el.style.display = on ? 'flex' : 'none';
    if (on) el.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>';
  }

  document.getElementById('btn-recap-user-preview').addEventListener('click', function () {
    var force = isForceScope() ? '1' : '';
    showRecapUserLoading(true);
    document.getElementById('recap-user-error').style.display   = 'none';
    document.getElementById('recap-user-empty').style.display   = 'none';
    document.getElementById('recap-user-frame').style.display   = 'none';
    document.getElementById('recap-user-subject').style.display = 'none';
    document.getElementById('btn-recap-user-send').disabled     = true;

    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
      body: 'action=previewComptaRecap&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf())
          + '&user_id=' + encodeURIComponent(userId)
          + '&year='    + encodeURIComponent(recapYear)
          + '&force='   + encodeURIComponent(force)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      showRecapUserLoading(false);
      if (!data.ok) {
        if (data.error === 'no_entries') {
          var scopeWrap = document.getElementById('recap-scope-toggle-wrap');
          var scopeVisible = scopeWrap.style.display !== 'none';
          if (scopeVisible && !document.getElementById('recap-scope-all').checked) {
            // Current year, new-only mode → auto-switch to all and retry
            document.getElementById('recap-scope-all').checked = true;
            document.getElementById('btn-recap-user-preview').click();
            return;
          }
          // Force was already on and still nothing — truly no entries
          document.getElementById('recap-user-empty-msg').textContent = <?= json_encode($GLOBAL['comptaRecapNoEntriesForce']) ?>;
          document.getElementById('recap-user-empty').style.display = '';
        } else {
          var el = document.getElementById('recap-user-error');
          el.textContent = data.error || '<?= addslashes($GLOBAL['error'] ?? 'Erreur') ?>';
          el.style.display = '';
        }
        return;
      }
      var sub = document.getElementById('recap-user-subject');
      sub.textContent  = data.subject;
      sub.style.display = '';
      var frame = document.getElementById('recap-user-frame');
      frame.srcdoc = data.html || '<pre>' + (data.text || '') + '</pre>';
      frame.style.display = '';
      frame.addEventListener('load', function () {
        try { frame.style.height = (frame.contentDocument.body.scrollHeight + 16) + 'px'; } catch(e){}
      }, { once: true });
      frame.style.height = '400px';
      document.getElementById('btn-recap-user-send').disabled = false;
    })
    .catch(function () {
      showRecapUserLoading(false);
      var el = document.getElementById('recap-user-error');
      el.textContent = '<?= addslashes($GLOBAL['error'] ?? 'Erreur réseau') ?>';
      el.style.display = '';
    });
  });

  document.getElementById('btn-recap-user-send').addEventListener('click', function () {
    var force = isForceScope() ? '1' : '';
    var btn   = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><?= addslashes($GLOBAL['sending'] ?? 'Envoi…') ?>';

    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrf() },
      body: 'action=sendComptaRecapOne&view=comptaRecap&csrf=' + encodeURIComponent(getCsrf())
          + '&user_id=' + encodeURIComponent(userId)
          + '&year='    + encodeURIComponent(recapYear)
          + '&force='   + encodeURIComponent(force)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        bootstrap.Modal.getInstance(document.getElementById('modal-send-recap-user')).hide();
        // Briefly show success toast if available, otherwise just re-enable button
        btn.innerHTML = '<i class="fas fa-check me-1"></i><?= addslashes($GLOBAL['sent'] ?? 'Envoyé') ?>';
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i><?= addslashes($GLOBAL['comptaRecapSendOne']) ?>';
        var el = document.getElementById('recap-user-error');
        el.textContent = data.error || '<?= addslashes($GLOBAL['error'] ?? 'Erreur') ?>';
        el.style.display = '';
      }
    })
    .catch(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i><?= addslashes($GLOBAL['comptaRecapSendOne']) ?>';
    });
  });
}());
</script>
<?php endif ?>
<?php
$_ctBg = [
    'bg-primary-subtle'   => 'rgba(13,110,253,0.7)',
    'bg-secondary-subtle' => 'rgba(108,117,125,0.7)',
    'bg-success-subtle'   => 'rgba(25,135,84,0.7)',
    'bg-danger-subtle'    => 'rgba(220,53,69,0.7)',
    'bg-warning-subtle'   => 'rgba(255,193,7,0.7)',
    'bg-info-subtle'      => 'rgba(13,202,240,0.7)',
    'bg-light'            => 'rgba(173,181,189,0.7)',
    'bg-dark-subtle'      => 'rgba(52,58,64,0.7)',
    'ca-orange-subtle'    => 'rgba(253,126,20,0.7)',
    'ca-teal-subtle'      => 'rgba(32,201,151,0.7)',
    'ca-pink-subtle'      => 'rgba(214,51,132,0.7)',
    'ca-purple-subtle'    => 'rgba(111,66,193,0.7)',
    'ca-indigo-subtle'    => 'rgba(102,16,242,0.7)',
    'ca-lime-subtle'      => 'rgba(128,189,64,0.7)',
];
$_ctBorder = [
    'bg-primary-subtle'   => 'rgba(13,110,253,1)',
    'bg-secondary-subtle' => 'rgba(108,117,125,1)',
    'bg-success-subtle'   => 'rgba(25,135,84,1)',
    'bg-danger-subtle'    => 'rgba(220,53,69,1)',
    'bg-warning-subtle'   => 'rgba(255,193,7,1)',
    'bg-info-subtle'      => 'rgba(13,202,240,1)',
    'bg-light'            => 'rgba(173,181,189,1)',
    'bg-dark-subtle'      => 'rgba(52,58,64,1)',
    'ca-orange-subtle'    => 'rgba(253,126,20,1)',
    'ca-teal-subtle'      => 'rgba(32,201,151,1)',
    'ca-pink-subtle'      => 'rgba(214,51,132,1)',
    'ca-purple-subtle'    => 'rgba(111,66,193,1)',
    'ca-indigo-subtle'    => 'rgba(102,16,242,1)',
    'ca-lime-subtle'      => 'rgba(128,189,64,1)',
];
$_frMonths = $GLOBAL['monthsShort'];
$_typeAgg  = [];
$_periodAgg = []; // period key => amount
$_stmt2 = db()->prepare($query2);
$_stmt2->execute($_baseParams);
while ($_r = $_stmt2->fetchObject()) {
    // Donut: aggregate by type
    $_lbl = $_r->ct_label ?? $GLOBAL['withoutType'];
    $_col = $_r->ct_color ?? 'bg-secondary-subtle';
    if (!isset($_typeAgg[$_lbl])) $_typeAgg[$_lbl] = ['sum' => 0.0, 'color' => $_col];
    $_typeAgg[$_lbl]['sum'] += (float)$_r->sum;
    // Timeline: aggregate by month (specific year) or by year (all years)
    $_ts = $_r->date ? strtotime($_r->date) : 0;
    $_pk = ($year == -2) ? date('Y', $_ts) : date('Y-m', $_ts);
    if (!isset($_periodAgg[$_pk])) $_periodAgg[$_pk] = 0.0;
    $_periodAgg[$_pk] += (float)$_r->sum;
}
uasort($_typeAgg, fn($a, $b) => $b['sum'] <=> $a['sum']);
$_tLabels = array_keys($_typeAgg);
$_tData   = array_values(array_map(fn($v) => round($v['sum'], 2), $_typeAgg));
$_tBg     = array_values(array_map(fn($v) => $_ctBg[$v['color']] ?? 'rgba(108,117,125,0.7)', $_typeAgg));
$_tBorder = array_values(array_map(fn($v) => $_ctBorder[$v['color']] ?? 'rgba(108,117,125,1)', $_typeAgg));

// Build timeline labels + monthly + cumulative arrays
$_timeLabels  = [];
$_timeMonthly = [];
$_timeCumul   = [];
$_cumul = 0;
ksort($_periodAgg);
foreach ($_periodAgg as $_pk => $_amt) {
    if ($year == -2) {
        $_timeLabels[] = $_pk; // year string
    } else {
        $m = (int)substr($_pk, 5);
        $_timeLabels[] = $_frMonths[$m];
    }
    $_cumul += $_amt;
    $_timeMonthly[] = round($_amt, 2);
    $_timeCumul[]   = round($_cumul, 2);
}
$_showTimeline = count($_periodAgg) >= 2;
?>
<?php if (count($_typeAgg) > 0): ?>
<div class="row mt-4 g-4 align-items-start">

  <!-- Donut: répartition par type -->
  <div class="col-md-5">
    <p class="text-muted small fw-semibold mb-2 text-center"><?= $GLOBAL['distByType'] ?></p>
    <div style="position:relative;height:300px">
      <canvas id="myChart"></canvas>
    </div>
  </div>

  <!-- Timeline -->
  <?php if ($_showTimeline): ?>
  <div class="col-md-7">
    <p class="text-muted small fw-semibold mb-2 text-center">
      <?= $year == -2 ? $GLOBAL['historyByYear'] : $GLOBAL['monthlyVsCumulative'] ?>
    </p>
    <div style="position:relative;height:260px">
      <canvas id="timelineChart"></canvas>
    </div>
  </div>
  <?php endif ?>

</div>
<script>
(function() {
    function destroyChart(id) {
        if (window.Chart && Chart.instances) {
            Object.keys(Chart.instances).forEach(function(k) {
                var c = Chart.instances[k];
                if (c && c.canvas && c.canvas.id === id) { c.destroy(); }
            });
        }
    }
    var fmtChf = function(v) { return v.toLocaleString('fr-CH') + ' CHF'; };
    requestAnimationFrame(function() { requestAnimationFrame(function() {

    destroyChart('myChart');
    new Chart(document.getElementById('myChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($_tLabels) ?>,
            datasets: [{
                data: <?= json_encode($_tData) ?>,
                backgroundColor: <?= json_encode($_tBg) ?>,
                borderColor: <?= json_encode($_tBorder) ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'right', labels: { fontSize: 11, boxWidth: 12 } },
            tooltips: {
                callbacks: {
                    label: function(item, data) {
                        return data.labels[item.index] + ': ' + fmtChf(data.datasets[0].data[item.index]);
                    }
                }
            }
        }
    });

    <?php if ($_showTimeline): ?>
    destroyChart('timelineChart');
    new Chart(document.getElementById('timelineChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($_timeLabels) ?>,
            datasets: [
                {
                    label: '<?= $year == -2 ? $GLOBAL['annualAmount'] : $GLOBAL['monthlyAmount'] ?>',
                    type: 'bar',
                    data: <?= json_encode($_timeMonthly) ?>,
                    backgroundColor: 'rgba(13,110,253,0.45)',
                    borderColor: 'rgba(13,110,253,0.9)',
                    borderWidth: 1,
                    yAxisID: 'y-bar'
                },
                {
                    label: '<?= $GLOBAL['cumulative'] ?>',
                    type: 'line',
                    data: <?= json_encode($_timeCumul) ?>,
                    borderColor: 'rgba(25,135,84,0.9)',
                    backgroundColor: 'rgba(25,135,84,0.08)',
                    fill: true,
                    pointRadius: 3,
                    borderWidth: 2,
                    yAxisID: 'y-line'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontSize: 11, boxWidth: 12 } },
            tooltips: {
                mode: 'index',
                callbacks: {
                    label: function(item, data) {
                        return data.datasets[item.datasetIndex].label + ': ' + fmtChf(item.yLabel);
                    }
                }
            },
            scales: {
                yAxes: [
                    {
                        id: 'y-bar',
                        position: 'left',
                        ticks: { callback: fmtChf, fontSize: 10 },
                        gridLines: { drawOnChartArea: true }
                    },
                    {
                        id: 'y-line',
                        position: 'right',
                        ticks: { callback: fmtChf, fontSize: 10 },
                        gridLines: { drawOnChartArea: false }
                    }
                ],
                xAxes: [{ ticks: { fontSize: 11 } }]
            }
        }
    });
    <?php endif ?>

    }); }); // end double-rAF
})();
</script>
<?php endif ?>


