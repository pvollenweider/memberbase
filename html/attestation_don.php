<?php
define('APP_ENTRY', true);
/**
 * Generates a single donation attestation PDF for one member/year via pdftk.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/includes/lib/auth.php';
requireLogin();
// Attestations expose nominative donation data — restrict to managers/admins,
// matching the sendAttestation* actions (attestation_email.php).
if (!isManager()) { http_response_code(403); exit('Forbidden'); }
ob_start();
include __DIR__ . "/includes/lib/bootstrap.php";
require_once __DIR__ . '/locales/resources_fr.php';
require_once __DIR__ . '/includes/lib/attestation.php';
include "classes/contact_class.php";

$emailId = isset($_GET['emailid']) ? (int)$_GET['emailid'] : 0;
$asOf    = null;

if ($emailId > 0) {
    // Regenerate a previously sent attestation from its email_log entry — reuses
    // the original send date for the "Lieu / Date" line (see email_detail.php).
    $stmt = $pdo->prepare("SELECT user_id, subject, created_at, tpl_key FROM email_log WHERE id = ? LIMIT 1");
    $stmt->execute([$emailId]);
    $log = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$log || $log->tpl_key !== 'tpl_attestation_don' || !$log->user_id) {
        http_response_code(404);
        die('Not found');
    }
    $userid = (int)$log->user_id;
    $asOf   = strtotime($log->created_at);
    $year   = preg_match('/\b(20\d{2})\b/', $log->subject, $m) ? (int)$m[1] : (int)date('Y', $asOf);
    $stamp  = !isset($_GET['stamp']) || !empty($_GET['stamp']); // regenerated copies are stamped by default
} else {
    $userid = isset($_GET['userid']) ? (int)$_GET['userid'] : 0;
    $year   = isset($_GET['year'])   ? (int)$_GET['year']   : (int)date('Y');
    $stamp  = !empty($_GET['stamp']);
}

if (!$userid) { http_response_code(400); die('Missing userid'); }

$user = new Contact();
$user->lookupUser($userid);

$pdf = mbGenerateAttestationForUser($pdo, $appSettings, $user, $year, $stamp, $asOf);

if ($pdf === null) {
    http_response_code(500);
    echo '<pre>' . sprintf($GLOBAL['pdftkError'], -1) . '</pre>';
    exit;
}

$filename = 'attestation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $user->getLastName() . '_' . $year) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache');
echo $pdf;
exit;
