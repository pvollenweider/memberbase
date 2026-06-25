<?php
$viewall = false;
if (isset($_GET['viewall']) && ($_GET['viewall'] === 'true' || $_GET['viewall'] === 'false')) {
    $viewall = $_GET['viewall'] === 'true';
}

$whereHidden = $viewall ? "" : "AND t.hidden = 0";

// Fetch teams with category info
$stmtAll = $pdo->query("
    SELECT t.id, t.name, t.hidden,
           COALESCE(cat.name, '') AS cat_name,
           COALESCE(cat.id, 0) AS cat_id,
           COALESCE(cat.sort_order, 99999) AS cat_sort
    FROM team t
    LEFT JOIN (
        SELECT j.teamid, c.id, c.name, c.sort_order
        FROM metagroup j
        JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
        WHERE j.teamid IS NOT NULL
        GROUP BY j.teamid
    ) cat ON cat.teamid = t.id
    WHERE 1=1 $whereHidden
    ORDER BY cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
");

$memberTeams    = [];
$nonMemberTeams = [];
while ($row = $stmtAll->fetchObject()) {
    if ($user->isMemberOfTeam($row->id)) {
        $memberTeams[] = $row;
    } else {
        $nonMemberTeams[] = $row;
    }
}

$_catSortFn = function($a, $b) {
    if (!$a['cat_id'] && $b['cat_id']) return 1;
    if ($a['cat_id'] && !$b['cat_id']) return -1;
    $s = $a['cat_sort'] <=> $b['cat_sort'];
    return $s !== 0 ? $s : strcmp($a['cat_name'], $b['cat_name']);
};

// Group member teams by category
$memberBycat = [];
foreach ($memberTeams as $t) {
    $key = $t->cat_id . '|' . ($t->cat_name ?: '');
    if (!isset($memberBycat[$key])) {
        $memberBycat[$key] = ['cat_id' => $t->cat_id, 'cat_name' => $t->cat_name, 'cat_sort' => (int)$t->cat_sort, 'teams' => []];
    }
    $memberBycat[$key]['teams'][] = $t;
}
uasort($memberBycat, $_catSortFn);

// Group non-member teams by category
$nonMemberBycat = [];
foreach ($nonMemberTeams as $t) {
    $key = $t->cat_id . '|' . ($t->cat_name ?: '');
    if (!isset($nonMemberBycat[$key])) {
        $nonMemberBycat[$key] = ['cat_id' => $t->cat_id, 'cat_name' => $t->cat_name, 'cat_sort' => (int)$t->cat_sort, 'teams' => []];
    }
    $nonMemberBycat[$key]['teams'][] = $t;
}
uasort($nonMemberBycat, $_catSortFn);
?>

<p class="form-section-title" style="margin-top:0">Groupes</p>

<?php if (empty($memberTeams)): ?>
    <p class="text-muted small">Aucun groupe.</p>
<?php else: ?>
    <?php foreach ($memberBycat as $group): ?>
        <?php if ($group['cat_name']): ?>
        <p class="mb-1" style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <?= htmlentities($group['cat_name'], ENT_COMPAT, $charset) ?>
        </p>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <?php foreach ($group['teams'] as $t): ?>
                <a class="member-pill"
                   href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=removeMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>"
                   title="Retirer de <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>">
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                    <span class="pill-x" aria-hidden="true">✕</span>
                </a>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
<?php endif ?>

<p class="form-section-title">Ajouter</p>

<?php foreach ($nonMemberBycat as $group): ?>
    <?php if ($group['cat_name']): ?>
    <p class="mb-1 mt-2" style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
        <?= htmlentities($group['cat_name'], ENT_COMPAT, $charset) ?>
    </p>
    <?php endif ?>
    <div class="group-add-list mb-1">
        <?php foreach ($group['teams'] as $t): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=addMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>">
                <i class="far fa-plus-square" aria-hidden="true"></i>
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
            </a>
        <?php endforeach ?>
    </div>
<?php endforeach ?>

<div class="mt-3">
    <?php if (!$viewall): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&id=<?= $user->id ?>&viewall=true">
            <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Groupes masqués
        </a>
    <?php else: ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&id=<?= $user->id ?>">
            <i class="fas fa-eye me-1" aria-hidden="true"></i>Masquer les groupes cachés
        </a>
    <?php endif ?>
</div>
