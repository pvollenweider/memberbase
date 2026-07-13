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

$_cotiTypeIds     = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
$_noCotiSegment   = (int)($appSettings['member_no_coti_segment'] ?? 0);
$_lapsedCount     = count(mbGetLapsedMembers(db(), $_year, $_cotiTypeIds, $_noCotiSegment));
$_pendingMigrationsCount = isAdmin() ? count(pendingMigrations($pdo)) : 0;

// Shortcut: pending compta-recap notifications for the current year (same
// count as compta_recap.php's own stat card).
$_pendingRecapCount = 0;
if (isManager()) {
    $_pendingRecapStmt = db()->prepare(
        "SELECT COUNT(DISTINCT c.user_id) FROM compta c
         JOIN contact u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL AND c.sum <> 0 AND YEAR(c.date) = ?"
    );
    $_pendingRecapStmt->execute([$_year]);
    $_pendingRecapCount = (int)$_pendingRecapStmt->fetchColumn();
}

// Shortcut: attestable donors for the just-completed year — only surfaced in
// January, when attestations for the prior year are normally sent out.
$_isJanuary          = (int)date('n') === 1;
$_attestableDonsCount = 0;
if ($_isJanuary && isManager()) {
    $_attestYear = $_year - 1;
    $_exclSub    = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $_attestStmt = db()->prepare(
        "SELECT COUNT(*) FROM (
            SELECT c.user_id
            FROM compta c
            JOIN contact u ON u.id = c.user_id AND u.status = 1
            WHERE c.date > ? AND c.date < ?
            GROUP BY c.user_id
            HAVING SUM(CASE WHEN c.type_id NOT IN ($_exclSub) THEN c.sum ELSE 0 END) >= 300
                OR MAX(c.wants_attestation) = 1
         ) donors"
    );
    $_attestStmt->execute([
        mbDateTimeBound(mktime(0, 0, 0, 1, 0, $_attestYear)),
        mbDateTimeBound(mktime(0, 0, 0, 1, 1, $_attestYear + 1)),
    ]);
    $_attestableDonsCount = (int)$_attestStmt->fetchColumn();
}

// Last N accounting entries, light view.
$_recentCompta = canWrite() ? db()->query(
    "SELECT c.id, c.sum, c.user_id, u.society, u.lastname, u.firstname, ct.label AS type_label
     FROM compta c
     JOIN contact u ON u.id = c.user_id
     LEFT JOIN compta_type ct ON ct.id = c.type_id
     ORDER BY c.date DESC, c.id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_OBJ) : [];
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
      <div class="card-header"><?= $GLOBAL['dashboardShortcutsTitle'] ?></div>
      <div class="list-group list-group-flush">
        <a href="<?= appUrl() . '?view=peopleFinance&tab=lapsed&year=' . $_year ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= $GLOBAL['cotiUnpayed'] ?></span>
          <span class="fw-bold"><?= $_lapsedCount ?></span>
        </a>
        <?php if (isManager()): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance<?= $_pendingRecapCount > 0 ? '&tab=recap' : '' ?>"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= $GLOBAL['peopleFinanceTabRecap'] ?></span>
          <?php if ($_pendingRecapCount > 0): ?>
          <span class="fw-bold"><?= $_pendingRecapCount ?></span>
          <?php endif ?>
        </a>
        <?php endif ?>
        <?php if ($_isJanuary && $_attestableDonsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=peopleFinance&tab=dons" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= $GLOBAL['peopleFinanceTabDons'] ?></span>
          <span class="fw-bold"><?= $_attestableDonsCount ?></span>
        </a>
        <?php endif ?>
        <?php if (isAdmin() && $_pendingMigrationsCount > 0): ?>
        <a href="<?= appUrl() ?>?view=settings&amp;tab=health" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" hx-boost="false">
          <span><?= sprintf($GLOBAL['pendingDbMigrationsLabel'], $_pendingMigrationsCount > 1 ? 's' : '') ?></span>
          <span class="fw-bold"><?= $_pendingMigrationsCount ?></span>
        </a>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5 d-flex flex-column gap-3">
    <?php if (!empty($_recentCompta)): ?>
    <div class="card">
      <a href="<?= appUrl() ?>?view=journals&amp;tab=compta" class="card-header text-decoration-none d-block" hx-boost="false">
        <?= $GLOBAL['dashboardRecentComptaTitle'] ?>
      </a>
      <div class="table-responsive">
        <table id="dashboard-recent-compta" class="table table-sm table-hover mb-0" style="font-size:0.8rem">
          <tbody>
          <?php foreach ($_recentCompta as $_ce):
              $_ceName = trim(($_ce->society ? $_ce->society . ' ' : '') . $_ce->lastname . ' ' . $_ce->firstname);
              $_ceType = mb_strtoupper(mb_substr(trim((string)$_ce->type_label), 0, 3));
          ?>
          <tr class="ca-row-link" style="cursor:pointer" data-href="<?= appUrl() ?>?view=compta&amp;userid=<?= (int)$_ce->user_id ?>">
            <td class="text-nowrap"><?= htmlspecialchars($_ceName, ENT_QUOTES, $charset) ?></td>
            <td class="text-end text-nowrap">CHF <?= number_format((float)$_ce->sum, 2, '.', "'") ?></td>
            <td class="text-muted text-nowrap"><?= htmlspecialchars($_ceType, ENT_QUOTES, $charset) ?></td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
    (function () {
      var tbody = document.querySelector('#dashboard-recent-compta tbody');
      if (!tbody) return;
      tbody.addEventListener('click', function (e) {
        var tr = e.target.closest('tr.ca-row-link');
        if (!tr) return;
        window.location.href = tr.dataset.href;
      });
    })();
    </script>
    <?php endif ?>

    <div class="card">
      <div class="card-header"><?= $GLOBAL['documentation'] ?></div>
      <div class="list-group list-group-flush">
        <a href="https://pvollenweider.github.io/memberbase/docs/user.html" target="_blank" rel="noopener" class="list-group-item list-group-item-action">
          <i class="fas fa-book me-2" aria-hidden="true"></i><?= $GLOBAL['dashboardUserGuideLink'] ?>
        </a>
      </div>
    </div>
  </div>
</div>
