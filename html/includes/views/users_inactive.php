<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Lists inactive (deactivated) members.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$inactiveUsers = db()->query(
    "SELECT id, firstName, lastName, society, email
     FROM contact
     WHERE status=0
     ORDER BY society, lastName, firstName"
)->fetchAll(PDO::FETCH_OBJ);
$allSegments = db()->query("SELECT id, name, hidden FROM segment ORDER BY hidden ASC, name ASC")->fetchAll(PDO::FETCH_OBJ);

$_noOuterContainer = true;
$_phIcon = 'fa-gear';
$_phTitle = $GLOBAL['administration'];
include __DIR__ . '/../partials/page_header.php';
?>
<div class="container-xl px-4 ca-hero-overlap">

        <div class="card mb-4">
        <div class="card-header"><i class="fas fa-archive me-1" aria-hidden="true"></i><?= $GLOBAL['archivedMembers'] ?></div>
        <div class="card-body">
        <p class="small text-muted mb-4"><?= $GLOBAL['archivedMembersHint'] ?></p>

        <?php if (empty($inactiveUsers)): ?>
        <div class="alert alert-success py-2 px-3 mb-0" role="alert" style="font-size:0.85rem">
          <i class="fas fa-check-circle me-1" aria-hidden="true"></i><?= $GLOBAL['noArchivedMembers'] ?>
        </div>
        <?php else: ?>
        <table class="table table-sm align-middle" style="font-size:0.82rem;max-width:720px">
          <thead>
            <tr>
              <th><?= $GLOBAL['name'] ?></th>
              <th><?= $GLOBAL['email'] ?></th>
              <th style="width:3rem" class="text-center"><?= $GLOBAL['idLabel'] ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($inactiveUsers as $u): ?>
            <tr>
              <td>
                <?php $_uLabel = htmlspecialchars(trim(trim($u->lastName . ' ' . $u->firstName) ?: $u->society), ENT_QUOTES, $charset) ?>
                <a href="<?= appUrl() ?>?view=updateUser&id=<?= (int)$u->id ?>" class="text-decoration-none">
                  <?= $_uLabel ?: '<span class="text-muted fst-italic">' . $GLOBAL['noName'] . '</span>' ?>
                </a>
              </td>
              <td class="text-muted"><?= htmlspecialchars((string)$u->email, ENT_QUOTES, $charset) ?: '—' ?></td>
              <td class="text-center text-muted"><?= (int)$u->id ?></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-success py-0 px-2"
                        style="font-size:0.75rem;white-space:nowrap"
                        data-unarchive-id="<?= (int)$u->id ?>"
                        data-unarchive-name="<?= $_uLabel ?: sprintf($GLOBAL['noNameId'], (int)$u->id) ?>"
                        onclick="unarchiveConfirm(this)">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i><?= $GLOBAL['unarchive'] ?>
                </button>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>
        </div><!-- .card-body -->
        </div><!-- .card -->

      <!-- Modal désarchivage (réutilisé pour toutes les lignes) -->
      <div class="modal fade" id="unarchive-modal" tabindex="-1" aria-labelledby="unarchive-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
          <div class="modal-content">
            <div class="modal-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--ca-ink-muted)">
                <i class="fas fa-box-open" aria-hidden="true"></i>
              </div>
              <h6 class="mb-1" id="unarchive-modal-label"><?= $GLOBAL['unarchiveConfirmTitle'] ?></h6>
              <p class="text-muted mb-1" style="font-size:0.83rem" id="unarchive-modal-name"></p>
              <p class="text-muted mb-4" style="font-size:0.83rem"><?= $GLOBAL['unarchiveModalBody'] ?></p>
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $GLOBAL['cancel'] ?></button>
                <button type="button" class="btn btn-success" id="unarchive-confirm-btn">
                  <i class="fas fa-box-open me-1" aria-hidden="true"></i><?= $GLOBAL['unarchive'] ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form method="post" action="<?= appUrl() ?>" id="unarchive-form" data-no-dirty>
        <input type="hidden" name="action"   value="reactivateUser">
        <input type="hidden" name="id"       id="unarchive-id" value="">
        <input type="hidden" name="redirect" value="inactiveUsers">
      </form>

      <script>
      function unarchiveConfirm(btn) {
        var id   = btn.dataset.unarchiveId;
        var name = btn.dataset.unarchiveName;
        document.getElementById('unarchive-id').value = id;
        document.getElementById('unarchive-modal-name').textContent = name;
        new bootstrap.Modal(document.getElementById('unarchive-modal')).show();
      }
      document.getElementById('unarchive-confirm-btn').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('unarchive-modal')).hide();
        document.getElementById('unarchive-form').submit();
      });
      </script>
</div>
