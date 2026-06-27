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
                    if ($metagroup > 0) {
                        $mg = new Metagroup();
                        $mg->lookupMetagroup($metagroup);
                        $currentTeamTitle = $mg->getName();
                    } else if ($team == -3) {
                        $currentTeamTitle = $GLOBAL['allExceptArchives'];
                    } else if ($team == -3333) {
                        $currentTeamTitle = $GLOBAL['cotiUnpayedLast3Years'];
                    } else if ($team == -5555) {
                        $currentTeamTitle = $GLOBAL['nothingLast10Years'];
                    } else if ($team == -6666) {
                        $currentTeamTitle = $GLOBAL['nonInstitPayedSomethingLastYear'];
                    } else if ($team == -4) {
                        $currentTeamTitle = $GLOBAL['cotiUnpayed'];
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
                       href="<?= $_SERVER['PHP_SELF'] ?>?team=-3"><?= $GLOBAL['allExceptArchives'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] ?>?team=-3333"><?= $GLOBAL['cotiUnpayedLast3Years'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] ?>?team=-5555"><?= $GLOBAL['nothingLast10Years'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] ?>?team=-4"><?= $GLOBAL['cotiUnpayed'] ?></a>
                    <a class="dropdown-item" style="padding-left:1.5rem"
                       href="<?= $_SERVER['PHP_SELF'] ?>?team=-6666"><?= $GLOBAL['nonInstitPayedSomethingLastYear'] ?></a>

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
     class="ms-auto btn btn-sm btn-outline-secondary py-0"
     title="<?= $GLOBAL['addUser'] ?>"><i class="fas fa-user-plus" aria-hidden="true"></i></a>
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
    if ($team == -4) {
        $query .= ",compta ";
    }
    // -3/-5555/-6666 no longer need user_properties — status=1 replaces archive group filter
    if ($team != -1 && $team != -3 && $team != -5555 && $team != -6666) {
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
    if ($team == -2) {
        $query .= " AND users.id=user_properties.user_id AND NOT (";
        $query .= "    user_properties.parameter='team_13'";
        $query .= "    OR user_properties.parameter='team_10'";
        $query .= "    OR user_properties.parameter='team_20'";
        $query .= " )";
    } else if ($team == -3 || $team == -5555 || $team == -6666) {
        // status=1 already excludes archived members — no user_properties condition needed
    } else if ($team == -4 || $team == -1234 || $team == -3333) {
        # search member
        $query .= " AND users.id=user_properties.user_id ";
        $query .= "AND ( ";
        $query .= "user_properties.parameter='team_$membre') ";
    } else if ($team == -5) {
        $query .= " AND users.id=user_properties.user_id AND (";
        $query .= "    user_properties.parameter='team_6'";
        $query .= "    OR user_properties.parameter='team_5'";
        $query .= "    OR user_properties.parameter='team_9'";
        $query .= "    OR user_properties.parameter='team_7')";
    } else if ($team == -444) {
        # search member
        $query .= " AND users.id=user_properties.user_id ";
        $query .= "AND (user_properties.parameter='team_56' OR ";
        $query .= "user_properties.parameter='team_62' OR ";
        $query .= "user_properties.parameter='team_7' OR ";
        $query .= "user_properties.parameter='team_58' ) ";
    } else if ($team == -6) {
        $query .= " AND users.id=user_properties.user_id ";
        $query .= "AND user_properties.parameter='team_7' ";

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
    <th class="d-none d-sm-table-cell d-md-table-cell">
            <?=$GLOBAL['email']?>
    </th>
    <th class="d-none d-sm-table-cell d-md-table-cell">
            <?=$GLOBAL['creationDate']?>
    </th>
    <?php if ($team == -5555): ?>
    <th class="d-none d-sm-table-cell" style="font-size:0.75rem;white-space:nowrap">Historique compta</th>
    <?php endif ?>
    <th></th>
</tr>
</thead>
<tbody>
<?php
// Pre-fetch non-institutional compta for -6666 (replaces 1 SQL/user)
$_nonInstit6666 = [];
if ($team == -6666) {
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

// Pre-fetch cotisation years per user for -3333 (replaces 4x isCotisationPayed SQL/user)
$_coti3333 = [];
if ($team == -3333) {
    $_from3333 = mktime(0, 0, 0, 1, 0, $year - 3);
    $_to3333   = mktime(0, 0, 0, 1, 1, $year + 1);
    $_st3333 = $pdo->query("
        SELECT c.user_id, YEAR(FROM_UNIXTIME(c.date)) AS yr
        FROM compta c
        JOIN compta_type ct ON ct.id = c.type_id AND ct.is_cotisation = 1
        WHERE c.date > $_from3333 AND c.date < $_to3333
        GROUP BY c.user_id, YEAR(FROM_UNIXTIME(c.date))
    ");
    while ($_r3 = $_st3333->fetchObject()) {
        $_coti3333[(int)$_r3->user_id][(int)$_r3->yr] = true;
    }
}

// Pre-fetch compta summary for -5555 to avoid N+1 queries (hasComptaEntries per user)
$_compta5555 = [];
if ($team == -5555) {
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
$rowCount = 0;
while ($row = $stmt->fetchObject()) {
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
    } else if ($team == -3333) {
        $displayLine = false;
        $_u3 = $_coti3333[$user->getId()] ?? [];
        if (empty($_u3[$year]) && empty($_u3[$year-1]) && empty($_u3[$year-2]) && empty($_u3[$year-3])) {
            $displayLine = true;
        }
    } else if ($team == -5555) {
        $displayLine = true;
        $_c5 = $_compta5555[$user->getId()] ?? null;
        if ($_c5 && (int)$_c5->recent_count > 0) {
            $displayLine = false;
        }
    } else if ($team == -6666) {
        $displayLine = false;
        if (!empty($_nonInstit6666[$user->getId()])) {
            $displayLine = true;
        }
    } else if ($team == -444) {
        $displayLine = false;
        if ($user->hasDonated($year) != -1 || $user->hasDonated($year-1) != -1) {
            $displayLine = true;
        }
        if ($user->isMemberOfTeam(6) || $user->isMemberOfTeam(47) || $user->isMemberOfTeam(5) || $user->isMemberOfTeam(2) || $user->isMemberOfTeam(22)) {
            $displayLine = false;
        }
    } else if ($team == -6) {
        $displayLine = false;
        if ($user->hasPayed(2011) == -1) {
            $displayLine = true;
        }
        if ($user->isMemberOfTeam(6) || $user->isMemberOfTeam(5)) {
            $displayLine = true;
        }
        $displayLine = !$displayLine;
    } else if ($team == -4) {
        if ($user->isCotisationPayed($year) > -1) {
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
        <tr class="position-relative">
            <td class="d-none d-sm-table-cell d-md-table-cell"><?=$sexe?></td>
            <td class="bold"><div class="text-truncate" style="max-width:200px"><?=$society?></div></td>
            <td class="text-nowrap"><?=$lastName?></td>
            <td class="text-nowrap2"><?=$firstName?></td>
            <td class="text-nowrap d-none d-sm-table-cell"><div class="text-truncate" style="max-width:200px"><?=$address?></div></td>
            <td class="text-nowrap d-none d-sm-table-cell"><?=$npa?></td>
            <td class="d-none d-sm-table-cell d-md-table-cell"><a href="mailto:<?=$email?>" style="position:relative;z-index:2"><?=$emailStr?></a></td>
            <td class="d-none d-sm-table-cell d-md-table-cell"><?=timeStampToformatedDate($user->getCreationDate())?></td>
            <?php if ($team == -5555):
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
            <td>
                <a href="<?=$_SERVER['PHP_SELF']?>?view=generalData&amp;id=<?=(int)$id?>"
                   class="stretched-link" hx-boost="false"
                   aria-label="Ouvrir la fiche de <?= htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, $charset) ?>"></a>
                </a>
            </td>
        </tr>
        <?php
    }
}
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
if ($team == -4) {
    ?><span style="color:red">manque a gagner de CHF <?=$rowCount*50?> pour <?=$year?> avec les cotis non pay&eacute;es...</span><br/><?php
}
?>
<script>
$(document).ready(function () {
    $.fn.dataTable.moment('DD/MM/YYYY');
    if ($.fn.DataTable.isDataTable('.export')) { $('.export').DataTable().destroy(); }
    $('.export').DataTable({
        order: [[2, 'asc']],
        paging: false,
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' },
                    { extend: 'pdf',   text: '<i class="fas fa-file-pdf me-2" aria-hidden="true"></i>PDF' }
                ]
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns me-1" aria-hidden="true"></i>Colonnes',
                className: 'btn btn-dt'
            }
        ],
        columnDefs: [
            { targets: 7, visible: false }
        ],
        language: {
            info: '_TOTAL_ membres',
            infoFiltered: '(filtrés sur _MAX_)',
            search: '',
            searchPlaceholder: 'Filtrer…'
        }
    });
});
</script>
