<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Dashboard landing view (#153) — task banner, quick KPIs, role-filtered docs.
 * Reachable via ?view=dashboard and the nav bar shortcut (not the default
 * landing view — the member list stays the default, see routing/views.php).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/../lib/cotisation.php';

$_year = (int)date('Y');

$_urgentStmt = db()->prepare(
    "SELECT t.id, t.title, t.rule_key, t.due_date, t.user_id,
            u.firstname, u.lastname, u.society, u.email
     FROM suivi_task t
     LEFT JOIN contact u ON u.id = t.user_id
     WHERE t.done_at IS NULL AND t.due_date IS NOT NULL AND t.due_date <= ?
     ORDER BY t.due_date ASC, t.priority ASC
     LIMIT 5"
);
$_urgentStmt->execute([date('Y-m-d', strtotime('+3 days'))]);
$_urgentTasks = $_urgentStmt->fetchAll(PDO::FETCH_OBJ);

$_openTaskCount = (int)db()->query("SELECT COUNT(*) FROM suivi_task WHERE done_at IS NULL")->fetchColumn();

$_hasCotiTask = false;
foreach ($_urgentTasks as $_t) {
    if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_')) {
        $_hasCotiTask = true;
        break;
    }
}

$_cotiTypeIds     = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiSegment   = (int)($appSettings['member_no_coti_segment'] ?? 0);
$_lapsedCount     = count(mbGetLapsedMembers(db(), $_year, $_cotiTypeIds, $_noCotiSegment));
$_pendingMigrationsCount = isAdmin() ? count(pendingMigrations($pdo)) : 0;
?>

<div class="page-title-row mb-3">
  <h1 class="page-title"><?= $GLOBAL['dashboardPageTitle'] ?></h1>
</div>

<?php if (canWrite()): ?>
<div class="d-flex align-items-start gap-2 mb-3 flex-wrap">
  <div class="position-relative" style="max-width:22rem;width:100%">
    <label for="dashboard-compta-search" class="visually-hidden"><?= $GLOBAL['dashboardComptaSearchLabel'] ?></label>
    <input type="text" id="dashboard-compta-search" class="form-control form-control-sm"
           placeholder="<?= htmlspecialchars($GLOBAL['dashboardComptaSearchPlaceholder'], ENT_QUOTES, $charset) ?>"
           autocomplete="off" role="combobox" aria-expanded="false" aria-controls="dashboard-compta-results"
           aria-autocomplete="list" data-no-dirty>
    <ul id="dashboard-compta-results" role="listbox" class="list-group position-absolute w-100 shadow-sm"
        style="z-index:20;max-height:16rem;overflow-y:auto;display:none"
        aria-label="<?= htmlspecialchars($GLOBAL['dashboardComptaSearchLabel'], ENT_QUOTES, $charset) ?>"></ul>
    <div id="dashboard-compta-status" role="status" class="visually-hidden"></div>
  </div>
  <a href="<?= appUrl() ?>?view=addUser" class="btn btn-primary btn-sm">
    <i class="fas fa-user-plus me-1" aria-hidden="true"></i><?= $GLOBAL['addUser'] ?>
  </a>
</div>
<script>
(function () {
  var input   = document.getElementById('dashboard-compta-search');
  var results = document.getElementById('dashboard-compta-results');
  var status  = document.getElementById('dashboard-compta-status');
  if (!input || !results) return;

  var baseUrl = <?= json_encode(appUrl()) ?>;
  var items = [];
  var activeIndex = -1;
  var debounceTimer = null;

  function closeResults() {
    results.style.display = 'none';
    results.innerHTML = '';
    status.textContent = '';
    items = [];
    activeIndex = -1;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
  }

  function goToCompta(userId) {
    window.__dirtyOverride = true;
    window.location = baseUrl + '?view=compta&userid=' + encodeURIComponent(userId);
  }

  function renderResults(data) {
    items = data;
    activeIndex = -1;
    if (!items.length) {
      results.innerHTML = '<li class="list-group-item text-muted small" role="status">'
          + <?= json_encode($GLOBAL['dashboardComptaSearchNoResults']) ?> + '</li>';
      results.style.display = '';
      input.setAttribute('aria-expanded', 'true');
      status.textContent = <?= json_encode($GLOBAL['dashboardComptaSearchNoResults']) ?>;
      return;
    }
    results.innerHTML = items.map(function (m, i) {
      var name = (m.lastName + ' ' + m.firstName).trim() || m.society || '?';
      var sub  = m.email ? ' <span class="text-muted small">' + m.email + '</span>' : '';
      return '<li class="list-group-item list-group-item-action" style="cursor:pointer" role="option" '
          + 'aria-selected="false" id="dashboard-compta-opt-' + i + '" data-user-id="' + m.id + '">' + name + sub + '</li>';
    }).join('');
    results.style.display = '';
    input.setAttribute('aria-expanded', 'true');
    status.textContent = items.length === 1
      ? <?= json_encode($GLOBAL['dashboardComptaSearchOneResult']) ?>
      : <?= json_encode($GLOBAL['dashboardComptaSearchResultsCount']) ?>.replace('%d', items.length);
  }

  function setActive(index) {
    var opts = results.querySelectorAll('[role="option"][data-user-id]');
    opts.forEach(function (el) { el.classList.remove('active'); el.setAttribute('aria-selected', 'false'); });
    if (index >= 0 && index < opts.length) {
      opts[index].classList.add('active');
      opts[index].setAttribute('aria-selected', 'true');
      input.setAttribute('aria-activedescendant', opts[index].id);
      activeIndex = index;
    } else {
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }
  }

  input.addEventListener('input', function () {
    var q = input.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { closeResults(); return; }
    debounceTimer = setTimeout(function () {
      fetch('/api/contacts?search=' + encodeURIComponent(q) + '&limit=8', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) { renderResults(data.data || []); })
        .catch(closeResults);
    }, 250);
  });

  results.addEventListener('click', function (e) {
    var li = e.target.closest('[data-user-id]');
    if (li) { goToCompta(li.dataset.userId); }
  });

  input.addEventListener('keydown', function (e) {
    var opts = results.querySelectorAll('[role="option"][data-user-id]');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (opts.length) setActive(Math.min(activeIndex + 1, opts.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (opts.length) setActive(Math.max(activeIndex - 1, 0));
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) {
        e.preventDefault();
        goToCompta(items[activeIndex].id);
      }
    } else if (e.key === 'Escape') {
      closeResults();
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target !== input && !results.contains(e.target)) { closeResults(); }
  });
})();
</script>
<style>
#dashboard-compta-results .list-group-item.active {
  background: var(--ca-primary, #4f7ac7);
  color: #fff;
}
#dashboard-compta-results .list-group-item.active .text-muted { color: rgba(255,255,255,0.85) !important; }
</style>
<?php endif ?>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= $GLOBAL['dashboardTasksTitle'] ?></span>
        <span class="text-muted small"><?= sprintf($GLOBAL['dashboardOpenTaskCount'], $_openTaskCount) ?></span>
      </div>
      <?php if (empty($_urgentTasks)): ?>
      <div class="card-body">
        <p class="text-muted mb-0"><i class="fas fa-circle-check me-1 text-success" aria-hidden="true"></i><?= $GLOBAL['noOpenTasks'] ?></p>
      </div>
      <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($_urgentTasks as $_t):
            $_dueTs   = strtotime($_t->due_date);
            $_overdue = mbTaskIsOverdue($_dueTs, null);
            $_name    = $_t->user_id
                ? trim(($_t->society ? htmlentities($_t->society, ENT_COMPAT, $charset) . ' ' : '') .
                       htmlentities($_t->lastname, ENT_COMPAT, $charset) . ' ' .
                       htmlentities($_t->firstname, ENT_COMPAT, $charset))
                : $GLOBAL['globalTask'];
            $_href = $_t->user_id
                ? appUrl() . '?view=memberTasks&userid=' . (int)$_t->user_id
                : appUrl() . '?view=updateTask&taskid=' . (int)$_t->id;
            $_statusLabel = $_overdue ? $GLOBAL['taskOverdue'] : date('d.m.Y', $_dueTs);
            $_rowLabel = sprintf('%s: %s — %s (%s)', $GLOBAL['taskTitle'], $_t->title, $_statusLabel,
                $_t->user_id ? trim(($_t->society ? $_t->society . ' ' : '') . $_t->lastname . ' ' . $_t->firstname) : $GLOBAL['globalTask']);
        ?>
        <div class="list-group-item d-flex align-items-center gap-2 position-relative">
          <i class="fas fa-circle <?= $_overdue ? 'text-danger' : 'text-warning' ?>" style="font-size:0.5rem" aria-hidden="true"></i>
          <span class="flex-grow-1">
            <span class="fw-medium"><?= htmlspecialchars($_t->title, ENT_QUOTES, $charset) ?></span>
            <span class="text-muted small d-block"><?= $_name ?></span>
          </span>
          <span class="small fw-semibold <?= $_overdue ? 'text-danger' : 'text-warning' ?> text-nowrap">
            <?= $_overdue
                ? $GLOBAL['taskOverdue']
                : htmlspecialchars(date('d.m.Y', $_dueTs), ENT_QUOTES, $charset) ?>
          </span>
          <?php if ($_t->rule_key && str_starts_with($_t->rule_key, 'unpaid_coti_current_') && $_t->user_id && trim((string)$_t->email) !== ''): ?>
          <button type="button" class="btn btn-outline-primary btn-sm js-task-send-coti" style="position:relative;z-index:2"
                  data-user-id="<?= (int)$_t->user_id ?>"
                  data-year="<?= $_year ?>"
                  data-task-id="<?= (int)$_t->id ?>"
                  data-confirm="<?= htmlspecialchars(sprintf($GLOBAL['cotiReminderConfirmOne'], trim(($_t->firstname ?? '') . ' ' . ($_t->lastname ?? ''))), ENT_QUOTES, $charset) ?>"
                  data-msg-fail="<?= htmlspecialchars($GLOBAL['cotiReminderSentFail'], ENT_QUOTES, $charset) ?>"
                  data-label-sending="<?= htmlspecialchars($GLOBAL['sendCotiRemindersSending'], ENT_QUOTES, $charset) ?>">
              <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?= $GLOBAL['sendCotiRemindersBtnOne'] ?>
          </button>
          <?php endif ?>
          <a href="<?= $_href ?>" class="stretched-link" hx-boost="false"
             aria-label="<?= htmlspecialchars($_rowLabel, ENT_QUOTES, $charset) ?>"></a>
        </div>
        <?php endforeach ?>
      </div>
      <?php endif ?>
      <div class="card-footer">
        <a href="<?= appUrl() ?>?view=tasks"><?= $GLOBAL['dashboardViewAllTasks'] ?></a>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5 d-flex flex-column gap-3">
    <div class="card">
      <div class="card-header"><?= $GLOBAL['dashboardKpiTitle'] ?></div>
      <div class="list-group list-group-flush">
        <a href="<?= appUrl() . '?view=lapsedMembers&year=' . $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= $GLOBAL['cotiUnpayed'] ?></span>
          <span class="fw-bold"><?= $_lapsedCount ?></span>
        </a>
        <?php if (isAdmin() && $_pendingMigrationsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=settings&amp;tab=health" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= sprintf($GLOBAL['pendingDbMigrationsLabel'], $_pendingMigrationsCount > 1 ? 's' : '') ?></span>
          <span class="fw-bold"><?= $_pendingMigrationsCount ?></span>
        </a>
        <?php endif ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><?= $GLOBAL['documentation'] ?></div>
      <div class="list-group list-group-flush">
        <a href="https://pvollenweider.github.io/memberbase/docs/user.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-book me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardUserGuideLink'] ?>
        </a>
        <?php if (isManager()): ?>
        <a href="https://pvollenweider.github.io/memberbase/docs/admin.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-screwdriver-wrench me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardAdminGuideLink'] ?>
        </a>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>

<?php if ($_hasCotiTask): ?>
<?php require __DIR__ . '/../partials/task_coti_reminder_modal.php'; ?>
<?php endif ?>
