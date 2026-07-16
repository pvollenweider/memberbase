<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Form for creating or editing a segment.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    $url = appUrl() . '?view=settings&tab=groups';
    if (!empty($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
    exit;
}
$segment = new Segment();
$segment->lookupSegment($id);

// Fetch members of this segment
$stmtMembers = db()->prepare(
    "SELECT u.id, u.lastname, u.firstname, u.society
     FROM contact u
     INNER JOIN contact_segment us ON us.user_id = u.id
     WHERE us.segment_id = ? AND u.status=1
     ORDER BY u.lastname, u.firstname"
);
$stmtMembers->execute([$id]);
$members = $stmtMembers->fetchAll(PDO::FETCH_OBJ);
$memberCount = count($members);

// Fetch other segments for reassignment + import
$stmtSegments = db()->query("SELECT id, name FROM segment WHERE id != $id AND hidden = 0 ORDER BY name");
$otherSegments = $stmtSegments->fetchAll(PDO::FETCH_OBJ);

// Fetch categories and current category for this segment
$allCats = db()->query("SELECT id, name FROM combined_segment WHERE is_filter=0 ORDER BY name ASC")->fetchAll(PDO::FETCH_OBJ);
$stmtCurrentCat = db()->prepare("SELECT c.id FROM combined_segment_member mm JOIN combined_segment c ON c.id=mm.combined_segment_id AND c.is_filter=0 WHERE mm.segment_id=? LIMIT 1");
$stmtCurrentCat->execute([$id]);
$currentCatId = (int)($stmtCurrentCat->fetchColumn() ?: 0);

// Precompute import counts per year (donors + cotisants not yet in segment)
$importCountsPerYear = [];
$currentYear = (int)date('Y');

$cotisTypeIds        = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$excludedTypeIds     = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 1));
$institutionalTypeIds = array_keys(array_filter($comptaTypes, fn($ct) => (int)($ct->is_institutional ?? 0) === 1 && (int)($ct->is_excluded_from_donation ?? 0) === 0));
$nonInstTypeIds      = array_keys(array_filter($comptaTypes, fn($ct) => (int)($ct->is_institutional ?? 0) === 0 && (int)($ct->is_excluded_from_donation ?? 0) === 0));

/** Helper: count distinct donors for a given set of allowed type IDs */
$countDonors = function(array $allowedTypeIds, string $from, string $to) use ($id): int {
    if (empty($allowedTypeIds)) return 0;
    $ph = implode(',', array_fill(0, count($allowedTypeIds), '?'));
    $r = db()->prepare("
        SELECT COUNT(DISTINCT u.id)
        FROM contact u JOIN compta c ON c.user_id = u.id
        WHERE c.type_id IN ($ph)
          AND c.date > ? AND c.date < ?
          AND u.id NOT IN (SELECT user_id FROM contact_segment WHERE segment_id = ?)
    ");
    $r->execute(array_merge($allowedTypeIds, [$from, $to, $id]));
    return (int)$r->fetchColumn();
};

// All non-excluded type IDs (for "all donors" count)
$allDonorTypeIds = array_keys(array_filter($comptaTypes, fn($ct) => (int)$ct->is_excluded_from_donation === 0));

for ($yi = 0; $yi < 10; $yi++) {
    $dy   = $currentYear - $yi;
    $from = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $dy));
    $to   = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $dy + 1));

    $importCountsPerYear[$dy] = [
        'donors'         => $countDonors($allDonorTypeIds,      $from, $to),
        'donors_inst'    => $countDonors($institutionalTypeIds, $from, $to),
        'donors_non_inst'=> $countDonors($nonInstTypeIds,       $from, $to),
        'cotis'          => 0,
    ];

    // Cotisants count
    if (!empty($cotisTypeIds)) {
        $cotisPlaceholders = implode(',', array_fill(0, count($cotisTypeIds), '?'));
        $r2 = db()->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM contact u JOIN compta c ON c.user_id = u.id
            WHERE c.type_id IN ($cotisPlaceholders)
              AND c.date > ? AND c.date < ?
              AND u.id NOT IN (SELECT user_id FROM contact_segment WHERE segment_id = ?)
        ");
        $r2->execute(array_merge($cotisTypeIds, [$from, $to, $id]));
        $importCountsPerYear[$dy]['cotis'] = (int)$r2->fetchColumn();
    }
}

// Member counts per segment (for badges)
$cntRows = db()->query("SELECT segment_id, COUNT(*) AS cnt FROM contact_segment GROUP BY segment_id")->fetchAll(PDO::FETCH_OBJ);
$segmentCounts = [];
foreach ($cntRows as $cr) { $segmentCounts[(int)$cr->segment_id] = (int)$cr->cnt; }
?>
<?php if (isset($_REQUEST['imported'])): ?>
<div class="alert alert-success d-flex gap-2 py-2 px-3 mb-3" style="font-size:0.82rem" role="status">
  <i class="fas fa-check-circle mt-1 flex-shrink-0" aria-hidden="true"></i>
  <?= $_REQUEST['imported'] === 'cotisants' ? $GLOBAL['cotisantsImported'] : $GLOBAL['donorsImported'] ?>
</div>
<?php endif ?>
<div class="row justify-content-center mt-4">
  <div class="col-md-6 d-flex flex-column gap-4">

    <!-- Edit form -->
    <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex align-items-baseline justify-content-between mb-1">
        <p class="form-section-title mb-0"><?= $GLOBAL['editSegment'] ?></p>
        <a href="<?= appUrl() ?>?segment=<?= (int)$id ?>" class="small">
          <?= $GLOBAL['viewList'] ?> <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
        </a>
      </div>
      <form action="<?=appUrl()?>" method="post">
        <input type="hidden" name="id" value="<?=$segment->getId()?>"/>
        <input type="hidden" name="action" value="updateSegment"/>
        <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>

        <div class="row mb-2 align-items-center">
          <label for="name" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['name'] ?></label>
          <div class="col-sm-9">
            <input type="text" class="form-control form-control-sm" id="name" name="name"
                   value="<?=htmlentities($segment->getName(),ENT_COMPAT,$charset)?>" maxlength="255" required/>
          </div>
        </div>

        <div class="row mb-3 align-items-center">
          <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="hidden" name="hidden" value="1"<?= $segment->getHidden() ? ' checked' : '' ?>>
              <label class="form-check-label small" for="hidden">
                <i class="fas fa-eye-slash me-1 text-muted" aria-hidden="true"></i><?= $GLOBAL['hideInInterfaces'] ?>
              </label>
            </div>
          </div>
        </div>

        <?php if (count($allCats) > 0): ?>
        <div class="row mb-3 align-items-center">
          <label for="segment_category" class="col-sm-3 col-form-label col-form-label-sm text-sm-end"><?= $GLOBAL['category'] ?></label>
          <div class="col-sm-9">
            <select class="form-select form-select-sm" id="segment_category" name="categoryId">
              <option value="0"<?= $currentCatId === 0 ? ' selected' : '' ?>><?= $GLOBAL['noCategoryOptionLower'] ?></option>
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
          <button type="submit" id="btn-update-segment" class="btn btn-primary btn-sm"><?=$GLOBAL['update']?></button>
          <a href="<?=appUrl()?>?view=settings&amp;tab=groups" class="btn btn-outline-secondary btn-sm"><?= $GLOBAL['cancel'] ?></a>
        </div>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->

    <!-- Import members -->
    <?php if (count($otherSegments) > 0): ?>
    <div class="card mb-4">
    <div class="card-body">
      <form action="<?= appUrl() ?>?view=updateSegment&amp;id=<?= $segment->getId() ?>" method="post">
        <input type="hidden" name="action" value="importSegmentMembers"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            <?= $GLOBAL['importMembersFromOtherSegments'] ?>
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
                <?= $GLOBAL['oneTimeCopyImportWarning'] ?><br>
                <span class="text-muted"><?= sprintf($GLOBAL['dynamicFilterHint'], '<a href="' . appUrl() . '?view=settings&amp;tab=filters">' . $GLOBAL['combinedSegments'] . '</a>') ?></span>
              </div>
            </div>
            <div class="d-flex flex-column gap-1 mb-3">
              <?php foreach ($otherSegments as $t): ?>
              <?php $cnt = $segmentCounts[(int)$t->id] ?? 0; ?>
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
              <i class="fas fa-file-import me-1" aria-hidden="true"></i><?= $GLOBAL['import'] ?>
            </button>
          </div>
        </details>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->
    <?php endif ?>

    <!-- Import cotisation payers by year -->
    <div class="card mb-4">
    <div class="card-body">
      <form action="<?= appUrl() ?>?view=updateSegment&amp;id=<?= $segment->getId() ?>" method="post">
        <input type="hidden" name="action" value="importCotisants"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            <?= $GLOBAL['importCotisantsOfYear'] ?>
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
                <?= $GLOBAL['oneTimeCopyCotisWarning'] ?>
                <?php if (!empty($cotisTypeIds)): ?>
                <?= sprintf($GLOBAL['typesTakenIntoAccount'], implode(', ', array_map(fn($tid) => '<strong>' . htmlentities($comptaTypes[$tid]->label, ENT_COMPAT, $charset) . '</strong>', $cotisTypeIds))) ?>
                <?php else: ?>
                <span class="text-danger"><strong><?= sprintf($GLOBAL['noCotisationTypeWarning'], '<a href="' . appUrl() . '?view=manageComptaTypes">' . $GLOBAL['comptaTypes'] . '</a>') ?></strong></span>
                <?php endif ?>
              </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-auto">
                <label for="cotis_year" class="form-label form-label-sm mb-1"><?= $GLOBAL['year'] ?></label>
                <select class="form-select form-select-sm" id="cotis_year" name="cotis_year" style="width:auto">
                  <?php for ($yi = 0; $yi < 10; $yi++): $dy = $currentYear - $yi;
                    $cnt = $importCountsPerYear[$dy]['cotis'] ?? 0; ?>
                  <option value="<?= $dy ?>"><?= $dy ?><?= $cnt > 0 ? " (+$cnt)" : ' (0)' ?></option>
                  <?php endfor ?>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-import me-1" aria-hidden="true"></i><?= $GLOBAL['importCotisantsBtn'] ?>
            </button>
          </div>
        </details>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->

    <!-- Import donors by year -->
    <div class="card mb-4">
    <div class="card-body">
      <form action="<?= appUrl() ?>?view=updateSegment&amp;id=<?= $segment->getId() ?>" method="post">
        <input type="hidden" name="action" value="importDonors"/>

        <details style="font-size:0.8rem">
          <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
            <?= $GLOBAL['importDonorsOfYear'] ?>
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
                <?= $GLOBAL['oneTimeCopyDonorsWarning'] ?>
              </div>
            </div>
            <div class="row g-2 align-items-end mb-3">
              <div class="col-auto">
                <label for="donor_type" class="form-label form-label-sm mb-1"><?= $GLOBAL['type'] ?></label>
                <select class="form-select form-select-sm" id="donor_type" name="donor_type" style="width:auto"
                        data-no-dirty onchange="caUpdateDonorCounts(this.closest('form'))">
                  <option value="all"><?= $GLOBAL['allDonors'] ?></option>
                  <option value="non_institutional"><?= $GLOBAL['nonInstitutionals'] ?></option>
                  <option value="institutional"><?= $GLOBAL['institutionals'] ?></option>
                </select>
              </div>
              <div class="col-auto">
                <label for="donor_year" class="form-label form-label-sm mb-1"><?= $GLOBAL['year'] ?></label>
                <select class="form-select form-select-sm" id="donor_year" name="donor_year" style="width:auto"
                        data-no-dirty onchange="caUpdateDonorCounts(this.closest('form'))">
                  <?php for ($yi = 0; $yi < 10; $yi++): $dy = $currentYear - $yi; ?>
                  <option value="<?= $dy ?>"
                    data-cnt-all="<?= $importCountsPerYear[$dy]['donors'] ?? 0 ?>"
                    data-cnt-inst="<?= $importCountsPerYear[$dy]['donors_inst'] ?? 0 ?>"
                    data-cnt-non-inst="<?= $importCountsPerYear[$dy]['donors_non_inst'] ?? 0 ?>">
                    <?= $dy ?>
                  </option>
                  <?php endfor ?>
                </select>
              </div>
              <div class="col-auto">
                <label for="donor_minsum" class="form-label form-label-sm mb-1"><?= $GLOBAL['minChf'] ?></label>
                <select class="form-select form-select-sm" id="donor_minsum" name="donor_minsum" style="width:auto">
                  <?php foreach ([1, 100, 200, 500, 1000] as $_ms): ?>
                  <option value="<?= $_ms ?>"><?= $_ms ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="col-auto align-self-end">
                <span id="donor_count_badge" class="badge bg-secondary" style="font-size:0.75rem"></span>
              </div>
            </div>
            <script>
            function caUpdateDonorCounts(form) {
              var typeEl = form.querySelector('[name="donor_type"]');
              var yearEl = form.querySelector('[name="donor_year"]');
              var badge  = form.querySelector('#donor_count_badge');
              if (!typeEl || !yearEl || !badge) return;
              var opt = yearEl.options[yearEl.selectedIndex];
              var cnt = 0;
              if (typeEl.value === 'institutional')     cnt = parseInt(opt.dataset.cntInst    || 0);
              else if (typeEl.value === 'non_institutional') cnt = parseInt(opt.dataset.cntNonInst || 0);
              else                                       cnt = parseInt(opt.dataset.cntAll     || 0);
              badge.textContent = cnt > 0 ? <?= json_encode($GLOBAL['toImportCount']) ?>.replace('%d', cnt) : <?= json_encode($GLOBAL['zeroToImport']) ?>;
            }
            document.addEventListener('DOMContentLoaded', function() {
              document.querySelectorAll('form [name="donor_type"]').forEach(function(el) {
                caUpdateDonorCounts(el.closest('form'));
              });
            });
            </script>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-import me-1" aria-hidden="true"></i><?= $GLOBAL['importDonorsBtn'] ?>
            </button>
          </div>
        </details>
      </form>
    </div><!-- .card-body -->
    </div><!-- .card -->

    <!-- Delete section -->
    <div class="card mb-4">
    <div class="card-body">
      <details>
        <summary class="text-muted" style="cursor:pointer;user-select:none;list-style:none;display:flex;align-items:center;gap:0.35rem;font-size:0.8rem">
          <i class="fas fa-chevron-right" style="font-size:0.6rem;transition:transform 0.15s" aria-hidden="true"></i>
          <?= $GLOBAL['reassignOrDissolve'] ?>
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
              <?= sprintf($GLOBAL['membersBelongToSegment'], '<strong>' . $memberCount . '</strong>', $memberCount > 1 ? 's' : '') ?>
            </p>
            <ul class="list-unstyled mb-0" style="font-size:0.8rem;max-height:200px;overflow-y:auto;border:1px solid var(--ca-border);border-radius:4px;padding:0.4rem 0.75rem">
              <?php foreach ($members as $m): ?>
                <li>
                  <a href="<?=appUrl()?>?view=generalData&id=<?= $m->id ?>" class="text-decoration-none">
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
          <?php if (count($otherSegments) > 0): ?>
          <div class="p-3" style="background:var(--ca-ground);border-radius:6px">
            <p class="small fw-semibold mb-2"><?= $GLOBAL['transferMembersToOtherSegment'] ?></p>
            <form action="<?=appUrl()?>" method="post" class="d-flex align-items-center gap-2 flex-wrap" hx-boost="false">
              <input type="hidden" name="action" value="reassignSegment"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$segment->getId()?>"/>
              <select name="targetSegmentId" class="form-select form-select-sm" style="width:auto" required>
                <option value=""><?= $GLOBAL['chooseSegmentOption'] ?></option>
                <?php foreach ($otherSegments as $t): ?>
                  <?php $cnt = $segmentCounts[(int)$t->id] ?? 0; ?>
                  <option value="<?= $t->id ?>"><?= htmlentities($t->name, ENT_COMPAT, $charset) ?><?= $cnt > 0 ? " ($cnt)" : '' ?></option>
                <?php endforeach ?>
              </select>
              <button type="button" class="btn btn-sm btn-warning"
                      data-bs-toggle="modal" data-bs-target="#modal-reassign-segment">
                <?= $GLOBAL['transferAndDissolve'] ?>
              </button>
            </form>
          </div>
          <?php endif ?>

          <!-- Option B: force delete -->
          <div class="p-3" style="background:var(--ca-danger-light);border-radius:6px">
            <p class="small fw-semibold mb-1" style="color:var(--ca-danger)"><?= $GLOBAL['removeAllMembersAndDelete'] ?></p>
            <p class="small text-muted mb-2"><?= sprintf($GLOBAL['membersWillBeRemoved'], $memberCount, $memberCount > 1 ? 's' : '') ?></p>
            <form action="<?=appUrl()?>" method="post" hx-boost="false">
              <input type="hidden" name="action" value="deleteSegmentForce"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$segment->getId()?>"/>
              <button type="button" class="btn btn-sm btn-danger"
                      data-bs-toggle="modal" data-bs-target="#modal-delete-segment-members">
                <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['removeMembersAndDelete'] ?>
              </button>
            </form>
          </div>

          <?php else: ?>
          <!-- No members — simple delete -->
          <div>
            <p class="small text-muted mb-2"><?= $GLOBAL['segmentHasNoMembers'] ?></p>
            <form action="<?=appUrl()?>" method="post" hx-boost="false">
              <input type="hidden" name="action" value="deleteSegmentForce"/>
              <input type="hidden" name="view" value="settings"/>
        <input type="hidden" name="tab"  value="groups"/>
              <input type="hidden" name="id" value="<?=$segment->getId()?>"/>
              <button type="button" class="btn btn-sm btn-danger"
                      data-bs-toggle="modal" data-bs-target="#modal-delete-segment-empty">
                <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
              </button>
            </form>
          </div>
          <?php endif ?>

        </div>
      </details>
    </div><!-- .card-body -->
    </div><!-- .card -->

  </div>
</div>

<?php if ($memberCount > 0): ?>
<div class="modal fade" id="modal-reassign-segment" tabindex="-1" aria-labelledby="modal-reassign-segment-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-reassign-segment-label"><?= $GLOBAL['transferAndDissolve'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['reassignAndDeleteConfirm'], $memberCount, $memberCount > 1 ? 's' : '', htmlentities($segment->getName(), ENT_QUOTES, $charset)) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-warning"
                onclick="document.querySelector('form [name=action][value=reassignSegment]').closest('form').submit()">
          <?= $GLOBAL['transferAndDissolve'] ?>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-delete-segment-members" tabindex="-1" aria-labelledby="modal-delete-segment-members-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-segment-members-label"><?= $GLOBAL['delete'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['deleteSegmentAndRemoveMembersConfirm'], htmlentities($segment->getName(), ENT_QUOTES, $charset), $memberCount, $memberCount > 1 ? 's' : '') ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-danger"
                onclick="document.querySelector('form [name=action][value=deleteSegmentForce]').closest('form').submit()">
          <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['removeMembersAndDelete'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="modal fade" id="modal-delete-segment-empty" tabindex="-1" aria-labelledby="modal-delete-segment-empty-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-segment-empty-label"><?= $GLOBAL['delete'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= sprintf($GLOBAL['deleteSegmentConfirm'], htmlentities($segment->getName(), ENT_QUOTES, $charset)) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <button type="button" class="btn btn-danger"
                onclick="document.querySelector('form [name=action][value=deleteSegmentForce]').closest('form').submit()">
          <i class="fas fa-trash me-1" aria-hidden="true"></i><?= $GLOBAL['delete'] ?>
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif ?>
