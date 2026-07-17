<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin UI for managing segments and their members.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Member counts per segment (guard: tables may not exist if migration is pending)
try {
    $countRows = db()->query("SELECT segment_id, COUNT(*) AS cnt FROM contact_segment GROUP BY segment_id")->fetchAll(PDO::FETCH_OBJ);
    $allSegments = db()->query("SELECT id, name FROM segment WHERE hidden = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $countRows = [];
    $allSegments = [];
}
$segmentCounts = [];
foreach ($countRows as $cr) { $segmentCounts[(int)$cr->segment_id] = (int)$cr->cnt; }
try {
    $categories = db()->query("SELECT id, name FROM combined_segment WHERE is_filter = 0 ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $categories = [];
}

// Category map for import grouping: segment_id → category name (guard: table may not exist yet)
$_importCatRows = [];
try {
    $_importCatRows = db()->query("
        SELECT mm.segment_id AS segmentid, m.name AS cat_name, MIN(m.sort_order) AS sort_order
        FROM combined_segment_member mm
        JOIN combined_segment m ON m.id = mm.combined_segment_id AND m.is_filter = 0
        GROUP BY mm.segment_id, m.name
    ")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {}
$_importSegCat = [];
foreach ($_importCatRows as $_icr) {
    $_importSegCat[(int)$_icr->segmentid] = $_icr->cat_name;
}
// Group visible segments by category for import section
$_importByCat  = []; // cat_name => [segment objects]
$_importNoCat  = [];
foreach ($allSegments as $_it) {
    if (isset($_importSegCat[(int)$_it->id])) {
        $_importByCat[$_importSegCat[(int)$_it->id]][] = $_it;
    } else {
        $_importNoCat[] = $_it;
    }
}
ksort($_importByCat);

// Cascade rules: assigning source_segment_id also assigns target_segment_id (#154)
try {
    $cascadeRules = db()->query("
        SELECT r.id, r.source_segment_id, r.target_segment_id, s.name AS source_name, t.name AS target_name
        FROM segment_cascade_rule r
        JOIN segment s ON s.id = r.source_segment_id
        JOIN segment t ON t.id = r.target_segment_id
        ORDER BY s.name, t.name
    ")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $cascadeRules = [];
}

// Emit undo toast sentinel if set by previous bulk action
if (!isset($_SESSION)) session_start();
if (!empty($_SESSION['segment_toast'])) {
    $_gt = $_SESSION['segment_toast'];
    unset($_SESSION['segment_toast']);
    $_gtHidden  = ($_gt['undo_act'] === 'bulkHide') ? 1 : 0;
    $_gtIdsStr  = implode(',', $_gt['undo_ids']);
    $_gtUndoUrl = appUrl() . '?action=undoSegmentVisibility&hidden=' . $_gtHidden . '&ids=' . urlencode($_gtIdsStr) . '&view=settings&tab=groups';
    echo '<div id="casa-membership-toast" hidden data-msg="' . htmlspecialchars($_gt['msg'], ENT_QUOTES) . '" data-undo-url="' . htmlspecialchars($_gtUndoUrl, ENT_QUOTES) . '"></div>';
}
?>

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['addSegment'] ?></h2></div>
<div class="card-body">

<form role="form" action="<?= appUrl() ?>" method="post" name="addSegment" class="mb-0">
  <input type="hidden" name="action" value="addSegmentWithImport"/>
  <input type="hidden" name="view"   value="settings"/>
  <input type="hidden" name="tab"    value="groups"/>

  <div class="d-flex align-items-center gap-2 mb-2">
    <input type="text" class="form-control form-control-sm" id="name" name="name"
           placeholder="<?= htmlentities($GLOBAL['segmentName'], ENT_COMPAT, $charset) ?>" required/>
    <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><?= $GLOBAL['addBtn'] ?></button>
  </div>
  <?php if (count($categories) > 0): ?>
  <div class="mb-2">
    <select name="categoryId" class="form-select form-select-sm" style="max-width:260px">
      <option value="0"><?= $GLOBAL['noCategoryOptionLower'] ?></option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat->id ?>"><?= htmlentities($cat->name, ENT_COMPAT, $charset) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <?php endif ?>

  <?php if (count($allSegments) > 0): ?>
  <details style="font-size:0.8rem">
    <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
      <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
      <?= $GLOBAL['importMembersFromOtherSegments'] ?>
    </summary>
    <script>
      document.currentScript.closest('details').addEventListener('toggle', function(e) {
        var icon = this.querySelector('.fa-chevron-right');
        icon.style.transform = this.open ? 'rotate(90deg)' : '';
      });
    </script>
    <div class="mt-2 p-3" style="background:var(--ca-ground);border-radius:6px">
      <p class="text-muted mb-2" style="font-size:0.75rem"><?= $GLOBAL['importCopyHint'] ?></p>
      <div class="d-flex flex-column gap-1">
        <?php
        function _renderImportCb($t, $segmentCounts, $charset) { ?>
        <div class="form-check form-check-sm">
          <input class="form-check-input" type="checkbox"
                 name="importFrom[]" value="<?= (int)$t->id ?>"
                 id="import_<?= (int)$t->id ?>">
          <label class="form-check-label" for="import_<?= (int)$t->id ?>">
            <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
            <?php $cnt = $segmentCounts[(int)$t->id] ?? 0; if ($cnt > 0): ?>
            <span class="badge rounded-pill ms-1" style="font-size:0.6rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
            <?php endif ?>
          </label>
        </div>
        <?php }

        foreach ($_importByCat as $_catName => $_catSegments): ?>
        <p class="text-muted mb-0 mt-2" style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">
          <?= htmlentities($_catName, ENT_COMPAT, $charset) ?>
        </p>
        <?php foreach ($_catSegments as $t): _renderImportCb($t, $segmentCounts, $charset); endforeach;
        endforeach;

        if (!empty($_importNoCat)):
          if (!empty($_importByCat)): ?>
        <p class="text-muted mb-0 mt-2" style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em"><?= $GLOBAL['noCategoryLabel'] ?></p>
          <?php endif;
          foreach ($_importNoCat as $t): _renderImportCb($t, $segmentCounts, $charset); endforeach;
        endif; ?>
      </div>
    </div>
  </details>
  <?php endif ?>
</form>
</div><!-- .card-body -->
</div><!-- .card -->

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
try {
    $_gsStmt = db()->query("
        SELECT t.id, t.name, t.hidden,
               COALESCE(cat.name, '') AS cat_name,
               COALESCE(cat.id, 0) AS cat_id,
               COALESCE(cat.sort_order, 99999) AS cat_sort
        FROM segment t
        LEFT JOIN (
            SELECT mm.segment_id, MIN(c.id) AS id, MIN(c.name) AS name, MIN(c.sort_order) AS sort_order
            FROM combined_segment_member mm
            JOIN combined_segment c ON c.id = mm.combined_segment_id AND c.is_filter = 0
            GROUP BY mm.segment_id
        ) cat ON cat.segment_id = t.id
        ORDER BY t.hidden ASC, cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
    ")->fetchAll(PDO::FETCH_OBJ);
    $_gsLoadError = false;
} catch (PDOException $e) {
    $_gsStmt = [];
    $_gsLoadError = true;
}
$_gsVisible = array_filter($_gsStmt, fn($r) => !(int)$r->hidden);
$_gsHidden  = array_filter($_gsStmt, fn($r) => (int)$r->hidden);

// Shared row renderer for both the visible and hidden segment tables —
// identical markup, only the category-divider row differs (hidden segments
// aren't grouped by category).
$_gsRenderRow = function ($row) use ($charset, $segmentCounts) {
    $id       = $row->id;
    $name     = htmlentities($row->name, ENT_COMPAT, $charset);
    $isHidden = (int) $row->hidden;
    ?>
    <tr class="<?= $isHidden ? 'text-muted' : '' ?>" data-segment-id="<?= $id ?>">
      <td style="width:1.5rem">
        <input type="checkbox" class="form-check-input bulk-cb" name="ids[]" value="<?= $id ?>">
      </td>
      <td>
        <!-- Static view -->
        <span class="segment-name-view">
          <a href="<?= appUrl() ?>?segment=<?= $id ?>"
             class="text-decoration-none <?= $isHidden ? 'text-muted' : '' ?>">
            <?= $name ?>
          </a>
          <?php $cnt = $segmentCounts[$id] ?? 0; if ($cnt > 0): ?>
          <span class="badge rounded-pill ms-1" style="font-size:0.65rem;font-weight:500;background:var(--ca-primary-light);color:var(--ca-primary-dark)"><?= $cnt ?></span>
          <?php endif ?>
        </span>
        <!-- Inline rename input (hidden by default) -->
        <span class="segment-name-edit" style="display:none">
          <input type="text" class="form-control form-control-sm d-inline-block segment-rename-input"
                 value="<?= $name ?>" maxlength="255"
                 style="width:auto;max-width:220px;font-size:0.82rem;padding:0.15rem 0.4rem;height:auto"
                 aria-label="<?= sprintf($GLOBAL['renameSegmentAria'], $name) ?>"/>
          <button type="button" class="btn btn-sm btn-success segment-rename-save ms-1 px-2 py-0"
                  aria-label="<?= $GLOBAL['saveEnterAria'] ?>" style="font-size:0.75rem;line-height:1.6">
            <i class="fas fa-check" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary segment-rename-cancel px-2 py-0"
                  aria-label="<?= $GLOBAL['cancelEscapeAria'] ?>" style="font-size:0.75rem;line-height:1.6">
            <i class="fas fa-xmark" aria-hidden="true"></i>
          </button>
        </span>
      </td>
      <td class="text-end" style="width:4rem;white-space:nowrap">
        <button type="button"
                class="btn btn-link btn-sm segment-rename-btn p-0 me-2 text-muted"
                aria-label="<?= sprintf($GLOBAL['renameNameAria'], $name) ?>" style="font-size:0.78rem;line-height:1">
          <i class="fas fa-i-cursor" aria-hidden="true"></i>
        </button>
        <a href="<?= appUrl() ?>?view=updateSegment&amp;id=<?= $id ?>"
           class="text-decoration-none text-muted" aria-label="<?= sprintf($GLOBAL['segmentSettingsAria'], $name) ?>" style="font-size:0.78rem">
          <i class="fas fa-gear" aria-hidden="true"></i>
        </a>
      </td>
    </tr>
    <?php
};
?>
<form id="bulk-form" action="<?= appUrl() ?>" method="post">
  <input type="hidden" name="action" id="bulk-action" value=""/>
  <input type="hidden" name="view" value="settings"/>
  <input type="hidden" name="tab"  value="groups"/>

  <!-- Bulk action bar (hidden until selection) -->
  <div id="bulk-bar" class="d-none mb-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.8rem">
    <span id="bulk-count" class="text-muted"></span>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('bulkHide')">
      <i class="fas fa-eye-slash me-1" aria-hidden="true"></i><?= $GLOBAL['hide'] ?>
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkAction('bulkShow')">
      <i class="fas fa-eye me-1" aria-hidden="true"></i><?= $GLOBAL['show'] ?>
    </button>
    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openCombinedSegmentModal()">
      <i class="fas fa-layer-group me-1" aria-hidden="true"></i><?= $GLOBAL['createFilter'] ?>
    </button>
    <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="clearSelection()"><?= $GLOBAL['deselect'] ?></button>
  </div>

<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['groups'] ?></h2></div>
<div class="card-body">
  <?php if ($_gsLoadError): ?>
  <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:0.85rem">
    <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['loadError'] ?>
  </div>
  <?php else: ?>
  <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
    <thead>
      <tr>
        <th style="width:1.5rem">
          <input type="checkbox" class="form-check-input" id="bulk-select-all" title="<?= $GLOBAL['selectAll'] ?>">
        </th>
        <th colspan="2"></th>
      </tr>
    </thead>
    <tbody>
<?php
$_gsPrevCatId = -1;
foreach ($_gsVisible as $row) {
    $catId = (int) $row->cat_id;
    if ($catId !== $_gsPrevCatId) {
        $_gsPrevCatId = $catId;
        $catLabel = $row->cat_name ?: $GLOBAL['noCategoryLabel'];
        ?>
        <tr><td colspan="3" class="pt-3 pb-1" style="border:none">
          <span style="font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <?= htmlentities($catLabel, ENT_COMPAT, $charset) ?>
          </span>
        </td></tr>
        <?php
    }
    $_gsRenderRow($row);
}
?>
    </tbody>
  </table>
  <?php endif ?>
</div><!-- .card-body -->
</div><!-- .card -->

<?php if (!$_gsLoadError && count($_gsHidden) > 0): ?>
<div class="card mb-4">
  <div class="card-header" style="cursor:pointer" onclick="toggleHiddenCard(this)" role="button" tabindex="0"
       onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleHiddenCard(this);}">
    <h2 class="h6 mb-0 d-flex align-items-center justify-content-between">
      <span><i class="fas fa-eye-slash me-1" aria-hidden="true"></i><?= $GLOBAL['hiddenPlural'] ?> (<?= count($_gsHidden) ?>)</span>
      <i class="fas fa-chevron-right" id="hidden-card-chevron" style="font-size:0.75rem;transition:transform 0.15s" aria-hidden="true"></i>
    </h2>
  </div>
  <div class="collapse" id="hidden-segments-body">
    <div class="card-body">
      <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
        <tbody>
<?php foreach ($_gsHidden as $row) { $_gsRenderRow($row); } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif ?>
</form>

<script>
function toggleHiddenCard() {
  bootstrap.Collapse.getOrCreateInstance(document.getElementById('hidden-segments-body')).toggle();
}
(function () {
  var sec = document.getElementById('hidden-segments-body');
  var chev = document.getElementById('hidden-card-chevron');
  if (!sec || !chev) return;
  sec.addEventListener('show.bs.collapse', function () { chev.style.transform = 'rotate(90deg)'; });
  sec.addEventListener('hide.bs.collapse', function () { chev.style.transform = ''; });
})();
</script>

<!-- Combined segment name modal -->
<div class="modal fade" id="mg-name-modal" tabindex="-1" aria-labelledby="mg-name-modal-label" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="mg-name-modal-label" style="font-size:0.85rem"><?= $GLOBAL['filterNamePlaceholder'] ?></h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body py-2">
        <input type="text" id="mg-name-input" class="form-control form-control-sm" placeholder="<?= $GLOBAL['filterNameExample'] ?>" maxlength="255"/>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-primary btn-sm" onclick="submitCombinedSegment()"><?= $GLOBAL['create'] ?></button>
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
      countEl.textContent = <?= json_encode($GLOBAL['selectedCount']) ?>.replace('%d', checked.length).replace('%s', checked.length > 1 ? 's' : '');
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
    document.getElementById('bulk-form').requestSubmit();
  };

  window.clearSelection = function() {
    allCbs.forEach(function(cb) { cb.checked = false; });
    selectAll.checked = false;
    updateBar();
  };

  var mgModal = new bootstrap.Modal(document.getElementById('mg-name-modal'));
  window.openCombinedSegmentModal = function() {
    document.getElementById('mg-name-input').value = '';
    mgModal.show();
    setTimeout(function() { document.getElementById('mg-name-input').focus(); }, 300);
  };

  window.submitCombinedSegment = function() {
    var name = document.getElementById('mg-name-input').value.trim();
    if (!name) { document.getElementById('mg-name-input').focus(); return; }
    document.getElementById('bulk-action').value = 'bulkCreateCombinedSegment';
    var input = document.createElement('input');
    input.type = 'hidden'; input.name = 'combinedSegmentName'; input.value = name;
    document.getElementById('bulk-form').appendChild(input);
    mgModal.hide();
    window.__dirtyOverride = true;
    document.getElementById('bulk-form').requestSubmit();
  };

  document.getElementById('mg-name-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); submitCombinedSegment(); }
  });

  // Live region for rename confirmations (screen readers)
  var _renameStatus = document.createElement('div');
  _renameStatus.setAttribute('role', 'status');
  _renameStatus.setAttribute('aria-live', 'polite');
  _renameStatus.className = 'hide';
  document.body.appendChild(_renameStatus);

  // Inline rename
  document.querySelectorAll('.segment-rename-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var row   = btn.closest('tr');
      row._renameTriggerBtn = btn; // store for focus return
      var view  = row.querySelector('.segment-name-view');
      var edit  = row.querySelector('.segment-name-edit');
      var input = row.querySelector('.segment-rename-input');
      view.style.display = 'none';
      edit.style.display = '';
      input.focus();
      input.select();
    });
  });

  function doRename(row) {
    var segmentId = row.dataset.segmentId;
    var input  = row.querySelector('.segment-rename-input');
    var name   = input.value.trim();
    if (!name) { input.focus(); return; }

    var body = new URLSearchParams();
    body.append('action', 'renameSegment');
    body.append('id',     segmentId);
    body.append('name',   name);

    fetch(window.location.pathname, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true', 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' },
      body:    body.toString()
    }).then(function(r) {
      return r.text().then(function(txt) {
        try { return JSON.parse(txt); }
        catch(e) { throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200)); }
      });
    }).then(function(data) {
      if (data.ok) {
        var view = row.querySelector('.segment-name-view');
        var link = view.querySelector('a');
        link.textContent = data.name;
        input.value = data.name;
        input.setAttribute('aria-label', <?= json_encode($GLOBAL['renameSegmentAria']) ?>.replace('%s', data.name));
        row.querySelector('.segment-rename-btn').setAttribute('aria-label', <?= json_encode($GLOBAL['renameNameAria']) ?>.replace('%s', data.name));
        cancelRename(row);
        _renameStatus.textContent = <?= json_encode($GLOBAL['segmentRenamedTo']) ?>.replace('%s', data.name);
      } else {
        alert(data.error || <?= json_encode($GLOBAL['renameError']) ?>);
      }
    }).catch(function(err) { alert(err && err.message ? err.message : <?= json_encode($GLOBAL['networkError']) ?>); });
  }

  function cancelRename(row) {
    var view  = row.querySelector('.segment-name-view');
    var edit  = row.querySelector('.segment-name-edit');
    edit.style.display = 'none';
    view.style.display = '';
    // Return focus to trigger button
    if (row._renameTriggerBtn) { row._renameTriggerBtn.focus(); }
  }

  document.querySelectorAll('.segment-rename-save').forEach(function(btn) {
    btn.addEventListener('click', function() { doRename(btn.closest('tr')); });
  });

  document.querySelectorAll('.segment-rename-cancel').forEach(function(btn) {
    btn.addEventListener('click', function() { cancelRename(btn.closest('tr')); });
  });

  document.querySelectorAll('.segment-rename-input').forEach(function(input) {
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter')  { e.preventDefault(); doRename(input.closest('tr')); }
      if (e.key === 'Escape') { cancelRename(input.closest('tr')); }
    });
  });
})();
</script>

<?php if (count($allSegments) > 1): ?>
<div class="card mb-4">
<div class="card-header"><h2 class="h6 mb-0"><?= $GLOBAL['segmentCascadeRules'] ?></h2></div>
<div class="card-body">
<p class="text-muted mb-2" style="font-size:0.78rem"><?= $GLOBAL['segmentCascadeRulesHint'] ?></p>

<?php if ($cascadeRules): ?>
<ul class="list-unstyled mb-3" style="font-size:0.82rem">
  <?php foreach ($cascadeRules as $rule): ?>
  <li class="d-flex align-items-center gap-2 mb-1">
    <span><?= htmlspecialchars($rule->source_name, ENT_QUOTES, $charset) ?> <i class="fas fa-arrow-right mx-1 text-muted" aria-hidden="true"></i> <?= htmlspecialchars($rule->target_name, ENT_QUOTES, $charset) ?></span>
    <form method="post" action="<?= appUrl() ?>" class="d-inline" data-no-dirty>
      <input type="hidden" name="action" value="deleteSegmentCascadeRule">
      <input type="hidden" name="ruleId" value="<?= (int)$rule->id ?>">
      <input type="hidden" name="view" value="settings">
      <input type="hidden" name="tab" value="groups">
      <button type="submit" class="btn btn-sm py-0 px-1 text-muted" title="<?= $GLOBAL['delete'] ?>">
        <i class="fas fa-trash-can" style="font-size:0.7rem" aria-hidden="true"></i>
      </button>
    </form>
  </li>
  <?php endforeach ?>
</ul>
<?php endif ?>

<form method="post" action="<?= appUrl() ?>" class="d-flex align-items-center gap-2 flex-wrap" data-no-dirty>
  <input type="hidden" name="action" value="addSegmentCascadeRule">
  <input type="hidden" name="view" value="settings">
  <input type="hidden" name="tab" value="groups">
  <select name="sourceSegmentId" class="form-select form-select-sm" style="max-width:220px" required data-no-dirty>
    <option value=""><?= $GLOBAL['segmentCascadeSourceLabel'] ?></option>
    <?php foreach ($allSegments as $seg): ?>
    <option value="<?= (int)$seg->id ?>"><?= htmlentities($seg->name, ENT_COMPAT, $charset) ?></option>
    <?php endforeach ?>
  </select>
  <i class="fas fa-arrow-right text-muted" aria-hidden="true"></i>
  <select name="targetSegmentId" class="form-select form-select-sm" style="max-width:220px" required data-no-dirty>
    <option value=""><?= $GLOBAL['segmentCascadeTargetLabel'] ?></option>
    <?php foreach ($allSegments as $seg): ?>
    <option value="<?= (int)$seg->id ?>"><?= htmlentities($seg->name, ENT_COMPAT, $charset) ?></option>
    <?php endforeach ?>
  </select>
  <button type="submit" class="btn btn-outline-primary btn-sm"><?= $GLOBAL['addBtn'] ?></button>
</form>
</div><!-- .card-body -->
</div><!-- .card -->
<?php endif ?>

