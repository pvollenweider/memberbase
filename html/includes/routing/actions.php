<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Routes incoming action requests to the appropriate action handler.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (isset($_REQUEST['action'])) {
    static $ACTION_MAP = [
        'updateUser'           => 'contacts',
        'addUser'              => 'contacts',
        'mergeUsers'             => 'contacts',
        'reactivateUser'         => 'contacts',
        'deactivateUser'         => 'contacts',
        'anonymizeUser'          => 'contacts',
        'deleteOrDeactivateUser' => 'contacts',
'bulkDeleteUsers'        => 'contacts',
'bulkAnonymizeUsers'     => 'contacts',
        'deleteSegment'           => 'segments',
        'deleteSegmentForce'      => 'segments',
        'bulkDeleteSegmentsForce' => 'segments',
        'reassignSegment'         => 'segments',
        'importSegmentMembers'    => 'segments',
        'importCotisants'      => 'segments',
        'importDonors'         => 'segments',
        'bulkHide'             => 'segments',
        'bulkShow'             => 'segments',
        'undoSegmentVisibility'  => 'segments',
        'bulkCreateCombinedSegment'  => 'segments',
        'createLapsedSegment'    => 'segments',
        'addSegment'              => 'segments',
        'addSegmentWithImport'    => 'segments',
        'renameSegment'           => 'segments',
        'updateSegment'           => 'segments',
        'assignSegment'        => 'segments',
        'unassignSegment'     => 'segments',
        'addSegmentCascadeRule'    => 'segments',
        'deleteSegmentCascadeRule' => 'segments',
        'fixCotisationSegment'     => 'segments',
        'updateCategoryOrder'  => 'combined_segments',
        'updateSegmentCategory'   => 'combined_segments',
        'updateCombinedSegmentMembers' => 'combined_segments',
        'deleteCombinedSegment'      => 'combined_segments',
        'addCombinedSegment'         => 'combined_segments',
        'updateCombinedSegment'      => 'combined_segments',
        'saveSettings'         => 'settings',
        'zefixLookup'          => 'settings',
        'saveSmtp'             => 'settings',
        'sendTestEmail'        => 'settings',
        'purgeEmailLog'             => 'settings',
        'resendEmail'               => 'settings',
        'saveEmailTemplate'         => 'settings',
        'resetEmailTemplate'        => 'settings',
        'applyMigrations'      => 'maintenance',
        'updateComptaTypeOrder'=> 'settings',
        'addComptaType'        => 'settings',
        'updateComptaType'     => 'settings',
        'deleteComptaType'     => 'settings',
        'addContactType' => 'settings',
        'deleteContactType' => 'settings',
        'updateContactTypeLabel' => 'settings',
        'updateContactTypeComptaMatrixColumn' => 'settings',
        'updateContactTypeDefaultComptaType' => 'settings',
        'bulkSetContactTypeBySegment' => 'settings',
        'previewCotisationReminder' => 'cotisation_reminder',
        'sendCotisationReminders'   => 'cotisation_reminder',
        'sendCotisationReminderOne' => 'cotisation_reminder',
        'previewAttestation'         => 'attestation_email',
        'previewAttestationsBulkList' => 'attestation_email',
        'sendAttestationOne'        => 'attestation_email',
        'sendAttestationsBulk'      => 'attestation_email',
        'sendComptaRecap'       => 'compta_recap',
        'sendComptaRecapOne'    => 'compta_recap',
        'previewComptaRecap'    => 'compta_recap',
        'markAllComptaNotified' => 'compta_recap',
        'addCompta'            => 'compta',
        'updateCompta'         => 'compta',
        'toggleWantsAttestation' => 'compta',
        'deleteComptaEntry'      => 'compta',
        'importUpload'            => 'import',
        'importApply'             => 'import',
        'importResolveDuplicates' => 'import',
        'addSuivi'             => 'suivi',
        'updateSuivi'          => 'suivi',
        'deleteSuiviEntry'     => 'suivi',
        'addTask'              => 'suivi_tasks',
        'updateTask'           => 'suivi_tasks',
        'closeTask'            => 'suivi_tasks',
        'reopenTask'           => 'suivi_tasks',
        'pauseTask'            => 'suivi_tasks',
        'resumeTask'           => 'suivi_tasks',
        'deleteTask'           => 'suivi_tasks',
        'generateUnpaidCotiTasks' => 'suivi_tasks',
        'generateComptaRecapTasks' => 'suivi_tasks',
        'generateDuplicateTasks' => 'suivi_tasks',
        'generateHiddenSegmentTasks' => 'suivi_tasks',
        'generateAttestationTasks' => 'suivi_tasks',
        'bulkDeleteCompletedTasks' => 'suivi_tasks',
        'logout'               => 'auth',
        'changePassword'       => 'auth',
        'updateOwnProfile'     => 'auth',
        'changeLocale'         => 'auth',
        'createAppUser'        => 'auth',
        'updateAppUser'        => 'auth',
        'deleteAppUser'        => 'auth',
        'resetUserPassword'    => 'auth',
        'flushAuditLog'        => 'auth',
        'sidebarState'         => 'auth',
    ];

    $handler = $ACTION_MAP[$_REQUEST['action']] ?? null;
    if ($handler !== null) {
        // CSRF guard: every mapped action mutates state and must carry a valid
        // token, on ANY HTTP method. Sources: hidden `csrf` field for native forms,
        // `X-CSRF-Token` header for htmx/fetch. htmx-boosted GET links (segment
        // assign/unassign, delete-type modals, the undo toast) get that header via
        // htmx:configRequest in app.js, so token-carrying GET triggers still pass.
        //
        // Scoped to known handlers only: unmapped action= values (e.g. action=search
        // used as a view hint in users_list.php) reach no mutation code, so gating
        // them would just 403 plain-GET page loads that carry no CSRF token.
        if (!csrfCheck()) {
            auditLog($pdo, 'csrfRejected', 'action=' . ($_REQUEST['action'] ?? '?') . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? '?') . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            exit($GLOBAL['csrfRejected']);
        }
        require __DIR__ . '/../actions/' . $handler . '.php';
    }
}
?>
