<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Sends donation attestation PDFs by email (individual or bulk).
 * The stamp/signature overlay is always applied on emailed attestations
 * (unlike the direct-download endpoints, where it's opt-in).
 *
 * Actions:
 *   previewAttestation         — return the rendered subject/html/text for one member (no send)
 *   previewAttestationsBulkList — list qualifying donors for a year + who already got one (no send)
 *   sendAttestationOne         — send to a single member for one year (member card / résumé dons row)
 *   sendAttestationsBulk       — bulk send to all qualifying donors of a year (résumé dons);
 *                                 skips members already sent one this year unless their id is in force_ids
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_attAction = $_REQUEST['action'] ?? '';
$_attValidActions = ['previewAttestation', 'previewAttestationsBulkList', 'sendAttestationOne', 'sendAttestationsBulk'];
if (!in_array($_attAction, $_attValidActions, true)) { return; }
if (!isManager()) { http_response_code(403); exit; }

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/attestation.php';

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
if ($year <= 0) { $year = (int)date('Y'); }
$bcc = !empty($_REQUEST['bcc']) && trim($appSettings['smtp_reply_to'] ?? '') !== '';

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
    $result      = mbSendTemplateWithAttachment($pdo, $member->email, 'tpl_attestation_don', $vars, $userId, $attachments, $bcc);

    if ($result === true) {
        auditLog($pdo, 'attestationSent',
            "sent to {$member->firstname} {$member->lastname} <{$member->email}> year=$year");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => is_string($result) ? $result : 'send_failed']);
    }
    exit;
}

// ── previewAttestationsBulkList ───────────────────────────────────────────────
$minSum = isset($_REQUEST['minSum']) ? (int)$_REQUEST['minSum'] : 1;
if (!in_array($minSum, [1, 100, 200, 500, 1000])) { $minSum = 1; }

if ($_attAction === 'previewAttestationsBulkList') {
    $donors      = mbGetQualifyingDonors($pdo, $year, $minSum);
    $donorIds    = array_map(fn($d) => (int)$d->id, $donors);
    $alreadyMap  = mbGetAlreadySentAttestationIds($pdo, $year, $donorIds);

    $list = array_map(function ($d) use ($alreadyMap) {
        $uid = (int)$d->id;
        return [
            'id'          => $uid,
            'name'        => trim(($d->lastname ?? '') . ' ' . ($d->firstname ?? '')) ?: ($d->society ?? ''),
            'email'       => $d->email ?? '',
            'alreadySent' => $alreadyMap[$uid] ?? null,
        ];
    }, $donors);

    echo json_encode(['ok' => true, 'donors' => $list]);
    exit;
}

// ── sendAttestationsBulk ──────────────────────────────────────────────────────
$forceIds = array_filter(array_map('intval', explode(',', (string)($_REQUEST['force_ids'] ?? ''))));

$donors     = mbGetQualifyingDonors($pdo, $year, $minSum);
$donorIds   = array_map(fn($d) => (int)$d->id, $donors);
$alreadyMap = mbGetAlreadySentAttestationIds($pdo, $year, $donorIds);

$sentCount    = 0;
$skipCount    = 0;
$alreadyCount = 0;

foreach ($donors as $row) {
    $uid = (int)$row->id;

    if (isset($alreadyMap[$uid]) && !in_array($uid, $forceIds, true)) {
        $alreadyCount++;
        auditLog($pdo, 'attestationSent', "skip id=$uid (already sent this year)");
        continue;
    }
    if (trim($row->email) === '') {
        $skipCount++;
        auditLog($pdo, 'attestationSent', "skip id=$uid (no email)");
        continue;
    }

    $fields = mbBuildAttestationFields(
        $appSettings, $row->lastname ?? '', $row->firstname ?? '', $row->npa ?? '', $row->address ?? '',
        (float)$row->total, $year
    );
    $pdf = mbGenerateAttestationPdf($fields);
    if ($pdf === null) {
        $skipCount++;
        auditLog($pdo, 'attestationSent', "FAILED (pdf) id=$uid");
        continue;
    }

    require_once __DIR__ . '/../lib/attestation_stamp.php';
    $pdf = mbStampAttestationBytes($pdf);

    $vars        = mbBuildAttestationVarsForUser($pdo, $row, $appSettings, $year);
    $attachments = [['name' => "attestation-don-$year.pdf", 'mime' => 'application/pdf', 'data' => $pdf]];
    $result      = mbSendTemplateWithAttachment($pdo, $row->email, 'tpl_attestation_don', $vars, $uid, $attachments, $bcc);

    if ($result === true) {
        $sentCount++;
        auditLog($pdo, 'attestationSent',
            "sent to {$row->firstname} {$row->lastname} <{$row->email}> year=$year" . (isset($alreadyMap[$uid]) ? ' (forced resend)' : ''));
    } else {
        $skipCount++;
        auditLog($pdo, 'attestationSent',
            "FAILED for {$row->firstname} {$row->lastname} <{$row->email}> year=$year: $result");
    }
}

auditLog($pdo, 'attestationSent', "bulk year=$year minSum=$minSum sent=$sentCount skipped=$skipCount already=$alreadyCount");
echo json_encode(['ok' => true, 'sent' => $sentCount, 'skipped' => $skipCount, 'already' => $alreadyCount]);
exit;
