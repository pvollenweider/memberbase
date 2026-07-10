<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Sends donation attestation PDFs by email (individual or bulk).
 * The stamp/signature overlay is always applied on emailed attestations
 * (unlike the direct-download endpoints, where it's opt-in).
 *
 * Actions:
 *   previewAttestation    — return the rendered subject/html/text for one member (no send)
 *   sendAttestationOne    — send to a single member for one year (member card / résumé dons row)
 *   sendAttestationsBulk  — bulk send to all qualifying donors of a year (résumé dons)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_attAction = $_REQUEST['action'] ?? '';
$_attValidActions = ['previewAttestation', 'sendAttestationOne', 'sendAttestationsBulk'];
if (!in_array($_attAction, $_attValidActions, true)) { return; }
if (!isManager()) { http_response_code(403); exit; }

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/attestation.php';

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
if ($year <= 0) { $year = (int)date('Y'); }

// ── previewAttestation ────────────────────────────────────────────────────────
if ($_attAction === 'previewAttestation') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = $pdo->prepare("SELECT id, firstname, lastname, society, sexe, email FROM contact WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }

    $vars     = mbBuildAttestationVarsForUser($pdo, $member, $appSettings, $year);
    $tpl      = mbGetTemplate($pdo, 'tpl_attestation_don');
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    echo json_encode(['ok' => true, 'subject' => $subject, 'html' => $bodyHtml, 'text' => $bodyText, 'email' => $member->email]);
    exit;
}

// ── sendAttestationOne ────────────────────────────────────────────────────────
if ($_attAction === 'sendAttestationOne') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = $pdo->prepare("SELECT id, firstname, lastname, society, sexe, email FROM contact WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }
    if (trim($member->email) === '') { echo json_encode(['ok' => false, 'error' => 'no_email']); exit; }

    $user = new Contact();
    $user->lookupUser($userId);
    $pdf = mbGenerateAttestationForUser($pdo, $appSettings, $user, $year, true);
    if ($pdf === null) { echo json_encode(['ok' => false, 'error' => 'pdf_generation_failed']); exit; }

    $vars        = mbBuildAttestationVarsForUser($pdo, $member, $appSettings, $year);
    $attachments = [['name' => "attestation-don-$year.pdf", 'mime' => 'application/pdf', 'data' => $pdf]];
    $result      = mbSendTemplateWithAttachment($pdo, $member->email, 'tpl_attestation_don', $vars, $userId, $attachments);

    if ($result === true) {
        auditLog($pdo, 'attestationSent',
            "sent to {$member->firstname} {$member->lastname} <{$member->email}> year=$year");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => is_string($result) ? $result : 'send_failed']);
    }
    exit;
}

// ── sendAttestationsBulk ──────────────────────────────────────────────────────
$minSum = isset($_REQUEST['minSum']) ? (int)$_REQUEST['minSum'] : 1;
if (!in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }

$from = mktime(0, 0, 0, 1, 0, $year);
$to   = mktime(0, 0, 0, 1, 1, $year + 1);

$stmt = $pdo->prepare("
    SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.email, u.npa, u.address,
           SUM(c.sum) AS total
    FROM contact u
    JOIN compta c ON u.id = c.user_id
    WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
      AND c.date > ? AND c.date < ?
    GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.email, u.npa, u.address
    HAVING SUM(c.sum) >= ?
    ORDER BY u.lastname, u.firstname
");
$stmt->execute([$from, $to, $minSum]);
$donors = $stmt->fetchAll(PDO::FETCH_OBJ);

$sentCount = 0;
$skipCount = 0;

foreach ($donors as $row) {
    if (trim($row->email) === '') {
        $skipCount++;
        auditLog($pdo, 'attestationSent', "skip id={$row->id} (no email)");
        continue;
    }

    $fields = mbBuildAttestationFields(
        $appSettings, $row->lastname ?? '', $row->firstname ?? '', $row->npa ?? '', $row->address ?? '',
        (float)$row->total, $year
    );
    $pdf = mbGenerateAttestationPdf($fields);
    if ($pdf === null) {
        $skipCount++;
        auditLog($pdo, 'attestationSent', "FAILED (pdf) id={$row->id}");
        continue;
    }

    require_once __DIR__ . '/../lib/attestation_stamp.php';
    $pdf = mbStampAttestationBytes($pdf);

    $vars        = mbBuildAttestationVarsForUser($pdo, $row, $appSettings, $year);
    $attachments = [['name' => "attestation-don-$year.pdf", 'mime' => 'application/pdf', 'data' => $pdf]];
    $result      = mbSendTemplateWithAttachment($pdo, $row->email, 'tpl_attestation_don', $vars, (int)$row->id, $attachments);

    if ($result === true) {
        $sentCount++;
        auditLog($pdo, 'attestationSent',
            "sent to {$row->firstname} {$row->lastname} <{$row->email}> year=$year");
    } else {
        $skipCount++;
        auditLog($pdo, 'attestationSent',
            "FAILED for {$row->firstname} {$row->lastname} <{$row->email}> year=$year: $result");
    }
}

auditLog($pdo, 'attestationSent', "bulk year=$year minSum=$minSum sent=$sentCount skipped=$skipCount");
echo json_encode(['ok' => true, 'sent' => $sentCount, 'skipped' => $skipCount]);
exit;
