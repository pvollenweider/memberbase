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
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$UA_VIEW_ROUTES = [
    // Membres
    'list'                => ['users_list.php'],
    'usersList'           => ['users_list.php'], // alias utilisé par le pushState de la recherche AJAX
    'addUser'             => ['users_add_form.php',        'canWrite'],
    'updateUser'          => ['users_edit_form.php'],
    'generalData'         => ['users_edit_form.php'],
    'compta'              => ['users_edit_form.php'],
    'suivi'               => ['users_edit_form.php'],
    'userHistory'         => ['users_edit_form.php'],
    'deleteUser'          => ['users_delete_confirm.php',  'isAdmin'],
    'deleteUserConfirm'   => ['users_deactivate_legacy.php', 'isAdmin'],
    'anonymizeUser'       => ['users_anonymize.php',       'isAdmin'],
    'mergeUsers'          => ['users_merge.php',           'isManager'],
    'inactiveUsers'       => ['users_inactive.php'],
    'lapsedMembers'       => ['members_lapsed.php'],

    // Import CSV (wizard 3 étapes)
    'importStep1'         => ['import_step1.php',          'isManager'],
    'importStep2'         => ['import_step2.php',          'isManager'],
    'importStep3'         => ['import_step3.php',          'isManager'],

    // Comptabilité
    'updateCompta'        => ['compta_edit_form.php'],
    'lastEntryCompta'     => ['compta_last_entry.php'],
    'removeCompta'        => ['compta_delete_confirm.php', 'canWrite'],
    'deleteComptaConfirm' => ['compta_delete_do.php',      'canWrite'],

    // Donateurs
    'resume'              => ['donors_summary.php'],
    'lapsedDonors'        => ['donors_lapsed.php'],
    'loyalDonors'         => ['donors_loyal.php'],
    'newDonors'           => ['donors_new.php'],

    // Suivi
    'updateSuivi'         => ['suivi_edit_form.php',       null, [], 'suivi'],
    'lastEntrySuivi'      => ['suivi_last_entry.php'],
    'removeSuivi'         => ['suivi_delete_confirm.php',  'canWrite'],
    'removeSuiviConfirm'  => ['suivi_delete_do.php',       'canWrite'],

    // Réglages & administration
    'settings'            => ['settings_general.php'],
    'updateTeam'          => ['settings_general.php',      null, ['tab' => 'groups']],
    'updateMetagroup'     => ['settings_general.php',      null, ['tab' => 'filters']],
    'manageComptaTypes'   => ['settings_compta_types.php'],
    'manageAppUsers'      => ['settings_app_users.php'],
    'auditLog'            => ['settings_audit_log.php'],
    'changePassword'      => ['auth_change_password.php'],
];

$uaRequestedView = $_REQUEST['view'] ?? 'list';

// Legacy view — redirect to settings groups tab
if ($uaRequestedView === 'manageTeam') {
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=settings&tab=groups');
    exit;
}

if (!isset($UA_VIEW_ROUTES[$uaRequestedView])) {
    echo '<div class="alert alert-warning"><i class="fas fa-circle-question me-2" aria-hidden="true"></i>Vue introuvable.</div>';
    return;
}

[$uaViewFile, $uaViewGuard, $uaViewOverrides, $uaViewVar] = array_pad($UA_VIEW_ROUTES[$uaRequestedView], 4, null);

if ($uaViewGuard !== null && !$uaViewGuard()) {
    echo '<div class="alert alert-danger"><i class="fas fa-lock me-2" aria-hidden="true"></i>Accès refusé.</div>';
    return;
}

foreach ((array)$uaViewOverrides as $uaKey => $uaValue) {
    $_REQUEST[$uaKey] = $uaValue;
}
if ($uaViewVar !== null) {
    $view = $uaViewVar;
}

include __DIR__ . '/../views/' . $uaViewFile;
