<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Budgets — per compta_type, per-year target amounts (year-1/year/year+1),
 * editable in place, alongside the amount actually collected so far for the
 * two already-started years. Feeds the dashboard's "Contributions" KPI
 * budget-gap line.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isManager()) { ?>
  <div class="alert alert-danger" role="alert">
    <i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?>
  </div>
<?php return; }

$_bgYear  = (int)date('Y');
$_bgYears = [$_bgYear - 1, $_bgYear, $_bgYear + 1];

$_bgTypes = db()->query(
    "SELECT id, label, is_excluded_from_donation FROM compta_type WHERE is_archived = 0 ORDER BY sort_order, label"
)->fetchAll(PDO::FETCH_OBJ);

$_bgBudgets = []; // [compta_type_id][year] => amount
$_bgStmt = db()->query("SELECT compta_type_id, year, amount FROM compta_budget");
foreach ($_bgStmt->fetchAll(PDO::FETCH_OBJ) as $_bgRow) {
    $_bgBudgets[(int)$_bgRow->compta_type_id][(int)$_bgRow->year] = (float)$_bgRow->amount;
}

// Collected-to-date per type, for the two already-started years (year-1 and
// year) — not year+1, always zero/meaningless for a year that hasn't started.
$_bgCollected = []; // [compta_type_id][year] => sum
$_bgCollStmt = db()->prepare(
    "SELECT type_id, YEAR(date) AS y, COALESCE(SUM(sum),0) AS total
     FROM compta WHERE type_id = ? AND YEAR(date) IN (?, ?)
     GROUP BY type_id, YEAR(date)"
);
foreach ($_bgTypes as $_bgCt) {
    $_bgCollStmt->execute([$_bgCt->id, $_bgYear - 1, $_bgYear]);
    foreach ($_bgCollStmt->fetchAll(PDO::FETCH_OBJ) as $_bgCr) {
        $_bgCollected[(int)$_bgCt->id][(int)$_bgCr->y] = (float)$_bgCr->total;
    }
}

$_noOuterContainer = true;
$_phIcon  = 'fa-bullseye';
$_phTitle = $GLOBAL['budgetsPageTitle'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['budgetsPageTitle'] ?></h2></div>
<div class="card-body">
<p class="text-muted small"><?= $GLOBAL['budgetsHelp'] ?></p>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle" id="budgets-table">
  <thead class="table-light">
    <tr>
      <th><?= $GLOBAL['labelField'] ?></th>
      <th class="text-end" style="width:130px"><?= sprintf($GLOBAL['budgetColYear'], $_bgYears[0]) ?></th>
      <th class="text-end text-muted" style="width:130px"><?= sprintf($GLOBAL['budgetColCollected'], $_bgYears[0]) ?></th>
      <th class="text-end" style="width:130px"><?= sprintf($GLOBAL['budgetColYear'], $_bgYears[1]) ?></th>
      <th class="text-end text-muted" style="width:130px"><?= sprintf($GLOBAL['budgetColCollected'], $_bgYears[1]) ?></th>
      <th class="text-end" style="width:130px"><?= sprintf($GLOBAL['budgetColYear'], $_bgYears[2]) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($_bgTypes as $_bgCt): ?>
    <tr>
      <td>
        <?= htmlspecialchars($_bgCt->label, ENT_QUOTES, $charset) ?>
        <?php if ((int)$_bgCt->is_excluded_from_donation === 1): ?>
        <span class="text-muted small">(<?= $GLOBAL['excludedFromDonationShort'] ?>)</span>
        <?php endif ?>
      </td>
      <?php foreach ($_bgYears as $_bgY): ?>
      <td class="text-end">
        <input type="number" step="0.01" min="0" class="form-control form-control-sm budget-input text-end"
               style="max-width:120px;margin-left:auto"
               data-compta-type-id="<?= (int)$_bgCt->id ?>" data-year="<?= $_bgY ?>"
               value="<?= isset($_bgBudgets[$_bgCt->id][$_bgY]) ? number_format($_bgBudgets[$_bgCt->id][$_bgY], 2, '.', '') : '' ?>"
               aria-label="<?= htmlspecialchars(sprintf($GLOBAL['budgetInputLabel'], $_bgCt->label, $_bgY), ENT_QUOTES, $charset) ?>">
      </td>
      <?php if ($_bgY !== $_bgYears[2]): ?>
      <td class="text-end text-muted" style="font-size:0.85rem">
        CHF <?= number_format($_bgCollected[$_bgCt->id][$_bgY] ?? 0, 0, '.', "'") ?>
      </td>
      <?php endif ?>
      <?php endforeach ?>
    </tr>
  <?php endforeach ?>
  </tbody>
  <tfoot class="table-light fw-semibold">
    <tr>
      <td><?= $GLOBAL['total'] ?></td>
      <?php foreach ($_bgYears as $_bgY):
          $_bgColTotal = 0.0;
          foreach ($_bgTypes as $_bgCt) { $_bgColTotal += $_bgBudgets[$_bgCt->id][$_bgY] ?? 0; }
      ?>
      <td class="text-end" data-total-year="<?= $_bgY ?>">CHF <?= number_format($_bgColTotal, 0, '.', "'") ?></td>
      <?php if ($_bgY !== $_bgYears[2]):
          $_bgColCollected = 0.0;
          foreach ($_bgTypes as $_bgCt) { $_bgColCollected += $_bgCollected[$_bgCt->id][$_bgY] ?? 0; }
      ?>
      <td class="text-end text-muted" style="font-size:0.85rem">CHF <?= number_format($_bgColCollected, 0, '.', "'") ?></td>
      <?php endif ?>
      <?php endforeach ?>
    </tr>
  </tfoot>
</table>
</div>
<div id="budgets-status" class="small text-success mt-1" style="min-height:1.2em"></div>
</div>
</div>

</div>

<script>
(function () {
  var table  = document.getElementById('budgets-table');
  if (!table) return;
  var status  = document.getElementById('budgets-status');
  var baseUrl = <?= json_encode(appUrl()) ?>;
  var savedMsg = <?= json_encode($GLOBAL['budgetSavedMsg']) ?>;
  var errMsg   = <?= json_encode($GLOBAL['loadError']) ?>;
  var statusTimer = null;

  function showStatus(text, isError) {
    clearTimeout(statusTimer);
    status.textContent = text;
    status.classList.toggle('text-danger', !!isError);
    status.classList.toggle('text-success', !isError);
    statusTimer = setTimeout(function () { status.textContent = ''; }, 2500);
  }

  function recomputeColumnTotal(year) {
    var sum = 0;
    table.querySelectorAll('.budget-input[data-year="' + year + '"]').forEach(function (input) {
      sum += parseFloat(input.value) || 0;
    });
    var cell = table.querySelector('[data-total-year="' + year + '"]');
    if (cell) { cell.textContent = 'CHF ' + Math.round(sum).toLocaleString('fr-CH'); }
  }

  table.addEventListener('change', function (e) {
    if (!e.target.classList.contains('budget-input')) return;
    var input = e.target;
    var amount = parseFloat(input.value);
    if (isNaN(amount) || amount < 0) { amount = 0; input.value = ''; }
    var body = new URLSearchParams();
    body.append('action', 'updateComptaBudget');
    body.append('compta_type_id', input.dataset.comptaTypeId);
    body.append('year', input.dataset.year);
    body.append('amount', String(amount));
    fetch(baseUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true', 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        showStatus(data.ok ? savedMsg : errMsg, !data.ok);
        recomputeColumnTotal(input.dataset.year);
      })
      .catch(function () { showStatus(errMsg, true); });
  });
})();
</script>
