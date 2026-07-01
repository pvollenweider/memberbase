<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Routes incoming action requests to the appropriate action handler.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (isset($_REQUEST['action'])) {
    static $ACTION_MAP = [
        'updateUser'           => 'members',
        'addUser'              => 'members',
        'mergeUsers'             => 'members',
        'reactivateUser'         => 'members',
        'deactivateUser'         => 'members',
        'anonymizeUser'          => 'members',
        'deleteOrDeactivateUser' => 'members',
        'deleteTeam'           => 'groups',
        'deleteTeamForce'      => 'groups',
        'reassignTeam'         => 'groups',
        'importTeamMembers'    => 'groups',
        'importCotisants'      => 'groups',
        'importDonors'         => 'groups',
        'bulkHide'             => 'groups',
        'bulkShow'             => 'groups',
        'undoGroupVisibility'  => 'groups',
        'bulkCreateMetagroup'  => 'groups',
        'createLapsedGroup'    => 'groups',
        'addTeam'              => 'groups',
        'addTeamWithImport'    => 'groups',
        'renameTeam'           => 'groups',
        'updateTeam'           => 'groups',
        'addMembership'        => 'groups',
        'removeMembership'     => 'groups',
        'updateCategoryOrder'  => 'metagroups',
        'updateTeamCategory'   => 'metagroups',
        'updateMetagroupTeams' => 'metagroups',
        'deleteMetagroup'      => 'metagroups',
        'addMetagroup'         => 'metagroups',
        'updateMetagroup'      => 'metagroups',
        'saveSettings'         => 'settings',
        'updateComptaTypeOrder'=> 'settings',
        'addComptaType'        => 'settings',
        'updateComptaType'     => 'settings',
        'deleteComptaType'     => 'settings',
        'addCompta'            => 'compta',
        'updateCompta'         => 'compta',
        'toggleWantsAttestation' => 'compta',
        'fixComptaSum'           => 'compta',
        'deleteComptaEntry'      => 'compta',
        'importUpload'            => 'import',
        'importApply'             => 'import',
        'importResolveDuplicates' => 'import',
        'addSuivi'             => 'suivi',
        'updateSuivi'          => 'suivi',
        'logout'               => 'auth',
        'changePassword'       => 'auth',
        'createAppUser'        => 'auth',
        'updateAppUser'        => 'auth',
        'deleteAppUser'        => 'auth',
        'resetUserPassword'    => 'auth',
        'flushAuditLog'        => 'auth',
    ];

    $handler = $ACTION_MAP[$_REQUEST['action']] ?? null;
    if ($handler !== null) {
        require __DIR__ . '/../actions/' . $handler . '.php';
    }
}
?>
