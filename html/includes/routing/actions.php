<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Routes incoming action requests to the appropriate action handler.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (isset($_REQUEST['action'])) {
    // CSRF guard: every state-changing action is a POST and must carry a valid
    // token (hidden `csrf` field for native forms, `X-CSRF-Token` header for
    // htmx/fetch). GET actions (navigation) are not gated. See auth.php.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrfCheck()) {
        // Security log: a POST action was rejected for a missing/invalid token.
        auditLog($pdo, 'csrfRejected', 'action=' . ($_REQUEST['action'] ?? '?') . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($GLOBAL['csrfRejected']);
    }

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
        'zefixLookup'          => 'settings',
        'saveSmtp'             => 'settings',
        'sendTestEmail'        => 'settings',
        'purgeEmailLog'             => 'settings',
        'resendEmail'               => 'settings',
        'saveEmailTemplate'         => 'settings',
        'saveEmailTemplateSettings' => 'settings',
        'applyMigrations'      => 'maintenance',
        'updateComptaTypeOrder'=> 'settings',
        'addComptaType'        => 'settings',
        'updateComptaType'     => 'settings',
        'deleteComptaType'     => 'settings',
        'addCompta'            => 'compta',
        'updateCompta'         => 'compta',
        'toggleWantsAttestation' => 'compta',
        'deleteComptaEntry'      => 'compta',
        'importUpload'            => 'import',
        'importApply'             => 'import',
        'importResolveDuplicates' => 'import',
        'addSuivi'             => 'suivi',
        'updateSuivi'          => 'suivi',
        'logout'               => 'auth',
        'changePassword'       => 'auth',
        'changeLocale'         => 'auth',
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
