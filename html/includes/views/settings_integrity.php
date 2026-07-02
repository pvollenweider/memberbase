<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
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

// --- Format & cohérence des données ---

// compta.date invalide (0 ou dans le futur)
$stmtDateInvalid = $pdo->query("
    SELECT c.id, c.date, c.user_id, u.firstname, u.lastname, c.libele
    FROM compta c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.date = 0 OR c.date > UNIX_TIMESTAMP()
    ORDER BY c.id DESC
    LIMIT 100
");
$dateInvalid = $stmtDateInvalid->fetchAll(PDO::FETCH_OBJ);

// compta.type_id NULL
$stmtTypeNull = $pdo->query("
    SELECT c.id, c.user_id, u.firstname, u.lastname, c.libele, c.sum
    FROM compta c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.type_id IS NULL
    ORDER BY c.id DESC
    LIMIT 100
");
$typeNull = $stmtTypeNull->fetchAll(PDO::FETCH_OBJ);

// users.email mal formaté (non-vide, pas de @)
$stmtEmailInvalid = $pdo->query("
    SELECT id, firstname, lastname, email
    FROM users
    WHERE status=1 AND TRIM(email) != '' AND email NOT LIKE '%@%'
    ORDER BY lastname, firstname
");
$emailInvalid = $stmtEmailInvalid->fetchAll(PDO::FETCH_OBJ);

// users.sexe hors enum
$stmtSexeInvalid = $pdo->query("
    SELECT id, firstname, lastname, sexe
    FROM users
    WHERE status=1 AND sexe NOT IN ('na','hf','f','m')
    ORDER BY lastname, firstname
");
$sexeInvalid = $stmtSexeInvalid->fetchAll(PDO::FETCH_OBJ);

// users sans nom de famille ni société
$stmtNoName = $pdo->query("
    SELECT id, firstname, lastname, society
    FROM users
    WHERE status=1 AND TRIM(lastname) = '' AND TRIM(society) = ''
    ORDER BY id
");
$noName = $stmtNoName->fetchAll(PDO::FETCH_OBJ);

// users.email_alt mal formaté
$stmtEmailAltInvalid = $pdo->query("
    SELECT id, firstname, lastname, email_alt
    FROM users
    WHERE status=1 AND TRIM(email_alt) != '' AND email_alt NOT LIKE '%@%'
    ORDER BY lastname, firstname
");
$emailAltInvalid = $stmtEmailAltInvalid->fetchAll(PDO::FETCH_OBJ);

// users.birthday dans le futur
$stmtBirthdayFuture = $pdo->query("
    SELECT id, firstname, lastname, birthday
    FROM users
    WHERE status=1 AND birthday > 0 AND birthday > UNIX_TIMESTAMP()
    ORDER BY lastname, firstname
");
$birthdayFuture = $stmtBirthdayFuture->fetchAll(PDO::FETCH_OBJ);

$allOk = empty($dupNames) && empty($dupEmails) && empty($hiddenInCats) && empty($hiddenInMeta) && empty($hiddenWithMembers)
      && empty($dateInvalid) && empty($typeNull)
      && empty($emailInvalid) && empty($emailAltInvalid) && empty($sexeInvalid) && empty($birthdayFuture)
      && empty($noName);
?>

<p class="form-section-title mb-1">
  <i class="fas fa-stethoscope me-1" aria-hidden="true"></i>Intégrité
</p>
<p class="small text-muted mb-3">Doublons potentiels dans les membres et segments masqués encore assignés.</p>

<?php if ($allOk): ?>
<div class="alert alert-success py-2 px-3" role="alert" style="font-size:0.85rem">
  <i class="fas fa-shield-halved me-1" aria-hidden="true"></i>Tout est clean — aucun problème détecté.
</div>
<?php else: ?>

<?php if (!empty($dupNames)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-user-group me-1 text-danger" aria-hidden="true"></i>
    Membres avec même nom
    <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupNames) ?></span>
  </summary>
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
</details>
<?php endif ?>

<?php if (!empty($dupEmails)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-envelope me-1 text-danger" aria-hidden="true"></i>
    Membres avec même email
    <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dupEmails) ?></span>
  </summary>
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
</details>
<?php endif ?>

<?php if (!empty($hiddenInCats)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-tag me-1 text-warning" aria-hidden="true"></i>
    Segments masqués dans une catégorie
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInCats) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead>
      <tr>
        <th>Segment masqué</th>
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
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($hiddenInMeta)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-layer-group me-1 text-warning" aria-hidden="true"></i>
    Segments masqués dans un segment combiné
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenInMeta) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead>
      <tr>
        <th>Segment masqué</th>
        <th>Segment combiné</th>
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
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($hiddenWithMembers)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-users me-1 text-warning" aria-hidden="true"></i>
    Segments masqués avec des membres
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($hiddenWithMembers) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead>
      <tr>
        <th>Segment masqué</th>
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
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($noName)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-id-card me-1 text-danger" aria-hidden="true"></i>
    Membres sans nom de famille ni société
    <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($noName) ?></span>
  </summary>
  <p class="small text-muted mt-2 mb-1">Ces membres n'ont ni nom de famille ni société — ils sont difficilement identifiables.</p>
  <table class="table table-sm align-middle mt-1 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Prénom</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($noName as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname) ?: '—', ENT_COMPAT, $charset) ?> <span class="text-muted" style="font-size:0.75rem">#<?= (int)$r->id ?></span></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$r->id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($dateInvalid)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-calendar-xmark me-1 text-danger" aria-hidden="true"></i>
    Dates compta invalides
    <span class="badge text-bg-danger ms-1" style="font-size:0.7rem"><?= count($dateInvalid) ?></span>
  </summary>
  <p class="small text-muted mt-2 mb-1">Entrées avec date à 0 ou dans le futur.</p>
  <table class="table table-sm align-middle mt-1 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Libellé</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dateInvalid as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname) ?: '#'.(int)$r->user_id, ENT_COMPAT, $charset) ?></td>
        <td><?= htmlentities($r->libele, ENT_COMPAT, $charset) ?></td>
        <td><code class="text-danger"><?= $r->date == 0 ? '0 (vide)' : date('d.m.Y', (int)$r->date) ?></code></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= (int)$r->user_id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Compta</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($typeNull)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-tag me-1 text-warning" aria-hidden="true"></i>
    Entrées compta sans type
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($typeNull) ?></span>
  </summary>
  <p class="small text-muted mt-2 mb-1">Ces entrées ont <code>type_id = NULL</code> — elles n'apparaissent dans aucune ventilation par type.</p>
  <table class="table table-sm align-middle mt-1 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Libellé</th><th>Montant</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($typeNull as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname) ?: '#'.(int)$r->user_id, ENT_COMPAT, $charset) ?></td>
        <td><?= htmlentities($r->libele, ENT_COMPAT, $charset) ?></td>
        <td><?= htmlentities($r->sum, ENT_COMPAT, $charset) ?></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=compta&amp;userid=<?= (int)$r->user_id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Compta</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($emailInvalid)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-at me-1 text-warning" aria-hidden="true"></i>
    Emails mal formatés
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($emailInvalid) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Email</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($emailInvalid as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname), ENT_COMPAT, $charset) ?></td>
        <td><code class="text-warning"><?= htmlentities($r->email, ENT_COMPAT, $charset) ?></code></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$r->id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($emailAltInvalid)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-at me-1 text-warning" aria-hidden="true"></i>
    Emails alt. mal formatés
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($emailAltInvalid) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Email alt.</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($emailAltInvalid as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname), ENT_COMPAT, $charset) ?></td>
        <td><code class="text-warning"><?= htmlentities($r->email_alt, ENT_COMPAT, $charset) ?></code></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$r->id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($sexeInvalid)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-venus-mars me-1 text-warning" aria-hidden="true"></i>
    Genre hors valeurs autorisées
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($sexeInvalid) ?></span>
  </summary>
  <p class="small text-muted mt-2 mb-1">Valeurs attendues : <code>na</code>, <code>hf</code>, <code>f</code>, <code>m</code>.</p>
  <table class="table table-sm align-middle mt-1 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Valeur</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sexeInvalid as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname), ENT_COMPAT, $charset) ?></td>
        <td><code class="text-warning"><?= htmlentities($r->sexe, ENT_COMPAT, $charset) ?></code></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$r->id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php if (!empty($birthdayFuture)): ?>
<details class="ca-integrity-section mb-3" open>
  <summary class="ca-integrity-summary">
    <i class="fas fa-cake-candles me-1 text-warning" aria-hidden="true"></i>
    Date de naissance dans le futur
    <span class="badge text-bg-warning ms-1" style="font-size:0.7rem"><?= count($birthdayFuture) ?></span>
  </summary>
  <table class="table table-sm align-middle mt-2 mb-0" style="font-size:0.82rem">
    <thead><tr><th>Membre</th><th>Date de naissance</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($birthdayFuture as $r): ?>
      <tr>
        <td><?= htmlentities(trim($r->firstname . ' ' . $r->lastname), ENT_COMPAT, $charset) ?></td>
        <td><code class="text-warning"><?= date('d.m.Y', (int)$r->birthday) ?></code></td>
        <td class="text-end">
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&amp;id=<?= (int)$r->id ?>"
             class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem">Éditer</a>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</details>
<?php endif ?>

<?php endif ?>
