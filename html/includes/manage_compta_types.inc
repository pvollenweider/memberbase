<?php
$colorOptions = [
    'bg-primary-subtle'   => 'Bleu',
    'bg-secondary-subtle' => 'Gris',
    'bg-success-subtle'   => 'Vert',
    'bg-danger-subtle'    => 'Rouge',
    'bg-warning-subtle'   => 'Jaune',
    'bg-info-subtle'      => 'Cyan',
    'bg-light'            => 'Blanc',
    'bg-dark-subtle'      => 'Sombre',
];

$types = $pdo->query("
    SELECT ct.id, ct.label, ct.color, ct.sort_order, ct.is_cotisation, ct.is_excluded_from_donation, COUNT(c.id) AS cnt
    FROM compta_type ct
    LEFT JOIN compta c ON c.type_id = ct.id
    GROUP BY ct.id, ct.label, ct.color, ct.sort_order, ct.is_cotisation, ct.is_excluded_from_donation
    ORDER BY ct.label ASC
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
</style>

<div class="row justify-content-center mt-4">
  <div class="col-lg-8">

    <div class="d-flex align-items-baseline justify-content-between mb-3">
      <p class="form-section-title mb-0" style="margin-top:0">Types de compta</p>
      <a href="<?= $_SERVER['PHP_SELF'] ?>?view=settings" class="text-muted small text-decoration-none">
        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Réglages
      </a>
    </div>

    <!-- Add form -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <p class="fw-semibold mb-2" style="font-size:0.85rem">Nouveau type</p>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="d-flex gap-3 align-items-end flex-wrap">
          <input type="hidden" name="action" value="addComptaType">
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
          <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
        </form>
      </div>
    </div>

    <!-- Types table -->
    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th style="width:40px">ID</th>
          <th>Label</th>
          <th style="width:80px">Couleur</th>
          <th style="width:80px" class="text-end">Entrées</th>
          <th style="width:70px" class="text-center">Ordre</th>
          <th style="width:60px" class="text-center" title="Compte comme cotisation">Coti</th>
          <th style="width:60px" class="text-center" title="Exclu des dons">Excl. don</th>
          <th style="width:130px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($types as $ct): ?>
        <tr id="row-<?= $ct->id ?>">
          <td class="text-muted" style="font-size:0.8rem"><?= $ct->id ?></td>
          <td><?= htmlentities($ct->label, ENT_COMPAT, $charset) ?></td>
          <td>
            <span class="d-inline-block rounded border <?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>"
                  style="width:28px;height:20px" title="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>"></span>
          </td>
          <td class="text-end text-muted" style="font-size:0.85rem"><?= $ct->cnt ?></td>
          <td class="text-center text-muted" style="font-size:0.85rem"><?= $ct->sort_order ?></td>
          <td class="text-center">
            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
              <input type="hidden" name="action" value="updateComptaType">
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <input type="hidden" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="color" value="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="sort_order" value="<?= $ct->sort_order ?>">
              <input type="hidden" name="is_excluded_from_donation" value="<?= $ct->is_excluded_from_donation ?>">
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
              <input type="hidden" name="id" value="<?= $ct->id ?>">
              <input type="hidden" name="label" value="<?= htmlentities($ct->label, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="color" value="<?= htmlentities($ct->color, ENT_COMPAT, $charset) ?>">
              <input type="hidden" name="sort_order" value="<?= $ct->sort_order ?>">
              <input type="hidden" name="is_cotisation" value="<?= $ct->is_cotisation ?>">
              <input type="hidden" name="is_excluded_from_donation" value="<?= $ct->is_excluded_from_donation ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                      title="<?= $ct->is_excluded_from_donation ? 'Oui — cliquer pour désactiver' : 'Non — cliquer pour activer' ?>">
                <i class="fas <?= $ct->is_excluded_from_donation ? 'fa-check-circle text-warning' : 'fa-circle text-muted' ?>"></i>
              </button>
            </form>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-outline-secondary btn-sm py-0"
                    onclick="toggleEdit(<?= $ct->id ?>)">Modifier</button>
            <?php if ($ct->cnt == 0): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?action=deleteComptaType&id=<?= $ct->id ?>"
               class="btn btn-outline-danger btn-sm py-0"
               onclick="return confirm('Supprimer ce type ?')">Suppr.</a>
            <?php endif ?>
          </td>
        </tr>
        <tr id="edit-<?= $ct->id ?>" style="display:none" class="table-light">
          <td colspan="6" class="py-2">
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" class="d-flex gap-3 align-items-end flex-wrap">
              <input type="hidden" name="action" value="updateComptaType">
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
                <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="toggleEdit(<?= $ct->id ?>)">Annuler</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>

  </div>
</div>

<script>
function toggleEdit(id) {
    var row  = document.getElementById('row-'  + id);
    var edit = document.getElementById('edit-' + id);
    var hidden = edit.style.display === 'none';
    edit.style.display = hidden ? '' : 'none';
    row.style.display  = hidden ? 'none' : '';
}
</script>
