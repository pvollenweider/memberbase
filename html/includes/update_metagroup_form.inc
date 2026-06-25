<?php
$mgId = (int)$_REQUEST['id'];
$mg = new Metagroup();
$mg->lookupMetagroup($mgId);

// Read is_filter for this metagroup entity row
$stmtIsFilter = $pdo->prepare("SELECT is_filter FROM metagroup WHERE id=? AND name IS NOT NULL");
$stmtIsFilter->execute([$mgId]);
$isFilter = (int)($stmtIsFilter->fetchColumn() ?? 1);

// Teams currently in this metagroup
$stmtIn = $pdo->prepare("SELECT teamid FROM metagroup WHERE id=? AND teamid IS NOT NULL");
$stmtIn->execute([$mgId]);
$memberTeamIds = $stmtIn->fetchAll(PDO::FETCH_COLUMN);
$memberTeamIds = array_map('intval', $memberTeamIds);

// All visible teams — members first, then non-members, each alphabetically
$allTeams = $pdo->query("SELECT id, name FROM team WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
usort($allTeams, function($a, $b) use ($memberTeamIds) {
    $aIn = in_array((int)$a->id, $memberTeamIds);
    $bIn = in_array((int)$b->id, $memberTeamIds);
    if ($aIn !== $bIn) return $bIn - $aIn;
    return strcmp($a->name, $b->name);
});

// Category map: teamid → [category_id, category_name]
$stmtCats = $pdo->query(
    "SELECT j.teamid, m.id AS cat_id, m.name AS cat_name
     FROM metagroup j
     JOIN metagroup m ON m.id = j.id AND m.name IS NOT NULL AND m.is_filter = 0
     WHERE j.teamid IS NOT NULL"
);
$teamCategory = [];
foreach ($stmtCats->fetchAll(PDO::FETCH_OBJ) as $row) {
    $teamCategory[(int)$row->teamid] = ['id' => (int)$row->cat_id, 'name' => $row->cat_name];
}

// Build ordered groups: members-in-category, then unassigned members, then non-members
$catGroups = []; // cat_name => [teams]
$uncategorized = [];
foreach ($allTeams as $t) {
    if (isset($teamCategory[(int)$t->id])) {
        $cn = $teamCategory[(int)$t->id]['name'];
        $catGroups[$cn][] = $t;
    } else {
        $uncategorized[] = $t;
    }
}
ksort($catGroups);

// Member counts per team
$cntRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
$teamCounts = [];
foreach ($cntRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }
?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <!-- Rename + type form -->
    <div>
      <p class="form-section-title">Modifier</p>
      <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
        <input type="hidden" name="action" value="updateMetagroup"/>
        <input type="hidden" name="view" value="manageTeam"/>
        <input type="hidden" name="id" value="<?= $mgId ?>"/>

        <div class="row mb-3 align-items-center">
          <label for="mgname" class="col-sm-3 col-form-label col-form-label-sm text-sm-end">Nom</label>
          <div class="col-sm-9">
            <input type="text" class="form-control form-control-sm" id="mgname" name="name"
                   value="<?= htmlentities($mg->getName(), ENT_COMPAT, $charset) ?>" maxlength="255" required/>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-sm-9 offset-sm-3">
            <div class="d-flex flex-column gap-2">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="is_filter" id="type_filter" value="1" <?= $isFilter ? 'checked' : '' ?>>
                <label class="form-check-label small" for="type_filter">
                  <strong>Filtre de groupes</strong>
                  <span class="text-muted d-block" style="font-size:0.78rem">Regroupe plusieurs groupes en un filtre dynamique. Apparaît dans le menu «Groupes de groupes» de la barre de navigation.</span>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="is_filter" id="type_category" value="0" <?= !$isFilter ? 'checked' : '' ?>>
                <label class="form-check-label small" for="type_category">
                  <strong>Catégorie</strong>
                  <span class="text-muted d-block" style="font-size:0.78rem">Sert à organiser les groupes en sections dans les listes. N'apparaît pas dans la navigation — c'est un regroupement visuel uniquement.</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= $_SERVER['PHP_SELF'] ?>?view=manageTeam" class="btn btn-outline-secondary btn-sm">Annuler</a>
        </div>
      </form>
    </div>

    <!-- Team membership -->
    <div>
      <div class="d-flex align-items-baseline justify-content-between mb-1">
        <p class="form-section-title mb-0"><?= $isFilter ? 'Groupes membres' : 'Groupes dans cette catégorie' ?></p>
        <?php if ($isFilter): ?>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?metagroup=<?= $mgId ?>" class="small">
          Voir la liste filtrée <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
        <?php endif ?>
      </div>
      <p class="small text-muted mb-3">
        <?php if ($isFilter): ?>
          Les groupes qui font partie du filtre «<?= htmlentities($mg->getName(), ENT_COMPAT, $charset) ?>». Sauvegarde automatique à chaque coche.
        <?php else: ?>
          Les groupes classés dans la catégorie «<?= htmlentities($mg->getName(), ENT_COMPAT, $charset) ?>». Sauvegarde automatique à chaque coche.
        <?php endif ?>
      </p>

      <div id="mg-team-list" class="d-flex flex-column gap-1" style="font-size:0.85rem">
        <?php
        // Helper to render one checkbox row
        function renderTeamCb($t, $memberTeamIds, $charset, $teamCounts) { ?>
        <div class="form-check form-check-sm d-flex align-items-center gap-2">
          <input class="form-check-input mg-team-cb" type="checkbox"
                 data-teamid="<?= (int)$t->id ?>"
                 id="mgteam_<?= (int)$t->id ?>"
                 <?= in_array((int)$t->id, $memberTeamIds) ? 'checked' : '' ?>>
          <label class="form-check-label flex-grow-1" for="mgteam_<?= (int)$t->id ?>">
            <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
          </label>
          <?php if (isset($teamCounts[(int)$t->id])): ?>
          <span class="badge text-bg-light border" style="font-size:0.7rem;font-weight:500">
            <?= $teamCounts[(int)$t->id] ?>
          </span>
          <?php endif ?>
        </div>
        <?php }

        if (!empty($catGroups)):
            foreach ($catGroups as $catName => $teams): ?>
          <p class="text-muted mb-0 mt-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600">
            <?= htmlentities($catName, ENT_COMPAT, $charset) ?>
          </p>
          <?php foreach ($teams as $t): renderTeamCb($t, $memberTeamIds, $charset, $teamCounts); endforeach;
            endforeach;
            if (!empty($uncategorized)): ?>
          <p class="text-muted mb-0 mt-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600">Sans catégorie</p>
          <?php foreach ($uncategorized as $t): renderTeamCb($t, $memberTeamIds, $charset, $teamCounts); endforeach;
            endif;
        else:
            foreach ($allTeams as $t): renderTeamCb($t, $memberTeamIds, $charset, $teamCounts); endforeach;
        endif;
        ?>
      </div>
    </div>

    <!-- Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
      <div id="mg-toast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="mg-toast-body"></div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
      </div>
    </div>

    <script>
    (function() {
      var mgId = <?= $mgId ?>;
      var toast = new bootstrap.Toast(document.getElementById('mg-toast'), { delay: 2500 });
      var toastEl = document.getElementById('mg-toast');
      var toastBody = document.getElementById('mg-toast-body');

      function showToast(msg, ok) {
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(ok ? 'bg-success' : 'bg-danger');
        toastBody.textContent = msg;
        toast.show();
      }

      document.querySelectorAll('.mg-team-cb').forEach(function(cb) {
        cb.addEventListener('change', function() {
          var checked = Array.from(document.querySelectorAll('.mg-team-cb:checked'))
                             .map(function(c) { return c.dataset.teamid; });
          var body = new URLSearchParams();
          body.append('action', 'updateMetagroupTeams');
          body.append('view', 'manageTeam');
          body.append('id', mgId);
          checked.forEach(function(id) { body.append('teams[]', id); });

          fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
          }).then(function(r) {
            if (r.ok) {
              var label = document.querySelector('label[for="mgteam_' + cb.dataset.teamid + '"]').textContent.trim();
              showToast((cb.checked ? '✓ ' : '✗ ') + label, true);
            } else {
              showToast('Erreur lors de la sauvegarde', false);
            }
          }).catch(function() {
            showToast('Erreur réseau', false);
          });
        });
      });
    })();
    </script>

    <!-- Delete -->
    <div>
      <p class="form-section-title" style="color:var(--ca-danger)">Supprimer</p>
      <p class="small text-muted mb-2">Supprime le métagroupe. Les groupes membres ne sont pas affectés.</p>
      <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
        <input type="hidden" name="action" value="deleteMetagroup"/>
        <input type="hidden" name="view" value="manageTeam"/>
        <input type="hidden" name="id" value="<?= $mgId ?>"/>
        <button type="submit" class="btn btn-sm btn-danger"
                onclick="return confirm('Supprimer «<?= htmlentities($mg->getName(), ENT_QUOTES, $charset) ?>» ?')">
          <i class="fas fa-trash me-1" aria-hidden="true"></i>Supprimer
        </button>
      </form>
    </div>

  </div>
</div>
