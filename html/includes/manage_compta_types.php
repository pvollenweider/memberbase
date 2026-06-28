<?php
/**
 * Admin UI for managing accounting entry types and their display options.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$colorOptions = [
    'bg-primary-subtle'   => 'Bleu',
    'bg-secondary-subtle' => 'Gris',
    'bg-success-subtle'   => 'Vert',
    'bg-danger-subtle'    => 'Rouge',
    'bg-warning-subtle'   => 'Jaune',
    'bg-info-subtle'      => 'Cyan',
    'bg-light'            => 'Blanc',
    'bg-dark-subtle'      => 'Sombre',
    'ca-orange-subtle'    => 'Orange',
    'ca-teal-subtle'      => 'Sarcelle',
    'ca-pink-subtle'      => 'Rose',
    'ca-purple-subtle'    => 'Violet',
    'ca-indigo-subtle'    => 'Indigo',
    'ca-lime-subtle'      => 'Lime',
];

$types = $pdo->query("
    SELECT ct.id, ct.label, ct.color, ct.sort_order, ct.is_cotisation, ct.is_excluded_from_donation, ct.is_institutional, COUNT(c.id) AS cnt
    FROM compta_type ct
    LEFT JOIN compta c ON c.type_id = ct.id
    GROUP BY ct.id, ct.label, ct.color, ct.sort_order, ct.is_cotisation, ct.is_excluded_from_donation, ct.is_institutional
    ORDER BY ct.sort_order ASC, ct.label ASC
")->fetchAll(PDO::FETCH_OBJ);
?>

<style>
.color-swatch-radio { display:none; }
.color-swatch-label {
    display:inline-block;
    width:28px; height:28px;
    border-radius:4px;
    border:2px solid transparent;
    cursor:pointer;
    outline:2px solid transparent;
    outline-offset:2px;
}
.color-swatch-radio:checked + .color-swatch-label {
    outline-color: #495057;
}
.ca-orange-subtle { background-color: rgba(253,126,20,0.18) !important; }
.ca-teal-subtle   { background-color: rgba(32,201,151,0.18) !important; }
.ca-pink-subtle   { background-color: rgba(214,51,132,0.18) !important; }
.ca-purple-subtle { background-color: rgba(111,66,193,0.18) !important; }
.ca-indigo-subtle { background-color: rgba(102,16,242,0.18) !important; }
.ca-lime-subtle   { background-color: rgba(128,189,64,0.18)  !important; }
</style>

<?php $ctEmbedded = $ctEmbedded ?? false; $ctReturnView = $ctReturnView ?? 'settings'; $ctReturnTab = $ctReturnTab ?? 'compta'; ?>
<?php if (!$ctEmbedded): ?>
<div class="row justify-content-center mt-4">
  <div class="col-lg-8">
<?php endif ?>

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <p class="form-section-title mb-0" style="margin-top:0">Types de compta</p>
      <?php if (!$ctEmbedded): ?>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=settings" class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Réglages
      </a>
      <?php endif ?>
    </div>

    <!-- Add form -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <p class="fw-semibold mb-2" style="font-size:0.85rem">Nouveau type</p>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="d-flex gap-3 align-items-end flex-wrap">
          <input type="hidden" name="action" value="addComptaType">
          <input type="hidden" name="returnView" value="<?= htmlentities($ctReturnView, ENT_COMPAT, $charset) ?>">
          <input type="hidden" name="returnTab" value="<?= htmlentities($ctReturnTab, ENT_COMPAT, $charset) ?>">
          <div>
            <label class="form-label form-label-sm mb-1" style="font-size:0.8rem">Label</label>
            <input type="text" name="label" class="form-control form-control-sm" style="width:200px" required>
          </div>
          <div>
            <label class="form-label form-label-sm mb-1" style="font-size:0.8rem">Couleur</label>
            <div class="d-flex gap-1 flex-wrap">
              <?php foreach ($colorOptions as $cls => $name): ?>
              <label title="<?= htmlentities($name, ENT_COMPAT, $charset) ?>">
                <input type="radio" name="color" value="<?= $cls ?>" class="color-swatch-radio"
                       id="add_color_<?= $cls ?>" <?= $cls === 'bg-light' ? 'checked' : '' ?>>
                <span class="color-swatch-label <?= $cls ?>" for="add_color_<?= $cls ?>"></span>
              </label>
              <?php endforeach ?>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['addBtn'] ?></button>
        </form>
      </div>
    </div>

    <!-- Types table -->
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th style="width:24px"></th>
          <th>Label</th>
          <th style="width:80px">Couleur</th>
          <th style="width:80px" class="text-end">Entrées</th>
          <th style="width:60px" class="text-center" title="Compte comme cotisation">Coti</th>
          <th style="width:60px" class="text-center" title="Exclu des dons">Excl. don</th>
          <th style="width:60px" class="text-center" title="Versement institutionnel">Instit.</th>
          <th style="width:130px"></th>
        </tr>
      </thead>
      <tbody id="ct-tbody">
        <?php foreach ($types as $ct): ?>
        <tr id="row-<?= $ct->id ?>" draggable="true" data-id="<?= $ct->id ?>">
          <td class="text-center text-muted" style="cursor:grab;font-size:0.9rem">
            <i class="fas fa-grip-vertical" aria-hidden="true"></i>
          </td>
          <td><?= htmlentities($ct->label, ENT_COMPAT, $charset) ?></td>
          <td>
            <span class="d-inline-block rounded border <?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>"
                  style="width:28px;height:20px" title="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>"></span>
          </td>
          <td class="text-end text-muted" style="font-size:0.85rem"><?= $ct->cnt ?></td>
          <td class="text-center">
            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
              <input type="hidden" name="action" value="updateComptaType">
              <input type="hidden" name="returnView" value="<?= htmlentities($ctReturnView, ENT_COMPAT, $charset) ?>">
          <input type="hidden" name="returnTab" value="<?= htmlentities($ctReturnTab, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <input type="hidden" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="color" value="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="sort_order" value="<?= $ct->sort_order ?>">
              <input type="hidden" name="is_excluded_from_donation" value="<?= $ct->is_excluded_from_donation ?>">
              <input type="hidden" name="is_institutional" value="<?= $ct->is_institutional ?>">
              <input type="hidden" name="is_cotisation" value="<?= $ct->is_cotisation ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                      title="<?= $ct->is_cotisation ? 'Oui — cliquer pour désactiver' : 'Non — cliquer pour activer' ?>">
                <i class="fas <?= $ct->is_cotisation ? 'fa-check-circle text-success' : 'fa-circle text-muted' ?>"></i>
              </button>
            </form>
          </td>
          <td class="text-center">
            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
              <input type="hidden" name="action" value="updateComptaType">
              <input type="hidden" name="returnView" value="<?= htmlentities($ctReturnView, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="returnTab" value="<?= htmlentities($ctReturnTab, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <input type="hidden" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="color" value="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="sort_order" value="<?= $ct->sort_order ?>">
              <input type="hidden" name="is_cotisation" value="<?= $ct->is_cotisation ?>">
              <input type="hidden" name="is_excluded_from_donation" value="<?= $ct->is_excluded_from_donation ? 0 : 1 ?>">
              <input type="hidden" name="is_institutional" value="<?= $ct->is_institutional ?>">
              <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                      title="<?= $ct->is_excluded_from_donation ? 'Oui — cliquer pour désactiver' : 'Non — cliquer pour activer' ?>">
                <i class="fas <?= $ct->is_excluded_from_donation ? 'fa-check-circle text-warning' : 'fa-circle text-muted' ?>"></i>
              </button>
            </form>
          </td>
          <td class="text-center">
            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
              <input type="hidden" name="action" value="updateComptaType">
              <input type="hidden" name="returnView" value="<?= htmlentities($ctReturnView, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="returnTab" value="<?= htmlentities($ctReturnTab, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <input type="hidden" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="color" value="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="sort_order" value="<?= $ct->sort_order ?>">
              <input type="hidden" name="is_cotisation" value="<?= $ct->is_cotisation ?>">
              <input type="hidden" name="is_excluded_from_donation" value="<?= $ct->is_excluded_from_donation ?>">
              <input type="hidden" name="is_institutional" value="<?= $ct->is_institutional ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                      title="<?= $ct->is_institutional ? 'Oui — cliquer pour désactiver' : 'Non — cliquer pour activer' ?>">
                <i class="fas <?= $ct->is_institutional ? 'fa-check-circle text-info' : 'fa-circle text-muted' ?>"></i>
              </button>
            </form>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-outline-secondary btn-sm py-0"
                    onclick="toggleEdit(<?= $ct->id ?>)"><?= $GLOBAL['edit'] ?></button>
            <?php if ($ct->cnt == 0): ?>
            <button type="button" class="btn btn-outline-danger btn-sm py-0"
                    data-bs-toggle="modal" data-bs-target="#modal-delete-compta-type"
                    data-href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?action=deleteComptaType&id=' . $ct->id . '&returnView=' . urlencode($ctReturnView) . '&returnTab=' . urlencode($ctReturnTab), ENT_QUOTES, $charset) ?>">Suppr.</button>
            <?php endif ?>
          </td>
        </tr>
        <tr id="edit-<?= $ct->id ?>" style="display:none" class="table-light">
          <td colspan="6" class="py-2">
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="d-flex gap-3 align-items-end flex-wrap">
              <input type="hidden" name="action" value="updateComptaType">
              <input type="hidden" name="returnView" value="<?= htmlentities($ctReturnView, ENT_COMPAT, $charset) ?>">
          <input type="hidden" name="returnTab" value="<?= htmlentities($ctReturnTab, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <div>
                <label class="form-label form-label-sm mb-1" style="font-size:0.8rem">Label</label>
                <input type="text" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>"
                       class="form-control form-control-sm" style="width:200px" required>
              </div>
              <div>
                <label class="form-label form-label-sm mb-1" style="font-size:0.8rem">Couleur</label>
                <div class="d-flex gap-1 flex-wrap">
                  <?php foreach ($colorOptions as $cls => $name): ?>
                  <label title="<?= htmlentities($name, ENT_COMPAT, $charset) ?>">
                    <input type="radio" name="color" value="<?= $cls ?>" class="color-swatch-radio"
                           id="edit_<?= $ct->id ?>_<?= $cls ?>" <?= $ct->color === $cls ? 'checked' : '' ?>>
                    <span class="color-swatch-label <?= $cls ?>" for="edit_<?= $ct->id ?>_<?= $cls ?>"></span>
                  </label>
                  <?php endforeach ?>
                </div>
              </div>
              <div>
                <label class="form-label form-label-sm mb-1" style="font-size:0.8rem">Ordre</label>
                <input type="number" name="sort_order" value="<?= $ct->sort_order ?>"
                       class="form-control form-control-sm" style="width:70px" min="0">
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><?= $GLOBAL['save'] ?></button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="toggleEdit(<?= $ct->id ?>)"><?= $GLOBAL['cancel'] ?></button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>

<?php if (!$ctEmbedded): ?>
  </div>
</div>
<?php endif ?>

<style>
#ct-tbody tr.drag-over { outline: 2px solid var(--ca-primary); outline-offset: -1px; }
</style>
<script>
(function() {
  var tbody = document.getElementById('ct-tbody');
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
    var target = e.target.closest('tr[data-id]');
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
    body.append('action', 'updateComptaTypeOrder');
    body.append('view', 'manageComptaTypes');
    ids.forEach(function(id) { body.append('ids[]', id); });
    fetch(window.location.pathname, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'HX-Request': 'true' },
      body: body.toString()
    });
  }
})();

function toggleEdit(id) {
    var row  = document.getElementById('row-'  + id);
    var edit = document.getElementById('edit-' + id);
    var hidden = edit.style.display === 'none';
    edit.style.display = hidden ? '' : 'none';
    row.style.display  = hidden ? 'none' : '';
}
</script>

<div class="modal fade" id="modal-delete-compta-type" tabindex="-1" aria-labelledby="modal-delete-compta-type-label" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-delete-compta-type-label">Supprimer ce type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $GLOBAL['close'] ?>"></button>
      </div>
      <div class="modal-body">Supprimer ce type de cotisation? Cette action est irréversible.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
        <a id="modal-delete-compta-type-link" href="#" class="btn btn-danger"><?= $GLOBAL['delete'] ?></a>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('modal-delete-compta-type').addEventListener('show.bs.modal', function (e) {
    var href = e.relatedTarget ? e.relatedTarget.dataset.href : '';
    document.getElementById('modal-delete-compta-type-link').href = href || '#';
});
</script>
