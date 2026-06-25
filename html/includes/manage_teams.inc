<div class="row mt-4">
  <div class="col-md-7 col-lg-6">

    <ul class="nav nav-tabs mb-3" id="manage-team-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-groupes-btn"
                data-bs-toggle="tab" data-bs-target="#tab-groupes"
                type="button" role="tab"
                aria-controls="tab-groupes" aria-selected="true">
          <i class="fas fa-users me-1" aria-hidden="true"></i>Groupes
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-orga-btn"
                data-bs-toggle="tab" data-bs-target="#tab-orga"
                type="button" role="tab"
                aria-controls="tab-orga" aria-selected="false">
          <i class="fas fa-tag me-1" aria-hidden="true"></i>Catégories
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-filters-btn"
                data-bs-toggle="tab" data-bs-target="#tab-filters"
                type="button" role="tab"
                aria-controls="tab-filters" aria-selected="false">
          <i class="fas fa-layer-group me-1" aria-hidden="true"></i>Filtres
        </button>
      </li>
    </ul>

    <div class="tab-content">

    <div class="tab-pane fade show active" id="tab-groupes" role="tabpanel" aria-labelledby="tab-groupes-btn">

    <?php
    // Member counts per team
    $countRows = $pdo->query("SELECT SUBSTRING(parameter, 6) AS team_id, COUNT(*) AS cnt FROM user_properties WHERE parameter LIKE 'team_%' GROUP BY parameter")->fetchAll(PDO::FETCH_OBJ);
    $teamCounts = [];
    foreach ($countRows as $cr) { $teamCounts[(int)$cr->team_id] = (int)$cr->cnt; }
    ?>

    <form id="bulk-form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" id="bulk-action" value=""/>
      <input type="hidden" name="view" value="manageTeam"/>

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
          <i class="fas fa-layer-group me-1" aria-hidden="true"></i>Créer un métagroupe
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
            <tr class="<?= $isHidden ? 'text-muted' : '' ?>">
              <td style="width:1.5rem">
                <input type="checkbox" class="form-check-input bulk-cb" name="ids[]" value="<?= $id ?>">
              </td>
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
            <h6 class="modal-title" id="mg-name-modal-label" style="font-size:0.85rem">Nom du métagroupe</h6>
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
        document.getElementById('bulk-form').submit();
      };

      document.getElementById('mg-name-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); submitMetagroup(); }
      });
    })();
    </script>

    <p class="form-section-title"><?= $GLOBAL['addTeam'] ?></p>

    <?php
    $allTeams   = $pdo->query("SELECT id, name FROM team WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    $categories = $pdo->query("SELECT DISTINCT id, name FROM metagroup WHERE name IS NOT NULL AND is_filter = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
    ?>

    <form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="addTeam">
      <input type="hidden" name="action" value="addTeamWithImport"/>
      <input type="hidden" name="view"   value="manageTeam"/>

      <div class="d-flex align-items-center gap-2 mb-2">
        <input type="text" class="form-control form-control-sm" id="name" name="name"
               placeholder="<?= htmlentities($GLOBAL['teamName'], ENT_COMPAT, $charset) ?>" required/>
        <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Ajouter</button>
      </div>
      <?php if (count($categories) > 0): ?>
      <div class="mb-3">
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
          <p class="text-muted mb-2" style="font-size:0.75rem">Copie ponctuelle — les membres sont copiés tels qu'ils sont maintenant. Pour un filtre dynamique, crée plutôt un filtre de groupes ci-dessous.</p>
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

    </div><!-- #tab-groupes -->

    <div class="tab-pane fade" id="tab-orga" role="tabpanel" aria-labelledby="tab-orga-btn">

    <p class="form-section-title" style="margin-top:0">Catégories</p>
    <p class="small text-muted mb-3">Organisent les groupes en sections visuelles dans les listes. Un groupe appartient à une seule catégorie.</p>

    <?php
    $allCats = $pdo->query("
        SELECT m.id, m.name,
               COUNT(DISTINCT j.teamid) AS team_count
        FROM metagroup m
        LEFT JOIN metagroup j
            ON j.id = m.id
            AND j.teamid IS NOT NULL
            AND j.name IS NULL
            AND j.teamid IN (SELECT id FROM team WHERE hidden = 0)
        WHERE m.name IS NOT NULL AND m.is_filter = 0
        GROUP BY m.id, m.name
        ORDER BY MIN(m.sort_order) ASC, m.name ASC
    ")->fetchAll(PDO::FETCH_OBJ);
    ?>
    <?php if (count($allCats) > 0): ?>
    <table class="table table-sm table-hover align-middle mb-3" style="font-size:0.82rem">
      <tbody id="cat-order-tbody">
      <?php foreach ($allCats as $cat): ?>
        <tr data-id="<?= (int)$cat->id ?>" draggable="true" style="cursor:default">
          <td style="width:1.5rem;cursor:grab;color:var(--ca-ink-muted)" class="cat-grip">
            <i class="fas fa-grip-vertical" style="font-size:0.75rem" aria-hidden="true"></i>
          </td>
          <td><?= htmlentities($cat->name, ENT_COMPAT, $charset) ?></td>
          <td class="text-muted" style="font-size:0.75rem;width:5rem"><?= (int)$cat->team_count ?> groupe<?= $cat->team_count != 1 ? 's' : '' ?></td>
          <td class="text-end" style="width:2rem">
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$cat->id ?>" class="text-decoration-none text-muted" title="Modifier">
              <i class="fas fa-pen" style="font-size:0.75rem"></i>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <style>
    #cat-order-tbody tr.drag-over { outline: 2px solid var(--ca-primary); outline-offset: -1px; }
    </style>
    <script>
    (function() {
      var tbody = document.getElementById('cat-order-tbody');
      if (!tbody) return;
      var dragging = null;

      tbody.addEventListener('dragstart', function(e) {
        dragging = e.target.closest('tr');
        dragging.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
      });

      tbody.addEventListener('dragend', function() {
        if (dragging) dragging.style.opacity = '';
        tbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
        saveOrder();
      });

      tbody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var target = e.target.closest('tr');
        if (!target || target === dragging) return;
        tbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
        target.classList.add('drag-over');
        var mid = target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
        if (e.clientY < mid) {
          tbody.insertBefore(dragging, target);
        } else {
          tbody.insertBefore(dragging, target.nextSibling);
        }
      });

      function saveOrder() {
        var ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(function(r) { return r.dataset.id; });
        var body = new URLSearchParams();
        body.append('action', 'updateCategoryOrder');
        body.append('view', 'manageTeam');
        ids.forEach(function(id) { body.append('ids[]', id); });
        fetch(window.location.pathname, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
      }
    })();
    </script>
    <?php else: ?>
    <p class="text-muted small mb-3">Aucune catégorie.</p>
    <?php endif ?>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" value="addMetagroup"/>
      <input type="hidden" name="view" value="manageTeam"/>
      <input type="hidden" name="is_filter" value="0"/>
      <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control form-control-sm" name="name" placeholder="Nom de la catégorie" maxlength="255" required style="max-width:240px"/>
        <button type="submit" class="btn btn-outline-primary btn-sm flex-shrink-0">Ajouter</button>
      </div>
    </form>

    </div><!-- #tab-orga -->

    <div class="tab-pane fade" id="tab-filters" role="tabpanel" aria-labelledby="tab-filters-btn">

    <p class="form-section-title" style="margin-top:0">Filtres de groupes</p>
    <p class="small text-muted mb-3">Regroupent plusieurs groupes en un filtre dynamique — accessible depuis la barre de navigation.</p>

    <?php
    $allFilters = $pdo->query("SELECT m.id, m.name, COUNT(j.teamid) AS team_count FROM metagroup m LEFT JOIN metagroup j ON j.id=m.id AND j.teamid IS NOT NULL WHERE m.name IS NOT NULL AND m.is_filter=1 GROUP BY m.id, m.name ORDER BY m.name")->fetchAll(PDO::FETCH_OBJ);
    ?>
    <?php if (count($allFilters) > 0): ?>
    <table class="table table-sm table-hover align-middle mb-3" style="font-size:0.82rem">
      <tbody>
      <?php foreach ($allFilters as $mg): ?>
        <tr>
          <td>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?metagroup=<?= (int)$mg->id ?>" class="text-decoration-none">
              <?= htmlentities($mg->name, ENT_COMPAT, $charset) ?>
            </a>
          </td>
          <td class="text-muted" style="font-size:0.75rem;width:5rem"><?= (int)$mg->team_count ?> groupe<?= $mg->team_count != 1 ? 's' : '' ?></td>
          <td class="text-end" style="width:2rem">
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$mg->id ?>" class="text-decoration-none text-muted" title="Modifier">
              <i class="fas fa-pen" style="font-size:0.75rem"></i>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted small mb-3">Aucun filtre.</p>
    <?php endif ?>

    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
      <input type="hidden" name="action" value="addMetagroup"/>
      <input type="hidden" name="view" value="manageTeam"/>
      <input type="hidden" name="is_filter" value="1"/>
      <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control form-control-sm" name="name" placeholder="Nom du filtre" maxlength="255" required style="max-width:240px"/>
        <button type="submit" class="btn btn-outline-primary btn-sm flex-shrink-0">Ajouter</button>
      </div>
    </form>

    </div><!-- #tab-filters -->

    </div><!-- .tab-content -->

    <script>
    (function() {
      var STORAGE_KEY = 'manageTeam_activeTab';
      var tabs = document.getElementById('manage-team-tabs');
      if (!tabs) return;
      var saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        var btn = tabs.querySelector('[data-bs-target="' + saved + '"]');
        if (btn) { bootstrap.Tab.getOrCreateInstance(btn).show(); }
      }
      tabs.addEventListener('shown.bs.tab', function(e) {
        sessionStorage.setItem(STORAGE_KEY, e.target.getAttribute('data-bs-target'));
      });
    })();
    </script>

  </div>
</div>
