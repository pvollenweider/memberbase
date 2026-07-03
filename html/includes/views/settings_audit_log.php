<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Admin-only activity audit log view.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (!isAdmin()) { echo '<div class="alert alert-danger">' . $GLOBAL['accessDenied'] . '</div>'; return; } ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><?= $GLOBAL['activityLog'] ?></h2>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#flushPanel">
        <i class="fas fa-trash-can me-1"></i><?= $GLOBAL['cleanUp'] ?>
    </button>
</div>

<?php if (isset($_GET['flushed'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $GLOBAL['auditLogFlushed'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= $GLOBAL['close'] ?>"></button>
</div>
<?php endif; ?>

<div class="collapse mb-3" id="flushPanel">
    <div class="card card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" data-no-dirty>
                    <input type="hidden" name="action" value="flushAuditLog">
                    <input type="hidden" name="keep_days" value="0">
                    <button type="button" class="btn btn-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#modal-flush-all">
                        <i class="fas fa-trash me-1"></i><?= $GLOBAL['deleteAll'] ?>
                    </button>
                </form>
            </div>
            <div class="col-auto">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-flex gap-2 align-items-end" data-no-dirty>
                    <input type="hidden" name="action" value="flushAuditLog">
                    <div>
                        <label for="keep_days" class="form-label mb-1 small"><?= $GLOBAL['keepLastLabel'] ?></label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="keep_days" name="keep_days" class="form-control" value="30" min="1" max="3650" style="width:80px">
                            <span class="input-group-text"><?= $GLOBAL['days'] ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fas fa-clock me-1"></i><?= $GLOBAL['cleanUp'] ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
$rows = $pdo->query(
    "SELECT created_at, username, action, detail FROM audit_log ORDER BY created_at DESC LIMIT 2000"
)->fetchAll(PDO::FETCH_OBJ);
$total = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$auditUsers   = $pdo->query("SELECT DISTINCT username FROM audit_log WHERE username IS NOT NULL ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
$auditActions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex gap-2 align-items-end flex-wrap mb-3" style="font-size:0.82rem">
  <div>
    <label for="audit-filter-user" class="form-label mb-1 small"><?= $GLOBAL['user'] ?></label>
    <select id="audit-filter-user" class="form-select form-select-sm" style="min-width:140px">
      <option value=""><?= $GLOBAL['allMasculineOption'] ?></option>
      <?php foreach ($auditUsers as $u): ?>
      <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div>
    <label for="audit-filter-action" class="form-label mb-1 small"><?= $GLOBAL['action'] ?></label>
    <select id="audit-filter-action" class="form-select form-select-sm" style="min-width:180px">
      <option value=""><?= $GLOBAL['allFeminineOption'] ?></option>
      <?php foreach ($auditActions as $a): ?>
      <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <button type="button" id="audit-filter-reset" class="btn btn-outline-secondary btn-sm mb-0" style="align-self:flex-end">
    <i class="fas fa-xmark me-1" aria-hidden="true"></i><?= $GLOBAL['reset'] ?>
  </button>
  <span class="text-muted ms-auto" style="align-self:flex-end">
    <?= sprintf($GLOBAL['entriesTotalCount'], $total, $total > 1 ? 's' : '') ?><?= $total > 2000 ? ' ' . $GLOBAL['auditLogDisplayCap'] : '' ?>
  </span>
</div>

<table id="auditLogTable" class="table table-sm table-striped table-hover">
    <thead>
        <tr>
            <th style="white-space:nowrap"><?= $GLOBAL['date'] ?></th>
            <th><?= $GLOBAL['user'] ?></th>
            <th><?= $GLOBAL['action'] ?></th>
            <th><?= $GLOBAL['detail'] ?></th>
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
                text: '<?= $GLOBAL['export'] ?> <i class="fas fa-caret-down ms-1" aria-hidden="true"></i>',
                className: 'btn btn-dt',
                buttons: [
                    { extend: 'copy',  text: '<i class="fas fa-copy me-2" aria-hidden="true"></i><?= $GLOBAL['copy'] ?>' },
                    { extend: 'csv',   text: '<i class="fas fa-file-csv me-2" aria-hidden="true"></i>CSV', bom: true },
                    { extend: 'excel', text: '<i class="fas fa-file-excel me-2" aria-hidden="true"></i><?= $GLOBAL['excel'] ?>' },
                    { extend: 'print', text: '<i class="fas fa-print me-2" aria-hidden="true"></i><?= $GLOBAL['print'] ?>' }
                ]
            }
        ],
        language: {
            search: <?= json_encode($GLOBAL['dtSearch']) ?>,
            lengthMenu: <?= json_encode($GLOBAL['dtLengthMenu']) ?>,
            info: <?= json_encode($GLOBAL['dtInfo']) ?>,
            infoFiltered: <?= json_encode($GLOBAL['dtInfoFiltered']) ?>,
            paginate: { previous: <?= json_encode($GLOBAL['dtPrevious']) ?>, next: <?= json_encode($GLOBAL['dtNext']) ?> }
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

<div class="modal fade" id="modal-flush-all" tabindex="-1" aria-labelledby="modal-flush-all-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-flush-all-label"><?= $GLOBAL['deleteAllAuditLogTitle'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">
        <?= $GLOBAL['deleteAllAuditLogConfirm'] ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" data-no-dirty class="d-inline" hx-boost="false">
          <input type="hidden" name="action" value="flushAuditLog">
          <input type="hidden" name="keep_days" value="0">
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash me-1"></i><?= $GLOBAL['deleteAll'] ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
