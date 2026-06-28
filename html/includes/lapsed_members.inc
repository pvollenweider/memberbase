<?php
$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date("Y");
if ($year <= 0) { $year = (int)date("Y"); }

$membreTeamId = (int)($appSettings['default_team'] ?? 0);

$prevTeamStmt = $pdo->prepare("SELECT id, name FROM team WHERE name = ?");
$prevTeamStmt->execute(["Membre " . ($year - 1)]);
$prevTeam = $prevTeamStmt->fetch(PDO::FETCH_OBJ);
$prevTeamId = $prevTeam ? (int)$prevTeam->id : 0;

$rows = [];
if ($prevTeamId > 0 && $membreTeamId > 0) {
    $sql = "
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        FROM users u
        JOIN user_properties up ON up.user_id = u.id AND up.parameter = ? AND up.value = 'true'
        WHERE u.status=1 AND u.id NOT IN (
            SELECT user_id FROM user_properties WHERE parameter = ? AND value = 'true'
        )
        ORDER BY u.lastname, u.firstname, u.society
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["team_$prevTeamId", "team_$membreTeamId"]);
    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
}
$count = count($rows);
?>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=resume&amp;year=<?= $year ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Retour à l'aperçu des dons
  </a>
  <span class="text-muted" style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">
    Membres perdus <?= $year-1 ?> → <?= $year ?>
  </span>

  <div class="dropdown ms-1">
    <button class="ca-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <?= $year ?>
    </button>
    <ul class="dropdown-menu">
      <?php for ($i = 0; $i < 8; $i++): $y = (int)date("Y") - $i; ?>
      <li><a class="dropdown-item<?= $y === $year ? ' active' : '' ?>"
             href="<?= $_SERVER['PHP_SELF'] ?>?view=lapsedMembers&amp;year=<?= $y ?>"><?= $y ?></a></li>
      <?php endfor ?>
    </ul>
  </div>
</div>

<?php if ($prevTeamId <= 0): ?>
<div class="alert alert-secondary" style="font-size:0.85rem">
  Aucune équipe «Membre <?= $year-1 ?>» trouvée en base.
</div>
<?php else: ?>

<form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
  <input type="hidden" name="action"    value="createLapsedGroup">
  <input type="hidden" name="groupType" value="members">
  <input type="hidden" name="year"      value="<?= $year ?>">
  <input type="hidden" name="view"      value="lapsedMembers">
  <button type="submit" class="btn btn-outline-warning btn-sm mb-3"
          onclick="return confirm('Créer le groupe «Membres à relancer <?= $year ?>» avec <?= $count ?> personne(s)?')">
    <i class="fas fa-users me-1" aria-hidden="true"></i>Créer groupe «Membres à relancer <?= $year ?>»
  </button>
</form>

<div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" role="status" style="font-size:0.85rem">
  <i class="fas fa-user-clock mt-1 flex-shrink-0" aria-hidden="true"></i>
  <span><strong><?= $count ?> membre<?= $count > 1 ? 's' : '' ?></strong> étaient dans «Membre <?= $year-1 ?>» mais pas dans «Membre <?= $year ?>».</span>
</div>

<?php
$dt_order      = [[1, 'asc']];
$extra_columns = [];
$row_href      = fn($row) => $_SERVER['PHP_SELF'] . '?view=compta&userid=' . (int)$row->id;
include '_donor_table.inc';
?>
<?php endif ?>
