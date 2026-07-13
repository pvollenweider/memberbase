<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Dispatches include-based sub-views within the application.
 *
 * Route table format: view name => [file, guard, requestOverrides, viewVar]
 *   - file             view file relative to includes/views/
 *   - guard            auth function name (canWrite / isManager / isAdmin) or null for any logged-in user
 *   - requestOverrides assoc array merged into $_REQUEST before include (e.g. forcing a settings tab)
 *   - viewVar          overrides the $view variable consumed by shared views like users_edit_form.php
 *
 * A route missing from this table renders a "view not found" warning — a new
 * view MUST be declared here, which forces the guard decision to be explicit.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$UA_VIEW_ROUTES = [
    // Tableau de bord
    'dashboard'           => ['dashboard.php'],

    // Membres & finances — hub à onglets (#164), remplace Listes/Relances
    // cotisation/Aperçu des dons dans la navbar. Guard par onglet géré dans
    // la vue elle-même (Membres/Dons ouverts à tous, Relances réservé aux
    // managers, comme comptaRecap).
    'peopleFinance'       => ['people_finance.php'],

    // Journaux — hub à onglets, remplace Compta/Suivi dans la navbar. Ouvert
    // à tous les rôles, comme les deux routes qu'il remplace.
    'journals'            => ['journals.php'],

    // Membres
    'list'                => ['users_list.php'],
    'usersList'           => ['users_list.php'], // alias utilisé par le pushState de la recherche AJAX
    'addUser'             => ['users_add_form.php',        'canWrite'],
    'updateUser'          => ['users_edit_form.php'],
    'generalData'         => ['users_edit_form.php'],
    'compta'              => ['users_edit_form.php'],
    'suivi'               => ['users_edit_form.php'],
    'memberTasks'         => ['users_edit_form.php'],
    'userHistory'         => ['users_edit_form.php'],
    'deleteUser'          => ['users_delete_confirm.php',  'isAdmin'],
    // Legacy direct-link alias — shows the confirm dialog (state changes are POST-only).
    'deleteUserConfirm'   => ['users_delete_confirm.php',  'isAdmin'],
    'anonymizeUser'       => ['users_anonymize.php',       'isAdmin'],
    'mergeUsers'          => ['users_merge.php',           'isManager'],
    'inactiveUsers'       => ['users_inactive.php'],
    
    // Import CSV (wizard 3 étapes)
    'importStep1'         => ['import_step1.php',          'isManager'],
    'importStep2'         => ['import_step2.php',          'isManager'],
    'importStep3'         => ['import_step3.php',          'isManager'],

    // Comptabilité
    'updateCompta'        => ['compta_edit_form.php'],
    'lastEntryCompta'     => ['compta_last_entry.php'],
    'comptaRecap'         => ['compta_recap.php',         'isManager'],
    'removeCompta'        => ['compta_delete_confirm.php', 'canWrite'],

    // Donateurs
    'resume'              => ['donors_summary.php'],
        'loyalDonors'         => ['donors_loyal.php'],
    'newDonors'           => ['donors_new.php'],
    'newMembers'          => ['members_new.php'],

    // Suivi
    'updateSuivi'         => ['suivi_edit_form.php',       null, [], 'suivi'],
    'lastEntrySuivi'      => ['suivi_last_entry.php'],
    'emailDetail'         => ['email_detail.php',         'isManager'],
    'removeSuivi'         => ['suivi_delete_confirm.php',  'canWrite'],

    // Tâches
    'tasks'               => ['tasks_global.php'],
    'updateTask'          => ['tasks_edit_form.php',       null, [], 'memberTasks'],
    'removeTask'          => ['tasks_delete_confirm.php',  'canWrite'],

    // Réglages & administration
    'settings'            => ['settings_general.php'],
    'updateSegment'          => ['settings_general.php',      null, ['tab' => 'segments']],
    'updateCombinedSegment'     => ['settings_general.php',      null, ['tab' => 'filters']],
    'manageComptaTypes'   => ['settings_compta_types.php'],
    'contactTypes'        => ['settings_contact_types.php', 'isAdmin'],
    'manageAppUsers'      => ['settings_app_users.php'],
    'auditLog'            => ['settings_audit_log.php'],
    'changePassword'      => ['auth_change_password.php'],
];

$uaRequestedView = mbDefaultView($_REQUEST);

// Legacy view — redirect to settings segments tab
if ($uaRequestedView === 'manageTeam') {
    header('Location: ' . appUrl() . '?view=settings&tab=segments');
    exit;
}

// Legacy view — folded into the "Membres & finances" hub as its own tab
if ($uaRequestedView === 'lapsedMembers') {
    $_lmYear = isset($_REQUEST['year']) ? '&year=' . (int)$_REQUEST['year'] : '';
    header('Location: ' . appUrl() . '?view=peopleFinance&tab=lapsed' . $_lmYear);
    exit;
}

// Legacy view — folded into the "Membres & finances" hub as its own tab
if ($uaRequestedView === 'lapsedDonors') {
    $_ldYear = isset($_REQUEST['year']) ? '&year=' . (int)$_REQUEST['year'] : '';
    header('Location: ' . appUrl() . '?view=peopleFinance&tab=lapsedDonors' . $_ldYear);
    exit;
}

if (!isset($UA_VIEW_ROUTES[$uaRequestedView])) {
    echo '<div class="alert alert-warning"><i class="fas fa-circle-question me-2" aria-hidden="true"></i>' . $GLOBAL['viewNotFound'] . '</div>';
    return;
}

[$uaViewFile, $uaViewGuard, $uaViewOverrides, $uaViewVar] = array_pad($UA_VIEW_ROUTES[$uaRequestedView], 4, null);

if ($uaViewGuard !== null && !$uaViewGuard()) {
    // Security log: a role guard rejected access to a view.
    auditLog($pdo, 'accessDenied', "view={$uaRequestedView} guard={$uaViewGuard} role=" . ($_SESSION['app_user_role'] ?? '?'));
    echo '<div class="alert alert-danger"><i class="fas fa-lock me-2" aria-hidden="true"></i>' . $GLOBAL['accessDenied'] . '</div>';
    return;
}

foreach ((array)$uaViewOverrides as $uaKey => $uaValue) {
    $_REQUEST[$uaKey] = $uaValue;
}
if ($uaViewVar !== null) {
    $view = $uaViewVar;
}

include __DIR__ . '/../views/' . $uaViewFile;
