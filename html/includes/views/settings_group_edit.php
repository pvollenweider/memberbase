<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for creating or editing a group (team).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    $url = $_SERVER['PHP_SELF'] . '?view=settings&tab=groups';
    if (!empty($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
    exit;
}
$team = new Team();
$team->lookupTeam($id);

// Fetch members of this team
$stmtMembers = $pdo->prepare(
    "SELECT u.id, u.lastname, u.firstname, u.society
     FROM users u
     INNER JOIN user_properties up ON up.user_id = u.id
     WHERE up.parameter = ? AND u.status=1
     ORDER BY u.lastname, u.firstname"
);
$stmtMembers->execute(["team_$id"]);
$members = $stmtMembers->fetchAll(PDO::FETCH_OBJ);
$memberCount = count($members);

// Fetch other teams for reassignment + import
$stmtTeams = $pdo->query("SELECT id, name FROM team WHERE id != $id AND hidden = 0 ORDER BY name");
$otherTeams = $stmtTeams->fetchAll(PDO::FETCH_OBJ);

// Fetch categories and current category for this team
$allCats = $pdo->query("SELECT id, name FROM metagroup WHERE name IS NOT NULL AND is_filter=0 GROUP BY id ORDER BY name ASC")->fetchAll(PDO::FETCH_OBJ);
$stmtCurrentCat = $pdo->prepare("SELECT c.id FROM metagroup junc JOIN metagroup c ON c.id=junc.id AND c.name IS NOT NULL AND c.is_filter=0 WHERE junc.teamid=? LIMIT 1");
$stmtCurrentCat->execute([$id]);
$currentCatId = (int)($stmtCurrentCat->fetchColumn() ?: 0);

// Precompute import counts per year (donors + cotisants not yet in team)
$importCountsPerYear = [];
$currentYear = (int)date('Y');

$cotisTypeIds   = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$excludedTypeIds = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 1));

for ($yi = 0; $yi < 10; $yi++) {
    $dy   = $currentYear - $yi;
    $from = mktime(0, 0, 0, 1, 0, $dy);
    $to   = mktime(0, 0, 0, 1, 1, $dy + 1);

    // Donors count
    if (!empty($excludedTypeIds)) {
        $excPlaceholders = implode(',', array_fill(0, count($excludedTypeIds), '?'));
        $r = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM users u JOIN compta c ON c.user_id = u.id
            WHERE c.type_id NOT IN ($excPlaceholders)
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
        ");
        $r->execute(array_merge($excludedTypeIds, [$from, $to, "team_$id"]));
    } else {
        $r = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM users u JOIN compta c ON c.user_id = u.id
            WHERE c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
        ");
        $r->execute([$from, $to, "team_$id"]);
    }
    $donorCount = (int)$r->fetchColumn();

    // Cotisants count
    $cotisCount = 0;
    if (!empty($cotisTypeIds)) {
        $cotisPlaceholders = implode(',', array_fill(0, count($cotisTypeIds), '?'));
        $r2 = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM users u JOIN compta c ON c.user_id = u.id
            WHERE c.type_id IN ($cotisPlaceholders)
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM user_properties WHERE parameter = ?)
        ");
        $r2->execute(array_merge($cotisTypeIds, [$from, $to, "team_$id"]));
        $cotisCount = (int)$r2->fetchColumn();
    }

    $importCountsPerYear[$dy] = ['donors' => $donorCount, 'cotis' => $cotisCount];
}

// Member counts per team (for badges)
$cntRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
$teamCounts = [];
foreach ($cntRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }
?>
<?php if (isset($_REQUEST['imported'])): ?>
<div class="alert alert-success d-flex gap-2 py-2 px-3 mb-3" style="font-size:0.82rem" role="status">
  <i class="fas fa-check-circle mt-1 flex-shrink-0" aria-hidden="true"></i>
  <?= $_REQUEST['imported'] === 'cotisants' ? 'Cotisants importés dans le groupe.' : 'Donateurs importés dans le groupe.' ?>
</div>
<?php endif ?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <!-- Edit form -->
    <div>
      <div class="d-flex align-items-baseline justify-content-between mb-1">
        <p class="form-section-title mb-0"><?= $GLOBAL['editGroup'] ?></p>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?team=<?= (int)$id ?>" class="small">
          Voir la liste <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
      </div>
      <form action="<?=$_SERVER['PHP_SELF']?>" method="post">
        <input type="hidden" name="id" value="<?=$team->getId()?>"/>
        <input type="hidden" name="action" value="updateTeam"/>
        <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>

        <div class="row mb-2 align-items-center">
          <label for="name" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">Nom</label>
          <div class="col-sm-9">
            <input type="text" class="form-control form-control-sm" id="name" name="name"
                   value="<?=htmlentities($team->getName(),ENT_COMPAT,$charset)?>" maxlength="255" required/>
          </div>
        </div>

        <div class="row mb-3 align-items-center">
          <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="hidden" name="hidden" value="1"<?= $team->getHidden() ? ' checked' : '' ?>>
              <label class="form-check-label small" for="hidden">
                <i class="fas fa-eye-slash me-1 text-muted" aria-hidden="true"></i>Masquer dans les interfaces
              </label>
            </div>
          </div>
        </div>

        <?php if (count($allCats) > 0): ?>
        <div class="row mb-3 align-items-center">
          <label for="team_category" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">Catégorie</label>
          <div class="col-sm-9">
            <select class="form-select form-select-sm" id="team_category" name="categoryId">
              <option value="0"<?= $currentCatId === 0 ? ' selected' : '' ?>>— sans catégorie —</option>
              <?php foreach ($allCats as $cat): ?>
              <option value="<?= (int)$cat->id ?>"<?= $currentCatId === (int)$cat->id ? ' selected' : '' ?>>
                <?= htmlentities($cat->name, ENT_COMPAT, $charset) ?>
              </option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
        <?php endif ?>

        <div class="d-flex gap-2">
          <button type="submit" id="btn-update-team" class="btn btn-primary btn-sm"><?=$GLOBAL['update']?></button>
          <a href="<?=$_SERVER['PHP_SELF']?>?view=settings&amp;tab=groups" class="btn btn-outline-secondary btn-sm"><?= $GLOBAL['cancel'] ?></a>
        </div>
      </form>
    </div>

    <!-- Import members -->
    <?php if (count($otherTeams) > 0): ?>
    <div>
      <form action="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= $team->getId() ?>" method="post">
        <input type="hidden" name="action" value="importTeamMembers"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            Importer des membres d'autres groupes
          </summary>
          <script>
            document.currentScript.closest('details').addEventListener('toggle', function() {
              var icon = this.querySelector('.fa-chevron-right');
              icon.style.transform = this.open ? 'rotate(90deg)' : '';
            });
          </script>
          <div class="mt-2 p-3" style="background:var(--ca-ground);border-radius:6px">
            <div class="alert alert-warning d-flex gap-2 py-2 px-3 mb-3" style="font-size:0.78rem;border-radius:6px" role="note">
              <i class="fas fa-copy mt-1 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <strong>Copie ponctuelle</strong> — l'import copie les membres tels qu'ils sont <em>maintenant</em>. Si le groupe source change plus tard, ce groupe n'est pas mis à jour.<br>
                <span class="text-muted">Pour un filtre dynamique, utilise plutôt un filtre de groupes dans <a href="<?= $_SERVER['PHP_SELF'] ?>?view=settings&amp;tab=groups">Groupes</a>.</span>
              </div>
            </div>
            <div class="d-flex flex-column gap-1 mb-3">
              <?php foreach ($otherTeams as $t): ?>
              <?php $cnt = $teamCounts[(int)$t->id] ?? 0; ?>
              <div class="form-check form-check-sm">
                <input class="form-check-input" type="checkbox"
                       name="importFrom[]" value="<?= (int)$t->id ?>"
                       id="import_<?= (int)$t->id ?>">
                <label class="form-check-label" for="import_<?= (int)$t->id ?>">
                  <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                  <?php if ($cnt > 0): ?>
                  <span class="badge rounded-pill ms-1" style="font-size:0.6rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
                  <?php endif ?>
                </label>
              </div>
              <?php endforeach ?>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer
            </button>
          </div>
        </details>
      </form>
    </div>
    <?php endif ?>

    <!-- Import cotisation payers by year -->
    <div>
      <form action="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= $team->getId() ?>" method="post">
        <input type="hidden" name="action" value="importCotisants"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            Importer les cotisants d'une année
          </summary>
          <script>
            document.currentScript.closest('details').addEventListener('toggle', function() {
              var icon = this.querySelector('.fa-chevron-right');
              icon.style.transform = this.open ? 'rotate(90deg)' : '';
            });
          </script>
          <div class="mt-2 p-3" style="background:var(--ca-ground);border-radius:6px">
            <div class="alert alert-warning d-flex gap-2 py-2 px-3 mb-3" style="font-size:0.78rem;border-radius:6px" role="note">
              <i class="fas fa-copy mt-1 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <strong>Copie ponctuelle</strong> — membres déjà dans ce groupe non touchés. Seuls les cotisants absents sont ajoutés.
                <?php if (!empty($cotisTypeIds)): ?>
                Types pris en compte: <?= implode(', ', array_map(fn($tid) => '<strong>' . htmlentities($comptaTypes[$tid]->label, ENT_COMPAT, $charset) . '</strong>', $cotisTypeIds)) ?>.
                <?php else: ?>
                <span class="text-danger"><strong>Aucun type marqué «cotisation» — configure-les dans <a href="<?= $_SERVER['PHP_SELF'] ?>?view=manageComptaTypes">Types compta</a>.</strong></span>
                <?php endif ?>
              </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-auto">
                <label for="cotis_year" class="form-label form-label-sm mb-1">Année</label>
                <select class="form-select form-select-sm" id="cotis_year" name="cotis_year" style="width:auto">
                  <?php for ($yi = 0; $yi < 10; $yi++): $dy = $currentYear - $yi;
                    $cnt = $importCountsPerYear[$dy]['cotis'] ?? 0; ?>
                  <option value="<?= $dy ?>"><?= $dy ?><?= $cnt > 0 ? " (+$cnt)" : ' (0)' ?></option>
                  <?php endfor ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer les cotisants
            </button>
          </div>
        </details>
      </form>
    </div>

    <!-- Import donors by year -->
    <div>
      <form action="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= $team->getId() ?>" method="post">
        <input type="hidden" name="action" value="importDonors"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            Importer les donateurs d'une année
          </summary>
          <script>
            document.currentScript.closest('details').addEventListener('toggle', function() {
              var icon = this.querySelector('.fa-chevron-right');
              icon.style.transform = this.open ? 'rotate(90deg)' : '';
            });
          </script>
          <div class="mt-2 p-3" style="background:var(--ca-ground);border-radius:6px">
            <div class="alert alert-warning d-flex gap-2 py-2 px-3 mb-3" style="font-size:0.78rem;border-radius:6px" role="note">
              <i class="fas fa-copy mt-1 flex-shrink-0" aria-hidden="true"></i>
              <div>
                <strong>Copie ponctuelle</strong> — les membres déjà dans ce groupe ne sont pas touchés. Seuls les donateurs absents sont ajoutés.
              </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-auto">
                <label for="donor_year" class="form-label form-label-sm mb-1">Année</label>
                <select class="form-select form-select-sm" id="donor_year" name="donor_year" style="width:auto">
                  <?php for ($yi = 0; $yi < 10; $yi++): $dy = $currentYear - $yi;
                    $cnt = $importCountsPerYear[$dy]['donors'] ?? 0; ?>
                  <option value="<?= $dy ?>"><?= $dy ?><?= $cnt > 0 ? " (+$cnt)" : ' (0)' ?></option>
                  <?php endfor ?>
                </select>
              </div>
              <div class="col-auto">
                <label for="donor_minsum" class="form-label form-label-sm mb-1">Min CHF</label>
                <select class="form-select form-select-sm" id="donor_minsum" name="donor_minsum" style="width:auto">
                  <?php foreach ([1, 100, 200, 500, 1000] as $_ms): ?>
                  <option value="<?= $_ms ?>"><?= $_ms ?></option>
                  <?php endforeach ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-import me-1" aria-hidden="true"></i>Importer les donateurs
            </button>
          </div>
        </details>
      </form>
    </div>

    <!-- Delete section -->
    <div>
      <details>
        <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem;font-size:0.8rem">
          <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
          Réaffecter ou dissoudre…
          <?php if ($memberCount > 0): ?>
          <span class="badge rounded-pill" style="font-size:0.6rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $memberCount ?></span>
          <?php endif ?>
        </summary>
        <script>
          document.currentScript.closest('details').addEventListener('toggle', function() {
            var icon = this.querySelector('.fa-chevron-right');
            icon.style.transform = this.open ? 'rotate(90deg)' : '';
          });
        </script>

        <div class="mt-3 d-flex flex-column gap-3">

          <?php if ($memberCount > 0): ?>
          <!-- Member list -->
          <div>
            <p class="small text-muted mb-2">
              <strong><?= $memberCount ?></strong> membre<?= $memberCount > 1 ? 's' : '' ?> appartiennent à ce groupe&nbsp;:
            </p>
            <ul class="list-unstyled mb-0" style="font-size:0.8rem;max-height:200px;overflow-y:auto;border:1px solid var(--ca-border);border-radius:4px;padding:0.4rem 0.75rem">
              <?php foreach ($members as $m): ?>
                <li>
                  <a href="<?=$_SERVER['PHP_SELF']?>?view=generalData&id=<?= $m->id ?>" class="text-decoration-none">
                    <?= htmlentities($m->lastname, ENT_COMPAT, $charset) ?>
                    <?= htmlentities($m->firstname, ENT_COMPAT, $charset) ?>
                    <?php if ($m->society): ?>
                      <span class="text-muted">(<?= htmlentities($m->society, ENT_COMPAT, $charset) ?>)</span>
                    <?php endif ?>
                  </a>
                </li>
              <?php endforeach ?>
            </ul>
          </div>

          <!-- Option A: reassign -->
          <?php if (count($otherTeams) > 0): ?>
          <div class="p-3" style="background:var(--ca-ground);border-radius:6px">
            <p class="small fw-semibold mb-2">Transférer les membres vers un autre groupe</p>
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post" class="d-flex align-items-center gap-2 flex-wrap" hx-boost="false">
              <input type="hidden" name="action" value="reassignTeam"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <select name="targetTeamId" class="form-select form-select-sm" style="width:auto" required>
                <option value="">— choisir le groupe —</option>
                <?php foreach ($otherTeams as $t): ?>
                  <?php $cnt = $teamCounts[(int)$t->id] ?? 0; ?>
                  <option value="<?= $t->id ?>"><?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $cnt > 0 ? " ($cnt)" : '' ?></option>
                <?php endforeach ?>
              </select>
              <button type="button" class="btn btn-sm btn-warning"
                      data-bs-toggle="modal" data-bs-target="#modal-reassign-team">
                Transférer et dissoudre
              </button>
            </form>
          </div>
          <?php endif ?>

          <!-- Option B: force delete -->
          <div class="p-3" style="background:var(--ca-danger-light);border-radius:6px">
            <p class="small fw-semibold mb-1" style="color:var(--ca-danger)">Retirer tous les membres et supprimer le groupe</p>
            <p class="small text-muted mb-2">Les <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> seront retirés du groupe mais leurs comptes resteront intacts.</p>
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post" hx-boost="false">
              <input type="hidden" name="action" value="deleteTeamForce"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <button type="button" class="btn btn-sm btn-danger"
                      data-bs-toggle="modal" data-bs-target="#modal-delete-team-members">
                <i class="fas fa-trash me-1" aria-hidden="true"></i>Retirer les membres et supprimer
              </button>
            </form>
          </div>

          <?php else: ?>
          <!-- No members — simple delete -->
          <div>
            <p class="small text-muted mb-2">Ce groupe n'a aucun membre.</p>
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post" hx-boost="false">
              <input type="hidden" name="action" value="deleteTeamForce"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <button type="button" class="btn btn-sm btn-danger"
                      data-bs-toggle="modal" data-bs-target="#modal-delete-team-empty">
                <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
              </button>
            </form>
          </div>
          <?php endif ?>

        </div>
      </details>
    </div>

  </div>
</div>

<?php if ($memberCount > 0): ?>
<div class="modal fade" id="modal-reassign-team" tabindex="-1" aria-labelledby="modal-reassign-team-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-reassign-team-label">Transférer et dissoudre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        Réaffecter <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> et supprimer le groupe
        «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>» ?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-warning"
                onclick="document.querySelector('form [name=action][value=reassignTeam]').closest('form').submit()">
          Transférer et dissoudre
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-delete-team-members" tabindex="-1" aria-labelledby="modal-delete-team-members-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-team-members-label"><?= $GLOBAL['delete'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        Supprimer le groupe «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>»
        et retirer ses <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> ?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-danger"
                onclick="document.querySelector('form [name=action][value=deleteTeamForce]').closest('form').submit()">
          <i class="fas fa-trash me-1" aria-hidden="true"></i>Retirer les membres et supprimer
        </button>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="modal fade" id="modal-delete-team-empty" tabindex="-1" aria-labelledby="modal-delete-team-empty-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-team-empty-label"><?= $GLOBAL['delete'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        Supprimer le groupe «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>» ?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-danger"
                onclick="document.querySelector('form [name=action][value=deleteTeamForce]').closest('form').submit()">
          <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif ?>
