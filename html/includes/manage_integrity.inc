<?php
// Duplicate members — same firstName+lastName
$stmtDupName = $pdo->query("
    SELECT firstName, lastName, COUNT(*) AS cnt,
           GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
    FROM users
    WHERE TRIM(firstName) != '' OR TRIM(lastName) != ''
    GROUP BY TRIM(LOWER(firstName)), TRIM(LOWER(lastName))
    HAVING COUNT(*) > 1
    ORDER BY lastName, firstName
");
$dupNames = $stmtDupName->fetchAll(PDO::FETCH_OBJ);

// Duplicate members — same email (non-empty)
$stmtDupEmail = $pdo->query("
    SELECT email, COUNT(*) AS cnt,
           GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
    FROM users
    WHERE TRIM(email) != ''
    GROUP BY TRIM(LOWER(email))
    HAVING COUNT(*) > 1
    ORDER BY email
");
$dupEmails = $stmtDupEmail->fetchAll(PDO::FETCH_OBJ);

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
  <i class="fas fa-stethoscope me-1" aria-hidden="true"></i>Intégrité
</p>
<p class="small text-muted mb-4">Doublons potentiels dans les membres et groupes masqués encore assignés.</p>

<?php if (empty($dupNames) && empty($dupEmails) && empty($hiddenInCats) && empty($hiddenInMeta) && empty($hiddenWithMembers)): ?>
<div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Aucun problème détecté.
</div>
<?php endif ?>

<?php if (!empty($dupNames)): ?>
<p class="fw-semibold mb-1" style="font-size:0.85rem">
  <i class="fas fa-user-friends me-1 text-danger" aria-hidden="true"></i>
  Membres avec même nom
  <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupNames) ?></span>
</p>
<table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
  <thead>
    <tr>
      <th>Prénom / Nom</th>
      <th style="width:3rem" class="text-center">Fiches</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dupNames as $dup):
    $ids = explode(',', $dup->ids); ?>
    <tr>
      <td><?= htmlentities($dup->firstName . ' ' . $dup->lastName, ENT_COMPAT, $charset) ?></td>
      <td class="text-center text-muted"><?= (int)$dup->cnt ?></td>
      <td class="text-end">
        <?php foreach ($ids as $uid): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$uid ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" style="font-size:0.75rem">#<?= (int)$uid ?></a>
        <?php endforeach ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>

<?php if (!empty($dupEmails)): ?>
<p class="fw-semibold mb-1" style="font-size:0.85rem">
  <i class="fas fa-envelope me-1 text-danger" aria-hidden="true"></i>
  Membres avec même email
  <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupEmails) ?></span>
</p>
<table class="table table-sm align-middle mb-4" style="font-size:0.82rem">
  <thead>
    <tr>
      <th>Email</th>
      <th style="width:3rem" class="text-center">Fiches</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($dupEmails as $dup):
    $ids = explode(',', $dup->ids); ?>
    <tr>
      <td><?= htmlentities($dup->email, ENT_COMPAT, $charset) ?></td>
      <td class="text-center text-muted"><?= (int)$dup->cnt ?></td>
      <td class="text-end">
        <?php foreach ($ids as $uid): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$uid ?>"
           class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" style="font-size:0.75rem">#<?= (int)$uid ?></a>
        <?php endforeach ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
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
