<?php if (!isAdmin()) { echo '<div class="alert alert-danger">Accès refusé.</div>'; return; } ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">Journal d'activité</h2>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#flushPanel">
        <i class="fas fa-trash-alt me-1"></i>Nettoyer
    </button>
</div>

<?php if (isset($_GET['flushed'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Journal nettoyé.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
<?php endif; ?>

<div class="collapse mb-3" id="flushPanel">
    <div class="card card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" data-no-dirty>
                    <input type="hidden" name="action" value="flushAuditLog">
                    <input type="hidden" name="keep_days" value="0">
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Supprimer tout le journal ?')">
                        <i class="fas fa-trash me-1"></i>Tout supprimer
                    </button>
                </form>
            </div>
            <div class="col-auto">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex gap-2 align-items-end" data-no-dirty>
                    <input type="hidden" name="action" value="flushAuditLog">
                    <div>
                        <label for="keep_days" class="form-label mb-1 small">Garder les derniers</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="keep_days" name="keep_days" class="form-control" value="30" min="1" max="3650" style="width:80px">
                            <span class="input-group-text">jours</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fas fa-clock me-1"></i>Nettoyer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$rows = $pdo->query(
    "SELECT created_at, username, action, detail FROM audit_log ORDER BY created_at DESC LIMIT 2000"
)->fetchAll(PDO::FETCH_OBJ);
$total = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
?>

<p class="text-muted small"><?= $total ?> entrée<?= $total > 1 ? 's' : '' ?> au total<?= $total > 2000 ? ' (2000 affichées)' : '' ?>.</p>

<table id="auditLogTable" class="table table-sm table-striped table-hover">
    <thead>
        <tr>
            <th style="white-space:nowrap">Date</th>
            <th>Utilisateur</th>
            <th>Action</th>
            <th>Détail</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td data-order="<?= htmlspecialchars($r->created_at) ?>" style="white-space:nowrap"><?= htmlspecialchars($r->created_at) ?></td>
            <td><?= htmlspecialchars($r->username ?? '') ?></td>
            <td><code><?= htmlspecialchars($r->action) ?></code></td>
            <td class="text-muted small"><?= htmlspecialchars($r->detail ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
$(function () {
    window.auditLogDT = $('#auditLogTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        language: {
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Entrées _START_ à _END_ sur _TOTAL_',
            paginate: { previous: 'Précédent', next: 'Suivant' }
        }
    });
});
</script>
