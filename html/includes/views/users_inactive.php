<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists inactive (deactivated) members.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$inactiveUsers = db()->query(
    "SELECT id, firstName, lastName, society, email
     FROM contact
     WHERE status=0
     ORDER BY society, lastName, firstName"
)->fetchAll(PDO::FETCH_OBJ);
$allSegments = db()->query("SELECT id, name, hidden FROM segment ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);

// Eligibility for permanent deletion: no compta entries at all — same rule
// enforced server-side by bulkDeleteUsers/anonymizeUser (accounts WITH compta
// data get anonymized instead, to preserve the financial trail).
$_iuComptaCounts = [];
if (!empty($inactiveUsers)) {
    $_iuIds = array_map(fn($u) => (int)$u->id, $inactiveUsers);
    $_iuPh  = implode(',', array_fill(0, count($_iuIds), '?'));
    $_iuStmt = db()->prepare("SELECT user_id, COUNT(*) AS cnt FROM compta WHERE user_id IN ($_iuPh) GROUP BY user_id");
    $_iuStmt->execute($_iuIds);
    while ($_iuRow = $_iuStmt->fetchObject()) {
        $_iuComptaCounts[(int)$_iuRow->user_id] = (int)$_iuRow->cnt;
    }
}

$_noOuterContainer = true;
$_phIcon = 'fa-gear';
$_phTitle = $GLOBAL['administration'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

        <div class="card mb-4">
        <div class="card-header"><h2 class="h6 mb-0"><i class="fas fa-archive me-1" aria-hidden="true"></i><?= $GLOBAL['archivedMembers'] ?></h2></div>
        <div class="card-body">
        <p class="small text-muted mb-4"><?= $GLOBAL['archivedMembersHint'] ?></p>

        <?php if (empty($inactiveUsers)): ?>
        <div class="alert alert-success py-2 px-3 mb-0" role="alert" style="font-size:0.85rem">
          <i class="fas fa-check-circle me-1" aria-hidden="true"></i><?= $GLOBAL['noArchivedMembers'] ?>
        </div>
        <?php else: ?>
        <!-- Bulk action bar (hidden until selection) -->
        <div id="iu-bulk-bar" class="d-none mb-2 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.8rem">
          <span id="iu-bulk-count" class="text-muted"></span>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="iuBulkConfirm('delete')">
            <i class="fas fa-trash-can me-1" aria-hidden="true"></i><?= $GLOBAL['bulkDeleteBtn'] ?>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="iuBulkConfirm('anonymize')">
            <i class="fas fa-user-secret me-1" aria-hidden="true"></i><?= $GLOBAL['bulkAnonymizeBtn'] ?>
          </button>
          <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="iuClearSelection()"><?= $GLOBAL['deselect'] ?></button>
        </div>
        <table class="table table-sm align-middle" style="font-size:0.82rem;max-width:820px">
          <thead>
            <tr>
              <th style="width:1.5rem">
                <input type="checkbox" class="form-check-input" id="iu-select-all" title="<?= $GLOBAL['selectAll'] ?>">
              </th>
              <th><?= $GLOBAL['name'] ?></th>
              <th><?= $GLOBAL['email'] ?></th>
              <th style="width:3rem" class="text-center"><?= $GLOBAL['idLabel'] ?></th>
              <th></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($inactiveUsers as $u):
            $_iuCnt      = $_iuComptaCounts[(int)$u->id] ?? 0;
            $_iuEligible = $_iuCnt === 0;
          ?>
            <tr>
              <td>
                <input type="checkbox" class="form-check-input iu-bulk-cb" name="ids[]" value="<?= (int)$u->id ?>"
                       data-eligible="<?= $_iuEligible ? '1' : '0' ?>"
                       data-name="<?= htmlspecialchars(trim(trim($u->lastName . ' ' . $u->firstName) ?: $u->society) ?: sprintf($GLOBAL['noNameId'], (int)$u->id), ENT_QUOTES, $charset) ?>">
              </td>
              <td>
                <?php $_uLabel = htmlspecialchars(trim(trim($u->lastName . ' ' . $u->firstName) ?: $u->society), ENT_QUOTES, $charset) ?>
                <a href="<?= appUrl() ?>?view=updateUser&id=<?= (int)$u->id ?>" class="text-decoration-none">
                  <?= $_uLabel ?: '<span class="text-muted fst-italic">' . $GLOBAL['noName'] . '</span>' ?>
                </a>
              </td>
              <td class="text-muted"><?= htmlspecialchars((string)$u->email, ENT_QUOTES, $charset) ?: '—' ?></td>
              <td class="text-center text-muted"><?= (int)$u->id ?></td>
              <td>
                <?php if ($_iuEligible): ?>
                <span class="badge bg-success-subtle text-success-emphasis" style="font-size:0.7rem" title="<?= htmlspecialchars($GLOBAL['deletionEligibleLabel'], ENT_QUOTES, $charset) ?>">
                  <i class="fas fa-check me-1" aria-hidden="true"></i><?= $GLOBAL['deletionEligibleLabel'] ?>
                </span>
                <?php else: ?>
                <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size:0.7rem" title="<?= htmlspecialchars($GLOBAL['anonymizeTooltip'], ENT_QUOTES, $charset) ?>">
                  <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><?= $GLOBAL['deletionBlockedBadge'] ?>
                </span>
                <?php endif ?>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                        style="font-size:0.75rem;white-space:nowrap"
                        data-unarchive-id="<?= (int)$u->id ?>"
                        data-unarchive-name="<?= $_uLabel ?: sprintf($GLOBAL['noNameId'], (int)$u->id) ?>"
                        onclick="unarchiveConfirm(this)">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i><?= $GLOBAL['unarchive'] ?>
                </button>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>
        </div><!-- .card-body -->
        </div><!-- .card -->

      <!-- Modal désarchivage (réutilisé pour toutes les lignes) -->
      <div class="modal fade" id="unarchive-modal" tabindex="-1" aria-labelledby="unarchive-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
          <div class="modal-content">
            <div class="modal-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--ca-ink-muted)">
                <i class="fas fa-box-open" aria-hidden="true"></i>
              </div>
              <h6 class="mb-1" id="unarchive-modal-label"><?= $GLOBAL['unarchiveConfirmTitle'] ?></h6>
              <p class="text-muted mb-1" style="font-size:0.83rem" id="unarchive-modal-name"></p>
              <p class="text-muted mb-4" style="font-size:0.83rem"><?= $GLOBAL['unarchiveModalBody'] ?></p>
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
                <button type="button" class="btn btn-success" id="unarchive-confirm-btn">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i><?= $GLOBAL['unarchive'] ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="post" action="<?= appUrl() ?>" id="unarchive-form" data-no-dirty>
        <input type="hidden" name="action"   value="reactivateUser">
        <input type="hidden" name="id"       id="unarchive-id" value="">
        <input type="hidden" name="redirect" value="inactiveUsers">
      </form>

      <!-- Bulk delete/anonymize confirmation (shared modal, content swapped by JS) -->
      <div class="modal fade" id="iu-bulk-modal" tabindex="-1" aria-labelledby="iu-bulk-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
          <div class="modal-content">
            <div class="modal-body p-4">
              <div class="mb-3 text-center" id="iu-bulk-modal-icon" style="font-size:2rem;color:var(--ca-danger,#dc3545)">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
              </div>
              <h6 class="mb-3 text-center" id="iu-bulk-modal-title"></h6>
              <div id="iu-bulk-modal-warning" class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.82rem;display:none"></div>
              <p class="text-muted mb-1" style="font-size:0.82rem"><?= $GLOBAL['bulkDeleteIrreversibleWarning'] ?></p>
              <ul class="mb-4" id="iu-bulk-modal-names" style="font-size:0.82rem;max-height:180px;overflow-y:auto"></ul>
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
                <button type="button" class="btn btn-danger" id="iu-bulk-confirm-btn"></button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="post" action="<?= appUrl() ?>" id="iu-bulk-form" data-no-dirty>
        <input type="hidden" name="action" id="iu-bulk-action" value="">
      </form>

      <script>
      function unarchiveConfirm(btn) {
        var id   = btn.dataset.unarchiveId;
        var name = btn.dataset.unarchiveName;
        document.getElementById('unarchive-id').value = id;
        document.getElementById('unarchive-modal-name').textContent = name;
        new bootstrap.Modal(document.getElementById('unarchive-modal')).show();
      }
      document.getElementById('unarchive-confirm-btn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('unarchive-modal')).hide();
        document.getElementById('unarchive-form').submit();
      });

      (function () {
        var allCbs    = document.querySelectorAll('.iu-bulk-cb');
        var selectAll = document.getElementById('iu-select-all');
        var bar       = document.getElementById('iu-bulk-bar');
        var countEl   = document.getElementById('iu-bulk-count');
        if (!allCbs.length) return;

        function checked() { return Array.from(allCbs).filter(function (cb) { return cb.checked; }); }

        function updateBar() {
          var n = checked().length;
          if (n > 0) {
            bar.classList.remove('d-none');
            countEl.textContent = <?= json_encode($GLOBAL['selectedCount']) ?>.replace('%d', n).replace('%s', n > 1 ? 's' : '');
          } else {
            bar.classList.add('d-none');
          }
          selectAll.indeterminate = n > 0 && n < allCbs.length;
          selectAll.checked = n === allCbs.length;
        }

        allCbs.forEach(function (cb) { cb.addEventListener('change', updateBar); });
        selectAll.addEventListener('change', function () {
          allCbs.forEach(function (cb) { cb.checked = selectAll.checked; });
          updateBar();
        });

        window.iuClearSelection = function () {
          allCbs.forEach(function (cb) { cb.checked = false; });
          selectAll.checked = false;
          updateBar();
        };

        var bulkModal = new bootstrap.Modal(document.getElementById('iu-bulk-modal'));

        window.iuBulkConfirm = function (mode) {
          var sel = checked();
          if (!sel.length) return;
          var eligibleWanted = mode === 'delete'; // delete wants no-compta rows; anonymize wants compta rows
          var matching   = sel.filter(function (cb) { return (cb.dataset.eligible === '1') === eligibleWanted; });
          var mismatched = sel.length - matching.length;

          document.getElementById('iu-bulk-action').value = mode === 'delete' ? 'bulkDeleteUsers' : 'bulkAnonymizeUsers';

          var namesEl = document.getElementById('iu-bulk-modal-names');
          namesEl.innerHTML = '';
          sel.forEach(function (cb) {
            var li = document.createElement('li');
            li.textContent = cb.dataset.name;
            namesEl.appendChild(li);
          });

          var warningEl = document.getElementById('iu-bulk-modal-warning');
          if (mismatched > 0) {
            warningEl.style.display = '';
            warningEl.innerHTML = '<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>' +
              (mode === 'delete' ? <?= json_encode($GLOBAL['bulkDeleteIneligibleWarning']) ?> : <?= json_encode($GLOBAL['bulkAnonymizeIneligibleWarning']) ?>).replace('%d', mismatched);
          } else {
            warningEl.style.display = 'none';
          }

          var iconEl  = document.getElementById('iu-bulk-modal-icon');
          var titleEl = document.getElementById('iu-bulk-modal-title');
          var btnEl   = document.getElementById('iu-bulk-confirm-btn');
          if (mode === 'delete') {
            iconEl.innerHTML = '<i class="fas fa-trash-can" aria-hidden="true"></i>';
            titleEl.textContent = <?= json_encode($GLOBAL['bulkDeleteConfirmTitle']) ?>;
            btnEl.innerHTML = '<i class="fas fa-trash-can me-1" aria-hidden="true"></i>' + <?= json_encode($GLOBAL['bulkDeleteBtn']) ?>;
          } else {
            iconEl.innerHTML = '<i class="fas fa-user-secret" aria-hidden="true"></i>';
            titleEl.textContent = <?= json_encode($GLOBAL['bulkAnonymizeConfirmTitle']) ?>;
            btnEl.innerHTML = '<i class="fas fa-user-secret me-1" aria-hidden="true"></i>' + <?= json_encode($GLOBAL['bulkAnonymizeBtn']) ?>;
          }
          bulkModal.show();
        };

        document.getElementById('iu-bulk-confirm-btn').addEventListener('click', function () {
          var form = document.getElementById('iu-bulk-form');
          checked().forEach(function (cb) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'ids[]';
            hidden.value = cb.value;
            form.appendChild(hidden);
          });
          bulkModal.hide();
          window.__dirtyOverride = true;
          form.submit();
        });
      })();
      </script>
</div>
