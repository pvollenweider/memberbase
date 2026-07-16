<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for creating or editing a combined segment (category or filter).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$mgId = (int)$_REQUEST['id'];
$mg = new CombinedSegment();
$mg->lookupCombinedSegment($mgId);

// Read is_filter for this combined segment entity row
$stmtIsFilter = db()->prepare("SELECT is_filter FROM combined_segment WHERE id=?");
$stmtIsFilter->execute([$mgId]);
$isFilter = (int)($stmtIsFilter->fetchColumn() ?? 1);

// Segments currently in this combined segment
$stmtIn = db()->prepare("SELECT segment_id FROM combined_segment_member WHERE combined_segment_id=?");
$stmtIn->execute([$mgId]);
$memberSegmentIds = $stmtIn->fetchAll(PDO::FETCH_COLUMN);
$memberSegmentIds = array_map('intval', $memberSegmentIds);

// All segments (including hidden) — members first, then non-members, each alphabetically
$allSegmentsRaw = db()->query("SELECT id, name, hidden FROM segment ORDER BY name")->fetchAll(PDO::FETCH_OBJ);
// Only keep visible segments + hidden segments that are already members
$allSegments = array_filter($allSegmentsRaw, fn($t) => !(int)$t->hidden || in_array((int)$t->id, $memberSegmentIds));
usort($allSegments, function($a, $b) use ($memberSegmentIds) {
    $aIn = in_array((int)$a->id, $memberSegmentIds);
    $bIn = in_array((int)$b->id, $memberSegmentIds);
    if ($aIn !== $bIn) return $bIn - $aIn;
    return strcmp($a->name, $b->name);
});

// Category map: segment_id → [category_id, category_name]
$stmtCats = db()->query(
    "SELECT mm.segment_id, m.id AS cat_id, m.name AS cat_name
     FROM combined_segment_member mm
     JOIN combined_segment m ON m.id = mm.combined_segment_id AND m.is_filter = 0"
);
$segmentCategory = [];
foreach ($stmtCats->fetchAll(PDO::FETCH_OBJ) as $row) {
    $segmentCategory[(int)$row->segment_id] = ['id' => (int)$row->cat_id, 'name' => $row->cat_name];
}

// Build ordered groups: members-in-category, then unassigned members, then non-members
$catGroups = []; // cat_name => [segments]
$uncategorized = [];
foreach ($allSegments as $t) {
    if (isset($segmentCategory[(int)$t->id])) {
        $cn = $segmentCategory[(int)$t->id]['name'];
        $catGroups[$cn][] = $t;
    } else {
        $uncategorized[] = $t;
    }
}
ksort($catGroups);

// Member counts per segment
$cntRows = db()->query("SELECT segment_id, COUNT(*) AS cnt FROM contact_segment GROUP BY segment_id")->fetchAll(PDO::FETCH_OBJ);
$segmentCounts = [];
foreach ($cntRows as $cr) { $segmentCounts[(int)$cr->segment_id] = (int)$cr->cnt; }
?>
<?php if (!empty($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
  <i class="fas fa-check-circle mt-1 flex-shrink-0" aria-hidden="true"></i>
  <div>
    <strong><?= sprintf($GLOBAL['combinedSegmentCreated'], htmlspecialchars($mg->name, ENT_QUOTES, 'UTF-8')) ?></strong>
    <?= $GLOBAL['assignSegmentsBelowOr'] ?>
    <a href="<?= appUrl() ?>?view=settings&amp;tab=filters" class="alert-link"><?= $GLOBAL['backToListLink'] ?></a>.
  </div>
  <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="<?= $GLOBAL['close'] ?>"></button>
</div>
<?php endif ?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <?php $_mgBackTab = $isFilter ? 'filters' : 'categories'; $_mgBackLabel = $isFilter ? $GLOBAL['combinedSegmentsLower'] : $GLOBAL['categoriesLower']; ?>
    <div>
      <a href="<?= appUrl() ?>?view=settings&amp;tab=<?= $_mgBackTab ?>"
         class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i><?= sprintf($GLOBAL['backToLabel'], $_mgBackLabel) ?>
      </a>
    </div>

    <!-- Rename + type form -->
    <div class="card mb-4">
    <div class="card-body">
      <p class="form-section-title" style="margin-top:0"><?= $GLOBAL['editCombinedSegment'] ?></p>
      <form action="<?= appUrl() ?>" method="post">
        <input type="hidden" name="action" value="updateCombinedSegment"/>
        <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="<?= $_mgBackTab ?>"/>
        <input type="hidden" name="id" value="<?= $mgId ?>"/>

        <div class="row mb-3 align-items-center">
          <label for="mgname" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['name'] ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control form-control-sm" id="mgname" name="name"
                   value="<?= htmlentities($mg->getName(), ENT_COMPAT, $charset) ?>" maxlength="255" required/>
          </div>
        </div>

        <input type="hidden" name="is_filter" value="<?= $isFilter ? '1' : '0' ?>"/>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['update'] ?></button>
          <a href="<?= appUrl() ?>?view=settings&amp;tab=<?= $_mgBackTab ?>" class="btn btn-outline-secondary btn-sm"><?= $GLOBAL['cancel'] ?></a>
        </div>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->

    <!-- Segment membership -->
    <div class="card mb-4">
    <div class="card-body">
      <?php if ($isFilter): ?>

      <!-- SEGMENT COMBINÉ: all segments grouped by category, checkboxes -->
      <div class="d-flex align-items-baseline justify-content-between mb-1">
        <p class="form-section-title mb-0"><?= $GLOBAL['memberSegments'] ?></p>
        <a href="<?= appUrl() ?>?combinedSegment=<?= $mgId ?>" class="small">
          <?= $GLOBAL['viewFilteredList'] ?> <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
      </div>
      <p class="small text-muted mb-3"><?= $GLOBAL['autoSaveOnCheck'] ?></p>

      <div id="mg-segment-list" class="d-flex flex-column gap-1" style="font-size:0.85rem">
        <?php
        function renderSegmentCb($t, $memberSegmentIds, $charset, $segmentCounts) {
          global $GLOBAL;
          $isHidden = (int)$t->hidden; ?>
        <div class="form-check form-check-sm <?= $isHidden ? 'text-muted' : '' ?>">
          <input class="form-check-input mg-segment-cb" type="checkbox"
                 data-segmentid="<?= (int)$t->id ?>"
                 id="mgsegment_<?= (int)$t->id ?>"
                 <?= in_array((int)$t->id, $memberSegmentIds) ? 'checked' : '' ?>>
          <label class="form-check-label" for="mgsegment_<?= (int)$t->id ?>">
            <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
            <?php if ($isHidden): ?>
            <i class="fas fa-eye-slash ms-1" style="font-size:0.7rem" aria-label="<?= $GLOBAL['hiddenSegmentLower'] ?>" title="<?= $GLOBAL['hiddenSegment'] ?>"></i>
            <?php endif ?>
            <?php if (isset($segmentCounts[(int)$t->id])): ?>
            <span class="badge text-bg-light border ms-1" style="font-size:0.7rem;font-weight:500"><?= $segmentCounts[(int)$t->id] ?></span>
            <?php endif ?>
          </label>
        </div>
        <?php }

        if (!empty($catGroups)):
            foreach ($catGroups as $catName => $segments): ?>
          <p class="text-muted mb-0 mt-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600">
            <?= htmlentities($catName, ENT_COMPAT, $charset) ?>
          </p>
          <?php foreach ($segments as $t): renderSegmentCb($t, $memberSegmentIds, $charset, $segmentCounts); endforeach;
            endforeach;
            if (!empty($uncategorized)): ?>
          <p class="text-muted mb-0 mt-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?= $GLOBAL['noCategoryLabel'] ?></p>
          <?php foreach ($uncategorized as $t): renderSegmentCb($t, $memberSegmentIds, $charset, $segmentCounts); endforeach;
            endif;
        else:
            foreach ($allSegments as $t): renderSegmentCb($t, $memberSegmentIds, $charset, $segmentCounts); endforeach;
        endif;
        ?>
      </div>

      <?php else: ?>

      <!-- CATÉGORIE: members listed, then collapsible list to add others -->
      <?php
      $memberSegments    = array_filter($allSegments, fn($t) => in_array((int)$t->id, $memberSegmentIds));
      $nonMemberSegments = array_filter($allSegments, fn($t) => !in_array((int)$t->id, $memberSegmentIds) && !(int)$t->hidden);
      ?>

      <p class="form-section-title mb-1"><?= $GLOBAL['segmentsInThisCategory'] ?></p>

      <div id="mg-segment-list">
        <?php if (empty($memberSegments)): ?>
        <p class="text-muted small mb-2" id="mg-empty-msg"><?= $GLOBAL['noSegmentsInCategory'] ?></p>
        <?php else: ?>
        <p class="text-muted small mb-2" id="mg-empty-msg" style="display:none"><?= $GLOBAL['noSegmentsInCategory'] ?></p>
        <?php endif ?>

        <ul class="list-unstyled mb-0 d-flex flex-column gap-1" id="mg-member-list" style="font-size:0.85rem">
        <?php foreach ($memberSegments as $t): ?>
          <?php $_mHidden = (int)$t->hidden; ?>
          <li class="d-flex align-items-center justify-content-between gap-2 <?= $_mHidden ? 'text-muted' : '' ?>" id="mg-row-<?= (int)$t->id ?>">
            <span>
              <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
              <?php if ($_mHidden): ?>
              <i class="fas fa-eye-slash ms-1" style="font-size:0.7rem" aria-label="<?= $GLOBAL['hiddenSegmentLower'] ?>" title="<?= $GLOBAL['hiddenSegment'] ?>"></i>
              <?php endif ?>
              <?php if (isset($segmentCounts[(int)$t->id])): ?>
              <span class="badge text-bg-light border ms-1" style="font-size:0.7rem;font-weight:500"><?= $segmentCounts[(int)$t->id] ?></span>
              <?php endif ?>
            </span>
            <button type="button" class="btn btn-sm py-0 px-1 text-muted mg-segment-cb mg-remove-btn"
                    data-segmentid="<?= (int)$t->id ?>" data-checked="1"
                    title="<?= $GLOBAL['removeFromCategory'] ?>" aria-label="<?= sprintf($GLOBAL['removeName'], htmlentities($t->name, ENT_QUOTES, $charset)) ?>">
              <i class="fas fa-xmark" style="font-size:0.75rem" aria-hidden="true"></i>
            </button>
            <input type="hidden" class="mg-cat-member" data-segmentid="<?= (int)$t->id ?>" value="1"/>
          </li>
        <?php endforeach ?>
        </ul>

        <?php if (!empty($nonMemberSegments)):
          // Group non-members by current category
          $_nmByCat = []; // catName => [segments]
          $_nmNoCat = [];
          $_currentCatName = htmlentities($mg->getName(), ENT_COMPAT, $charset);
          foreach ($nonMemberSegments as $t) {
              $_tCat = $segmentCategory[(int)$t->id] ?? null;
              if ($_tCat) { $_nmByCat[$_tCat['name']][] = $t; }
              else        { $_nmNoCat[] = $t; }
          }
          ksort($_nmByCat);
        ?>
        <details class="mt-3" style="font-size:0.82rem" id="mg-add-details">
          <summary class="text-muted" style="cursor:pointer;list-style:none;user-select:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            <?= $GLOBAL['addSegments'] ?>
          </summary>
          <script>
            document.getElementById('mg-add-details').addEventListener('toggle', function() {
              this.querySelector('.fa-chevron-right').style.transform = this.open ? 'rotate(90deg)' : '';
            });
          </script>
          <div class="mt-2 d-flex flex-column gap-3 ps-1">
          <?php
          // Helper to render one add-row
          function renderAddRow($t, $charset, $segmentCounts, $currentCatName) {
              global $GLOBAL;
              $_nmHidden = (int)$t->hidden; ?>
            <li class="d-flex align-items-center justify-content-between gap-2" id="mg-row-<?= (int)$t->id ?>">
              <span class="text-muted">
                <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                <?php if ($_nmHidden): ?>
                <i class="fas fa-eye-slash ms-1" style="font-size:0.7rem" aria-label="<?= $GLOBAL['hiddenSegmentLower'] ?>" title="<?= $GLOBAL['hiddenSegment'] ?>"></i>
                <?php endif ?>
                <?php if (isset($segmentCounts[(int)$t->id])): ?>
                <span class="badge text-bg-light border ms-1" style="font-size:0.7rem;font-weight:500"><?= $segmentCounts[(int)$t->id] ?></span>
                <?php endif ?>
              </span>
              <button type="button" class="btn btn-sm py-0 px-1 text-muted mg-segment-cb mg-add-btn"
                      data-segmentid="<?= (int)$t->id ?>" data-checked="0"
                      data-dest="<?= $currentCatName ?>"
                      title="<?= sprintf($GLOBAL['moveToCategory'], $currentCatName) ?>"
                      aria-label="<?= sprintf($GLOBAL['moveNameToCategory'], htmlentities($t->name, ENT_QUOTES, $charset), $currentCatName) ?>">
                <i class="fas fa-arrow-right" style="font-size:0.75rem" aria-hidden="true"></i>
              </button>
              <input type="hidden" class="mg-cat-member" data-segmentid="<?= (int)$t->id ?>" value="0"/>
            </li>
          <?php }

          foreach ($_nmByCat as $_catLabel => $_catSegments): ?>
          <div>
            <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600">
              <?= htmlentities($_catLabel, ENT_COMPAT, $charset) ?>
            </p>
            <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
            <?php foreach ($_catSegments as $t): renderAddRow($t, $charset, $segmentCounts, $_currentCatName); endforeach ?>
            </ul>
          </div>
          <?php endforeach ?>
          <?php if (!empty($_nmNoCat)): ?>
          <div>
            <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?= $GLOBAL['noCategoryLabel'] ?></p>
            <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
            <?php foreach ($_nmNoCat as $t): renderAddRow($t, $charset, $segmentCounts, $_currentCatName); endforeach ?>
            </ul>
          </div>
          <?php endif ?>
          </div>
        </details>
        <?php endif ?>
      </div>

      <?php endif ?>
    </div><!-- .card-body -->
    </div><!-- .card -->

    <!-- Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
      <div id="mg-toast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex align-items-center">
          <div class="toast-body" id="mg-toast-body"></div>
          <button type="button" id="mg-toast-undo"
                  class="btn btn-sm btn-link text-white text-decoration-underline me-1 flex-shrink-0"
                  style="display:none;font-size:0.78rem;padding:0.1rem 0.4rem"
                  aria-label="<?= $GLOBAL['undoLastAction'] ?>"><?= $GLOBAL['cancel'] ?></button>
          <button type="button" class="btn-close btn-close-white me-2 ms-1 flex-shrink-0" data-bs-dismiss="toast" aria-label="<?= $GLOBAL['close'] ?>"></button>
        </div>
      </div>
    </div>

    <script>
    (function() {
      var mgId = <?= $mgId ?>;
      var isFilter = <?= $isFilter ? 'true' : 'false' ?>;
      var toast = new bootstrap.Toast(document.getElementById('mg-toast'), { delay: 4000 });
      var toastEl = document.getElementById('mg-toast');
      var toastBody = document.getElementById('mg-toast-body');
      var undoBtn = document.getElementById('mg-toast-undo');

      function showToast(msg, ok, undoFn) {
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(ok ? 'bg-success' : 'bg-danger');
        toastBody.textContent = msg;
        if (undoFn && ok) {
          undoBtn.style.display = '';
          undoBtn._handler && undoBtn.removeEventListener('click', undoBtn._handler);
          undoBtn.addEventListener('click', undoBtn._handler = function() {
            toast.hide();
            undoFn();
          });
        } else {
          undoBtn.style.display = 'none';
        }
        toast.show();
      }

      function getCurrentMemberIds() {
        return Array.from(document.querySelectorAll('.mg-cat-member'))
          .filter(function(h) { return h.value === '1'; })
          .map(function(h) { return h.dataset.segmentid; });
      }

      function saveMembers(memberIds, onSuccess, onError) {
        var body = new URLSearchParams();
        body.append('action', 'updateCombinedSegmentMembers');
        body.append('view', 'settings');
        body.append('id', mgId);
        memberIds.forEach(function(id) { body.append('segments[]', id); });
        fetch(window.location.pathname, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true', 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' },
          body: body.toString()
        }).then(function(r) {
          if (r.ok) { onSuccess(); }
          else { (onError || function() { showToast(<?= json_encode($GLOBAL['saveError']) ?>, false); })(); }
        }).catch(function() { showToast(<?= json_encode($GLOBAL['networkError']) ?>, false); });
      }

      if (isFilter) {
        // Métagroupe: checkbox mode
        document.querySelectorAll('.mg-segment-cb').forEach(function(cb) {
          cb.addEventListener('change', function() {
            var prevChecked = !cb.checked;
            var prevIds = Array.from(document.querySelectorAll('.mg-segment-cb'))
              .filter(function(c) { return c !== cb ? c.checked : prevChecked; })
              .map(function(c) { return c.dataset.segmentid; });
            var checked = Array.from(document.querySelectorAll('.mg-segment-cb:checked'))
                               .map(function(c) { return c.dataset.segmentid; });
            saveMembers(checked, function() {
              var label = document.querySelector('label[for="mgsegment_' + cb.dataset.segmentid + '"]').textContent.trim();
              showToast((cb.checked ? '✓ ' : '✗ ') + label, true, function() {
                cb.checked = prevChecked;
                saveMembers(prevIds, function() { showToast(<?= json_encode($GLOBAL['actionUndone']) ?>, true, null); });
              });
            });
          });
        });
      } else {
        // Catégorie: add/remove button mode
        function applyDomMove(btn, addMode) {
          var segmentid = btn.dataset.segmentid;
          var row = document.getElementById('mg-row-' + segmentid);
          var hidden = row.querySelector('.mg-cat-member');
          hidden.value = addMode ? '1' : '0';
          var memberList = document.getElementById('mg-member-list');
          var addList = document.querySelector('#mg-add-details ul');
          var span = row.querySelector('span');
          if (addMode) {
            span.classList.remove('text-muted');
            btn.classList.remove('mg-add-btn');
            btn.classList.add('mg-remove-btn');
            btn.dataset.checked = '1';
            btn.title = <?= json_encode($GLOBAL['removeFromCategory']) ?>;
            btn.setAttribute('aria-label', <?= json_encode($GLOBAL['removeName']) ?>.replace('%s', span.textContent.trim()));
            btn.querySelector('i').className = 'fas fa-xmark';
            btn.removeEventListener('click', btn._addHandler);
            btn.addEventListener('click', btn._removeHandler = function() { moveRow(btn, false); });
            memberList.appendChild(row);
          } else {
            span.classList.add('text-muted');
            btn.classList.remove('mg-remove-btn');
            btn.classList.add('mg-add-btn');
            btn.dataset.checked = '0';
            btn.title = <?= json_encode($GLOBAL['addToCategory']) ?>;
            btn.setAttribute('aria-label', <?= json_encode($GLOBAL['addName']) ?>.replace('%s', span.textContent.trim()));
            btn.querySelector('i').className = 'fas fa-plus';
            btn.removeEventListener('click', btn._removeHandler);
            btn.addEventListener('click', btn._addHandler = function() { moveRow(btn, true); });
            if (addList) addList.appendChild(row);
          }
          var emptyMsg = document.getElementById('mg-empty-msg');
          if (emptyMsg) emptyMsg.style.display = memberList.children.length === 0 ? '' : 'none';
        }

        function moveRow(btn, addMode) {
          var prevIds = getCurrentMemberIds();
          var segmentid = btn.dataset.segmentid;
          var row = document.getElementById('mg-row-' + segmentid);
          var segmentName = row.querySelector('span').textContent.trim();
          var destName = btn.dataset.dest || '';

          applyDomMove(btn, addMode);
          var memberIds = getCurrentMemberIds();
          saveMembers(memberIds, function() {
            showToast(addMode ? ('→ ' + destName + ' : ' + segmentName) : ('✗ ' + segmentName), true, function() {
              applyDomMove(btn, !addMode);
              saveMembers(prevIds, function() { showToast(<?= json_encode($GLOBAL['actionUndone']) ?>, true, null); });
            });
          });
        }

        document.querySelectorAll('.mg-remove-btn').forEach(function(btn) {
          btn.addEventListener('click', btn._removeHandler = function() { moveRow(btn, false); });
        });
        document.querySelectorAll('.mg-add-btn').forEach(function(btn) {
          btn.addEventListener('click', btn._addHandler = function() { moveRow(btn, true); });
        });
      }
    })();
    </script>

    <!-- Delete -->
    <div class="card mb-4">
    <div class="card-body">
      <p class="form-section-title" style="color:var(--ca-danger);margin-top:0"><?= $GLOBAL['delete'] ?></p>
      <p class="small text-muted mb-2"><?= $GLOBAL['deleteCombinedSegmentHelp'] ?></p>
      <form action="<?= appUrl() ?>" method="post" hx-boost="false">
        <input type="hidden" name="action" value="deleteCombinedSegment"/>
        <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="<?= $_mgBackTab ?>"/>
        <input type="hidden" name="id" value="<?= $mgId ?>"/>
        <button type="button" class="btn btn-sm btn-danger"
                data-bs-toggle="modal" data-bs-target="#modal-delete-combined-segment">
          <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
        </button>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->

  </div>
</div>

<div class="modal fade" id="modal-delete-combined-segment" tabindex="-1" aria-labelledby="modal-delete-combined-segment-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-combined-segment-label"><?= $GLOBAL['delete'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['deleteNameConfirm'], htmlentities($mg->getName(), ENT_QUOTES, $charset)) ?>
        <p class="small text-muted mt-1 mb-0"><?= $GLOBAL['memberSegmentsNotDeleted'] ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-danger"
                onclick="document.querySelector('form [name=action][value=deleteCombinedSegment]').closest('form').submit()">
          <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
