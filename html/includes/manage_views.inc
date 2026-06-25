<?php 
$logger = Logger::getLogger('manage_views.php');
if (isset($_REQUEST['view'])) {

    if ($_REQUEST['view'] == 'addUser') {
        $logger->debug("Dispaching add_user_form.inc");
        include "add_user_form.inc";
    } else if ($_REQUEST['view'] == 'updateUser') {
        $logger->debug("Dispaching update_user_form.inc");
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'updateTeam') {
        $logger->debug("Dispaching update_team_form.inc");
        include "update_team_form.inc";
    } else if ($_REQUEST['view'] == 'generalData') {
        $logger->debug("Dispaching update_user_form.inc");
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'compta') {
        $logger->debug("Dispaching update_user_form.inc");
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'suivi') {
        $logger->debug("Dispaching update_user_form.inc");
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'deleteUser') {
        $user = new User();
        $user->lookupUser($_REQUEST['id']);
        ?>
<div class="alert alert-warning">
        <h2><?=$GLOBAL['delete']?> <?=$user->firstName?> <?=$user->lastName?> ?</h2>
        <a class="btn btn-danger btn-lg" href="<?=$_SERVER['PHP_SELF']?>?view=deleteUserConfirm&amp;id=<?=$user->id?>" >
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
        $logger->debug("Dispaching view_users.inc");
        include "view_users.inc";
    } else if ($_REQUEST['view'] == 'manageTeam') {
        $logger->debug("Dispaching manage_teams.inc");
        include "manage_teams.inc";
    } else if ($_REQUEST['view'] == 'manageTeam') {
        $logger->debug("Dispaching manage_teams.inc");
        include "manage_teams.inc";
    } else if ($_REQUEST['view'] == 'manageMetagroups') {
        $logger->debug("Redirect manageMetagroups -> manageTeam");
        echo '<script>window.location.replace(' . json_encode($_SERVER['PHP_SELF'] . '?view=manageTeam') . ');</script>';
        exit;
    } else if ($_REQUEST['view'] == 'updateMetagroup') {
        $logger->debug("Dispaching update_metagroup_form.inc");
        include "update_metagroup_form.inc";
    } else if ($_REQUEST['view'] == 'updateCompta') {
        $logger->debug("Dispaching update_compta_form.inc");
        include "update_compta_form.inc";
    } else if ($_REQUEST['view'] == 'lastEntryCompta') {
        $logger->debug("Dispaching lastEntryCompta.inc");
        include "lastEntryCompta.inc";
    } else if ($_REQUEST['view'] == 'lastEntryCompta2') {
        $logger->debug("Dispaching lastEntryCompta2.inc");
        include "lastEntryCompta2.inc";
    } else if ($_REQUEST['view'] == 'resume') {
        $logger->debug("Dispaching resume.inc");
        include "resume.inc";
    } else if ($_REQUEST['view'] == 'lapsedDonors') {
        $logger->debug("Dispaching lapsed_donors.inc");
        include "lapsed_donors.inc";
    } else if ($_REQUEST['view'] == 'loyalDonors') {
        $logger->debug("Dispaching loyal_donors.inc");
        include "loyal_donors.inc";
    } else if ($_REQUEST['view'] == 'newDonors') {
        $logger->debug("Dispaching new_donors.inc");
        include "new_donors.inc";
    } else if ($_REQUEST['view'] == 'lapsedMembers') {
        $logger->debug("Dispaching lapsed_members.inc");
        include "lapsed_members.inc";
    } else if ($_REQUEST['view'] == 'resume2') {
        $logger->debug("Dispaching resume2.inc");
        include "resume2.inc";
    } else if ($_REQUEST['view'] == 'resume3') {
        $logger->debug("Dispaching resume3.inc");
        include "resume3.inc";
    }  else if ($_REQUEST['view'] == 'resume4') {
        $logger->debug("Dispaching resume4.inc");
        include "resume4.inc";
    }  else if ($_REQUEST['view'] == 'resume-non-coti') {
        $logger->debug("Dispaching resume-non-coti.inc");
        include "resume-non-coti.inc";
    } else if ($_REQUEST['view'] == 'resume_dna') {
        $logger->debug("Dispaching resume_dna.inc");
        include "resume_dna.inc";
    } else if ($_REQUEST['view'] == 'lastEntrySuivi') {
        $logger->debug("Dispaching lastEntrySuivi.inc");
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
                <a href="<?=$_SERVER['PHP_SELF']?>?view=updateUser&amp;userid=<?=$_REQUEST['userid']?>" class="btn btn-outline-secondary">
                  Annuler
                </a>
                <a href="<?=$_SERVER['PHP_SELF']?>?view=deleteComptaConfirm&amp;userid=<?=$_REQUEST['userid']?>&comptaid=<?=$compta->getId()?>" class="btn btn-danger">
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
        $logger->debug("update_user_form lastEntrySuivi.inc");
        include "update_user_form.inc";
    } else if ($_REQUEST['view'] == 'manageComptaTypes') {
        $logger->debug("Dispaching manage_compta_types.inc");
        include "manage_compta_types.inc";
    } else if ($_REQUEST['view'] == 'settings') {
        $logger->debug("Dispaching settings_form.inc");
        include "settings_form.inc";
    } else if ($_REQUEST['view'] == 'updateSuivi') {
        $view = "suivi";
        $logger->debug("Dispaching update_suivi_form.inc");
        include "update_suivi_form.inc";
    } else if ($_REQUEST['view'] == 'removeSuivi') {
        $userProperty = new UserProperty();
        $userProperty ->lookupUserProperty($_REQUEST['suiviid']);
        ?><div class="alert alert-warning">
        <h2><?=$GLOBAL['delete']?> <?=timeStampToformatedDate($userProperty->date)?> / <?=htmlentities($userProperty->getValue(),ENT_COMPAT,$charset)?>?</h2>
        <a href="<?=$_SERVER['PHP_SELF']?>?view=removeSuiviConfirm&amp;userid=<?=$_REQUEST['userid']?>&amp;suiviid=<?=$userProperty->getId()?>" class="btn btn-danger btn-lg">
            Supprimer
        </a>
        <a href="<?=$_SERVER['PHP_SELF']?>?view=suivi&amp;userid=<?=$_REQUEST['userid']?>&amp;suiviid=<?=$_REQUEST['suiviid']?>" class="btn btn-secondary btn-lg">
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
        $logger->debug("Dispaching update_user_form.inc");
        include "update_user_form.inc";
    }
} else {
    include "view_users.inc";
}
?>
