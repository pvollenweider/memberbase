<?php
$viewall = false;
if (isset($_GET['viewall']) && ($_GET['viewall'] === 'true' || $_GET['viewall'] === 'false')) {
    $viewall = $_GET['viewall'] === 'true';
}

$whereHidden = $viewall ? "" : "WHERE hidden = 0";
$query = "SELECT id, name, hidden FROM team $whereHidden ORDER BY name";
$stmt  = $pdo->query($query);

$memberTeams    = [];
$nonMemberTeams = [];
while ($row = $stmt->fetchObject()) {
    if ($user->isMemberOfTeam($row->id)) {
        $memberTeams[] = $row;
    } else {
        $nonMemberTeams[] = $row;
    }
}
?>

<p class="form-section-title" style="margin-top:0">Groupes</p>

<?php if (empty($memberTeams)): ?>
    <p class="text-muted small">Aucun groupe.</p>
<?php else: ?>
    <div class="d-flex flex-wrap gap-1 mb-3">
        <?php foreach ($memberTeams as $t): ?>
            <a class="member-pill"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=removeMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>"
               title="Retirer de <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>">
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                <span class="pill-x" aria-hidden="true">✕</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="form-section-title">Ajouter</p>

<div class="group-add-list">
    <?php foreach ($nonMemberTeams as $t): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=addMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>">
            <i class="far fa-plus-square" aria-hidden="true"></i>
            <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
        </a>
    <?php endforeach; ?>
</div>

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
    <?php endif; ?>
</div>
