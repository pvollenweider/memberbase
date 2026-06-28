<?php if (!isAdmin()) { echo '<div class="alert alert-danger">Accès refusé.</div>'; return; } ?>
<?php
$memberId = (int)$user->getId();

$histRows = $pdo->prepare("
    SELECT created_at, username, action, detail
    FROM audit_log
    WHERE subject_user_id = ?
    ORDER BY created_at DESC
");
$histRows->execute([$memberId]);
$history = $histRows->fetchAll(PDO::FETCH_OBJ);
?>

<p class="form-section-title mb-1">
  <i class="fas fa-history me-1" aria-hidden="true"></i>Historique des modifications
</p>
<p class="small text-muted mb-3">Toutes les actions enregistrées pour ce membre.</p>

<?php if (empty($history)): ?>
<div class="alert alert-secondary py-2 px-3" style="font-size:0.85rem">
  <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Aucune entrée dans le journal pour ce membre.
</div>
<?php else: ?>
<table id="userHistoryTable" class="table table-sm table-striped table-hover">
  <thead>
    <tr>
      <th style="white-space:nowrap">Date</th>
      <th class="d-none d-sm-table-cell">Utilisateur</th>
      <th>Action</th>
      <th>Détail</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($history as $r): ?>
    <tr>
      <td data-order="<?= htmlspecialchars($r->created_at) ?>" style="white-space:nowrap"><?= htmlspecialchars($r->created_at) ?></td>
      <td class="d-none d-sm-table-cell"><?= htmlspecialchars($r->username ?? '') ?></td>
      <td><code><?= htmlspecialchars($r->action) ?></code></td>
      <td class="text-muted small"><?= htmlspecialchars($r->detail ?? '') ?></td>
    </tr>
    <?php endforeach ?>
  </tbody>
</table>

<script>
$(function () {
    $('#userHistoryTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        columnDefs: [{ targets: [1], visible: window.innerWidth >= 576 }],
        language: {
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Entrées _START_ à _END_ sur _TOTAL_',
            infoFiltered: '(filtrées sur _MAX_)',
            paginate: { previous: 'Précédent', next: 'Suivant' },
            emptyTable: 'Aucune entrée.'
        }
    });
});
</script>
<?php endif ?>
