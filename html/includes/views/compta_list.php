<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Generic accounting entries view with year and type filters.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = -2; // default: all years
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
$donsOnly = !empty($_REQUEST['dons_only']);
$filterTypeId = isset($_REQUEST['type_id']) ? (int)$_REQUEST['type_id'] : 0;
$from = mktime(0, 0, 0, 1, 0, $year);
$to = mktime(0, 0, 0, 1, 1, $year + 1);
?>
<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">

  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?=$year == -2 ? $GLOBAL['allYear'] : $year?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $year == -2 ? ' active' : '' ?>"
             href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=-2"><?=$GLOBAL['allYear']?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= $year == $y ? ' active' : '' ?>"
               href="<?=$_SERVER['PHP_SELF']?>?view=compta&amp;userid=<?=$user->getId()?>&amp;year=<?=$y?>"><?=$y?></a></li><?php
      }
      ?>
    </ul>
  </div>

  <div class="form-check form-switch mb-0 ms-2" title="<?= $GLOBAL['hideNonDonationEntries'] ?>">
    <input class="form-check-input" type="checkbox" role="switch" id="dons-only-toggle"
           <?= $donsOnly ? 'checked' : '' ?>
           data-no-dirty
           onchange="window.__dirtyOverride=true;window.location='<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= $user->getId() ?>&amp;year=<?= $year ?>'+(this.checked?'&amp;dons_only=1':'')">
    <label class="form-check-label small" for="dons-only-toggle"><?= $GLOBAL['donationsOnly'] ?></label>
  </div>

  <?php if ($year != -2): ?>
  <div class="dropdown ms-auto">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-file-pdf me-1" aria-hidden="true"></i><?= $GLOBAL['attestation'] ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <?php for ($i = 0; $i < 10; $i++): $y = $currentYear - $i; ?>
      <li><a class="dropdown-item<?= $year == $y ? ' fw-semibold' : '' ?>"
             href="/attestation_don.php?userid=<?=$user->getId()?>&amp;year=<?=$y?>"
             target="_blank">
          <?= $y ?><?= $year == $y ? ' ' . $GLOBAL['displayedYear'] : '' ?>
      </a></li>
      <?php endfor ?>
    </ul>
  </div>
  <?php endif ?>
  <?php if ($filterTypeId > 0 && isset($comptaTypes[$filterTypeId])): ?>
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= $user->getId() ?>&amp;year=<?= $year ?>"
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


<form action="<?=$_SERVER['PHP_SELF']?>" method="post" name="addCompta">
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
             inputmode="decimal" pattern="^[0-9]+([.,][0-9]+)?$" title="<?= $GLOBAL['numericAmountHint'] ?>"/></td>
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
defined('APP_ENTRY') or die('Direct access not permitted.');
$_showZero = isset($_REQUEST['showZero']);
$_baseWhere = "FROM compta c LEFT JOIN compta_type ct ON ct.id = c.type_id WHERE c.user_id=" . $user->getId() . " ";
if ($year != -2) {
    $_baseWhere .= " AND c.date > $from AND c.date < $to ";
}
if ($donsOnly) {
    $_baseWhere .= " AND COALESCE(ct.is_excluded_from_donation,0) = 0 ";
}
if ($filterTypeId > 0) {
    $_baseWhere .= " AND c.type_id = " . $filterTypeId . " ";
}
// Count zero-sum entries so we can offer a "show all" toggle
$_zeroCount = (int)$pdo->query("SELECT COUNT(*) " . $_baseWhere . " AND c.sum = 0")->fetchColumn();
$_selectCols = "SELECT c.id, c.user_id, c.type_id, c.date, c.libele, c.sum, c.quittance, c.wants_attestation, c.cotisation_year, ct.label AS ct_label, ct.color AS ct_color, COALESCE(ct.is_excluded_from_donation,0) AS ct_excl, COALESCE(ct.is_cotisation,0) AS ct_coti ";
$query  = $_selectCols . $_baseWhere;
$query2 = $_selectCols . $_baseWhere;
if (!$_showZero) {
    $query  .= " AND c.sum <> 0 ";
    $query2 .= " AND c.sum <> 0 ";
}
$query  .= " ORDER BY c.date DESC";
$query2 .= " ORDER BY c.date ASC";
$stmt = $pdo->query($query);
$total = 0;
while ($row = $stmt->fetchObject()) {
    $id = $row->id;
    $date = $row->date;
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
     <tr <?= canWrite() ? 'class="ca-row-link" data-href="' . $_SERVER['PHP_SELF'] . '?view=updateCompta&comptaid=' . (int)$id . '&userid=' . (int)$user->getId() . '" style="cursor:pointer;' . htmlentities($rowStyle, ENT_COMPAT, $charset) . '"' : 'style="' . htmlentities($rowStyle, ENT_COMPAT, $charset) . '"' ?>>
        <td>
            <?= htmlentities($row->ct_label ?? '', ENT_COMPAT, $charset) ?>
            <?php if ($row->ct_coti && $row->cotisation_year): ?>
            <?php $_payYear = $row->date ? (int)date('Y', (int)$row->date) : 0; ?>
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
            <a href="<?=$_SERVER['PHP_SELF']?>?view=updateCompta&comptaid=<?=$id?>&userid=<?=$user->getId()?>"
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
      $GLOBAL['__toggleZeroUrl'] = $_SERVER['PHP_SELF'] . '?' . http_build_query($_qp);
      $GLOBAL['__toggleZeroLabel'] = sprintf($GLOBAL['hideZeroEntries'], $_zeroCount);
  } else {
      $_qp['showZero'] = '1';
      $GLOBAL['__toggleZeroUrl'] = $_SERVER['PHP_SELF'] . '?' . http_build_query($_qp);
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
    function toggleCotiYear() {
        if (!typeSelect || !cotiYearField) return;
        var isCoti = cotiIds.indexOf(parseInt(typeSelect.value, 10)) !== -1;
        cotiYearField.style.display = isCoti ? '' : 'none';
        cotiYearField.name = isCoti ? 'cotisation_year' : '';
    }
    if (typeSelect) {
        typeSelect.addEventListener('change', toggleCotiYear);
        toggleCotiYear();
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
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="modal-send-recap-user-label"><?= $GLOBAL['comptaRecapModalTitle'] ?></h5>
          <div class="text-muted small"><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, $charset) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
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
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="recap-force-toggle" value="1">
            <label class="form-check-label small" for="recap-force-toggle">
              <?= $GLOBAL['comptaRecapForceAll'] ?>
            </label>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="btn-recap-user-preview">
            <i class="fas fa-eye me-1" aria-hidden="true"></i><?= $GLOBAL['preview'] ?? 'Prévisualiser' ?>
          </button>
        </div>
        <!-- Preview area -->
        <div id="recap-user-loading" class="d-flex align-items-center justify-content-center py-5" style="display:none!important"></div>
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
  var baseUrl    = <?= json_encode($_SERVER['PHP_SELF']) ?>;
  var userId     = <?= (int)$user->getId() ?>;
  var recapYear  = <?= (int)date('Y') ?>;

  // Year picker
  document.querySelectorAll('.recap-user-year-item').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      recapYear = parseInt(this.dataset.year, 10);
      document.getElementById('recap-user-year-btn').textContent = this.dataset.year;
      document.querySelectorAll('.recap-user-year-item').forEach(function (el) { el.classList.remove('active'); });
      this.classList.add('active');
      // Reset preview
      document.getElementById('recap-user-frame').style.display  = 'none';
      document.getElementById('recap-user-subject').style.display = 'none';
      document.getElementById('btn-recap-user-send').disabled     = true;
    });
  });

  function showRecapUserLoading(on) {
    var el = document.getElementById('recap-user-loading');
    el.style.display = on ? 'flex' : 'none';
    if (on) el.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>';
  }

  document.getElementById('btn-recap-user-preview').addEventListener('click', function () {
    var force = document.getElementById('recap-force-toggle').checked ? '1' : '';
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
          var forceToggle = document.getElementById('recap-force-toggle');
          if (!forceToggle.checked) {
            // No pending entries — auto-enable force and retry to show already-sent
            forceToggle.checked = true;
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
    var force = document.getElementById('recap-force-toggle').checked ? '1' : '';
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
defined('APP_ENTRY') or die('Direct access not permitted.');
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
$_stmt2 = $pdo->query($query2);
while ($_r = $_stmt2->fetchObject()) {
    // Donut: aggregate by type
    $_lbl = $_r->ct_label ?? $GLOBAL['withoutType'];
    $_col = $_r->ct_color ?? 'bg-secondary-subtle';
    if (!isset($_typeAgg[$_lbl])) $_typeAgg[$_lbl] = ['sum' => 0.0, 'color' => $_col];
    $_typeAgg[$_lbl]['sum'] += (float)$_r->sum;
    // Timeline: aggregate by month (specific year) or by year (all years)
    $_ts = (int)$_r->date;
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


