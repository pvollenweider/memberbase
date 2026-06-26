<?php 
if (isset($_REQUEST['view'])) {

    if ($_REQUEST['view'] === 'changePassword') {
        include "change_password.inc";
    } else if ($_REQUEST['view'] === 'manageAppUsers') {
        include "manage_app_users.inc";
    } else if ($_REQUEST['view'] == 'addUser') {
        include "add_user_form.inc";
    } else if ($_REQUEST['view'] == 'updateUser') {
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'updateTeam') {
        include "update_team_form.inc";
    } else if ($_REQUEST['view'] == 'generalData') {
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'compta') {
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'suivi') {
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'deleteUser') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        ?>
<div class="alert alert-warning">
        <h2><?=$GLOBAL['delete']?> <?=htmlspecialchars($user->firstName, ENT_QUOTES, 'UTF-8')?> <?=htmlspecialchars($user->lastName, ENT_QUOTES, 'UTF-8')?> ?</h2>
        <a class="btn btn-danger btn-lg" href="<?=$_SERVER['PHP_SELF']?>?view=deleteUserConfirm&amp;id=<?=(int)$user->id?>" >
          Supprimer 
        </a>
<a href="<?=$_SERVER['PHP_SELF']?>" class="btn btn-secondary btn-lg">
            Conserver
        </a>
</div>
        <br/><br/>
        <?php 
    } else if ($_REQUEST['view'] == 'deleteUserConfirm') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        $user->remove();
        include "view_users.inc";
    } else if ($_REQUEST['view'] === 'auditLog') {
        include "audit_log.inc";
    } else if ($_REQUEST['view'] == 'manageTeam') {
        include "manage_teams.inc";
    } else if ($_REQUEST['view'] == 'updateMetagroup') {
        include "update_metagroup_form.inc";
    } else if ($_REQUEST['view'] == 'updateCompta') {
        include "update_compta_form.inc";
    } else if ($_REQUEST['view'] == 'lastEntryCompta') {
        include "lastEntryCompta.inc";
    } else if ($_REQUEST['view'] == 'resume') {
        include "resume.inc";
    } else if ($_REQUEST['view'] == 'lapsedDonors') {
        include "lapsed_donors.inc";
    } else if ($_REQUEST['view'] == 'loyalDonors') {
        include "loyal_donors.inc";
    } else if ($_REQUEST['view'] == 'newDonors') {
        include "new_donors.inc";
    } else if ($_REQUEST['view'] == 'lapsedMembers') {
        include "lapsed_members.inc";
    } else if ($_REQUEST['view'] == 'lastEntrySuivi') {
        include "lastEntrySuivi.inc";
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
        $compta->remove();
        $view = "compta";
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'manageComptaTypes') {
        include "manage_compta_types.inc";
    } else if ($_REQUEST['view'] == 'settings') {
        include "settings_form.inc";
    } else if ($_REQUEST['view'] == 'updateSuivi') {
        $view = "suivi";
        include "update_suivi_form.inc";
    } else if ($_REQUEST['view'] == 'removeSuivi') {
        $userProperty = new UserProperty();
        $userProperty ->lookupUserProperty($_REQUEST['suiviid']);
        ?><div class="alert alert-warning">
        <h2><?=$GLOBAL['delete']?> <?=timeStampToformatedDate($userProperty->date)?> / <?=htmlentities($userProperty->getValue(),ENT_COMPAT,$charset)?>?</h2>
        <a href="<?=$_SERVER['PHP_SELF']?>?view=removeSuiviConfirm&amp;userid=<?=(int)$_REQUEST['userid']?>&amp;suiviid=<?=$userProperty->getId()?>" class="btn btn-danger btn-lg">
            Supprimer
        </a>
        <a href="<?=$_SERVER['PHP_SELF']?>?view=suivi&amp;userid=<?=(int)$_REQUEST['userid']?>&amp;suiviid=<?=(int)$_REQUEST['suiviid']?>" class="btn btn-secondary btn-lg">
            Conserver
        </a>
	</div>
        <br/><br/>
        <?php 
    } else if ($_REQUEST['view'] == 'removeSuiviConfirm') {
        $userProperty = new UserProperty();
        $userProperty ->lookupUserProperty($_REQUEST['suiviid']);
        $userProperty->remove();
        $view = "suivi";
        include "update_user_form.inc";
    }
} else {
    include "view_users.inc";
}
?>
