<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Reusable donor/member table component (DataTables-powered).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
/*
 * Composant table donateur/membre partagé.
 *
 * Variables attendues depuis le fichier appelant :
 *   $rows          — array de stdObject avec au moins : id, society, lastname, firstname, email, address, npa
 *   $dt_order      — ordre initial DataTables, ex. [[6, 'desc']]
 *   $extra_columns — array de définitions de colonnes supplémentaires :
 *                    ['label' => string, 'value' => callable($row):string, 'style' => string, 'footer' => float|null]
 *                    'footer' => null = cellule vide dans le pied ; absent = pas de pied du tout
 *   $row_href      — callable($row):string — URL cible au clic sur la ligne
 */
$_has_footer = false;
foreach ($extra_columns as $_col) {
    if (array_key_exists('footer', $_col)) { $_has_footer = true; break; }
}
// Multiple includes of this partial can coexist in one page (e.g. the
// lapsed/new cohort pills in the peopleFinance hub render both tables
// server-side, only one visible at a time) — a unique id keeps each
// instance's DataTable init from colliding with the others' ".export"-style
// blanket selector.
$GLOBALS['_caCohortTableSeq'] = ($GLOBALS['_caCohortTableSeq'] ?? 0) + 1;
$_dtId = 'ca-cohort-table-' . $GLOBALS['_caCohortTableSeq'];
?>
<table class="table table-hover table-sm ca-cohort-table" id="<?= $_dtId ?>">
<thead>
<tr>
  <th><?= $GLOBAL['society'] ?></th>
  <th><?= $GLOBAL['lastName'] ?></th>
  <th><?= $GLOBAL['firstName'] ?></th>
  <th><?= $GLOBAL['email'] ?></th>
  <th><?= $GLOBAL['address'] ?></th>
  <th><?= $GLOBAL['npa'] ?></th>
  <?php foreach ($extra_columns as $_col): ?>
  <th><?= htmlspecialchars($_col['label'], ENT_COMPAT, $charset) ?></th>
  <?php endforeach ?>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
  $_society   = htmlentities($row->society   ?? '', ENT_COMPAT, $charset);
  $_lastName  = htmlentities($row->lastname  ?? '', ENT_COMPAT, $charset);
  $_firstName = htmlentities($row->firstname ?? '', ENT_COMPAT, $charset);
  $_email     = htmlentities($row->email     ?? '', ENT_COMPAT, $charset);
  $_address   = htmlentities($row->address   ?? '', ENT_COMPAT, $charset);
  $_npa       = htmlentities($row->npa       ?? '', ENT_COMPAT, $charset);
  $_href      = htmlspecialchars(($row_href)($row), ENT_QUOTES, $charset);
  $_aria      = htmlspecialchars(($row->firstname ?? '') . ' ' . ($row->lastname ?? ''), ENT_QUOTES, $charset);
  $_ctIcon    = trim((string)($row->ct_icon ?? ''));
  $_ctLabel   = trim((string)($row->ct_label ?? ''));
  $_ctBadge   = $_ctIcon !== ''
      ? '<i class="fas fa-' . htmlspecialchars($_ctIcon, ENT_QUOTES, $charset) . ' me-1 text-muted"'
        . ' aria-hidden="true" title="' . htmlspecialchars($_ctLabel, ENT_QUOTES, $charset) . '"></i>'
      : '';
?>
<tr style="cursor:pointer"
    data-href="<?= $_href ?>"
    aria-label="<?= sprintf($GLOBAL['viewAllEntriesOf'], $_aria) ?>">
  <td><?= $_society ?></td>
  <td><?= $_ctBadge ?><strong><?= $_lastName ?></strong></td>
  <td><?= $_firstName ?></td>
  <td><?= $_email ?></td>
  <td><?= $_address ?></td>
  <td><?= $_npa ?></td>
  <?php foreach ($extra_columns as $_col): ?>
  <td<?= $_col['style'] ? ' style="' . htmlspecialchars($_col['style'], ENT_COMPAT, $charset) . '"' : '' ?>><?= ($_col['value'])($row) ?></td>
  <?php endforeach ?>
</tr>
<?php endforeach ?>
</tbody>
<?php if ($_has_footer): ?>
<tfoot>
<tr>
  <td colspan="6" class="text-end text-muted" style="font-size:0.8rem"><?= $GLOBAL['total'] ?></td>
  <?php foreach ($extra_columns as $_col): ?>
  <?php if ($_col['footer'] !== null): ?>
  <td style="text-align:right"><strong><?= number_format((float)$_col['footer'], 2, '.', '\'') ?></strong></td>
  <?php else: ?>
  <td></td>
  <?php endif ?>
  <?php endforeach ?>
</tr>
</tfoot>
<?php endif ?>
</table>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('#<?= $_dtId ?>').DataTable({
        order: <?= json_encode($dt_order) ?>,
        paging: false,
        dom: CA_DT_DOM,
        buttons: [...CA_DT_BUTTONS, CA_DT_COLVIS],
        columnDefs: [
            { targets: [4, 5], visible: false }
        ],
        language: CA_DT_LANGUAGE
    });
    $('#<?= $_dtId ?> tbody').on('click', 'tr[data-href]', function() {
        window.__dirtyOverride = true;
        window.location = $(this).data('href');
    });
});
</script>
