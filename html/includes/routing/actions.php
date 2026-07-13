<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Routes incoming action requests to the appropriate action handler.
 *
 * @copyright 2026 Philippe Vollenweider
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
        'updateUser'           => 'contacts',
        'addUser'              => 'contacts',
        'mergeUsers'             => 'contacts',
        'reactivateUser'         => 'contacts',
        'deactivateUser'         => 'contacts',
        'anonymizeUser'          => 'contacts',
        'deleteOrDeactivateUser' => 'contacts',
        'deleteSegment'           => 'segments',
        'deleteSegmentForce'      => 'segments',
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
        'deleteTask'           => 'suivi_tasks',
        'generateUnpaidCotiTasks' => 'suivi_tasks',
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
