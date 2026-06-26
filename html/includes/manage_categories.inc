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
