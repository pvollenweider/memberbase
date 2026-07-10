<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Accounting entries list with sorting, filtering, and attestation support.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$year = -1; // date("Y");
$type = "allTypes";
$sort = "c.date";
if (isset($_REQUEST['sort'])) {
    $sortInput = $_REQUEST['sort'];
    if ($sortInput == "name") {
        $sort = "u.lastname,u.firstname,u.society";
    } elseif ($sortInput == "date") {
        $sort = "c.date";
    }
}
$filterTypeId = 0;
if (isset($_REQUEST['type_id']) && (int)$_REQUEST['type_id'] > 0 && isset($comptaTypes[(int)$_REQUEST['type_id']])) {
    $filterTypeId = (int)$_REQUEST['type_id'];
}
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}
if (isset($_REQUEST['viewDetail'])) {
    $year = $_REQUEST['viewDetail'];
}

if ($year == -1) {
    $year = date("Y");
}
$year = (int)$year;
if ($year === -3) {
    $from = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 1);
    $to   = time();
} elseif ($year === -4) {
    $from = mktime(0, 0, 0, date('n'), date('j'), (int)date('Y') - 2);
    $to   = time();
} elseif ($year === -2) {
    $from = 0;
    $to   = PHP_INT_MAX;
} else {
    $from = mktime(0, 0, 0, 1, 0, $year);
    $to   = mktime(0, 0, 0, 1, 1, $year + 1);
}
$addMem = -1;
if (isset($_REQUEST['addMem'])) {
    $addMem = $_REQUEST['addMem'];
}
if ($addMem != -1) {
    ?><?= $GLOBAL['assignSegmentEntry'] ?> <?=$addMem?><?php
}
?>


<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $GLOBAL['lastEntryCompta'] ?></span>

  <?php
  function _lec_type_swatch(string $color, string $label, string $charset): string {
      $bg  = $color ?: 'bg-secondary-subtle';
      $txt = (str_contains($bg, '-subtle') || $bg === 'bg-light') ? '#212529' : '#fff';
      return '<span class="d-inline-flex align-items-center justify-content-center rounded border ' . htmlentities($bg, ENT_COMPAT, $charset) . '"'
           . ' style="width:28px;height:20px;font-size:0.55rem;font-weight:700;line-height:1;letter-spacing:0.02em;color:' . $txt . '"'
           . ' title="' . htmlentities($label, ENT_COMPAT, $charset) . '">'
           . htmlentities(mb_strtoupper(mb_substr($label, 0, 3)), ENT_COMPAT, $charset)
           . '</span> ' . htmlentities($label, ENT_COMPAT, $charset);
  }
  $activeTypeCt = $filterTypeId > 0 ? ($comptaTypes[$filterTypeId] ?? null) : null;
  ?>
  <div class="dropdown ms-2">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?php if ($activeTypeCt): ?>
        <?= _lec_type_swatch($activeTypeCt->color ?? '', $activeTypeCt->label, $charset) ?>
      <?php else: ?><?= $GLOBAL['allTypesFull'] ?><?php endif ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $filterTypeId === 0 ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lastEntryCompta&amp;year=<?= $year ?>"><?= $GLOBAL['allTypes'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php foreach ($comptaTypes as $ct): ?>
      <li><a class="dropdown-item<?= $filterTypeId === (int)$ct->id ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lastEntryCompta&amp;type_id=<?= (int)$ct->id ?>&amp;year=<?= $year ?>">
             <?= _lec_type_swatch($ct->color ?? '', $ct->label, $charset) ?>
      </a></li>
      <?php endforeach ?>
    </ul>
  </div>

  <?php
  $_yearLabel = match(true) {
      $year === -2 => $GLOBAL['allYear'],
      $year === -3 => $GLOBAL['last12Months'],
      $year === -4 => $GLOBAL['last24Months'],
      default      => $year,
  };
  ?>
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $_yearLabel ?>
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item<?= $year === -2 ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=-2"><?= $GLOBAL['allYear'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item<?= $year === -3 ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=-3"><?= $GLOBAL['last12Months'] ?></a></li>
      <li><a class="dropdown-item<?= $year === -4 ? ' active' : '' ?>"
             href="<?= appUrl() ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=-4"><?= $GLOBAL['last24Months'] ?></a></li>
      <li><hr class="dropdown-divider"></li>
      <?php
      $currentYear = date("Y");
      for ($i = 0; $i < 10; $i++) {
          $y = $currentYear - $i;
          ?><li><a class="dropdown-item<?= $year === $y ? ' active' : '' ?>"
               href="<?= appUrl() ?>?view=lastEntryCompta&amp;type_id=<?= $filterTypeId ?>&amp;year=<?= $y ?>"><?= $y ?></a></li><?php
      }
      ?>
    </ul>
  </div>
</div>

<style>
.text-bg-ca-orange { background-color: rgb(253,126,20) !important; color: #fff !important; }
.text-bg-ca-teal   { background-color: rgb(32,201,151) !important; color: #fff !important; }
.text-bg-ca-pink   { background-color: rgb(214,51,132) !important; color: #fff !important; }
.text-bg-ca-purple { background-color: rgb(111,66,193) !important; color: #fff !important; }
.text-bg-ca-indigo { background-color: rgb(102,16,242) !important; color: #fff !important; }
.text-bg-ca-lime   { background-color: rgb(128,189,64) !important; color: #000 !important; }
</style>
<div class="table-responsive">
<table class="table  table-hover table-sm export">
<thead>
<tr>
    <th><?=$GLOBAL['date']?></th>
    <th class="d-none d-xl-table-cell"><?=$GLOBAL['society']?></th>
    <th><?=$GLOBAL['lastName']?></th>
    <th><?=$GLOBAL['firstName']?></th>
    <th><?=$GLOBAL['address']?></th>
    <th><?=$GLOBAL['npa']?></th>
    <th><?=$GLOBAL['email']?></th>
    <th><?=$GLOBAL['type']?></th>
    <th><?=$GLOBAL['libele']?></th>
    <th><?=$GLOBAL['sum']?></th>
    <th><?=$GLOBAL['comment']?></th>
    <th><?=$GLOBAL['creationDate']?></th>
</tr>
</thead>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$query = "SELECT DISTINCT u.firstname, u.lastname, u.society, u.id, c.type_id, c.date, c.libele, c.id AS comptaid, c.sum, c.quittance, c.user_id, u.address, u.npa, u.email, u.tel, u.portable, ct.label AS ct_label, ct.color AS ct_color FROM contact u JOIN compta c ON u.id = c.user_id LEFT JOIN compta_type ct ON ct.id = c.type_id WHERE u.status=1";
if ($filterTypeId > 0) {
    $query .= " AND c.type_id = " . (int)$filterTypeId;
}
if ($year != -2) {
    $query .= " AND c.date > $from AND c.date < $to";
}
$query2 = $query;
$query .= " ORDER BY $sort DESC LIMIT 0,20000";
$query2 .= " ORDER BY $sort ASC LIMIT 0,20000";
$stmt = $pdo->query($query);
$i = 0;
$total = 0.0;
while ($row = $stmt->fetchObject()) {
    $i++;
    $date = timeStampToformatedDate($row->date);
    $firstName = $row->firstname;
    $lastName = $row->lastname;
    $society = $row->society;
    $libele = $row->libele;
    $sum = (float) $row->sum;
    $quittance = $row->quittance;
    $userId = $row->user_id;
    $user = new Contact();
    $user->lookupUser($userId);
    $email   = $row->email;
    $address = $row->address;
    $npa     = $row->npa;
    $comptaid = $row->comptaid;
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

    if ($addMem != -1) {
        $user->assignSegment($addMem);
    }
    ?>
    <?php
    $_ctBg  = $row->ct_color ?: 'bg-secondary-subtle';
    $_ctTxt = (str_contains($_ctBg, '-subtle') || $_ctBg === 'bg-light') ? '#212529' : '#fff';
    ?>
    <tr class="ca-row-link" data-href="<?=appUrl()?>?view=compta&userid=<?=(int)$userId?>" style="cursor:pointer;">
        <td><?= htmlentities($date, ENT_COMPAT, $charset) ?></td>
        <td class="d-none d-xl-table-cell"><div class="text-truncate" style="max-width:125px"><?= htmlentities($society, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($lastName, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($firstName, ENT_COMPAT, $charset) ?></div></td>
        <td><div class="text-truncate" style="max-width:125px"><?= htmlentities($address, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:100px"><?= htmlentities($npa, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><div class="text-truncate" style="max-width:150px"><?= htmlentities($email, ENT_COMPAT, $charset) ?></div></td>
        <td class="text-nowrap"><span class="d-inline-flex align-items-center justify-content-center rounded border <?= htmlentities($_ctBg, ENT_COMPAT, $charset) ?>"
            style="width:28px;height:20px;font-size:0.55rem;font-weight:700;line-height:1;letter-spacing:0.02em;color:<?= $_ctTxt ?>"
            title="<?= htmlentities($row->ct_label ?? '', ENT_COMPAT, $charset) ?>"><?= htmlentities(mb_strtoupper(mb_substr($row->ct_label ?? '', 0, 3)), ENT_COMPAT, $charset) ?></span></td>
        <td><?= htmlentities($libele, ENT_COMPAT, $charset) ?></td>
        <td style="text-align:right;"><?= number_format($sum, 2, '.', '\'') ?></td>
        <td><?= htmlentities($quittance, ENT_COMPAT, $charset) ?></td>
        <td><?= timeStampToformatedDate($user->getCreationDate()) ?></td>
    </tr>
    <?php
}

?>
<tfoot>
<tr>
    <td colspan="9" class="text-end text-muted" style="font-size:0.8rem"><?= $GLOBAL['total'] ?></td>
    <td class="text-end"><strong><?= number_format($total, 2, '.', '\'') ?></strong></td>
    <td colspan="2"></td>
</tr>
</tfoot>
</table>
</div>
<script>
    $.fn.dataTable.moment('DD/MM/YYYY');

    $(document).ready(function () {
        if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
        $('.export').DataTable({
            order: [[0, 'desc']],
            paging: false,
            columnDefs: [
                { targets: [4, 5, 6, 10, 11], visible: false }
            ],
            dom: CA_DT_DOM,
            buttons: [...CA_DT_BUTTONS, CA_DT_COLVIS],
            language: CA_DT_LANGUAGE
        });
        document.querySelector('.export tbody') && document.querySelector('.export tbody').addEventListener('click', function(e) {
            var tr = e.target.closest('tr.ca-row-link');
            if (!tr) return;
            if (e.target.closest('a, button')) return;
            window.location.href = tr.dataset.href;
        });
    });
</script>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$_frM = $GLOBAL['monthsShortCap'];
$_monthly  = [];
$_typeAggL = [];
$_ctBgL = [
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
$_ctBorderL = [
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
$_stmt2 = $pdo->query($query2);
while ($_r = $_stmt2->fetchObject()) {
    // Timeline
    $_ts  = (int)$_r->date;
    $_mk  = ($year == -2) ? date('Y', $_ts) : date('Y-m', $_ts);
    $_lbl = ($year == -2) ? date('Y', $_ts) :
            $_frM[(int)date('n', $_ts)] . ' ' . date('Y', $_ts);
    if (!isset($_monthly[$_mk])) $_monthly[$_mk] = ['label' => $_lbl, 'sum' => 0.0];
    $_monthly[$_mk]['sum'] += (float)$_r->sum;
    // Donut
    $_tl  = $_r->ct_label ?? $GLOBAL['withoutType'];
    $_tc  = $_r->ct_color ?? 'bg-secondary-subtle';
    if (!isset($_typeAggL[$_tl])) $_typeAggL[$_tl] = ['sum' => 0.0, 'color' => $_tc];
    $_typeAggL[$_tl]['sum'] += (float)$_r->sum;
}
ksort($_monthly);
$_labels       = array_values(array_column($_monthly, 'label'));
$_monthly_data = array_values(array_map(fn($v) => round($v['sum'], 2), $_monthly));
$_cumul = 0.0;
$_cumul_data   = array_values(array_map(function($v) use (&$_cumul) {
    $_cumul += $v['sum']; return round($_cumul, 2);
}, $_monthly));
uasort($_typeAggL, fn($a, $b) => $b['sum'] <=> $a['sum']);
$_tLabelsL  = array_keys($_typeAggL);
$_tDataL    = array_values(array_map(fn($v) => round($v['sum'], 2), $_typeAggL));
$_tBgL      = array_values(array_map(fn($v) => $_ctBgL[$v['color']] ?? 'rgba(108,117,125,0.7)', $_typeAggL));
$_tBorderL  = array_values(array_map(fn($v) => $_ctBorderL[$v['color']] ?? 'rgba(108,117,125,1)', $_typeAggL));
$_showTimeline = count($_monthly) >= 2;
?>
<div class="row mt-4 g-4 align-items-start">

  <?php if (count($_typeAggL) > 0): ?>
  <div class="col-md-4">
    <p class="text-muted small fw-semibold mb-2 text-center"><?= $GLOBAL['distByType'] ?></p>
    <div style="position:relative;height:300px">
      <canvas id="lecDonut"></canvas>
    </div>
  </div>
  <?php endif ?>

  <?php if ($_showTimeline): ?>
  <div class="col-md-<?= count($_typeAggL) > 0 ? '8' : '12' ?>">
    <p class="text-muted small fw-semibold mb-2 text-center">
      <?= $year == -2 ? $GLOBAL['historyByYear'] : $GLOBAL['monthlyVsCumulative'] ?>
    </p>
    <div style="position:relative;height:300px">
      <canvas id="lecTimeline"></canvas>
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

    <?php if (count($_typeAggL) > 0): ?>
    destroyChart('lecDonut');
    new Chart(document.getElementById('lecDonut'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($_tLabelsL) ?>,
            datasets: [{ data: <?= json_encode($_tDataL) ?>, backgroundColor: <?= json_encode($_tBgL) ?>, borderColor: <?= json_encode($_tBorderL) ?>, borderWidth: 1 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            legend: { position: 'right', labels: { fontSize: 11, boxWidth: 12 } },
            tooltips: { callbacks: { label: function(item, data) {
                var total = data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
                var val = data.datasets[0].data[item.index];
                var pct = total > 0 ? Math.round(val / total * 100) : 0;
                return data.labels[item.index] + ': ' + fmtChf(val) + ' (' + pct + '%)';
            }}}
        }
    });
    <?php endif ?>

    <?php if ($_showTimeline): ?>
    destroyChart('lecTimeline');
    new Chart(document.getElementById('lecTimeline'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($_labels) ?>,
            datasets: [
                { type: 'bar',  label: '<?= $year == -2 ? $GLOBAL['annual'] : $GLOBAL['monthly'] ?>', data: <?= json_encode($_monthly_data) ?>, backgroundColor: 'rgba(13,110,253,0.55)', borderColor: 'rgba(13,110,253,1)', borderWidth: 1, yAxisID: 'y1' },
                { type: 'line', label: '<?= $GLOBAL['cumulative'] ?>', data: <?= json_encode($_cumul_data) ?>, borderColor: 'rgba(25,135,84,0.9)', backgroundColor: 'rgba(25,135,84,0.08)', borderWidth: 2, pointRadius: 3, fill: true, yAxisID: 'y2' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontSize: 11, boxWidth: 12 } },
            tooltips: { mode: 'index', callbacks: { label: function(item, data) {
                return data.datasets[item.datasetIndex].label + ': ' + fmtChf(item.yLabel);
            }}},
            scales: {
                yAxes: [
                    { id: 'y1', position: 'left',  ticks: { callback: fmtChf, fontSize: 10 }, gridLines: { drawOnChartArea: true } },
                    { id: 'y2', position: 'right', ticks: { callback: fmtChf, fontSize: 10 }, gridLines: { drawOnChartArea: false } }
                ],
                xAxes: [{ ticks: { fontSize: 11 } }]
            }
        }
    });
    <?php endif ?>

    }); }); // end double-rAF
})();
</script>


