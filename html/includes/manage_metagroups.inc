<div class="row mt-4">
  <div class="col-md-7 col-lg-6">

    <p class="form-section-title" style="margin-top:0">Groupes de groupes</p>
    <p class="small text-muted mb-3">Un métagroupe est un <strong>filtre dynamique</strong> — il regroupe plusieurs groupes existants et se met à jour automatiquement quand leurs membres changent. Contrairement à l'import, aucune copie n'est faite.</p>

    <?php
    $stmtMgList = $pdo->query("SELECT m.id, m.name, COUNT(j.teamid) AS team_count FROM metagroup m LEFT JOIN metagroup j ON j.id=m.id AND j.teamid IS NOT NULL WHERE m.name IS NOT NULL GROUP BY m.id, m.name ORDER BY m.name");
    $allMeta = $stmtMgList->fetchAll(PDO::FETCH_OBJ);
    ?>

    <?php if (count($allMeta) > 0): ?>
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.82rem">
      <tbody>
      <?php foreach ($allMeta as $mg): ?>
        <tr>
          <td>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?metagroup=<?= (int)$mg->id ?>"
               class="text-decoration-none">
              <?= htmlentities($mg->name, ENT_COMPAT, $charset) ?>
            </a>
          </td>
          <td class="text-muted" style="font-size:0.75rem;width:5rem">
            <?= (int)$mg->team_count ?> groupe<?= $mg->team_count != 1 ? 's' : '' ?>
          </td>
          <td class="text-end" style="width:2rem">
            <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateMetagroup&amp;id=<?= (int)$mg->id ?>"
               class="text-decoration-none text-muted" title="<?= $GLOBAL['update'] ?>">
              <i class="fas fa-pen" style="font-size:0.75rem"></i>
            </a>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted small">Aucun groupe de groupes pour l'instant.</p>
    <?php endif ?>

    <hr class="my-4">

    <p class="form-section-title"><?= $GLOBAL['addMetagroup'] ?></p>

    <form role="form" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" name="addMetagroup">
      <input type="hidden" name="action" value="addMetagroup"/>
      <input type="hidden" name="view" value="manageMetagroups"/>

      <div class="d-flex align-items-center gap-2 mb-3">
        <input type="text" class="form-control form-control-sm" name="name"
               placeholder="<?= htmlentities($GLOBAL['groupName'], ENT_COMPAT, $charset) ?>" maxlength="255" required/>
        <button type="submit" class="btn btn-primary btn-sm flex-shrink-0"><?= $GLOBAL['add'] ?></button>
      </div>

    </form>

  </div>
</div>
