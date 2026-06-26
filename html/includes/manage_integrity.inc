<?php
// Groups hidden but still assigned to a category (is_filter=0)
$stmtCat = $pdo->query("
    SELECT DISTINCT t.id AS team_id, t.name AS team_name,
           m.id AS mg_id, m.name AS mg_name
    FROM team t
    JOIN metagroup j ON j.teamid = t.id
    JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 0
    WHERE t.hidden = 1
    ORDER BY m.name, t.name
");
$hiddenInCats = $stmtCat->fetchAll(PDO::FETCH_OBJ);

// Groups hidden but still assigned to a metagroup (is_filter=1)
$stmtMg = $pdo->query("
    SELECT DISTINCT t.id AS team_id, t.name AS team_name,
           m.id AS mg_id, m.name AS mg_name
    FROM team t
    JOIN metagroup j ON j.teamid = t.id
    JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 1
    WHERE t.hidden = 1
    ORDER BY m.name, t.name
");
$hiddenInMeta = $stmtMg->fetchAll(PDO::FETCH_OBJ);

// Groups hidden but still have members assigned
$stmtMembers = $pdo->query("
    SELECT t.id AS team_id, t.name AS team_name,
           COUNT(up.user_id) AS member_count
    FROM team t
    JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
    WHERE t.hidden = 1
    GROUP BY t.id, t.name
    ORDER BY t.name
");
$hiddenWithMembers = $stmtMembers->fetchAll(PDO::FETCH_OBJ);
?>

<p class="form-section-title mb-1">
  <i class="fas fa-stethoscope me-1" aria-hidden="true"></i>Intégrité des groupes
</p>
<p class="small text-muted mb-4">Groupes masqués encore assignés à une catégorie, un métagroupe, ou ayant des membres actifs.</p>

<?php if (empty($hiddenInCats) && empty($hiddenInMeta) && empty($hiddenWithMembers)): ?>
<div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Aucun problème détecté.
</div>
<?php endif ?>

<?php if (!empty($hiddenInCats)): ?>
<p class="fw-semibold mb-1" style="font-size:0.85rem">
  <i class="fas fa-tag me-1 text-warning" aria-hidden="true"></i>
  Groupes masqués dans une catégorie
  <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInCats) ?></span>
</p>
<table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
  <thead>
    <tr>
      <th>Groupe masqué</th>
      <th>Catégorie</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($hiddenInCats as $row): ?>
    <tr>
      <td class="text-muted">
        <i class="fas fa-eye-slash me-1" style="font-size:0.7rem" aria-hidden="true"></i>
        <?= htmlentities($row->team_name, ENT_COMPAT, $charset) ?>
      </td>
      <td><?= htmlentities($row->mg_name, ENT_COMPAT, $charset) ?></td>
      <td class="text-end">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$row->mg_id ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">
          Éditer
        </a>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>

<?php if (!empty($hiddenInMeta)): ?>
<p class="fw-semibold mb-1" style="font-size:0.85rem">
  <i class="fas fa-layer-group me-1 text-warning" aria-hidden="true"></i>
  Groupes masqués dans un métagroupe
  <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInMeta) ?></span>
</p>
<table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
  <thead>
    <tr>
      <th>Groupe masqué</th>
      <th>Métagroupe</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($hiddenInMeta as $row): ?>
    <tr>
      <td class="text-muted">
        <i class="fas fa-eye-slash me-1" style="font-size:0.7rem" aria-hidden="true"></i>
        <?= htmlentities($row->team_name, ENT_COMPAT, $charset) ?>
      </td>
      <td><?= htmlentities($row->mg_name, ENT_COMPAT, $charset) ?></td>
      <td class="text-end">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$row->mg_id ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">
          Éditer
        </a>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>

<?php if (!empty($hiddenWithMembers)): ?>
<p class="fw-semibold mb-1" style="font-size:0.85rem">
  <i class="fas fa-users me-1 text-warning" aria-hidden="true"></i>
  Groupes masqués avec des membres
  <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenWithMembers) ?></span>
</p>
<table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
  <thead>
    <tr>
      <th>Groupe masqué</th>
      <th>Membres</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($hiddenWithMembers as $row): ?>
    <tr>
      <td class="text-muted">
        <i class="fas fa-eye-slash me-1" style="font-size:0.7rem" aria-hidden="true"></i>
        <?= htmlentities($row->team_name, ENT_COMPAT, $charset) ?>
      </td>
      <td><?= (int)$row->member_count ?></td>
      <td class="text-end">
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= (int)$row->team_id ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">
          Éditer
        </a>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
