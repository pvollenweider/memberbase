<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Member list view with search, filters, and column selection.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$searchString = "";
if (isset ($_REQUEST["searchString"])) {
    $searchString = trim($_REQUEST["searchString"]);
}
$team = (int)($appSettings['default_team'] ?? 0);
$membre = (int)($appSettings['membre_team'] ?? 245);
if (isset ($_REQUEST["team"])) {
    $team = $_REQUEST["team"];
}
$metagroup = 0;
if (isset($_REQUEST['metagroup']) && (int)$_REQUEST['metagroup'] > 0) {
    $metagroup = (int)$_REQUEST['metagroup'];
}
$assignSegment = -1;
if (isset ($_REQUEST["assignSegment"])) {
    $assignSegment = $_REQUEST["assignSegment"];
}
$unassignSegment = -1;
if (isset ($_REQUEST["unassignSegment"])) {
    $unassignSegment = $_REQUEST["unassignSegment"];
}

$allowedColumns = ['lastname', 'firstname', 'society', 'npa', 'email', 'id'];
$allowedSorts   = ['ASC', 'DESC'];
$orderSort = "ASC";
$orderSortInverse = "DESC";
$orderColumn = "lastname";
if (isset($_REQUEST['orderSort']) && in_array(strtoupper($_REQUEST['orderSort']), $allowedSorts)) {
    $orderSort = strtoupper($_REQUEST['orderSort']);
    $orderSortInverse = $orderSort === "ASC" ? "DESC" : "ASC";
}
if (isset($_REQUEST['orderColumn']) && in_array($_REQUEST['orderColumn'], $allowedColumns)) {
    $orderColumn = $_REQUEST['orderColumn'];
}
$year=date("Y");
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}

// AJAX search is safe when no complex server-side filter is active
$_ajaxSearchOk = ($metagroup === 0 && in_array((int)$team, [0, FILTER_ALL_EXCEPT_ARCHIVES], true));

?>
<?php if (!empty($_GET['import_done'])): ?>
<div class="alert alert-success py-2 px-3 mb-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-circle-check me-1" aria-hidden="true"></i>
  <?= $GLOBAL['importDone'] ?>
  <?php if ((int)($_GET['import_resolved'] ?? 0) > 0): ?>
    <?= sprintf($GLOBAL['duplicatesUpdated'], (int)$_GET['import_resolved'], (int)$_GET['import_resolved'] > 1 ? 's' : '') ?>
  <?php endif ?>
</div>
<?php endif ?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <div class="dropdown">
    <button class="ca-filter-btn dropdown-toggle" id="navbarDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">

                    <?php
                    $currentTeamTitle = "";
                    $currentFilterDesc = "";
                    if ($metagroup > 0) {
                        $mg = new Metagroup();
                        $mg->lookupMetagroup($metagroup);
                        $currentTeamTitle = $mg->getName();
                    } else if ($team == FILTER_ALL_EXCEPT_ARCHIVES) {
                        $currentTeamTitle = $GLOBAL['allExceptArchives'];
                    } else if ($team == FILTER_UNPAID_COTI_3Y) {
                        $currentTeamTitle = $GLOBAL['cotiUnpayedLast3Years'];
                        $_noCotiTeamId3 = (int)($appSettings['member_no_coti_team'] ?? 0);
                        $_noCotiExclusion = '';
                        if ($_noCotiTeamId3 > 0) {
                            try { $_noCotiTeamNameStr = Segment::nameById($_noCotiTeamId3); } catch (PDOException $e) { $_noCotiTeamNameStr = null; }
                            if ($_noCotiTeamNameStr) {
                                $_noCotiExclusion = sprintf($GLOBAL['noCotiExclusion'], '<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, $charset) . '?team=' . $_noCotiTeamId3 . '" style="color:inherit">' . htmlspecialchars($_noCotiTeamNameStr, ENT_QUOTES, $charset) . '</a>');
                            }
                        }
                        $currentFilterDesc = sprintf($GLOBAL['filterDescCotiUnpaid3y'], $year-2, $year) . $_noCotiExclusion;
                    } else if ($team == FILTER_NO_ACTIVITY_10Y) {
                        $currentTeamTitle = $GLOBAL['nothingLast10Years'];
                        $currentFilterDesc = sprintf($GLOBAL['filterDescNoActivity10y'], $year-10);
                    } else if ($team == FILTER_NON_INSTIT_LAST_YEAR) {
                        $currentTeamTitle = $GLOBAL['nonInstitPayedSomethingLastYear'];
                        $currentFilterDesc = sprintf($GLOBAL['filterDescNonInstitLastYear'], $year-1);
                    } else if ($team == FILTER_UNPAID_COTI_CURRENT) {
                        $currentTeamTitle = $GLOBAL['cotiUnpayed'];
                        $currentFilterDesc = sprintf($GLOBAL['filterDescCotiUnpaidCurrent'], $year);
                    } else if ($team > 0) {
                        try {
                            $currentteam = new Segment();
                            $currentteam->lookupSegment($team);
                            $currentTeamTitle = $currentteam->getName();
                        } catch (PDOException $e) {
                            $currentTeamTitle = $GLOBAL['list'];
                        }
                    } else {
                        $currentTeamTitle = $GLOBAL['list'];
                    }
                    ?>

                    <?=$currentTeamTitle?>
    </button>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown" style="min-width:220px;max-height:80vh;overflow-y:auto;font-size:0.75rem">

                    <div class="px-2 pb-1">
                      <input type="text" id="team-filter-input" class="form-control form-control-sm" placeholder="<?= $GLOBAL['filterPlaceholder'] ?>" autocomplete="off" oninput="filterTeamDropdown(this.value)">
                    </div>
                    <div class="dropdown-divider mt-1 mb-0"></div>

                    <?php
                    $metagroups = Metagroup::filterList();
                    if (count($metagroups) > 0):
                    ?>
                    <h6 class="dropdown-header" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em"><?= $GLOBAL['combinedSegments'] ?></h6>
                    <?php foreach ($metagroups as $mg): ?>
                    <a class="dropdown-item team-filterable <?= ($metagroup === (int)$mg->id) ? 'active' : '' ?>"
                       href="<?= $_SERVER['PHP_SELF'] ?>?metagroup=<?= (int)$mg->id ?>"
                       data-label="<?= htmlentities(mb_strtolower($mg->name), ENT_COMPAT, $charset) ?>">
                        <i class="fas fa-layer-group me-1 text-muted" aria-hidden="true" style="font-size:0.75rem"></i><?= htmlentities($mg->name, ENT_COMPAT, $charset) ?>
                    </a>
                    <?php endforeach; ?>
                    <div class="dropdown-divider"></div>
                    <?php endif; ?>

                    <h6 class="dropdown-header">
                      <i class="fas fa-bolt me-1" aria-hidden="true"></i><?= $GLOBAL['quickFilters'] ?>
                    </h6>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] . '?team=' . FILTER_ALL_EXCEPT_ARCHIVES ?>"><?= $GLOBAL['allExceptArchives'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] . '?team=' . FILTER_UNPAID_COTI_3Y ?>"><?= $GLOBAL['cotiUnpayedLast3Years'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] . '?team=' . FILTER_NO_ACTIVITY_10Y ?>"><?= $GLOBAL['nothingLast10Years'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] . '?team=' . FILTER_UNPAID_COTI_CURRENT ?>"><?= $GLOBAL['cotiUnpayed'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] . '?team=' . FILTER_NON_INSTIT_LAST_YEAR ?>"><?= $GLOBAL['nonInstitPayedSomethingLastYear'] ?></a>

                        <?php
                        $prevCatId = -1;
                        foreach ((function() { try { return Segment::listForDropdown(); } catch (PDOException $e) { return []; } })() as $row) {
                            $catId = (int)$row->cat_id;
                            if ($catId !== $prevCatId) {
                                if ($prevCatId !== -1) echo '<div class="dropdown-divider my-0 team-cat-divider" data-cat="' . $catId . '"></div>';
                                $prevCatId = $catId;
                                $label = $row->cat_name ?: $GLOBAL['noCategoryLabel'];
                                echo '<h6 class="dropdown-header team-cat-header" data-cat="' . $catId . '" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em">' . htmlentities($label, ENT_COMPAT, $charset) . '</h6>';
                            }
                            ?>
                            <a class="dropdown-item team-filterable d-flex align-items-center justify-content-between <?php if ($team == $row->id) { ?>active<?php } ?>"
                               href="<?= $_SERVER['PHP_SELF'] ?>?team=<?= (int)$row->id ?>"
                               data-label="<?= htmlentities(mb_strtolower($row->name), ENT_COMPAT, $charset) ?>"
                               data-cat="<?= $catId ?>"
                               style="padding-left:1.5rem">
                              <span><?= htmlentities($row->name, ENT_COMPAT, $charset) ?></span>
                              <?php if ((int)$row->member_count > 0): ?>
                              <span class="badge rounded-pill ms-2 flex-shrink-0" style="font-size:0.65rem;font-weight:500;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)"><?= (int)$row->member_count ?></span>
                              <?php endif ?>
                            </a>
                            <?php
                        }
                    ?>
                </div>
  </div>

  <?php if (isManager()): ?>
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=importStep1"
     class="ms-auto ca-filter-btn text-decoration-none"
     title="<?= $GLOBAL['importContacts'] ?>">
    <i class="fas fa-file-import" aria-hidden="true"></i>
    <span><?= $GLOBAL['import'] ?></span>
  </a>
  <?php endif ?>
  <?php if (canWrite()): ?>
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=addUser&searchString=<?= $searchString ?><?= $team > 0 ? '&fromTeam=' . $team : '' ?>"
     class="<?= isManager() ? '' : 'ms-auto ' ?>ca-filter-btn text-decoration-none"
     title="<?= $GLOBAL['addUser'] ?>">
    <i class="fas fa-user-plus" aria-hidden="true"></i>
    <span><?= $GLOBAL['addUser'] ?></span>
  </a>
  <?php endif ?>
</div>
<script>
function filterTeamDropdown(q) {
  var val = q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  document.querySelectorAll('.team-filterable').forEach(function(el) {
    var label = (el.dataset.label || '').normalize('NFD').replace(/[̀-ͯ]/g, '');
    el.classList.toggle('team-hidden', !label.includes(val));
  });
  document.querySelectorAll('.team-cat-header').forEach(function(h) {
    var cat = h.dataset.cat;
    var hasVisible = Array.from(document.querySelectorAll('.team-filterable[data-cat="' + cat + '"]')).some(function(el) {
      return !el.classList.contains('team-hidden');
    });
    var display = hasVisible ? '' : 'none';
    h.style.display = display;
    var divider = document.querySelector('.team-cat-divider[data-cat="' + cat + '"]');
    if (divider) divider.style.display = display;
  });
  document.querySelectorAll('.team-filterable.kb-focus').forEach(function(el) { el.classList.remove('kb-focus'); });
}

function visibleItems() {
  return Array.from(document.querySelectorAll('.team-filterable')).filter(function(el) {
    return !el.classList.contains('team-hidden');
  });
}

// Use document-level delegation so these listeners survive htmx content swaps.
// One-time init guard prevents duplicates when the script re-executes on boost navigation.
if (!window._caTeamFilterInit) {
  window._caTeamFilterInit = true;

  document.addEventListener('keydown', function(e) {
    if (!e.target || e.target.id !== 'team-filter-input') return;
    var items = visibleItems();
    if (!items.length) return;
    var focused = document.querySelector('.team-filterable.kb-focus');

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (focused) focused.classList.remove('kb-focus');
      var idx = focused ? items.indexOf(focused) : -1;
      idx = e.key === 'ArrowDown' ? Math.min(idx + 1, items.length - 1) : Math.max(idx - 1, 0);
      items[idx].classList.add('kb-focus');
      items[idx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      var target = focused || items[0];
      if (target) window.location = target.href;
    } else if (e.key === 'Escape') {
      var dd = document.getElementById('navbarDropdown');
      var inst = dd && bootstrap.Dropdown.getInstance(dd);
      if (inst) inst.hide();
    }
  });

  document.addEventListener('shown.bs.dropdown', function(e) {
    if (!e.target || e.target.id !== 'navbarDropdown') return;
    var input = document.getElementById('team-filter-input');
    if (!input) return;
    input.value = '';
    filterTeamDropdown('');
    setTimeout(function() { input.focus(); }, 0);
  });
}
</script>
<style>
.team-filterable.kb-focus { background: var(--ca-primary-light) !important; color: var(--ca-primary-dark) !important; }
.team-filterable.team-hidden { display: none !important; }
.text-bg-ca-orange  { background-color: rgba(253,126,20,0.85)  !important; color:#fff !important; }
.text-bg-ca-teal    { background-color: rgba(32,201,151,0.85)  !important; color:#fff !important; }
.text-bg-ca-pink    { background-color: rgba(214,51,132,0.85)  !important; color:#fff !important; }
.text-bg-ca-purple  { background-color: rgba(111,66,193,0.85)  !important; color:#fff !important; }
.text-bg-ca-indigo  { background-color: rgba(102,16,242,0.85)  !important; color:#fff !important; }
.text-bg-ca-lime    { background-color: rgba(128,189,64,0.85)   !important; color:#fff !important; }
</style>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$action = ($_REQUEST['action'] ?? '') == "search" ? "search" : "";
?>
<?php if ($metagroup > 0):
    $mgTeamNames = Metagroup::teamNames($metagroup);
    if ($mgTeamNames): ?>
<p class="text-muted mb-2" style="font-size:0.8rem">
    <i class="fas fa-layer-group me-1" aria-hidden="true"></i>
    <?= implode(' · ', array_map(fn($n) => htmlspecialchars($n, ENT_QUOTES, $charset), $mgTeamNames)) ?>
</p>
    <?php endif; endif; ?>
<p id="ca-filter-desc" class="text-muted mb-2" style="font-size:0.78rem<?= empty($currentFilterDesc) ? ';display:none' : '' ?>">
  <i class="fas fa-circle-info me-1" aria-hidden="true"></i><span id="ca-filter-desc-text"><?= htmlspecialchars($currentFilterDesc, ENT_COMPAT, $charset) ?></span>
</p>
<div class="table-responsive">
<table class="table table-hover table-sm export">
<thead>
<tr>
    <th class="d-none d-sm-table-cell d-md-table-cell"><?=$GLOBAL['sexe']?></th>
    <th>
            <?=$GLOBAL['society']?>
    </th>
    <th>
            <?=$GLOBAL['lastName']?>
    </th>
    <th>
            <?=$GLOBAL['firstName']?>
    </th>
    <th class="d-none d-sm-table-cell">
            <?=$GLOBAL['address']?>
    </th>
    <th class="d-none d-sm-table-cell">
            <?=$GLOBAL['npa']?>
    </th>
    <th class="d-md-table-cell">
            <?=$GLOBAL['email']?>
    </th>
    <th class="d-none d-sm-table-cell d-md-table-cell">
            <?=$GLOBAL['creationDate']?>
    </th>
    <th class="d-none d-sm-table-cell"><?= $GLOBAL['typesHeader'] ?></th>
    <?php if ($team == FILTER_NO_ACTIVITY_10Y): ?>
    <th class="d-none d-sm-table-cell" style="font-size:0.75rem;white-space:nowrap"><?= $GLOBAL['comptaHistory'] ?></th>
    <?php endif ?>
</tr>
</thead>
<tbody>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
// Virtual filters — matching IDs resolved once via the shared MemberFilter
// class (same source of truth as /api/members, see issue #57)
$_virtualIds = null;
if (in_array((int)$team, MemberFilter::RESOLVABLE, true)) {
    $_virtualIds = MemberFilter::resolveIds((int)$team, $pdo, (int)$year, $appSettings);
}

// Pre-fetch compta summary for the FILTER_NO_ACTIVITY_10Y history column
$_compta5555 = [];
if ($team == FILTER_NO_ACTIVITY_10Y) {
    $_compta5555 = Compta::activitySummaryByUser((int)$year);
}

// Fetch — query construction and execution live in User::listWithFilters()
$_allRows = User::listWithFilters([
    'team'         => (int)$team,
    'metagroup'    => $metagroup,
    'searchString' => $searchString,
    'action'       => $action,
    'membreTeam'   => $membre,
    'orderColumn'  => $orderColumn,
    'orderSort'    => $orderSort,
]);

// Pre-fetch compta types — only for users in this result set
$_userComptaTypes = Compta::typesByUser(
    array_unique(array_map(fn($r) => (int)$r->id, $_allRows))
);

$rowCount = 0;
foreach ($_allRows as $row) {
    $id = $row->id;
    $displayLine = true;
    // $row already carries the display columns; only id-based methods
    // (isCotisationPayed, assignSegment…) are needed, so skip the per-row
    // full SELECT that lookupUser() would run.
    $user = new User();
    $user->id = $id;

    if ($team == -1234) {
        $displayLine = false;
        if ($user->isCotisationPayed(2004) != -1 ||
            $user->isCotisationPayed(2005) != -1 ||
            $user->isCotisationPayed(2006) != -1 ||
            $user->isCotisationPayed(2007) != -1 ||
            $user->isCotisationPayed(2008) != -1 ||
            $user->isCotisationPayed(2009) != -1
            ) {
            $displayLine = true;
        }
        $displayLine = !$displayLine;
    } else if ($_virtualIds !== null) {
        $displayLine = isset($_virtualIds[(int)$id]);
    }


    if ($displayLine) {
        $rowCount++;
        $firstName = $row->firstname;
        $lastName = $row->lastname;
        $society = $row->society;
        $sexe = $row->sexe;
        if ($sexe == "na") { $sexe = ""; }
        else if ($sexe == "f") { $sexe = "<i class='fas fa-female s'></i><span class='d-none'>" . $GLOBAL['madame'] . "</span>"; }
        else if ($sexe == "m") { $sexe = "<i class='fas fa-male s'></i><span class='d-none'>" . $GLOBAL['monsieur'] . "</span>"; }
        else if ($sexe == "hf") { $sexe = "<i class='fas fa-male s'></i><i class='fas fa-female s'></i><span class='d-none'>" . $GLOBAL['fh'] . "</span>"; }
        $address = $row->address;
        $npa = $row->npa;
        $email = $row->email;
        $firstName = htmlentities($firstName,ENT_COMPAT,$charset);
        $lastName = htmlentities($lastName,ENT_COMPAT,$charset);
        $address = htmlentities($address,ENT_COMPAT,$charset);
        $npa = htmlentities($npa,ENT_COMPAT,$charset);
        $emailStr = htmlentities($email,ENT_COMPAT,$charset);
        if ($assignSegment != -1) {
            $user->assignSegment($assignSegment);
        }
        if ($unassignSegment != -1) {
            $user->unassignSegment($unassignSegment);
        }
        #if ($searchString) {
        #    $ss = htmlentities($searchString,ENT_COMPAT,$charset);
        #    $firstName = preg_replace("/($ss)/i","<mark>\\1</mark>",$firstName);
        #    $lastName = preg_replace("/($ss)/i","<mark>\\1</mark>",$lastName);
        #    $society = preg_replace("/($ss)/i","<mark>\\1</mark>",$society);
        #    $address = preg_replace("/($ss)/i","<mark>\\1</mark>",$address);
        #    $npa = preg_replace("/($ss)/i","<mark>\\1</mark>",$npa);
        #    $emailStr = preg_replace("/($ss)/i","<mark>\\1</mark>",$emailStr);
        #}
        $emailStr = str_replace(",","<br/>",$emailStr);
        ?>
        <tr class="ca-row-link" data-href="<?=$_SERVER['PHP_SELF']?>?view=generalData&id=<?=(int)$id?>" style="cursor:pointer">
            <td class="d-none d-sm-table-cell d-md-table-cell"><?=$sexe?></td>
            <td class="bold"><div class="text-truncate" style="max-width:200px"><?=$society?></div></td>
            <td class="text-nowrap"><?=$lastName?></td>
            <td class="text-nowrap2"><?=$firstName?></td>
            <td class="text-nowrap d-none d-sm-table-cell"><div class="text-truncate" style="max-width:200px"><?=$address?></div></td>
            <td class="text-nowrap d-none d-sm-table-cell"><?=$npa?></td>
            <td class="d-md-table-cell"><a href="mailto:<?=$email?>"><?=$emailStr?></a></td>
            <td class="d-none d-sm-table-cell d-md-table-cell"><?=timeStampToformatedDate((int)$row->creationDate)?></td>
            <td class="d-none d-sm-table-cell" style="white-space:nowrap">
              <?php if (!empty($_userComptaTypes[$id])): ?>
              <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&userid=<?= (int)$id ?>" class="text-decoration-none">
              <?php foreach ($_userComptaTypes[$id] as $_ct):
                  $_bgClass  = $_ct->color ?: 'bg-secondary-subtle';
                  $_txtColor = (str_contains($_bgClass, '-subtle') || $_bgClass === 'bg-light') ? '#212529' : '#fff';
              ?>
                <span class="d-inline-flex align-items-center justify-content-center rounded border <?= htmlspecialchars($_bgClass, ENT_QUOTES, $charset) ?>"
                      title="<?= htmlspecialchars($_ct->label, ENT_QUOTES, $charset) ?>"
                      style="width:28px;height:20px;font-size:0.55rem;font-weight:700;line-height:1;letter-spacing:0.02em;color:<?= $_txtColor ?>"
                      ><?= htmlspecialchars(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtoupper(mb_substr($_ct->label, 0, 3))), ENT_QUOTES, $charset) ?></span>
              <?php endforeach ?>
              </a>
              <?php endif ?>
            </td>
            <?php if ($team == FILTER_NO_ACTIVITY_10Y):
                $_c5 = $_compta5555[$user->getId()] ?? null; ?>
            <td class="d-none d-sm-table-cell text-muted" style="font-size:0.72rem;line-height:1.3">
                <?php if ($_c5 && (int)$_c5->total > 0):
                    $parts = [sprintf($GLOBAL['entriesCountShort'], (int)$_c5->total)];
                    if ((int)$_c5->coti_count > 0) $parts[] = sprintf($GLOBAL['cotiCountShort'], (int)$_c5->coti_count);
                    echo htmlspecialchars(implode(' · ', $parts));
                    echo '<br><span style="opacity:0.55">' . sprintf($GLOBAL['lastActivityYear'], date('Y', (int)$_c5->last_date)) . '</span>';
                else: ?><span style="opacity:0.4">—</span><?php endif ?>
            </td>
            <?php endif ?>
        </tr>
        <?php
    }
} // end foreach
?>
</tbody>
</table>
</div>
<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
if ($searchString) {
    ?>
    <script>
    (function() {
      var terms = <?= json_encode(array_values(array_filter(explode(' ', $searchString)))) ?>;
      terms.forEach(function(t) { $("table").highlight(t, { element: 'mark' }); });
    })();
    </script>
    <?php

}
if ($team == FILTER_UNPAID_COTI_CURRENT) {
    ?><span style="color:red"><?= sprintf($GLOBAL['missedRevenue'], $rowCount*50, $year) ?></span><br/><?php
}
?>
<script>
// bfcache: DataTables DOM state survives navigation — reload to avoid column mismatch
window.addEventListener('pageshow', function(e) { if (e.persisted) window.location.reload(); });

// Row click — delegate on tbody to survive DataTables re-render, skip inner links/buttons
document.querySelector('.export tbody') && document.querySelector('.export tbody').addEventListener('click', function(e) {
    var tr = e.target.closest('tr.ca-row-link');
    if (!tr) return;
    if (e.target.closest('a, button')) return;
    window.location.href = tr.dataset.href;
});

var CA_DT_INSTANCE = null;
function caInitDT() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
    CA_DT_INSTANCE = $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        dom: CA_DT_DOM,
        buttons: [...CA_DT_BUTTONS, CA_DT_COLVIS],
        columnDefs: [
            { targets: [0, 4, 5, 7], visible: false }
        ],
        language: Object.assign({}, CA_DT_LANGUAGE, { info: <?= json_encode($GLOBAL['dtInfoProfiles'], JSON_UNESCAPED_UNICODE) ?>, infoFiltered: <?= json_encode($GLOBAL['dtInfoFilteredMasc'], JSON_UNESCAPED_UNICODE) ?> })
    });
}
$(document).ready(caInitDT);

(function () {
  var BASE_PATH        = <?= json_encode($_SERVER['PHP_SELF']) ?>;
  var SEARCH_AJAX_OK   = <?= $_ajaxSearchOk ? 'true' : 'false' ?>;
  var INITIAL_METAGROUP = <?= (int)$metagroup ?>;
  <?php $_jsYear = (int)date('Y'); // Year values are built server-side so JS reuses the same locale keys ?>
  var FILTER_DESCS = {
    '-4':    <?= json_encode(sprintf($GLOBAL['filterDescCotiUnpaidCurrent'], $_jsYear), JSON_UNESCAPED_UNICODE) ?>,
    '-3333': <?= json_encode(sprintf($GLOBAL['filterDescCotiUnpaid3y'], $_jsYear - 2, $_jsYear), JSON_UNESCAPED_UNICODE) ?>,
    '-5555': <?= json_encode(sprintf($GLOBAL['filterDescNoActivity10y'], $_jsYear - 10), JSON_UNESCAPED_UNICODE) ?>,
    '-6666': <?= json_encode(sprintf($GLOBAL['filterDescNonInstitLastYear'], $_jsYear - 1), JSON_UNESCAPED_UNICODE) ?>
  };

  function sexeIcon(g) {
    if (g === 'm')  return "<i class='fas fa-male s' aria-hidden='true'></i><span class='d-none'>" + <?= json_encode($GLOBAL['monsieur'], JSON_UNESCAPED_UNICODE) ?> + "</span>";
    if (g === 'f')  return "<i class='fas fa-female s' aria-hidden='true'></i><span class='d-none'>" + <?= json_encode($GLOBAL['madame'], JSON_UNESCAPED_UNICODE) ?> + "</span>";
    if (g === 'hf') return "<i class='fas fa-male s' aria-hidden='true'></i><i class='fas fa-female s' aria-hidden='true'></i><span class='d-none'>" + <?= json_encode($GLOBAL['fh'], JSON_UNESCAPED_UNICODE) ?> + "</span>";
    return '';
  }

  function formatDate(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
  }

  function typesBadges(types, userId) {
    if (!types || !types.length) return '';
    var inner = types.map(function(t) {
      var bg  = t.color || 'bg-secondary-subtle';
      var txt = (bg.indexOf('-subtle') !== -1 || bg === 'bg-light') ? '#212529' : '#fff';
      return '<span class="d-inline-flex align-items-center justify-content-center rounded border ' + bg + '"'
           + ' style="width:28px;height:20px;font-size:0.55rem;font-weight:700;line-height:1;letter-spacing:0.02em;color:' + txt + '"'
           + ' title="' + esc(t.label) + '">' + abbr(t.label) + '</span>';
    }).join('');
    return '<a href="' + BASE_PATH + '?view=compta&userid=' + userId + '" class="text-decoration-none">' + inner + '</a>';
  }

  function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function abbr(s) { return esc(((s||'').substring(0, 3)).toUpperCase()); }

  function groupsBadges(groups) {
    if (!groups || !groups.length) return '';
    return groups.map(function(g) {
      return '<span class="badge text-bg-light border" style="font-size:0.6rem;padding:2px 5px;font-weight:500" title="' + esc(g.name) + '">' + esc(g.name) + '</span>';
    }).join(' ');
  }

  function buildRow(m) {
    var href = BASE_PATH + '?view=generalData&id=' + m.id;
    var email = m.email ? '<a href="mailto:' + esc(m.email) + '">' + esc(m.email).replace(',','<br>') + '</a>' : '';
    var typesOrGroups = m.groups && m.groups.length ? groupsBadges(m.groups) : typesBadges(m.types, m.id);
    return '<tr class="ca-row-link" data-href="' + href + '" style="cursor:pointer">' +
      '<td class="d-none d-sm-table-cell d-md-table-cell">' + sexeIcon(m.gender) + '</td>' +
      '<td class="bold"><div class="text-truncate" style="max-width:200px">' + esc(m.society||'') + '</div></td>' +
      '<td class="text-nowrap">' + esc(m.lastName||'') + '</td>' +
      '<td class="text-nowrap2">' + esc(m.firstName||'') + '</td>' +
      '<td class="text-nowrap d-none d-sm-table-cell"><div class="text-truncate" style="max-width:200px">' + esc(m.address||'') + '</div></td>' +
      '<td class="text-nowrap d-none d-sm-table-cell">' + esc(m.npa||'') + '</td>' +
      '<td class="d-md-table-cell">' + email + '</td>' +
      '<td class="d-none d-sm-table-cell d-md-table-cell">' + formatDate(m.createdAt) + '</td>' +
      '<td class="d-none d-sm-table-cell">' + typesOrGroups + '</td>' +
      '</tr>';
  }

  var _abortCtrl  = null;
  var _loaderTimer = null;

  // Virtual filter team IDs — must fall back to server-side render
  var VIRTUAL_FILTERS = [-3, -4, -3333, -5555, -6666];

  // Loader — after 200 ms replaces tbody with a spinner row; hidden on resolve/reject
  var _savedTbody = null;
  var _loader = {
    show: function() {
      var tbody = document.querySelector('.export tbody');
      if (!tbody) return;
      if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
      _savedTbody = tbody.innerHTML;
      var cols = document.querySelectorAll('.export thead tr th').length || 9;
      tbody.innerHTML = '<tr><td colspan="' + cols + '" class="text-center py-4 text-muted">' +
        '<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>' +
        <?= json_encode($GLOBAL['loading'], JSON_UNESCAPED_UNICODE) ?> + '</td></tr>';
    },
    hide: function() { _savedTbody = null; }
  };

  function applyHighlight(q) {
    if (!q) return;
    var terms = q.split(/\s+/).filter(Boolean);
    if (!terms.length) return;
    var re = new RegExp('(' + terms.map(function(t) {
      return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }).join('|') + ')', 'gi');
    var tbody = document.querySelector('.export tbody');
    if (!tbody) return;
    // Walk text nodes only — skip already-marked nodes
    var walker = document.createTreeWalker(tbody, NodeFilter.SHOW_TEXT, {
      acceptNode: function(n) {
        return n.parentNode && n.parentNode.nodeName !== 'MARK' && n.nodeValue.trim()
          ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      }
    });
    var nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(function(node) {
      if (!re.test(node.nodeValue)) return;
      re.lastIndex = 0;
      var frag = document.createDocumentFragment();
      var last = 0, m;
      while ((m = re.exec(node.nodeValue)) !== null) {
        if (m.index > last) frag.appendChild(document.createTextNode(node.nodeValue.slice(last, m.index)));
        var mark = document.createElement('mark');
        mark.textContent = m[0];
        frag.appendChild(mark);
        last = m.index + m[0].length;
      }
      if (last < node.nodeValue.length) frag.appendChild(document.createTextNode(node.nodeValue.slice(last)));
      node.parentNode.replaceChild(frag, node);
    });
  }

  function setFilterDesc(desc) {
    var el  = document.getElementById('ca-filter-desc');
    var txt = document.getElementById('ca-filter-desc-text');
    if (!el || !txt) return;
    if (desc) { txt.textContent = desc; el.style.display = ''; }
    else       { txt.textContent = '';  el.style.display = 'none'; }
  }

  function doFetch(apiUrl, pushUrl, searchTerm) {
    if (_abortCtrl) _abortCtrl.abort();
    _abortCtrl = new AbortController();

    clearTimeout(_loaderTimer);
    _loaderTimer = setTimeout(function() { _loader.show(); }, 200);

    fetch(apiUrl, { signal: _abortCtrl.signal, credentials: 'same-origin' })
      .then(function(r) { return r.json(); })
      .then(function(json) {
        clearTimeout(_loaderTimer);
        _loader.hide();

        var rows = (json.data || []).map(buildRow).join('');
        var tbody = document.querySelector('.export tbody');
        if (!tbody) return;

        if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
        tbody.innerHTML = rows;
        caInitDT();
        if (searchTerm) applyHighlight(searchTerm);

        // Update filter description for virtual filters
        var _usp  = new URLSearchParams(apiUrl.split('?')[1] || '');
        var _team = _usp.has('team') ? _usp.get('team') : null;
        setFilterDesc(_team ? (FILTER_DESCS[_team] || '') : '');

        history.pushState({caState: {apiUrl: apiUrl, pushUrl: pushUrl, searchTerm: searchTerm}}, '', pushUrl);
      })
      .catch(function(e) {
        clearTimeout(_loaderTimer);
        _loader.hide();
        if (e.name !== 'AbortError') console.error('member fetch failed', e);
      });
  }

  function doSearch(q) {
    var apiUrl  = '/api/members?limit=2000&types=1' + (q ? '&search=' + encodeURIComponent(q) : '');
    var pushUrl = window.location.pathname + (q
      ? '?action=search&team=<?= FILTER_ALL_EXCEPT_ARCHIVES ?>&searchString=' + encodeURIComponent(q)
      : '?view=usersList');
    doFetch(apiUrl, pushUrl, q);
  }

  // Intercept both search forms before htmx handles them (only when no complex filter active)
  if (SEARCH_AJAX_OK) {
    ['main-search-form', 'mobile-search-form'].forEach(function(id) {
      var frm = document.getElementById(id);
      if (!frm) return;
      frm.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // prevent htmx boost
        var inp = frm.querySelector('[name="searchString"]');
        var q = inp ? inp.value.trim() : '';
        ['search', 'mobile-search'].forEach(function(sid) {
          var el = document.getElementById(sid);
          if (el && el !== inp) el.value = q;
        });
        doSearch(q);
      }, true); // capture phase — fires before htmx listener
    });
  }

  // Intercept team/metagroup dropdown links (skip virtual filters)
  document.addEventListener('click', function(e) {
    var link = e.target.closest('.dropdown-item[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    var usp  = new URLSearchParams(href.split('?')[1] || '');
    var teamVal = usp.has('team') ? parseInt(usp.get('team'), 10) : null;
    var mgVal   = usp.has('metagroup') ? parseInt(usp.get('metagroup'), 10) : null;

    // Let non-team/non-metagroup links navigate normally
    if (teamVal === null && mgVal === null) return;

    e.preventDefault();
    e.stopPropagation();       // prevent event reaching htmx bubble listener on body
    e.stopImmediatePropagation();

    var apiUrl = '/api/members?limit=2000&types=1';
    if (teamVal !== null && teamVal !== 0) apiUrl += '&team=' + teamVal;
    if (mgVal   !== null && mgVal   > 0)  apiUrl += '&metagroup=' + mgVal;

    // team=0 = all members, no extra param needed
    doFetch(apiUrl, href, '');

    // Update active state in dropdown
    document.querySelectorAll('.dropdown-item.active').forEach(function(el) { el.classList.remove('active'); });
    link.classList.add('active');

    // Update dropdown button label — use first non-badge span to exclude member count badge
    var btnEl = document.getElementById('navbarDropdown');
    if (btnEl) {
      var nameSpan = link.querySelector('span:not(.badge)');
      btnEl.textContent = nameSpan ? nameSpan.textContent.trim() : link.textContent.replace(/\s*\d+\s*$/, '').trim();
    }

    // Close dropdown
    var ddEl = document.getElementById('navbarDropdown');
    if (ddEl && bootstrap && bootstrap.Dropdown) {
      var dd = bootstrap.Dropdown.getInstance(ddEl);
      if (dd) dd.hide();
    }
  }, true); // capture phase

  // On popstate (browser back/forward)
  window.addEventListener('popstate', function(e) {
    if (e.state && e.state.caState) {
      var s = e.state.caState;
      var inp = document.getElementById('search');
      if (inp) inp.value = s.searchTerm || '';
      doFetch(s.apiUrl, s.pushUrl, s.searchTerm || '');
    }
  });

  // On metagroup page load, replace PHP-rendered table with API result (includes groups column)
  if (INITIAL_METAGROUP > 0) {
    doFetch(
      '/api/members?limit=2000&types=1&metagroup=' + INITIAL_METAGROUP,
      window.location.href,
      ''
    );
  }
})();
</script>
