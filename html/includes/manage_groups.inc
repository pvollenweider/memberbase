<?php
// Member counts per team
$countRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
$teamCounts = [];
foreach ($countRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }

$allTeams   = $pdo->query("SELECT id, name FROM team WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
$categories = $pdo->query("SELECT DISTINCT id, name FROM metagroup WHERE name IS NOT NULL AND is_filter = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);

// Emit undo toast sentinel if set by previous bulk action
if (!isset($_SESSION)) session_start();
if (!empty($_SESSION['group_toast'])) {
    $_gt = $_SESSION['group_toast'];
    unset($_SESSION['group_toast']);
    $_gtHidden  = ($_gt['undo_act'] === 'bulkHide') ? 1 : 0;
    $_gtIdsStr  = implode(',', $_gt['undo_ids']);
    $_gtUndoUrl = $_SERVER['PHP_SELF'] . '?action=undoGroupVisibility&hidden=' . $_gtHidden . '&ids=' . urlencode($_gtIdsStr) . '&view=settings&tab=groups';
    echo '<div id="casa-membership-toast" hidden data-msg="' . htmlspecialchars($_gt['msg'], ENT_QUOTES) . '" data-undo-url="' . htmlspecialchars($_gtUndoUrl, ENT_QUOTES) . '"></div>';
}
?>

<p class="fw-semibold mb-2" style="font-size:0.85rem"><?= $GLOBAL['addTeam'] ?></p>

<form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="addTeam" class="mb-4">
  <input type="hidden" name="action" value="addTeamWithImport"/>
  <input type="hidden" name="view"   value="settings"/>
  <input type="hidden" name="tab"    value="groups"/>

  <div class="d-flex align-items-center gap-2 mb-2">
    <input type="text" class="form-control form-control-sm" id="name" name="name"
           placeholder="<?= htmlentities($GLOBAL['teamName'], ENT_COMPAT, $charset) ?>" required/>
    <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Ajouter</button>
  </div>
  <?php if (count($categories) > 0): ?>
  <div class="mb-2">
    <select name="categoryId" class="form-select form-select-sm" style="max-width:260px">
      <option value="0">— sans catégorie —</option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat->id ?>"><?= htmlentities($cat->name, ENT_COMPAT, $charset) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <?php endif ?>

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
      <p class="text-muted mb-2" style="font-size:0.75rem">Copie ponctuelle — les membres sont copiés tels qu'ils sont maintenant. Pour un filtre dynamique, crée plutôt un filtre de groupes.</p>
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

<form id="bulk-form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
  <input type="hidden" name="action" id="bulk-action" value=""/>
  <input type="hidden" name="view" value="settings"/>
  <input type="hidden" name="tab"  value="groups"/>

  <!-- Bulk action bar (hidden until selection) -->
  <div id="bulk-bar" class="d-none mb-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.8rem">
    <span id="bulk-count" class="text-muted"></span>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('bulkHide')">
      <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Masquer
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('bulkShow')">
      <i class="fas fa-eye me-1" aria-hidden="true"></i>Afficher
    </button>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openMetagroupModal()">
      <i class="fas fa-layer-group me-1" aria-hidden="true"></i>Créer un filtre
    </button>
    <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="clearSelection()">Désélectionner</button>
  </div>

  <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
    <thead>
      <tr>
        <th style="width:1.5rem">
          <input type="checkbox" class="form-check-input" id="bulk-select-all" title="Tout sélectionner">
        </th>
        <th colspan="2"></th>
      </tr>
    </thead>
    <tbody>
<?php
$stmt = $pdo->query("
    SELECT t.id, t.name, t.hidden,
           COALESCE(cat.name, '') AS cat_name,
           COALESCE(cat.id, 0) AS cat_id,
           COALESCE(cat.sort_order, 99999) AS cat_sort
    FROM team t
    LEFT JOIN (
        SELECT j.teamid, MIN(c.id) AS id, MIN(c.name) AS name, MIN(c.sort_order) AS sort_order
        FROM metagroup j
        JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
        WHERE j.teamid IS NOT NULL
        GROUP BY j.teamid
    ) cat ON cat.teamid = t.id
    ORDER BY t.hidden ASC, cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
");
$prevHidden = 0;
$prevCatId  = -1;
while ($row = $stmt->fetchObject()) {
    $id       = $row->id;
    $name     = htmlentities($row->name, ENT_COMPAT, $charset);
    $isHidden = (int) $row->hidden;
    $catId    = (int) $row->cat_id;
    if ($isHidden && !$prevHidden) {
        $prevHidden = 1;
        $prevCatId  = -1;
        ?>
        </tbody>
        <tbody>
        <tr><td colspan="3" class="pt-2 pb-1" style="border:none">
          <button type="button"
                  style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted);background:none;border:none;padding:0;cursor:pointer"
                  onclick="toggleHiddenSection(this)">
            <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Masqués
            <i class="fas fa-chevron-right ms-1" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
          </button>
        </td></tr>
        </tbody>
        <tbody id="hidden-section" style="display:none">
        <?php
    }
    if (!$isHidden && $catId !== $prevCatId) {
        $prevCatId = $catId;
        $catLabel  = $row->cat_name ?: 'Sans catégorie';
        ?>
        <tr><td colspan="3" class="pt-3 pb-1" style="border:none">
          <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <?= htmlentities($catLabel, ENT_COMPAT, $charset) ?>
          </span>
        </td></tr>
        <?php
    }
    ?>
        <tr class="<?= $isHidden ? 'text-muted' : '' ?>" data-team-id="<?= $id ?>">
          <td style="width:1.5rem">
            <input type="checkbox" class="form-check-input bulk-cb" name="ids[]" value="<?= $id ?>">
          </td>
          <td>
            <!-- Static view -->
            <span class="team-name-view">
              <a href="<?= $_SERVER['PHP_SELF'] ?>?team=<?= $id ?>"
                 class="text-decoration-none <?= $isHidden ? 'text-muted' : '' ?>">
                <?= $name ?>
              </a>
              <?php $cnt = $teamCounts[$id] ?? 0; if ($cnt > 0): ?>
              <span class="badge rounded-pill ms-1" style="font-size:0.65rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
              <?php endif ?>
            </span>
            <!-- Inline rename input (hidden by default) -->
            <span class="team-name-edit" style="display:none">
              <input type="text" class="form-control form-control-sm d-inline-block team-rename-input"
                     value="<?= $name ?>" maxlength="255"
                     style="width:auto;max-width:220px;font-size:0.82rem;padding:0.15rem 0.4rem;height:auto"
                     aria-label="Renommer le groupe"/>
              <button type="button" class="btn btn-sm btn-success team-rename-save ms-1 px-2 py-0"
                      title="Enregistrer (Entrée)" style="font-size:0.75rem;line-height:1.6">
                <i class="fas fa-check" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary team-rename-cancel px-2 py-0"
                      title="Annuler (Échap)" style="font-size:0.75rem;line-height:1.6">
                <i class="fas fa-times" aria-hidden="true"></i>
              </button>
            </span>
          </td>
          <td class="text-end" style="width:4rem;white-space:nowrap">
            <button type="button"
                    class="btn btn-link btn-sm team-rename-btn p-0 me-2 text-muted"
                    title="Renommer" style="font-size:0.78rem;line-height:1">
              <i class="fas fa-i-cursor" aria-hidden="true"></i>
            </button>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateTeam&amp;id=<?= $id ?>"
               class="text-decoration-none text-muted" title="Réglages du groupe" style="font-size:0.78rem">
              <i class="fas fa-cog"></i>
            </a>
          </td>
        </tr>
<?php } ?>
    </tbody>
  </table>
</form>

<script>
function toggleHiddenSection(btn) {
  var sec = document.getElementById('hidden-section');
  var chev = btn.querySelector('.fa-chevron-right');
  var open = sec.style.display !== 'none';
  sec.style.display = open ? 'none' : '';
  chev.style.transform = open ? '' : 'rotate(90deg)';
}
</script>

<!-- Metagroup name modal -->
<div class="modal fade" id="mg-name-modal" tabindex="-1" aria-labelledby="mg-name-modal-label" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="mg-name-modal-label" style="font-size:0.85rem">Nom du filtre</h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body py-2">
        <input type="text" id="mg-name-input" class="form-control form-control-sm" placeholder="Ex: Donateurs actifs" maxlength="255"/>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-primary btn-sm" onclick="submitMetagroup()">Créer</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var allCbs = document.querySelectorAll('.bulk-cb');
  var selectAll = document.getElementById('bulk-select-all');
  var bar = document.getElementById('bulk-bar');
  var countEl = document.getElementById('bulk-count');

  function updateBar() {
    var checked = document.querySelectorAll('.bulk-cb:checked');
    if (checked.length > 0) {
      bar.classList.remove('d-none');
      countEl.textContent = checked.length + ' sélectionné' + (checked.length > 1 ? 's' : '');
    } else {
      bar.classList.add('d-none');
    }
    selectAll.indeterminate = checked.length > 0 && checked.length < allCbs.length;
    selectAll.checked = checked.length === allCbs.length;
  }

  allCbs.forEach(function(cb) { cb.addEventListener('change', updateBar); });
  selectAll.addEventListener('change', function() {
    allCbs.forEach(function(cb) { cb.checked = selectAll.checked; });
    updateBar();
  });

  window.bulkAction = function(action) {
    document.getElementById('bulk-action').value = action;
    window.__dirtyOverride = true;
    document.getElementById('bulk-form').submit();
  };

  window.clearSelection = function() {
    allCbs.forEach(function(cb) { cb.checked = false; });
    selectAll.checked = false;
    updateBar();
  };

  var mgModal = new bootstrap.Modal(document.getElementById('mg-name-modal'));
  window.openMetagroupModal = function() {
    document.getElementById('mg-name-input').value = '';
    mgModal.show();
    setTimeout(function() { document.getElementById('mg-name-input').focus(); }, 300);
  };

  window.submitMetagroup = function() {
    var name = document.getElementById('mg-name-input').value.trim();
    if (!name) { document.getElementById('mg-name-input').focus(); return; }
    document.getElementById('bulk-action').value = 'bulkCreateMetagroup';
    var input = document.createElement('input');
    input.type = 'hidden'; input.name = 'metagroupName'; input.value = name;
    document.getElementById('bulk-form').appendChild(input);
    mgModal.hide();
    window.__dirtyOverride = true;
    document.getElementById('bulk-form').submit();
  };

  document.getElementById('mg-name-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); submitMetagroup(); }
  });

  // Inline rename
  document.querySelectorAll('.team-rename-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var row   = btn.closest('tr');
      var view  = row.querySelector('.team-name-view');
      var edit  = row.querySelector('.team-name-edit');
      var input = row.querySelector('.team-rename-input');
      view.style.display = 'none';
      edit.style.display = '';
      input.focus();
      input.select();
    });
  });

  function doRename(row) {
    var teamId = row.dataset.teamId;
    var input  = row.querySelector('.team-rename-input');
    var name   = input.value.trim();
    if (!name) { input.focus(); return; }

    var body = new URLSearchParams();
    body.append('action', 'renameTeam');
    body.append('id',     teamId);
    body.append('name',   name);

    fetch(window.location.pathname, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body.toString()
    }).then(function(r) { return r.json(); }).then(function(data) {
      if (data.ok) {
        var view  = row.querySelector('.team-name-view');
        var link  = view.querySelector('a');
        link.textContent = data.name;
        cancelRename(row);
      } else {
        alert(data.error || 'Erreur lors du renommage');
      }
    }).catch(function() { alert('Erreur réseau'); });
  }

  function cancelRename(row) {
    var view  = row.querySelector('.team-name-view');
    var edit  = row.querySelector('.team-name-edit');
    var input = row.querySelector('.team-rename-input');
    // Restore input to current displayed name in case user typed something
    var link = view.querySelector('a');
    input.value = link.textContent.trim();
    edit.style.display  = 'none';
    view.style.display  = '';
  }

  document.querySelectorAll('.team-rename-save').forEach(function(btn) {
    btn.addEventListener('click', function() { doRename(btn.closest('tr')); });
  });

  document.querySelectorAll('.team-rename-cancel').forEach(function(btn) {
    btn.addEventListener('click', function() { cancelRename(btn.closest('tr')); });
  });

  document.querySelectorAll('.team-rename-input').forEach(function(input) {
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter')  { e.preventDefault(); doRename(input.closest('tr')); }
      if (e.key === 'Escape') { cancelRename(input.closest('tr')); }
    });
  });
})();
</script>

