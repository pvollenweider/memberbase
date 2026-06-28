<?php 
if (isset($_REQUEST['view'])) {

    if ($_REQUEST['view'] === 'changePassword') {
        include "change_password.php";
    } else if ($_REQUEST['view'] === 'manageAppUsers') {
        include "manage_app_users.php";
    } else if ($_REQUEST['view'] == 'addUser') {
        include "add_user_form.php";
    } else if ($_REQUEST['view'] == 'updateUser') {
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'updateTeam') {
        include "update_team_form.php";
    } else if ($_REQUEST['view'] == 'generalData') {
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'compta') {
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'suivi') {
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'userHistory') {
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'deleteUser') {
        $user = new User();
        $user->lookupUser((int)$_REQUEST['id']);
        $userName = trim($user->firstName . ' ' . $user->lastName) ?: $user->society;
        ?>
        <div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
          <div class="card shadow-sm border-0" style="max-width:440px;width:100%">
            <div class="card-body p-4">
              <div class="mb-3 text-center" style="font-size:2rem;color:var(--ca-danger)">
                <i class="fas fa-user-slash" aria-hidden="true"></i>
              </div>
              <h5 class="card-title mb-1 text-center">Supprimer ou archiver ce membre&nbsp;?</h5>
              <p class="text-muted text-center mb-4" style="font-size:0.85rem">
                <?= htmlspecialchars($userName, ENT_QUOTES, $charset) ?>
                <span class="text-muted ms-1" style="font-size:0.78rem">#<?= (int)$user->getId() ?></span>
              </p>
              <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                <input type="hidden" name="action" value="deleteOrDeactivateUser">
                <input type="hidden" name="id"     value="<?= (int)$user->getId() ?>">
                <div class="d-flex flex-column gap-2 mb-4">
                  <label class="ca-merge-radio" style="cursor:pointer">
                    <input type="radio" name="dispose" value="deactivate" checked>
                    <span><i class="fas fa-archive me-1 text-muted" aria-hidden="true"></i><strong>Archiver</strong></span>
                    <span class="text-muted ms-1" style="font-size:0.78rem">— conserve l'historique, retiré de toutes les vues</span>
                  </label>
                  <label class="ca-merge-radio ca-merge-radio--danger" style="cursor:pointer">
                    <input type="radio" name="dispose" value="delete">
                    <span><i class="fas fa-trash-alt me-1" aria-hidden="true"></i><strong>Supprimer définitivement</strong></span>
                    <span class="text-muted ms-1" style="font-size:0.78rem">— irréversible</span>
                  </label>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$user->getId() ?>"
                     class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
                  <button type="submit" class="btn btn-danger">Confirmer</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php
    } else if ($_REQUEST['view'] == 'deleteUserConfirm') {
        // Legacy direct-link confirm — treat as deactivate for safety
        $user = new User();
        $user->lookupUser((int)$_REQUEST['id']);
        $pdo->prepare("UPDATE users SET status=0 WHERE id=?")->execute([(int)$_REQUEST['id']]);
        auditLog($pdo, 'deactivateUser', "id={$_REQUEST['id']} {$user->firstName} {$user->lastName}");
        if ($isHtmx) { header('HX-Location: ' . $_SERVER['PHP_SELF']); exit; }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    } else if ($_REQUEST['view'] === 'auditLog') {
        include "audit_log.php";
    } else if ($_REQUEST['view'] == 'manageTeam') {
        // Legacy view — redirect to settings groups tab
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
        exit;
    } else if ($_REQUEST['view'] == 'updateMetagroup') {
        include "update_metagroup_form.php";
    } else if ($_REQUEST['view'] == 'updateCompta') {
        include "update_compta_form.php";
    } else if ($_REQUEST['view'] == 'lastEntryCompta') {
        include "lastEntryCompta.php";
    } else if ($_REQUEST['view'] == 'resume') {
        include "resume.php";
    } else if ($_REQUEST['view'] == 'lapsedDonors') {
        include "lapsed_donors.php";
    } else if ($_REQUEST['view'] == 'loyalDonors') {
        include "loyal_donors.php";
    } else if ($_REQUEST['view'] == 'newDonors') {
        include "new_donors.php";
    } else if ($_REQUEST['view'] == 'lapsedMembers') {
        include "lapsed_members.php";
    } else if ($_REQUEST['view'] == 'lastEntrySuivi') {
        include "lastEntrySuivi.php";
    } else if ($_REQUEST['view'] == 'removeCompta') {
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        ?>
        <div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
          <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
            <div class="card-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
              </div>
              <h5 class="card-title mb-1">Supprimer cette écriture&nbsp;?</h5>
              <p class="text-muted mb-3" style="font-size:0.85rem">Cette action est irréversible.</p>
              <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
                <div class="mb-1"><span class="text-muted">Date&nbsp;:</span> <strong><?=timeStampToformatedDate($compta->date)?></strong></div>
                <div class="mb-1"><span class="text-muted">Libellé&nbsp;:</span> <strong><?=htmlentities($compta->getLibele(),ENT_COMPAT,$charset)?></strong></div>
                <div><span class="text-muted">Montant&nbsp;:</span> <strong><?=$compta->sum?> CHF</strong></div>
              </div>
              <div class="d-flex gap-2 justify-content-center">
                <a href="<?=$_SERVER['PHP_SELF']?>?view=updateUser&amp;userid=<?=(int)$_REQUEST['userid']?>" class="btn btn-outline-secondary">
                  Annuler
                </a>
                <a href="<?=$_SERVER['PHP_SELF']?>?view=deleteComptaConfirm&amp;userid=<?=(int)$_REQUEST['userid']?>&comptaid=<?=$compta->getId()?>" class="btn btn-danger">
                  Supprimer
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php
    } else if ($_REQUEST['view'] == 'deleteComptaConfirm') {
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        $_auDcUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auDcUser->execute([(int)$compta->userId]);
        auditLog($pdo, 'deleteCompta', "compta#={$_REQUEST['comptaid']} | membre: " . ($_auDcUser->fetchColumn() ?: "id={$compta->userId}") . " | {$compta->sum} CHF");
        $compta->remove();
        $view = "compta";
        include "update_user_form.php";
    } else if ($_REQUEST['view'] == 'manageComptaTypes') {
        include "manage_compta_types.php";
    } else if ($_REQUEST['view'] == 'settings') {
        include "settings_form.php";
    } else if ($_REQUEST['view'] == 'updateSuivi') {
        $view = "suivi";
        include "update_suivi_form.php";
    } else if ($_REQUEST['view'] == 'removeSuivi') {
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        ?>
        <div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
          <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
            <div class="card-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
              </div>
              <h5 class="card-title mb-1">Supprimer cette entrée de suivi&nbsp;?</h5>
              <p class="text-muted mb-3" style="font-size:0.85rem">Cette action est irréversible.</p>
              <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
                <div class="mb-1"><span class="text-muted">Date&nbsp;:</span> <strong><?= timeStampToformatedDate($userProperty->date) ?></strong></div>
                <div><span class="text-muted">Contenu&nbsp;:</span> <strong><?= htmlentities($userProperty->getValue(), ENT_COMPAT, $charset) ?></strong></div>
              </div>
              <div class="d-flex gap-2 justify-content-center">
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= (int)$_REQUEST['userid'] ?>" class="btn btn-outline-secondary">
                  Annuler
                </a>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=removeSuiviConfirm&amp;userid=<?= (int)$_REQUEST['userid'] ?>&amp;suiviid=<?= $userProperty->getId() ?>" class="btn btn-danger">
                  Supprimer
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php
    } else if ($_REQUEST['view'] == 'anonymizeUser') {
        include "anonymize_user.php";
    } else if ($_REQUEST['view'] == 'mergeUsers') {
        include "merge_users.php";
    } else if ($_REQUEST['view'] == 'inactiveUsers') {
        include "inactive_users.php";
    } else if ($_REQUEST['view'] == 'removeSuiviConfirm') {
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        $_auRsUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auRsUser->execute([(int)$_REQUEST['userid']]);
        auditLog($pdo, 'deleteSuivi', "suivi#={$_REQUEST['suiviid']} | membre: " . ($_auRsUser->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | {$userProperty->parameter}: {$userProperty->getValue()}");
        $userProperty->remove();
        $view = "suivi";
        include "update_user_form.php";
    }
} else {
    include "view_users.php";
}
?>
