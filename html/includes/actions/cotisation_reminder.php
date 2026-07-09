<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Sends cotisation reminder emails to members who paid last year but not this year.
 * Skips members who already received a reminder this year (checked via email_log.tpl_key).
 *
 * Actions:
 *   sendCotisationReminders    — bulk send to all un-reminded lapsed members
 *   sendCotisationReminderOne  — send to a single member (per-row button)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
$_cotiAction = $_REQUEST['action'] ?? '';
if ($_cotiAction !== 'sendCotisationReminders' && $_cotiAction !== 'sendCotisationReminderOne') { return; }
if (!isManager()) { http_response_code(403); exit; }

// Discard any page HTML already buffered — this action returns pure JSON.
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
if ($year <= 0) { $year = (int)date('Y'); }

require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/cotisation.php';

$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
if (empty($cotiTypeIds)) {
    echo json_encode(['ok' => false, 'error' => 'no_coti_types']);
    exit;
}

// ── sendCotisationReminderOne ─────────────────────────────────────────────────
if ($_cotiAction === 'sendCotisationReminderOne') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = $pdo->prepare("SELECT id, firstname, lastname, society, email FROM contact WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }
    if (trim($member->email) === '') { echo json_encode(['ok' => false, 'error' => 'no_email']); exit; }

    $vars   = mbBuildCotiReminderVars($member, $year, $appSettings);
    $result = mbSendTemplate($pdo, $member->email, 'tpl_cotisation_reminder', $vars, $userId);
    if ($result === true) {
        auditLog($pdo, 'sendCotisationReminderOne',
            "sent to {$member->firstname} {$member->lastname} <{$member->email}> year=$year");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => is_string($result) ? $result : 'send_failed']);
    }
    exit;
}

// ── sendCotisationReminders (bulk) ────────────────────────────────────────────
$_noCotiTeam = (int)($appSettings['member_no_coti_team'] ?? 0);
$members     = mbGetLapsedMembers($pdo, $year, $cotiTypeIds, $_noCotiTeam);

if (empty($members)) {
    echo json_encode(['ok' => true, 'sent' => 0, 'skipped' => 0, 'already' => 0]);
    exit;
}

$force      = !empty($_REQUEST['force']);
$memberIds  = array_map(fn($m) => (int)$m->id, $members);
$alreadyMap = $force ? [] : mbGetAlreadyRemindedIds($pdo, $year, $memberIds);

$sentCount    = 0;
$skipCount    = 0;
$alreadyCount = 0;

foreach ($members as $m) {
    if (isset($alreadyMap[(int)$m->id])) {
        $alreadyCount++;
        auditLog($pdo, 'sendCotisationReminders', "skip id={$m->id} (already reminded this year)");
        continue;
    }
    if (trim($m->email) === '') {
        $skipCount++;
        auditLog($pdo, 'sendCotisationReminders', "skip id={$m->id} (no email)");
        continue;
    }

    $vars   = mbBuildCotiReminderVars($m, $year, $appSettings);
    $result = mbSendTemplate($pdo, $m->email, 'tpl_cotisation_reminder', $vars, (int)$m->id);
    if ($result === true) {
        $sentCount++;
        auditLog($pdo, 'sendCotisationReminders',
            "sent to {$m->firstname} {$m->lastname} <{$m->email}> year=$year");
    } else {
        $skipCount++;
        auditLog($pdo, 'sendCotisationReminders',
            "FAILED for {$m->firstname} {$m->lastname} <{$m->email}> year=$year: $result");
    }
}

auditLog($pdo, 'sendCotisationReminders',
    "year=$year sent=$sentCount skipped=$skipCount already=$alreadyCount");
echo json_encode(['ok' => true, 'sent' => $sentCount, 'skipped' => $skipCount, 'already' => $alreadyCount]);
exit;
