<?php
$id = $_REQUEST['id'];
$team = new Team();
$team->lookupTeam($id);

// Fetch members of this team
$stmtMembers = $pdo->prepare(
    "SELECT u.id, u.lastname, u.firstname, u.society
     FROM users u
     INNER JOIN user_properties up ON up.user_id = u.id
     WHERE up.parameter = ?
     ORDER BY u.lastname, u.firstname"
);
$stmtMembers->execute(["team_$id"]);
$members = $stmtMembers->fetchAll(PDO::FETCH_OBJ);
$memberCount = count($members);

// Fetch other teams for reassignment + import
$stmtTeams = $pdo->query("SELECT id, name FROM team WHERE id != $id AND hidden = 0 ORDER BY name");
$otherTeams = $stmtTeams->fetchAll(PDO::FETCH_OBJ);

// Member counts per team (for badges)
$cntRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
$teamCounts = [];
foreach ($cntRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }
?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <!-- Edit form -->
    <div>
      <p class="form-section-title">Modifier le groupe</p>
      <form action="<?=$_SERVER['PHP_SELF']?>" method="post">
        <input type="hidden" name="id" value="<?=$team->getId()?>"/>
        <input type="hidden" name="action" value="updateTeam"/>
        <input type="hidden" name="view" value="manageTeam"/>

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

        <div class="d-flex gap-2">
          <button type="submit" id="btn-update-team" class="btn btn-primary btn-sm"><?=$GLOBAL['update']?></button>
          <a href="<?=$_SERVER['PHP_SELF']?>?view=manageTeam" class="btn btn-outline-secondary btn-sm">Annuler</a>
        </div>
      </form>
      <script>
        document.getElementById('btn-update-team').closest('form').addEventListener('submit', function(e) {
          var checked = document.querySelectorAll('input[name="importFrom[]"]:checked');
          if (checked.length > 0) {
            var names = Array.from(checked).map(function(cb) {
              return document.querySelector('label[for="' + cb.id + '"]').textContent.trim();
            });
            if (!confirm('Tu as sélectionné ' + checked.length + ' groupe(s) à importer :\n– ' + names.join('\n– ') + '\n\nCe bouton ne fait que sauver le nom du groupe. Les membres ne seront PAS importés.\n\nContinuer quand même ?')) {
              e.preventDefault();
            }
          }
        });
      </script>
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
                <span class="text-muted">Pour un filtre dynamique, utilise plutôt un <a href="<?= $_SERVER['PHP_SELF'] ?>?view=manageMetagroups">métagroupe</a>.</span>
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
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post" class="d-flex align-items-center gap-2 flex-wrap">
              <input type="hidden" name="action" value="reassignTeam"/>
              <input type="hidden" name="view" value="manageTeam"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <select name="targetTeamId" class="form-select form-select-sm" style="width:auto" required>
                <option value="">— choisir le groupe —</option>
                <?php foreach ($otherTeams as $t): ?>
                  <?php $cnt = $teamCounts[(int)$t->id] ?? 0; ?>
                  <option value="<?= $t->id ?>"><?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $cnt > 0 ? " ($cnt)" : '' ?></option>
                <?php endforeach ?>
              </select>
              <button type="submit" class="btn btn-sm btn-warning"
                      onclick="return confirm('Réaffecter <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> et supprimer le groupe «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>» ?')">
                Transférer et dissoudre
              </button>
            </form>
          </div>
          <?php endif ?>

          <!-- Option B: force delete -->
          <div class="p-3" style="background:var(--ca-danger-light);border-radius:6px">
            <p class="small fw-semibold mb-1" style="color:var(--ca-danger)">Retirer tous les membres et supprimer le groupe</p>
            <p class="small text-muted mb-2">Les <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> seront retirés du groupe mais leurs comptes resteront intacts.</p>
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post">
              <input type="hidden" name="action" value="deleteTeamForce"/>
              <input type="hidden" name="view" value="manageTeam"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <button type="submit" class="btn btn-sm btn-danger"
                      onclick="return confirm('Supprimer le groupe «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>» et retirer ses <?= $memberCount ?> membre<?= $memberCount > 1 ? 's' : '' ?> ?')">
                <i class="fas fa-trash me-1" aria-hidden="true"></i>Retirer les membres et supprimer
              </button>
            </form>
          </div>

          <?php else: ?>
          <!-- No members — simple delete -->
          <div>
            <p class="small text-muted mb-2">Ce groupe n'a aucun membre.</p>
            <form action="<?=$_SERVER['PHP_SELF']?>" method="post">
              <input type="hidden" name="action" value="deleteTeamForce"/>
              <input type="hidden" name="view" value="manageTeam"/>
              <input type="hidden" name="id" value="<?=$team->getId()?>"/>
              <button type="submit" class="btn btn-sm btn-danger"
                      onclick="return confirm('Supprimer le groupe «<?= htmlentities($team->getName(), ENT_QUOTES, $charset) ?>» ?')">
                <i class="fas fa-trash me-1" aria-hidden="true"></i>Supprimer
              </button>
            </form>
          </div>
          <?php endif ?>

        </div>
      </details>
    </div>

  </div>
</div>
