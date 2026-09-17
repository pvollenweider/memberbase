<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Budgets — per compta_type, per-year target amounts (year-1/year/year+1),
 * editable in place, alongside the amount actually collected so far for the
 * two already-started years. Feeds the dashboard's "Contributions" KPI
 * budget-gap line.
 *
 * Split into two tables (donation-eligible vs excluded-from-donation types,
 * e.g. cotisations) since the dashboard's budget-gap comparisons are scoped
 * the same way (Contributions KPI excludes the latter, Membres KPI IS the
 * latter) — keeping the totals visually separate avoids a misleading
 * combined figure that matches neither KPI.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isManager()) { ?>
  <div class="alert alert-danger" role="alert">
    <i class="fas fa-lock me-2" aria-hidden="true"></i><?= $GLOBAL['adminOnly'] ?>
  </div>
<?php return; }

$_bgYear         = (int)date('Y');
$_bgPastYear     = $_bgYear - 1;
$_bgEditableYears = [$_bgYear, $_bgYear + 1];

$_bgAllTypes = db()->query(
    "SELECT id, label, is_excluded_from_donation FROM compta_type WHERE is_archived = 0 ORDER BY sort_order, label"
)->fetchAll(PDO::FETCH_OBJ);
$_bgDonationTypes = array_values(array_filter($_bgAllTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 0));
$_bgExcludedTypes = array_values(array_filter($_bgAllTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 1));

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
foreach ($_bgAllTypes as $_bgCt) {
    $_bgCollStmt->execute([$_bgCt->id, $_bgYear - 1, $_bgYear]);
    foreach ($_bgCollStmt->fetchAll(PDO::FETCH_OBJ) as $_bgCr) {
        $_bgCollected[(int)$_bgCt->id][(int)$_bgCr->y] = (float)$_bgCr->total;
    }
}

/** Renders a "collecté" cell, colored red/green against its budget (dashboard-style). */
function mbBudgetCollectedCell(float $collected, float $budget): string
{
    $chf = 'CHF ' . number_format($collected, 0, '.', "'");
    if ($budget <= 0) {
        return $chf;
    }
    $pct   = round($collected / $budget * 100);
    $gap   = $collected - $budget;
    $color = $pct >= 100 ? 'text-success' : 'text-danger';
    $icon  = $pct >= 100 ? 'fa-arrow-up' : 'fa-arrow-down';
    $gapChf = ($gap >= 0 ? '+' : '-') . number_format(abs($gap), 0, '.', "'") . ' CHF';
    return $chf
        . '<div class="' . $color . '" style="font-size:0.68rem;font-weight:600;white-space:nowrap">'
        . '<i class="fas ' . $icon . ' me-1" aria-hidden="true"></i>' . $gapChf . ' (' . $pct . '%)'
        . '</div>';
}

/**
 * Renders one budgets table for a subset of compta types.
 *
 * The past year ($pastYear) is display-only — no input, its budget can no
 * longer be edited — while $editableYears (current + next) get an editable
 * budget input each.
 */
function mbRenderBudgetTable(string $tableId, array $types, int $pastYear, array $editableYears, array $budgets, array $collected, array $GLOBAL, string $charset): void
{
    $_currentYear = $editableYears[0];
    ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover align-middle budgets-table" id="<?= htmlspecialchars($tableId, ENT_QUOTES, $charset) ?>">
      <thead class="table-light">
        <tr>
          <th><?= $GLOBAL['labelField'] ?></th>
          <th class="text-end text-muted" style="width:170px"><?= sprintf($GLOBAL['budgetColCollected'], $pastYear) ?></th>
          <th class="text-end" style="width:130px"><?= sprintf($GLOBAL['budgetColYear'], $editableYears[0]) ?></th>
          <th class="text-end text-muted" style="width:170px"><?= sprintf($GLOBAL['budgetColCollected'], $editableYears[0]) ?></th>
          <th class="text-end" style="width:130px"><?= sprintf($GLOBAL['budgetColYear'], $editableYears[1]) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($types)): ?>
        <tr><td colspan="5" class="text-muted"><?= $GLOBAL['budgetsNoTypes'] ?></td></tr>
      <?php endif ?>
      <?php foreach ($types as $_ct): ?>
        <tr>
          <td><?= htmlspecialchars($_ct->label, ENT_QUOTES, $charset) ?></td>
          <td class="text-end" style="font-size:0.85rem">
            <?= mbBudgetCollectedCell($collected[$_ct->id][$pastYear] ?? 0, $budgets[$_ct->id][$pastYear] ?? 0) ?>
          </td>
          <?php foreach ($editableYears as $_y): ?>
          <td class="text-end">
            <input type="number" step="0.01" min="0" class="form-control form-control-sm budget-input text-end"
                   style="max-width:120px;margin-left:auto"
                   data-compta-type-id="<?= (int)$_ct->id ?>" data-year="<?= $_y ?>"
                   value="<?= isset($budgets[$_ct->id][$_y]) ? number_format($budgets[$_ct->id][$_y], 2, '.', '') : '' ?>"
                   aria-label="<?= htmlspecialchars(sprintf($GLOBAL['budgetInputLabel'], $_ct->label, $_y), ENT_QUOTES, $charset) ?>">
          </td>
          <?php if ($_y === $_currentYear):
              $_rowCollected = $collected[$_ct->id][$_y] ?? 0;
          ?>
          <td class="text-end collected-cell" style="font-size:0.85rem"
              data-compta-type-id="<?= (int)$_ct->id ?>" data-year="<?= $_y ?>" data-collected="<?= $_rowCollected ?>">
            <?= mbBudgetCollectedCell($_rowCollected, $budgets[$_ct->id][$_y] ?? 0) ?>
          </td>
          <?php endif ?>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
      </tbody>
      <tfoot class="table-light fw-semibold">
        <tr>
          <td><?= $GLOBAL['total'] ?></td>
          <?php
              $_pastTotal = 0.0; $_pastCollected = 0.0;
              foreach ($types as $_ct) {
                  $_pastTotal     += $budgets[$_ct->id][$pastYear] ?? 0;
                  $_pastCollected += $collected[$_ct->id][$pastYear] ?? 0;
              }
          ?>
          <td class="text-end" style="font-size:0.85rem"><?= mbBudgetCollectedCell($_pastCollected, $_pastTotal) ?></td>
          <?php foreach ($editableYears as $_y):
              $_colTotal = 0.0;
              foreach ($types as $_ct) { $_colTotal += $budgets[$_ct->id][$_y] ?? 0; }
          ?>
          <td class="text-end" data-total-year="<?= $_y ?>">CHF <?= number_format($_colTotal, 0, '.', "'") ?></td>
          <?php if ($_y === $_currentYear):
              $_colCollected = 0.0;
              foreach ($types as $_ct) { $_colCollected += $collected[$_ct->id][$_y] ?? 0; }
          ?>
          <td class="text-end collected-total-cell" style="font-size:0.85rem" data-year="<?= $_y ?>" data-collected="<?= $_colCollected ?>">
            <?= mbBudgetCollectedCell($_colCollected, $_colTotal) ?>
          </td>
          <?php endif ?>
          <?php endforeach ?>
        </tr>
      </tfoot>
    </table>
    </div>
    <?php
}

$_noOuterContainer = true;
$_phIcon  = 'fa-bullseye';
$_phTitle = $GLOBAL['budgetsPageTitle'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['budgetsDonationTitle'] ?></h2></div>
<div class="card-body">
<p class="text-muted small"><?= $GLOBAL['budgetsDonationHelp'] ?></p>
<?php mbRenderBudgetTable('budgets-table-donation', $_bgDonationTypes, $_bgPastYear, $_bgEditableYears, $_bgBudgets, $_bgCollected, $GLOBAL, $charset); ?>
</div>
</div>

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['budgetsExcludedTitle'] ?></h2></div>
<div class="card-body">
<p class="text-muted small"><?= $GLOBAL['budgetsExcludedHelp'] ?></p>
<?php mbRenderBudgetTable('budgets-table-excluded', $_bgExcludedTypes, $_bgPastYear, $_bgEditableYears, $_bgBudgets, $_bgCollected, $GLOBAL, $charset); ?>
</div>
</div>

<div id="budgets-status" class="small text-success mb-3" style="min-height:1.2em"></div>

</div>

<script>
(function () {
  var tables = document.querySelectorAll('.budgets-table');
  if (!tables.length) return;
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

  function badgeHtml(collected, budget) {
    var chf = 'CHF ' + Math.round(collected).toLocaleString('fr-CH');
    if (budget <= 0) { return chf; }
    var pct    = Math.round(collected / budget * 100);
    var gap    = collected - budget;
    var color  = pct >= 100 ? 'text-success' : 'text-danger';
    var icon   = pct >= 100 ? 'fa-arrow-up' : 'fa-arrow-down';
    var gapChf = (gap >= 0 ? '+' : '-') + Math.round(Math.abs(gap)).toLocaleString('fr-CH') + ' CHF';
    return chf
      + '<div class="' + color + '" style="font-size:0.68rem;font-weight:600;white-space:nowrap">'
      + '<i class="fas ' + icon + ' me-1" aria-hidden="true"></i>' + gapChf + ' (' + pct + '%)'
      + '</div>';
  }

  function recomputeColumnTotal(table, year, comptaTypeId) {
    var sum = 0;
    table.querySelectorAll('.budget-input[data-year="' + year + '"]').forEach(function (input) {
      sum += parseFloat(input.value) || 0;
    });
    var totalCell = table.querySelector('[data-total-year="' + year + '"]');
    if (totalCell) { totalCell.textContent = 'CHF ' + Math.round(sum).toLocaleString('fr-CH'); }

    // Row's own collected/budget badge.
    var rowInput = table.querySelector('.budget-input[data-year="' + year + '"][data-compta-type-id="' + comptaTypeId + '"]');
    var rowCell  = table.querySelector('.collected-cell[data-year="' + year + '"][data-compta-type-id="' + comptaTypeId + '"]');
    if (rowInput && rowCell) {
      rowCell.innerHTML = badgeHtml(parseFloat(rowCell.dataset.collected) || 0, parseFloat(rowInput.value) || 0);
    }

    // Column total's collected/budget badge.
    var totalCollectedCell = table.querySelector('.collected-total-cell[data-year="' + year + '"]');
    if (totalCollectedCell) {
      totalCollectedCell.innerHTML = badgeHtml(parseFloat(totalCollectedCell.dataset.collected) || 0, sum);
    }
  }

  tables.forEach(function (table) {
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
          recomputeColumnTotal(table, input.dataset.year, input.dataset.comptaTypeId);
        })
        .catch(function () { showStatus(errMsg, true); });
    });
  });
})();
</script>
