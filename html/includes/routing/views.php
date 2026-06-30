<?php 
/**
 * Dispatches include-based sub-views within the application.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (isset($_REQUEST['view'])) {

    if ($_REQUEST['view'] === 'changePassword') {
        include __DIR__ . "/../views/auth_change_password.php";
    } else if ($_REQUEST['view'] === 'manageAppUsers') {
        include __DIR__ . "/../views/settings_app_users.php";
    } else if ($_REQUEST['view'] == 'addUser') {
        if (!canWrite()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
        include __DIR__ . "/../views/users_add_form.php";
    } else if ($_REQUEST['view'] == 'updateUser') {
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'updateTeam') {
        $_REQUEST['tab'] = 'groups';
        include __DIR__ . "/../views/settings_general.php";
    } else if ($_REQUEST['view'] == 'generalData') {
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'compta') {
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'suivi') {
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'userHistory') {
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'deleteUser') {
        if (!isAdmin()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
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
              <h5 class="card-title mb-1 text-center"><?= $GLOBAL['deleteOrArchive'] ?>&nbsp;?</h5>
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
                    <span><i class="fas fa-archive me-1 text-muted" aria-hidden="true"></i><strong><?= $GLOBAL['archive'] ?></strong></span>
                    <span class="text-muted ms-1" style="font-size:0.78rem">— conserve l'historique, retiré de toutes les vues</span>
                  </label>
                  <label class="ca-merge-radio ca-merge-radio--danger" style="cursor:pointer">
                    <input type="radio" name="dispose" value="delete">
                    <span><i class="fas fa-trash-can me-1" aria-hidden="true"></i><strong><?= $GLOBAL['deletePermanently'] ?></strong></span>
                    <span class="text-muted ms-1" style="font-size:0.78rem">— irréversible</span>
                  </label>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                  <a href="<?= $_SERVER['PHP_SELF'] ?>?view=updateUser&id=<?= (int)$user->getId() ?>"
                     class="btn btn-outline-secondary"><?= $GLOBAL['cancel'] ?></a>
                  <button type="submit" class="btn btn-danger"><?= $GLOBAL['confirm'] ?></button>
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
        include __DIR__ . "/../views/settings_audit_log.php";
    } else if ($_REQUEST['view'] == 'manageTeam') {
        // Legacy view — redirect to settings groups tab
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
        exit;
    } else if ($_REQUEST['view'] == 'updateMetagroup') {
        $_REQUEST['tab'] = 'filters';
        include __DIR__ . "/../views/settings_general.php";
    } else if ($_REQUEST['view'] == 'updateCompta') {
        include __DIR__ . "/../views/compta_edit_form.php";
    } else if ($_REQUEST['view'] == 'lastEntryCompta') {
        include __DIR__ . "/../views/compta_last_entry.php";
    } else if ($_REQUEST['view'] == 'resume') {
        include __DIR__ . "/../views/donors_summary.php";
    } else if ($_REQUEST['view'] == 'lapsedDonors') {
        include __DIR__ . "/../views/donors_lapsed.php";
    } else if ($_REQUEST['view'] == 'loyalDonors') {
        include __DIR__ . "/../views/donors_loyal.php";
    } else if ($_REQUEST['view'] == 'newDonors') {
        include __DIR__ . "/../views/donors_new.php";
    } else if ($_REQUEST['view'] == 'lapsedMembers') {
        include __DIR__ . "/../views/members_lapsed.php";
    } else if ($_REQUEST['view'] == 'lastEntrySuivi') {
        include __DIR__ . "/../views/suivi_last_entry.php";
    } else if ($_REQUEST['view'] == 'removeCompta') {
        if (!canWrite()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        ?>
        <div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
          <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
            <div class="card-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
              </div>
              <h5 class="card-title mb-1"><?= $GLOBAL['deleteEntry'] ?>&nbsp;?</h5>
              <p class="text-muted mb-3" style="font-size:0.85rem">Cette action est irréversible.</p>
              <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
                <div class="mb-1"><span class="text-muted">Date&nbsp;:</span> <strong><?=timeStampToformatedDate($compta->date)?></strong></div>
                <div class="mb-1"><span class="text-muted">Libellé&nbsp;:</span> <strong><?=htmlentities($compta->getLibele(),ENT_COMPAT,$charset)?></strong></div>
                <div><span class="text-muted">Montant&nbsp;:</span> <strong><?=$compta->sum?> CHF</strong></div>
              </div>
              <div class="d-flex gap-2 justify-content-center">
                <a href="<?=$_SERVER['PHP_SELF']?>?view=updateUser&amp;userid=<?=(int)$_REQUEST['userid']?>" class="btn btn-outline-secondary">
                  <?= $GLOBAL['cancel'] ?>
                </a>
                <a href="<?=$_SERVER['PHP_SELF']?>?view=deleteComptaConfirm&amp;userid=<?=(int)$_REQUEST['userid']?>&comptaid=<?=$compta->getId()?>" class="btn btn-danger">
                  <?= $GLOBAL['delete'] ?>
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php
    } else if ($_REQUEST['view'] == 'deleteComptaConfirm') {
        if (!canWrite()) { http_response_code(403); exit; }
        $compta = new Compta();
        $compta->lookupCompta($_REQUEST['comptaid']);
        $_auDcUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auDcUser->execute([(int)$compta->userId]);
        auditLog($pdo, 'deleteCompta', "compta#={$_REQUEST['comptaid']} | membre: " . ($_auDcUser->fetchColumn() ?: "id={$compta->userId}") . " | {$compta->sum} CHF");
        $compta->remove();
        $view = "compta";
        include __DIR__ . "/../views/users_edit_form.php";
    } else if ($_REQUEST['view'] == 'manageComptaTypes') {
        include __DIR__ . "/../views/settings_compta_types.php";
    } else if ($_REQUEST['view'] == 'settings') {
        include __DIR__ . "/../views/settings_general.php";
    } else if ($_REQUEST['view'] == 'updateSuivi') {
        $view = "suivi";
        include __DIR__ . "/../views/suivi_edit_form.php";
    } else if ($_REQUEST['view'] == 'removeSuivi') {
        if (!canWrite()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        ?>
        <div class="d-flex justify-content-center align-items-center" style="min-height:50vh">
          <div class="card shadow-sm border-0" style="max-width:420px;width:100%">
            <div class="card-body p-4 text-center">
              <div class="mb-3" style="font-size:2rem;color:var(--bs-danger)">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
              </div>
              <h5 class="card-title mb-1"><?= $GLOBAL['deleteSuiviEntry'] ?>&nbsp;?</h5>
              <p class="text-muted mb-3" style="font-size:0.85rem">Cette action est irréversible.</p>
              <div class="border rounded p-3 mb-4 text-start bg-light" style="font-size:0.875rem">
                <div class="mb-1"><span class="text-muted">Date&nbsp;:</span> <strong><?= timeStampToformatedDate($userProperty->date) ?></strong></div>
                <div><span class="text-muted">Contenu&nbsp;:</span> <strong><?= htmlentities($userProperty->getValue(), ENT_COMPAT, $charset) ?></strong></div>
              </div>
              <div class="d-flex gap-2 justify-content-center">
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=suivi&amp;userid=<?= (int)$_REQUEST['userid'] ?>" class="btn btn-outline-secondary">
                  <?= $GLOBAL['cancel'] ?>
                </a>
                <a href="<?= $_SERVER['PHP_SELF'] ?>?view=removeSuiviConfirm&amp;userid=<?= (int)$_REQUEST['userid'] ?>&amp;suiviid=<?= $userProperty->getId() ?>" class="btn btn-danger">
                  <?= $GLOBAL['delete'] ?>
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php
    } else if ($_REQUEST['view'] == 'anonymizeUser') {
        if (!isAdmin()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
        include __DIR__ . "/../views/users_anonymize.php";
    } else if ($_REQUEST['view'] == 'mergeUsers') {
        if (!isManager()) { echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Accès refusé.</div>'; return; }
        include __DIR__ . "/../views/users_merge.php";
    } else if ($_REQUEST['view'] == 'inactiveUsers') {
        include __DIR__ . "/../views/users_inactive.php";
    } else if ($_REQUEST['view'] == 'removeSuiviConfirm') {
        $userProperty = new UserProperty();
        $userProperty->lookupUserProperty($_REQUEST['suiviid']);
        $_auRsUser = $pdo->prepare("SELECT CONCAT(firstName,' ',lastName) FROM users WHERE id=?");
        $_auRsUser->execute([(int)$_REQUEST['userid']]);
        auditLog($pdo, 'deleteSuivi', "suivi#={$_REQUEST['suiviid']} | membre: " . ($_auRsUser->fetchColumn() ?: "id={$_REQUEST['userid']}") . " | {$userProperty->parameter}: {$userProperty->getValue()}");
        $userProperty->remove();
        $view = "suivi";
        include __DIR__ . "/../views/users_edit_form.php";
    }
} else {
    include __DIR__ . "/../views/users_list.php";
}
?>
