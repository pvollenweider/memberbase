<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Sends cotisation reminder emails to members who paid last year but not this year.
 * Skips members who already received a reminder this year (checked via email_log.tpl_key).
 *
 * Actions:
 *   previewCotisationReminder  — return the rendered subject/html/text for one member (no send)
 *   sendCotisationReminders    — bulk send to all un-reminded lapsed members
 *   sendCotisationReminderOne  — send to a single member (per-row button)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_cotiAction = $_REQUEST['action'] ?? '';
$_cotiValidActions = ['previewCotisationReminder', 'sendCotisationReminders', 'sendCotisationReminderOne'];
if (!in_array($_cotiAction, $_cotiValidActions, true)) { return; }
if (!isManager()) { http_response_code(403); exit; }

// Discard any page HTML already buffered — this action returns pure JSON.
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
if ($year <= 0) { $year = (int)date('Y'); }
$bcc = !empty($_REQUEST['bcc']) && trim($appSettings['smtp_reply_to'] ?? '') !== '';

require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/cotisation.php';
require_once __DIR__ . '/../lib/qr_bill.php';

$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
if (empty($cotiTypeIds)) {
    echo json_encode(['ok' => false, 'error' => 'no_coti_types']);
    exit;
}

// ── previewCotisationReminder ─────────────────────────────────────────────────
if ($_cotiAction === 'previewCotisationReminder') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = db()->prepare("SELECT id, firstname, lastname, society, email FROM contact WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }

    $vars     = mbBuildCotiReminderVars($member, $year, $appSettings);
    $tpl      = mbGetTemplate(db(), 'tpl_cotisation_reminder');
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    echo json_encode(['ok' => true, 'subject' => $subject, 'html' => $bodyHtml, 'text' => $bodyText, 'email' => $member->email]);
    exit;
}

// ── sendCotisationReminderOne ─────────────────────────────────────────────────
if ($_cotiAction === 'sendCotisationReminderOne') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = db()->prepare("SELECT id, firstname, lastname, society, email FROM contact WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }
    if (trim($member->email) === '') { echo json_encode(['ok' => false, 'error' => 'no_email']); exit; }

    $vars        = mbBuildCotiReminderVars($member, $year, $appSettings);
    $attachments = [];
    $qrPdf       = mbGenerateQrBillPdf($appSettings, $year);
    if ($qrPdf !== null) {
        $attachments[] = ['name' => "bulletin-versement-$year.pdf", 'mime' => 'application/pdf', 'data' => $qrPdf];
    }
    $result = mbSendTemplateWithAttachment(db(), $member->email, 'tpl_cotisation_reminder', $vars, $userId, $attachments, $bcc);
    if ($result === true) {
        auditLog(db(), 'sendCotisationReminderOne',
            "sent to {$member->firstname} {$member->lastname} <{$member->email}> year=$year");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => is_string($result) ? $result : 'send_failed']);
    }
    exit;
}

// ── sendCotisationReminders (bulk) ────────────────────────────────────────────
$_noCotiSegment = (int)($appSettings['member_no_coti_segment'] ?? 0);
$members        = mbGetLapsedMembers(db(), $year, $cotiTypeIds, $_noCotiSegment);

if (empty($members)) {
    echo json_encode(['ok' => true, 'sent' => 0, 'skipped' => 0, 'already' => 0]);
    exit;
}

$force      = !empty($_REQUEST['force']);
$memberIds  = array_map(fn($m) => (int)$m->id, $members);
$alreadyMap = $force ? [] : mbGetAlreadyRemindedIds(db(), $year, $memberIds);

// Generate QR bill PDF once — the same slip is attached to every reminder
$qrPdf = mbGenerateQrBillPdf($appSettings, $year);

$sentCount    = 0;
$skipCount    = 0;
$alreadyCount = 0;

foreach ($members as $m) {
    if (isset($alreadyMap[(int)$m->id])) {
        $alreadyCount++;
        auditLog(db(), 'sendCotisationReminders', "skip id={$m->id} (already reminded this year)");
        continue;
    }
    if (trim($m->email) === '') {
        $skipCount++;
        auditLog(db(), 'sendCotisationReminders', "skip id={$m->id} (no email)");
        continue;
    }

    $vars        = mbBuildCotiReminderVars($m, $year, $appSettings);
    $attachments = [];
    if ($qrPdf !== null) {
        $attachments[] = ['name' => "bulletin-versement-$year.pdf", 'mime' => 'application/pdf', 'data' => $qrPdf];
    }
    $result = mbSendTemplateWithAttachment(db(), $m->email, 'tpl_cotisation_reminder', $vars, (int)$m->id, $attachments, $bcc);
    if ($result === true) {
        $sentCount++;
        auditLog(db(), 'sendCotisationReminders',
            "sent to {$m->firstname} {$m->lastname} <{$m->email}> year=$year");
    } else {
        $skipCount++;
        auditLog(db(), 'sendCotisationReminders',
            "FAILED for {$m->firstname} {$m->lastname} <{$m->email}> year=$year: $result");
    }
}

auditLog(db(), 'sendCotisationReminders',
    "year=$year sent=$sentCount skipped=$skipCount already=$alreadyCount");
echo json_encode(['ok' => true, 'sent' => $sentCount, 'skipped' => $skipCount, 'already' => $alreadyCount]);
exit;
