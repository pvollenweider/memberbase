<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Handles membership toggle actions and renders the membership panel.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Membership toast sentinel
$_msAction  = $_REQUEST['action'] ?? '';
$_msTeamId  = (int)($_REQUEST['teamId'] ?? 0);
$_msUserId  = (int)($_REQUEST['id']     ?? 0);
if (($_msAction === 'addMembership' || $_msAction === 'removeMembership') && $_msTeamId > 0 && $_msUserId > 0) {
    $_msTeamNameStmt = $pdo->prepare("SELECT name FROM team WHERE id=?");
    $_msTeamNameStmt->execute([$_msTeamId]);
    $_msTeamName = $_msTeamNameStmt->fetchColumn() ?: "segment #$_msTeamId";
    if ($_msAction === 'addMembership') {
        $_msMsg     = 'Ajouté : ' . htmlspecialchars($_msTeamName, ENT_QUOTES, $charset);
        $_msUndoUrl = $_SERVER['PHP_SELF'] . '?view=generalData&action=removeMembership&id=' . $_msUserId . '&teamId=' . $_msTeamId;
    } else {
        $_msMsg     = 'Retiré : ' . htmlspecialchars($_msTeamName, ENT_QUOTES, $charset);
        $_msUndoUrl = $_SERVER['PHP_SELF'] . '?view=generalData&action=addMembership&id=' . $_msUserId . '&teamId=' . $_msTeamId;
    }
    echo '<div id="casa-membership-toast" hidden data-msg="' . htmlspecialchars($_msMsg, ENT_QUOTES, $charset) . '" data-undo-url="' . htmlspecialchars($_msUndoUrl, ENT_QUOTES, $charset) . '"></div>';
}

$viewall = false;
if (isset($_GET['viewall']) && ($_GET['viewall'] === 'true' || $_GET['viewall'] === 'false')) {
    $viewall = $_GET['viewall'] === 'true';
}

$whereHidden = $viewall ? "" : "AND t.hidden = 0";

// Fetch teams with category info
$stmtAll = $pdo->query("
    SELECT t.id, t.name, t.hidden,
           COALESCE(cat.name, '') AS cat_name,
           COALESCE(cat.id, 0) AS cat_id,
           COALESCE(cat.sort_order, 99999) AS cat_sort
    FROM team t
    LEFT JOIN (
        SELECT j.teamid, c.id, c.name, c.sort_order
        FROM metagroup j
        JOIN metagroup c ON c.id = j.id AND c.name IS NOT NULL AND c.is_filter = 0
        WHERE j.teamid IS NOT NULL
        GROUP BY j.teamid
    ) cat ON cat.teamid = t.id
    WHERE 1=1 $whereHidden
    ORDER BY cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
");

$memberTeams    = [];
$nonMemberTeams = [];
while ($row = $stmtAll->fetchObject()) {
    if ($user->isMemberOfTeam($row->id)) {
        $memberTeams[] = $row;
    } elseif (!$row->hidden) {
        $nonMemberTeams[] = $row;
    }
}

$_catSortFn = function($a, $b) {
    if (!$a['cat_id'] && $b['cat_id']) return 1;
    if ($a['cat_id'] && !$b['cat_id']) return -1;
    $s = $a['cat_sort'] <=> $b['cat_sort'];
    return $s !== 0 ? $s : strcmp($a['cat_name'], $b['cat_name']);
};

// Group member teams by category
$memberBycat = [];
foreach ($memberTeams as $t) {
    $key = $t->cat_id . '|' . ($t->cat_name ?: '');
    if (!isset($memberBycat[$key])) {
        $memberBycat[$key] = ['cat_id' => $t->cat_id, 'cat_name' => $t->cat_name, 'cat_sort' => (int)$t->cat_sort, 'teams' => []];
    }
    $memberBycat[$key]['teams'][] = $t;
}
uasort($memberBycat, $_catSortFn);

// Group non-member teams by category
$nonMemberBycat = [];
foreach ($nonMemberTeams as $t) {
    $key = $t->cat_id . '|' . ($t->cat_name ?: '');
    if (!isset($nonMemberBycat[$key])) {
        $nonMemberBycat[$key] = ['cat_id' => $t->cat_id, 'cat_name' => $t->cat_name, 'cat_sort' => (int)$t->cat_sort, 'teams' => []];
    }
    $nonMemberBycat[$key]['teams'][] = $t;
}
uasort($nonMemberBycat, $_catSortFn);
?>

<p class="form-section-title" style="margin-top:0">Segments</p>

<?php if (empty($memberTeams)): ?>
    <p class="text-muted small">Aucun segment.</p>
<?php else: ?>
    <?php foreach ($memberBycat as $group): ?>
        <?php if ($group['cat_name']): ?>
        <p class="mb-1" style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <?= htmlentities($group['cat_name'], ENT_COMPAT, $charset) ?>
        </p>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <?php
            $_justAdded = (($_REQUEST['action'] ?? '') === 'addMembership') ? (int)($_REQUEST['teamId'] ?? 0) : 0;
            foreach ($group['teams'] as $t):
                $isNew = $_justAdded === (int)$t->id;
            ?>
                <?php if (isManager()): ?>
                <a class="member-pill<?= $isNew ? ' pill-just-added' : '' ?><?= $t->hidden ? ' pill-hidden' : '' ?>"
                   href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=removeMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>"
                   title="<?= $t->hidden ? '[Segment masqué] ' : '' ?>Retirer de <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>">
                    <?php if ($t->hidden): ?><i class="fas fa-eye-slash me-1" aria-label="Segment masqué" style="font-size:0.65rem;opacity:0.6"></i><?php endif ?>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                    <span class="pill-x" aria-hidden="true">&#x2715;</span>
                </a>
                <?php else: ?>
                <span class="member-pill<?= $t->hidden ? ' pill-hidden' : '' ?>">
                    <?php if ($t->hidden): ?><i class="fas fa-eye-slash me-1" aria-label="Segment masqué" style="font-size:0.65rem;opacity:0.6"></i><?php endif ?>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                </span>
                <?php endif ?>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php if (isManager()): ?>
<details class="ca-integrity-section mt-3" <?= ((($_REQUEST['action'] ?? '') === 'addMembership') || !empty($_REQUEST['viewall'])) ? 'open' : '' ?>>
  <summary class="ca-integrity-summary">
    <i class="fas fa-plus me-1 text-muted" style="font-size:0.7rem" aria-hidden="true"></i>
    <?= $GLOBAL['addTeam'] ?>
  </summary>
  <div>
    <?php foreach ($nonMemberBycat as $group): ?>
        <?php if ($group['cat_name']): ?>
        <p class="mb-1 mt-2" style="font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:var(--ca-ink-muted)">
            <?= htmlentities($group['cat_name'], ENT_COMPAT, $charset) ?>
        </p>
        <?php endif ?>
        <div class="group-add-list mb-1">
            <?php foreach ($group['teams'] as $t): ?>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&amp;action=addMembership&amp;id=<?= $user->id ?>&amp;teamId=<?= $t->id ?>">
                    <i class="far fa-square-plus" aria-hidden="true"></i>
                    <?= htmlentities($t->name, ENT_COMPAT, $charset) ?>
                </a>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
    <div class="mt-2 pt-2" style="border-top:1px solid var(--ca-border)">
        <?php if (!$viewall): ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&id=<?= $user->id ?>&viewall=true">
                <i class="fas fa-eye-slash me-1" aria-hidden="true"></i>Segments masqués
            </a>
        <?php else: ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="<?= $_SERVER['PHP_SELF'] ?>?view=generalData&id=<?= $user->id ?>">
                <i class="fas fa-eye me-1" aria-hidden="true"></i>Masquer les segments cachés
            </a>
        <?php endif ?>
    </div>
  </div>
</details>
<?php endif ?>
<script>
(function () {
  var _userId = <?= (int)$user->getId() ?>;
  var _busy   = false;

  function _showToast(msg, undoAction, teamId) {
    var toastEl = document.getElementById('casaToast');
    var msgEl   = document.getElementById('casaToastMsg');
    var undoEl  = document.getElementById('casaToastUndo');
    if (!toastEl || !msgEl) return;
    msgEl.textContent = msg;
    if (undoEl) {
      if (undoAction) {
        undoEl.style.display = '';
        undoEl.onclick = function (e) {
          e.preventDefault();
          bootstrap.Toast.getInstance(toastEl)?.hide();
          _doMembership(undoAction, teamId, null);
        };
      } else {
        undoEl.style.display = 'none';
      }
    }
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 5000 }).show();
  }

  function _refreshPanel() {
    var panel = document.getElementById('ca-member-panel');
    return fetch(location.pathname + '?view=generalData&id=' + _userId, {
      headers: { 'HX-Request': 'true' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.text(); })
    .then(function (html) {
      var doc      = new DOMParser().parseFromString(html, 'text/html');
      var newPanel = doc.getElementById('ca-member-panel');
      if (newPanel && panel) {
        panel.innerHTML = newPanel.innerHTML;
      }
    });
  }

  function _doMembership(action, teamId, teamName) {
    if (_busy) return;
    _busy = true;
    fetch('/api/groups/' + teamId + '/members', {
      method:      action === 'add' ? 'POST' : 'DELETE',
      headers:     { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body:        JSON.stringify({ memberId: _userId })
    })
    .then(function (r) {
      if (!r.ok) throw new Error('API ' + r.status);
      return _refreshPanel();
    })
    .then(function () {
      _busy = false;
      if (teamName) {
        var undoAct = action === 'add' ? 'remove' : 'add';
        _showToast(action === 'add' ? 'Ajouté : ' + teamName : 'Retiré : ' + teamName, undoAct, teamId);
      }
    })
    .catch(function (err) { _busy = false; console.error('membership error', err); });
  }

  function _teamIdFromHref(href) {
    return parseInt(new URLSearchParams((href || '').split('?')[1] || '').get('teamId') || '0', 10);
  }

  // Single permanent capture-phase delegated listener — fires before htmx body bubble listener
  document.addEventListener('click', function (e) {
    var pill = e.target.closest('#ca-member-panel a.member-pill');
    if (pill && pill.getAttribute('href')) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      var teamId   = _teamIdFromHref(pill.getAttribute('href'));
      var teamName = (pill.textContent || '').replace(/✕/g, '').trim();
      if (teamId > 0) _doMembership('remove', teamId, teamName);
      return;
    }
    var addLink = e.target.closest('#ca-member-panel .group-add-list a');
    if (addLink && addLink.getAttribute('href')) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      var teamId   = _teamIdFromHref(addLink.getAttribute('href'));
      var teamName = (addLink.textContent || '').trim();
      if (teamId > 0) _doMembership('add', teamId, teamName);
      return;
    }
  }, true); // capture phase — fires before htmx body bubble listener
})();
</script>
