<?php
/**
 * Admin UI for managing navigation filters (metagroups).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
?>
<p class="small text-muted mb-3">Regroupent plusieurs groupes en un filtre dynamique — accessible depuis la barre de navigation.</p>

<?php
$allFilters = $pdo->query("SELECT m.id, m.name, COUNT(j.teamid) AS team_count FROM metagroup m LEFT JOIN metagroup j ON j.id=m.id AND j.teamid IS NOT NULL WHERE m.name IS NOT NULL AND m.is_filter=1 AND m.teamid IS NULL GROUP BY m.id, m.name ORDER BY m.name")->fetchAll(PDO::FETCH_OBJ);
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
        <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$mg->id ?>" class="text-decoration-none text-muted" title="<?= $GLOBAL['edit'] ?>">
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
  <input type="hidden" name="view" value="settings"/>
  <input type="hidden" name="tab" value="filters"/>
  <input type="hidden" name="is_filter" value="1"/>
  <div class="d-flex align-items-center gap-2">
    <input type="text" class="form-control form-control-sm" name="name" placeholder="Nom du filtre" maxlength="255" required style="max-width:240px"/>
    <button type="submit" class="btn btn-outline-primary btn-sm flex-shrink-0"><?= $GLOBAL['addBtn'] ?></button>
  </div>
</form>
