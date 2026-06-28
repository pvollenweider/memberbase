<?php
$searchString = "";
if (isset ($_REQUEST["searchString"])) {
    $searchString = trim($_REQUEST["searchString"]);
}
$team = (int)($appSettings['default_team'] ?? 249);
$membre = (int)($appSettings['membre_team'] ?? 245);
if (isset ($_REQUEST["team"])) {
    $team = $_REQUEST["team"];
}
$metagroup = 0;
if (isset($_REQUEST['metagroup']) && (int)$_REQUEST['metagroup'] > 0) {
    $metagroup = (int)$_REQUEST['metagroup'];
}
$addMembership = -1;
if (isset ($_REQUEST["addMembership"])) {
    $addMembership = $_REQUEST["addMembership"];
}
$removeMembership = -1;
if (isset ($_REQUEST["removeMembership"])) {
    $removeMembership = $_REQUEST["removeMembership"];
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

?>
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
                            $_noCotiTeamName3 = $pdo->prepare("SELECT name FROM team WHERE id=?");
                            $_noCotiTeamName3->execute([$_noCotiTeamId3]);
                            $_noCotiTeamNameStr = $_noCotiTeamName3->fetchColumn();
                            if ($_noCotiTeamNameStr) {
                                $_noCotiExclusion = ' Les membres du groupe <a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, $charset) . '?team=' . $_noCotiTeamId3 . '" style="color:inherit">' . htmlspecialchars($_noCotiTeamNameStr, ENT_QUOTES, $charset) . '</a> sont exclus.';
                            }
                        }
                        $currentFilterDesc = "Profils ayant payé au moins une cotisation dans leur historique, mais aucune lors des 3 dernières années (" . ($year-2) . "–$year)." . $_noCotiExclusion;
                    } else if ($team == FILTER_NO_ACTIVITY_10Y) {
                        $currentTeamTitle = $GLOBAL['nothingLast10Years'];
                        $currentFilterDesc = "Profils actifs sans aucune entrée comptable (cotisation, don ou autre) depuis " . ($year-10) . ".";
                    } else if ($team == FILTER_NON_INSTIT_LAST_YEAR) {
                        $currentTeamTitle = $GLOBAL['nonInstitPayedSomethingLastYear'];
                        $currentFilterDesc = "Profils ayant effectué au moins un versement non institutionnel en " . ($year-1) . " — inclut cotisations, dons et tout autre type non marqué «&nbsp;Institutionnel&nbsp;» dans les types compta.";
                    } else if ($team == FILTER_UNPAID_COTI_CURRENT) {
                        $currentTeamTitle = $GLOBAL['cotiUnpayed'];
                        $currentFilterDesc = "Membres dont la cotisation $year n'a pas encore été enregistrée.";
                    } else {
                        $currentteam = new Team();
                        $currentteam->lookupTeam($team);
                        $currentTeamTitle = $currentteam->getName();
                    }
                    ?>

                    <?=$currentTeamTitle?>
    </button>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown" style="min-width:220px;max-height:80vh;overflow-y:auto;font-size:0.75rem">

                    <div class="px-2 pb-1">
                      <input type="text" id="team-filter-input" class="form-control form-control-sm" placeholder="Filtrer…" autocomplete="off"
                             oninput="filterTeamDropdown(this.value)"
                             onclick="event.stopPropagation()">
                    </div>
                    <div class="dropdown-divider mt-1 mb-0"></div>

                    <?php
                    $stmtMg = $pdo->query("SELECT DISTINCT m.id, m.name FROM metagroup m WHERE m.name IS NOT NULL AND m.is_filter = 1 AND EXISTS (SELECT 1 FROM metagroup j WHERE j.id=m.id AND j.teamid IS NOT NULL) ORDER BY m.name");
                    $metagroups = $stmtMg->fetchAll(PDO::FETCH_OBJ);
                    if (count($metagroups) > 0):
                    ?>
                    <h6 class="dropdown-header" style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.08em">Groupes de groupes</h6>
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
                      <i class="fas fa-bolt me-1" aria-hidden="true"></i>Filtres rapides
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
                        $stmtTeams = $pdo->query("
                            SELECT t.id, t.name,
                                   COALESCE(cat.name, '') AS cat_name,
                                   COALESCE(cat.id, 0) AS cat_id,
                                   COALESCE(cat.sort_order, 99999) AS cat_sort,
                                   (SELECT COUNT(*) FROM user_properties up WHERE up.parameter = CONCAT('team_', t.id)) AS member_count
                            FROM team t
                            LEFT JOIN (
                                SELECT j.teamid, MIN(c.id) AS id, MIN(c.name) AS name, MIN(c.sort_order) AS sort_order
                                FROM metagroup j
                                JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
                                WHERE j.teamid IS NOT NULL
                                GROUP BY j.teamid
                            ) cat ON cat.teamid = t.id
                            WHERE t.hidden = 0
                            ORDER BY cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
                        ");
                        $prevCatId = -1;
                        while ($row = $stmtTeams->fetchObject()) {
                            $catId = (int)$row->cat_id;
                            if ($catId !== $prevCatId) {
                                if ($prevCatId !== -1) echo '<div class="dropdown-divider my-0"></div>';
                                $prevCatId = $catId;
                                $label = $row->cat_name ?: 'Sans catégorie';
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

  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=addUser&searchString=<?= $searchString ?>"
     class="ms-auto ca-filter-btn text-decoration-none"
     title="<?= $GLOBAL['addUser'] ?>">
    <i class="fas fa-user-plus" aria-hidden="true"></i>
    <span><?= $GLOBAL['addUser'] ?></span>
  </a>
</div>
<script>
function filterTeamDropdown(q) {
  var val = q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  document.querySelectorAll('.team-filterable').forEach(function(el) {
    var label = (el.dataset.label || '').normalize('NFD').replace(/[̀-ͯ]/g, '');
    el.style.display = label.includes(val) ? '' : 'none';
  });
  document.querySelectorAll('.team-cat-header').forEach(function(h) {
    var cat = h.dataset.cat;
    var hasVisible = Array.from(document.querySelectorAll('.team-filterable[data-cat="' + cat + '"]')).some(function(el) {
      return el.style.display !== 'none';
    });
    h.style.display = hasVisible ? '' : 'none';
  });
  document.querySelectorAll('.team-filterable.kb-focus').forEach(function(el) { el.classList.remove('kb-focus'); });
}

function visibleItems() {
  return Array.from(document.querySelectorAll('.team-filterable')).filter(function(el) {
    return el.style.display !== 'none';
  });
}

document.getElementById('team-filter-input').addEventListener('keydown', function(e) {
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
    bootstrap.Dropdown.getInstance(document.getElementById('navbarDropdown')).hide();
  }
});

document.getElementById('navbarDropdown').addEventListener('shown.bs.dropdown', function() {
  var input = document.getElementById('team-filter-input');
  input.value = '';
  filterTeamDropdown('');
  setTimeout(function() { input.focus(); }, 0);
});
</script>
<style>
.team-filterable.kb-focus { background: var(--ca-primary-light) !important; color: var(--ca-primary-dark) !important; }
.text-bg-ca-orange  { background-color: rgba(253,126,20,0.85)  !important; color:#fff !important; }
.text-bg-ca-teal    { background-color: rgba(32,201,151,0.85)  !important; color:#fff !important; }
.text-bg-ca-pink    { background-color: rgba(214,51,132,0.85)  !important; color:#fff !important; }
.text-bg-ca-purple  { background-color: rgba(111,66,193,0.85)  !important; color:#fff !important; }
.text-bg-ca-indigo  { background-color: rgba(102,16,242,0.85)  !important; color:#fff !important; }
.text-bg-ca-lime    { background-color: rgba(128,189,64,0.85)   !important; color:#fff !important; }
</style>
<?php
$query = "SELECT DISTINCT ".
           "users.id," .
           "users.firstname," .
           "users.lastname," .
           "users.society," .
           "users.sexe," .
           "users.address," .
           "users.npa," .
           "users.email";
$query .= " FROM users";
if ($metagroup > 0) {
    $query .= ",user_properties ";
} else {
    if ($team == FILTER_UNPAID_COTI_CURRENT) {
        $query .= ",compta ";
    }
    // Virtual filter IDs do not target a specific team — no user_properties join needed
    if ($team != -1 && $team != FILTER_ALL_EXCEPT_ARCHIVES && $team != FILTER_UNPAID_COTI_3Y && $team != FILTER_NO_ACTIVITY_10Y && $team != FILTER_NON_INSTIT_LAST_YEAR) {
        $query .= ",user_properties ";
    }
}
$query .= " WHERE 1=1 AND users.status=1 ";
$action = "";

$queryParams = [];
if (isset($_REQUEST['action'])) {
    if ($_REQUEST['action'] == "search") {
        $action = "search";
        $like = "%" . $searchString . "%";
        $query .= " AND (users.firstname LIKE ?";
        $query .= " OR users.lastname LIKE ?";
        $query .= " OR CONCAT(users.firstname, ' ', users.lastname) LIKE ?";
        $query .= " OR CONCAT(users.lastname, ' ', users.firstname) LIKE ?";
        $query .= " OR users.society LIKE ?";
        $query .= " OR users.npa LIKE ?";
        $query .= " OR users.email LIKE ?";
        $query .= " OR users.comment LIKE ?";
        $query .= " OR users.address LIKE ?)";
        $queryParams = array_fill(0, 9, $like);
    }
}
if ($metagroup > 0) {
    // Fetch all team IDs belonging to this metagroup
    $stmtMgTeams = $pdo->prepare("SELECT teamid FROM metagroup WHERE id=? AND teamid IS NOT NULL");
    $stmtMgTeams->execute([$metagroup]);
    $mgTeamIds = $stmtMgTeams->fetchAll(PDO::FETCH_COLUMN);
    if (count($mgTeamIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($mgTeamIds), '?'));
        $query .= " AND users.id=user_properties.user_id AND user_properties.parameter IN ($placeholders)";
        $queryParams = array_merge($queryParams, array_map(fn($id) => "team_$id", $mgTeamIds));
    } else {
        $query .= " AND 1=0"; // metagroup has no teams — return empty
    }
} else if ($team != -1) {
    if ($team == FILTER_ALL_EXCEPT_ARCHIVES || $team == FILTER_UNPAID_COTI_3Y || $team == FILTER_NO_ACTIVITY_10Y || $team == FILTER_NON_INSTIT_LAST_YEAR) {
        // status=1 already excludes archived members — no user_properties condition needed
    } else if ($team == FILTER_UNPAID_COTI_CURRENT || $team == -1234) {
        $query .= " AND users.id=user_properties.user_id ";
        $query .= "AND ( ";
        $query .= "user_properties.parameter='team_$membre') ";
    } else {
        $query .= " AND users.id=user_properties.user_id AND user_properties.parameter='team_$team'";
    }
}

$query .= " ORDER BY $orderColumn $orderSort";

?>
<?php if ($metagroup > 0):
    $stmtMgNames = $pdo->prepare(
        "SELECT t.name FROM team t
         JOIN metagroup j ON j.teamid = t.id
         WHERE j.id = ? ORDER BY t.name"
    );
    $stmtMgNames->execute([$metagroup]);
    $mgTeamNames = $stmtMgNames->fetchAll(PDO::FETCH_COLUMN);
    if ($mgTeamNames): ?>
<p class="text-muted mb-2" style="font-size:0.8rem">
    <i class="fas fa-layer-group me-1" aria-hidden="true"></i>
    <?= implode(' · ', array_map(fn($n) => htmlspecialchars($n, ENT_QUOTES, $charset), $mgTeamNames)) ?>
</p>
    <?php endif; endif; ?>
<?php if (!empty($currentFilterDesc)): ?>
<p class="text-muted mb-2" style="font-size:0.78rem"><i class="fas fa-circle-info me-1" aria-hidden="true"></i><?= $currentFilterDesc ?></p>
<?php endif ?>
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
    <th class="d-none d-sm-table-cell">Types</th>
    <?php if ($team == FILTER_NO_ACTIVITY_10Y): ?>
    <th class="d-none d-sm-table-cell" style="font-size:0.75rem;white-space:nowrap">Historique compta</th>
    <?php endif ?>
</tr>
</thead>
<tbody>
<?php
// Pre-fetch non-institutional compta for FILTER_NON_INSTIT_LAST_YEAR (avoids N+1 SQL)
$_nonInstit6666 = [];
if ($team == FILTER_NON_INSTIT_LAST_YEAR) {
    $_instit6666Ids = array_column($pdo->query("SELECT id FROM compta_type WHERE is_institutional=1")->fetchAll(PDO::FETCH_OBJ), 'id');
    $_from6666pre = mktime(0,0,0,1,0,$year-1);
    $_to6666pre   = mktime(0,0,0,1,1,$year);
    $_notIn = count($_instit6666Ids) ? implode(',', array_map('intval', $_instit6666Ids)) : '0';
    $_st6666 = $pdo->query("
        SELECT user_id
        FROM compta
        WHERE date > $_from6666pre AND date < $_to6666pre
          AND (type_id IS NULL OR type_id NOT IN ($_notIn))
        GROUP BY user_id
    ");
    while ($_r6 = $_st6666->fetchObject()) {
        $_nonInstit6666[(int)$_r6->user_id] = true;
    }
}

// Pre-fetch cotisation history for FILTER_UNPAID_COTI_3Y: ever paid vs. paid in last 3 years
// Also pre-fetch member_no_coti_team members to exclude them from coti filters
$_coti3333 = [];
$_noCotiMembers = [];
if ($team == FILTER_UNPAID_COTI_3Y || $team == FILTER_UNPAID_COTI_CURRENT) {
    $_noCotiTeam = (int)($appSettings['member_no_coti_team'] ?? 0);
    if ($_noCotiTeam > 0) {
        $_stNoCoti = $pdo->prepare("SELECT user_id FROM user_properties WHERE parameter=?");
        $_stNoCoti->execute(["team_$_noCotiTeam"]);
        while ($_rn = $_stNoCoti->fetchObject()) {
            $_noCotiMembers[(int)$_rn->user_id] = true;
        }
    }
}
if ($team == FILTER_UNPAID_COTI_3Y) {
    $_cutoff3333 = mktime(0, 0, 0, 1, 0, $year - 2);
    $_st3333 = $pdo->query("
        SELECT
            c.user_id,
            COUNT(*) AS ever_coti,
            SUM(CASE WHEN c.date > $_cutoff3333 THEN 1 ELSE 0 END) AS recent_coti
        FROM compta c
        JOIN compta_type ct ON ct.id = c.type_id AND ct.is_cotisation = 1
        GROUP BY c.user_id
    ");
    while ($_r3 = $_st3333->fetchObject()) {
        $_coti3333[(int)$_r3->user_id] = $_r3;
    }
}

// Pre-fetch compta summary for FILTER_NO_ACTIVITY_10Y to avoid N+1 queries
$_compta5555 = [];
if ($team == FILTER_NO_ACTIVITY_10Y) {
    $_from5555 = mktime(0, 0, 0, 1, 0, $year - 10);
    $_to5555   = mktime(0, 0, 0, 1, 1, $year + 1);
    $_st5555 = $pdo->query("
        SELECT c.user_id,
               COUNT(*) AS total,
               MAX(c.date) AS last_date,
               SUM(CASE WHEN COALESCE(ct.is_cotisation,0)=1 THEN 1 ELSE 0 END) AS coti_count,
               SUM(CASE WHEN c.date > $_from5555 AND c.date < $_to5555 THEN 1 ELSE 0 END) AS recent_count
        FROM compta c
        LEFT JOIN compta_type ct ON ct.id = c.type_id
        GROUP BY c.user_id
    ");
    while ($_r5 = $_st5555->fetchObject()) {
        $_compta5555[(int)$_r5->user_id] = $_r5;
    }
}

#print $query;
$stmt = $pdo->prepare($query);
$stmt->execute($queryParams);
$_allRows = $stmt->fetchAll(PDO::FETCH_OBJ);

// Pre-fetch compta types — only for users in this result set
$_userComptaTypes = [];
if (!empty($_allRows)) {
    $_resultIds = array_unique(array_map(fn($r) => (int)$r->id, $_allRows));
    $_inPlaceholders = implode(',', array_fill(0, count($_resultIds), '?'));
    $_stComptaTypes = $pdo->prepare("
        SELECT c.user_id, ct.id AS type_id, ct.label, ct.color
        FROM compta c
        JOIN compta_type ct ON ct.id = c.type_id
        WHERE c.user_id IN ($_inPlaceholders)
        GROUP BY c.user_id, ct.id, ct.label, ct.color
        ORDER BY ct.sort_order ASC, ct.label ASC
    ");
    $_stComptaTypes->execute($_resultIds);
    while ($_rct = $_stComptaTypes->fetchObject()) {
        $_uid = (int)$_rct->user_id;
        if (!isset($_userComptaTypes[$_uid])) $_userComptaTypes[$_uid] = [];
        $_userComptaTypes[$_uid][] = $_rct;
    }
}

$rowCount = 0;
foreach ($_allRows as $row) {
    $id = $row->id;
    $displayLine = true;
    $user = new User();
    $user->lookupUser($id);

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
    } else if ($team == FILTER_UNPAID_COTI_3Y) {
        $displayLine = false;
        if (empty($_noCotiMembers[$user->getId()])) {
            $_u3 = $_coti3333[$user->getId()] ?? null;
            if ($_u3 && (int)$_u3->ever_coti > 0 && (int)$_u3->recent_coti === 0) {
                $displayLine = true;
            }
        }
    } else if ($team == FILTER_NO_ACTIVITY_10Y) {
        $displayLine = true;
        $_c5 = $_compta5555[$user->getId()] ?? null;
        if ($_c5 && (int)$_c5->recent_count > 0) {
            $displayLine = false;
        }
    } else if ($team == FILTER_NON_INSTIT_LAST_YEAR) {
        $displayLine = false;
        if (!empty($_nonInstit6666[$user->getId()])) {
            $displayLine = true;
        }
    } else if ($team == FILTER_UNPAID_COTI_CURRENT) {
        if (!empty($_noCotiMembers[$user->getId()]) || $user->isCotisationPayed($year) > -1) {
            $displayLine = false;
        }
    }


    if ($displayLine) {
        $rowCount++;
        $firstName = $row->firstname;
        $lastName = $row->lastname;
        $society = $row->society;
        $sexe = $row->sexe;
        if ($sexe == "na") { $sexe = ""; }
        else if ($sexe == "f") { $sexe = "<i class='fas fa-female s'></i><span class='d-none'>Madame</span>"; }
        else if ($sexe == "m") { $sexe = "<i class='fas fa-male s'></i><span class='d-none'>Monsieur</span>"; }
        else if ($sexe == "hf") { $sexe = "<i class='fas fa-male s'></i><i class='fas fa-female s'></i><span class='d-none'>Madame et Monsieur</span>"; }
        $address = $row->address;
        $npa = $row->npa;
        $email = $row->email;
        $firstName = htmlentities($firstName,ENT_COMPAT,$charset);
        $lastName = htmlentities($lastName,ENT_COMPAT,$charset);
        $address = htmlentities($address,ENT_COMPAT,$charset);
        $npa = htmlentities($npa,ENT_COMPAT,$charset);
        $emailStr = htmlentities($email,ENT_COMPAT,$charset);
        if ($addMembership != -1) {
            $user->addMembership($addMembership);
        }
        if ($removeMembership != -1) {
            $user->removeMembership($removeMembership);
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
            <td class="d-none d-sm-table-cell d-md-table-cell"><?=timeStampToformatedDate($user->getCreationDate())?></td>
            <td class="d-none d-sm-table-cell" style="white-space:nowrap">
              <?php if (!empty($_userComptaTypes[$id])): ?>
              <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&userid=<?= (int)$id ?>" class="text-decoration-none">
              <?php foreach ($_userComptaTypes[$id] as $_ct):
                  $_bgClass = str_replace('-subtle', '', $_ct->color ?: 'bg-secondary');
                  if (str_starts_with($_bgClass, 'ca-')) {
                      $_badgeColor = 'text-bg-' . $_bgClass; // ca-orange → text-bg-ca-orange
                  } else {
                      $_badgeColor = preg_replace('/^bg-/', 'text-bg-', $_bgClass); // bg-primary → text-bg-primary
                  }
              ?>
                <span class="badge <?= htmlspecialchars($_badgeColor, ENT_QUOTES, $charset) ?>"
                      title="<?= htmlspecialchars($_ct->label, ENT_QUOTES, $charset) ?>"
                      style="font-size:0.6rem;padding:2px 4px"
                      ><?= htmlspecialchars(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtoupper(mb_substr($_ct->label, 0, 3))), ENT_QUOTES, $charset) ?></span>
              <?php endforeach ?>
              </a>
              <?php endif ?>
            </td>
            <?php if ($team == FILTER_NO_ACTIVITY_10Y):
                $_c5 = $_compta5555[$user->getId()] ?? null; ?>
            <td class="d-none d-sm-table-cell text-muted" style="font-size:0.72rem;line-height:1.3">
                <?php if ($_c5 && (int)$_c5->total > 0):
                    $parts = [(int)$_c5->total . ' entr.'];
                    if ((int)$_c5->coti_count > 0) $parts[] = (int)$_c5->coti_count . ' coti';
                    echo htmlspecialchars(implode(' · ', $parts));
                    echo '<br><span style="opacity:0.55">dernier: ' . date('Y', (int)$_c5->last_date) . '</span>';
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
    ?><span style="color:red">manque a gagner de CHF <?=$rowCount*50?> pour <?=$year?> avec les cotis non pay&eacute;es...</span><br/><?php
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
$(document).ready(function () {
    $.fn.dataTable.moment('DD/MM/YYYY');
    if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
    $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        dom: CA_DT_DOM,
        buttons: [...CA_DT_BUTTONS, CA_DT_COLVIS],
        columnDefs: [
            { targets: [0, 4, 5, 7], visible: false }
        ],
        language: Object.assign({}, CA_DT_LANGUAGE, { info: '_TOTAL_ profils', infoFiltered: '(filtrés sur _MAX_)' })
    });
});
</script>
