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
$auditUsers   = $pdo->query("SELECT DISTINCT username FROM audit_log WHERE username IS NOT NULL ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
$auditActions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex gap-2 align-items-end flex-wrap mb-3" style="font-size:0.82rem">
  <div>
    <label for="audit-filter-user" class="form-label mb-1 small">Utilisateur</label>
    <select id="audit-filter-user" class="form-select form-select-sm" style="min-width:140px">
      <option value="">— tous —</option>
      <?php foreach ($auditUsers as $u): ?>
      <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div>
    <label for="audit-filter-action" class="form-label mb-1 small">Action</label>
    <select id="audit-filter-action" class="form-select form-select-sm" style="min-width:180px">
      <option value="">— toutes —</option>
      <?php foreach ($auditActions as $a): ?>
      <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button type="button" id="audit-filter-reset" class="btn btn-outline-secondary btn-sm mb-0" style="align-self:flex-end">
    <i class="fas fa-times me-1" aria-hidden="true"></i>Réinitialiser
  </button>
  <span class="text-muted ms-auto" style="align-self:flex-end">
    <?= $total ?> entrée<?= $total > 1 ? 's' : '' ?> au total<?= $total > 2000 ? ' (2000 affichées)' : '' ?>
  </span>
</div>

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
        dom: '<"d-flex align-items-center justify-content-between mb-2"<"d-flex gap-2"B>f>rtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Exporter <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i>Copier' },
                    { extend: 'csv',   text: '<i class="fas fa-file-csv me-2" aria-hidden="true"></i>CSV', bom: true },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i>Excel' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i>Imprimer' }
                ]
            }
        ],
        language: {
            search: 'Rechercher :',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Entrées _START_ à _END_ sur _TOTAL_',
            infoFiltered: '(filtrées sur _MAX_)',
            paginate: { previous: 'Précédent', next: 'Suivant' }
        }
    });

    $('#audit-filter-user').on('change', function () {
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        window.auditLogDT.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#audit-filter-action').on('change', function () {
        var val = $.fn.dataTable.util.escapeRegex($(this).val());
        window.auditLogDT.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
    });

    $('#audit-filter-reset').on('click', function () {
        $('#audit-filter-user, #audit-filter-action').val('');
        window.auditLogDT.columns([1, 2]).search('').draw();
    });
});
</script>
