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
        WHERE u.id NOT IN (
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

<table class="table table-hover table-sm export">
<thead>
<tr>
  <th><?= $GLOBAL['society'] ?></th>
  <th><?= $GLOBAL['lastName'] ?></th>
  <th><?= $GLOBAL['firstName'] ?></th>
  <th><?= $GLOBAL['email'] ?></th>
  <th><?= $GLOBAL['address'] ?></th>
  <th><?= $GLOBAL['npa'] ?></th>
  <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row):
  $society   = htmlentities($row->society   ?? '', ENT_COMPAT, $charset);
  $lastName  = htmlentities($row->lastname  ?? '', ENT_COMPAT, $charset);
  $firstName = htmlentities($row->firstname ?? '', ENT_COMPAT, $charset);
  $email     = htmlentities($row->email     ?? '', ENT_COMPAT, $charset);
  $address   = htmlentities($row->address   ?? '', ENT_COMPAT, $charset);
  $npa       = htmlentities($row->npa       ?? '', ENT_COMPAT, $charset);
?>
<tr>
  <td><?= $society ?></td>
  <td><strong><?= $lastName ?></strong></td>
  <td><?= $firstName ?></td>
  <td><?= $email ?></td>
  <td><?= $address ?></td>
  <td><?= $npa ?></td>
  <td class="text-end">
    <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= (int)$row->id ?>"
       class="btn btn-outline-secondary btn-sm py-0 px-1"
       aria-label="Voir compta de <?= htmlspecialchars($row->firstname . ' ' . $row->lastname, ENT_QUOTES, $charset) ?>">
      <i class="fas fa-pencil-alt" aria-hidden="true"></i>
    </a>
  </td>
</tr>
<?php endforeach ?>
</tbody>
</table>

<script>
$(document).ready(function() {
    $.fn.dataTable.moment('DD/MM/YYYY');
    $('.export').DataTable({
        order: [[1, 'asc']],
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
            }
        ],
        language: {
            info: '_TOTAL_ entrées',
            infoFiltered: '(filtrées sur _MAX_)',
            search: '',
            searchPlaceholder: 'Filtrer…'
        }
    });
});
</script>
<?php endif ?>
