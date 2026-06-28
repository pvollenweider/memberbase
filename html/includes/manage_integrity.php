<?php
/**
 * Admin tool for detecting and resolving data integrity issues.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Duplicate members — same firstName+lastName
$stmtDupName = $pdo->query("
    SELECT firstName, lastName, COUNT(*) AS cnt,
           GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
    FROM users
    WHERE status=1 AND (TRIM(firstName) != '' OR TRIM(lastName) != '')
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
    WHERE status=1 AND TRIM(email) != ''
    GROUP BY TRIM(LOWER(email))
    HAVING COUNT(*) > 1
    ORDER BY email
");
$dupEmails = $stmtDupEmail->fetchAll(PDO::FETCH_OBJ);

// Groups hidden but still assigned to a category (is_filter=0)
$stmtCat = $pdo->query("
    SELECT DISTINCT t.id AS team_id, t.name AS team_name,
           m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
    FROM team t
    JOIN metagroup j ON j.teamid = t.id
    JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 0
    WHERE t.hidden = 1
    ORDER BY m.sort_order, m.name, t.name
");
$hiddenInCats = $stmtCat->fetchAll(PDO::FETCH_OBJ);

// Groups hidden but still assigned to a metagroup (is_filter=1)
$stmtMg = $pdo->query("
    SELECT DISTINCT t.id AS team_id, t.name AS team_name,
           m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
    FROM team t
    JOIN metagroup j ON j.teamid = t.id
    JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 1
    WHERE t.hidden = 1
    ORDER BY m.sort_order, m.name, t.name
");
$hiddenInMeta = $stmtMg->fetchAll(PDO::FETCH_OBJ);

// Groups hidden but still have members assigned
$stmtMembers = $pdo->query("
    SELECT t.id AS team_id, t.name AS team_name,
           COUNT(up.user_id) AS member_count
    FROM team t
    JOIN user_properties up ON up.parameter = CONCAT('team_', t.id)
    JOIN users u ON u.id = up.user_id AND u.status = 1
    WHERE t.hidden = 1
    GROUP BY t.id, t.name
    ORDER BY t.name
");
$hiddenWithMembers = $stmtMembers->fetchAll(PDO::FETCH_OBJ);

$allOk = empty($dupNames) && empty($dupEmails) && empty($hiddenInCats) && empty($hiddenInMeta) && empty($hiddenWithMembers);
?>

<p class="form-section-title mb-1">
  <i class="fas fa-stethoscope me-1" aria-hidden="true"></i>Intégrité
</p>
<p class="small text-muted mb-3">Doublons potentiels dans les membres et groupes masqués encore assignés.</p>

<?php if ($allOk): ?>
<div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Aucun problème détecté.
</div>
<?php else: ?>

<details class="ca-integrity-section mb-3">
  <summary class="ca-integrity-summary">
    <i class="fas fa-user-group me-1 <?= !empty($dupNames) ? 'text-danger' : 'text-muted' ?>" aria-hidden="true"></i>
    Membres avec même nom
    <?php if (!empty($dupNames)): ?>
      <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupNames) ?></span>
    <?php else: ?>
      <span class="badge text-bg-success ms-1" style="font-size:0.7rem">0</span>
    <?php endif ?>
  </summary>
  <?php if (!empty($dupNames)): ?>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
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
        <td><?= htmlentities(strtoupper($dup->lastName) . ' ' . $dup->firstName, ENT_COMPAT, $charset) ?></td>
        <td class="text-center text-muted"><?= (int)$dup->cnt ?></td>
        <td class="text-end">
          <?php foreach ($ids as $uid): ?>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$uid ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" style="font-size:0.75rem">#<?= (int)$uid ?></a>
          <?php endforeach ?>
          <?php if (count($ids) === 2): ?>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=mergeUsers&amp;a=<?= (int)$ids[0] ?>&amp;b=<?= (int)$ids[1] ?>"
             class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:0.75rem"
             hx-boost="false">
            <i class="fas fa-code-merge me-1" aria-hidden="true"></i>Fusionner
          </a>
          <?php elseif (count($ids) > 2): ?>
          <select class="form-select form-select-sm d-inline-block w-auto py-0 border-danger text-danger"
                  style="font-size:0.75rem;cursor:pointer"
                  data-no-dirty onchange="if(this.value){window.__dirtyOverride=true;window.location=this.value;}this.value=''">
            <option value=""><i class="fas fa-code-merge"></i> Fusionner…</option>
            <?php for ($pi=0;$pi<count($ids);$pi++) for ($pj=$pi+1;$pj<count($ids);$pj++): ?>
            <option value="<?= $_SERVER['PHP_SELF'] ?>?view=mergeUsers&a=<?= (int)$ids[$pi] ?>&b=<?= (int)$ids[$pj] ?>">
              #<?= (int)$ids[$pi] ?> + #<?= (int)$ids[$pj] ?>
            </option>
            <?php endfor ?>
          </select>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="text-muted small mt-2 mb-0">Aucun doublon de nom détecté.</p>
  <?php endif ?>
</details>

<details class="ca-integrity-section mb-3">
  <summary class="ca-integrity-summary">
    <i class="fas fa-envelope me-1 <?= !empty($dupEmails) ? 'text-danger' : 'text-muted' ?>" aria-hidden="true"></i>
    Membres avec même email
    <?php if (!empty($dupEmails)): ?>
      <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupEmails) ?></span>
    <?php else: ?>
      <span class="badge text-bg-success ms-1" style="font-size:0.7rem">0</span>
    <?php endif ?>
  </summary>
  <?php if (!empty($dupEmails)): ?>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
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
          <?php if (count($ids) === 2): ?>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=mergeUsers&amp;a=<?= (int)$ids[0] ?>&amp;b=<?= (int)$ids[1] ?>"
             class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size:0.75rem"
             hx-boost="false">
            <i class="fas fa-code-merge me-1" aria-hidden="true"></i>Fusionner
          </a>
          <?php elseif (count($ids) > 2): ?>
          <select class="form-select form-select-sm d-inline-block w-auto py-0 border-danger text-danger"
                  style="font-size:0.75rem;cursor:pointer"
                  data-no-dirty onchange="if(this.value){window.__dirtyOverride=true;window.location=this.value;}this.value=''">
            <option value="">Fusionner…</option>
            <?php for ($pi=0;$pi<count($ids);$pi++) for ($pj=$pi+1;$pj<count($ids);$pj++): ?>
            <option value="<?= $_SERVER['PHP_SELF'] ?>?view=mergeUsers&a=<?= (int)$ids[$pi] ?>&b=<?= (int)$ids[$pj] ?>">
              #<?= (int)$ids[$pi] ?> + #<?= (int)$ids[$pj] ?>
            </option>
            <?php endfor ?>
          </select>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="text-muted small mt-2 mb-0">Aucun doublon d'email détecté.</p>
  <?php endif ?>
</details>

<details class="ca-integrity-section mb-3">
  <summary class="ca-integrity-summary">
    <i class="fas fa-tag me-1 <?= !empty($hiddenInCats) ? 'text-warning' : 'text-muted' ?>" aria-hidden="true"></i>
    Groupes masqués dans une catégorie
    <?php if (!empty($hiddenInCats)): ?>
      <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInCats) ?></span>
    <?php else: ?>
      <span class="badge text-bg-success ms-1" style="font-size:0.7rem">0</span>
    <?php endif ?>
  </summary>
  <?php if (!empty($hiddenInCats)): ?>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
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
  <?php else: ?>
  <p class="text-muted small mt-2 mb-0">Aucun groupe masqué dans une catégorie.</p>
  <?php endif ?>
</details>

<details class="ca-integrity-section mb-3">
  <summary class="ca-integrity-summary">
    <i class="fas fa-layer-group me-1 <?= !empty($hiddenInMeta) ? 'text-warning' : 'text-muted' ?>" aria-hidden="true"></i>
    Groupes masqués dans un métagroupe
    <?php if (!empty($hiddenInMeta)): ?>
      <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInMeta) ?></span>
    <?php else: ?>
      <span class="badge text-bg-success ms-1" style="font-size:0.7rem">0</span>
    <?php endif ?>
  </summary>
  <?php if (!empty($hiddenInMeta)): ?>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
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
  <?php else: ?>
  <p class="text-muted small mt-2 mb-0">Aucun groupe masqué dans un métagroupe.</p>
  <?php endif ?>
</details>

<details class="ca-integrity-section mb-3">
  <summary class="ca-integrity-summary">
    <i class="fas fa-users me-1 <?= !empty($hiddenWithMembers) ? 'text-warning' : 'text-muted' ?>" aria-hidden="true"></i>
    Groupes masqués avec des membres
    <?php if (!empty($hiddenWithMembers)): ?>
      <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenWithMembers) ?></span>
    <?php else: ?>
      <span class="badge text-bg-success ms-1" style="font-size:0.7rem">0</span>
    <?php endif ?>
  </summary>
  <?php if (!empty($hiddenWithMembers)): ?>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
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
  <?php else: ?>
  <p class="text-muted small mt-2 mb-0">Aucun groupe masqué avec des membres.</p>
  <?php endif ?>
</details>

<?php endif ?>
