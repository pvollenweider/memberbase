<div class="row mt-4">
  <div class="col-md-7 col-lg-6">

    <p class="form-section-title" style="margin-top:0"><?= $GLOBAL['manageTeam'] ?></p>

    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
      <tbody>
<?php
// Member counts per team
$countRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
$teamCounts = [];
foreach ($countRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }

$query = "SELECT id, name, hidden FROM team ORDER BY hidden ASC, name ASC";
$stmt  = $pdo->query($query);
$prevHidden = 0;
while ($row = $stmt->fetchObject()) {
    $id       = $row->id;
    $name     = htmlentities($row->name, ENT_COMPAT, $charset);
    $isHidden = (int) $row->hidden;
    if ($isHidden && !$prevHidden) {
        $prevHidden = 1;
        ?>
        <tr><td colspan="2" class="pt-3 pb-1" style="border:none">
          <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Masqués
          </span>
        </td></tr>
        <?php
    }
    ?>
        <tr class="<?= $isHidden ? 'text-muted' : '' ?>">
          <td>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?team=<?= $id ?>"
               class="text-decoration-none <?= $isHidden ? 'text-muted' : '' ?>">
              <?= $name ?>
            </a>
            <?php $cnt = $teamCounts[$id] ?? 0; if ($cnt > 0): ?>
            <span class="badge rounded-pill ms-1" style="font-size:0.65rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
            <?php endif ?>
          </td>
          <td class="text-end" style="width:2rem">
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= $id ?>"
               class="text-decoration-none text-muted" title="<?= $GLOBAL['update'] ?>">
              <i class="fas fa-pen" style="font-size:0.75rem"></i>
            </a>
          </td>
        </tr>
<?php } ?>
      </tbody>
    </table>

    <hr class="my-4">

    <p class="form-section-title"><?= $GLOBAL['addTeam'] ?></p>

    <?php
    $allTeams = $pdo->query("SELECT id, name FROM team WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    ?>

    <form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="addTeam">
      <input type="hidden" name="action" value="addTeamWithImport"/>
      <input type="hidden" name="view"   value="manageTeam"/>

      <div class="d-flex align-items-center gap-2 mb-3">
        <input type="text" class="form-control form-control-sm" id="name" name="name"
               placeholder="<?= htmlentities($GLOBAL['teamName'], ENT_COMPAT, $charset) ?>" required/>
        <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Ajouter</button>
      </div>

      <?php if (count($allTeams) > 0): ?>
      <details style="font-size:0.8rem">
        <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
          <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
          Importer les membres d'autres groupes
        </summary>
        <script>
          document.currentScript.closest('details').addEventListener('toggle', function(e) {
            var icon = this.querySelector('.fa-chevron-right');
            icon.style.transform = this.open ? 'rotate(90deg)' : '';
          });
        </script>
        <div class="mt-2 p-3" style="background:var(--ca-ground);border-radius:6px">
          <p class="text-muted mb-2" style="font-size:0.75rem">Copie ponctuelle — les membres sont copiés tels qu'ils sont maintenant. Pour un filtre dynamique, crée plutôt un <a href="<?= $_SERVER['PHP_SELF'] ?>?view=manageMetagroups">métagroupe</a>.</p>
          <div class="d-flex flex-column gap-1">
            <?php foreach ($allTeams as $t): ?>
            <div class="form-check form-check-sm">
              <input class="form-check-input" type="checkbox"
                     name="importFrom[]" value="<?= (int)$t->id ?>"
                     id="import_<?= (int)$t->id ?>">
              <label class="form-check-label" for="import_<?= (int)$t->id ?>">
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                <?php $cnt = $teamCounts[(int)$t->id] ?? 0; if ($cnt > 0): ?>
                <span class="badge rounded-pill ms-1" style="font-size:0.6rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
                <?php endif ?>
              </label>
            </div>
            <?php endforeach ?>
          </div>
        </div>
      </details>
      <?php endif ?>

    </form>

  </div>
</div>
