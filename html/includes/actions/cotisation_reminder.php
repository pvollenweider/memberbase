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

$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
if (empty($cotiTypeIds)) {
    echo json_encode(['ok' => false, 'error' => 'no_coti_types']);
    exit;
}

$ph           = implode(',', array_fill(0, count($cotiTypeIds), '?'));
$_noCotiTeam  = (int)($appSettings['member_no_coti_team'] ?? 0);
$noCotiClause = $_noCotiTeam > 0
    ? "AND NOT EXISTS (SELECT 1 FROM user_properties WHERE user_id=u.id AND parameter='team_$_noCotiTeam' AND value='true')"
    : '';

/** Build template vars for one member. */
function _cotiReminderVars(object $m, int $year, array $appSettings): array
{
    $contactEmail    = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
    $membershipUrl   = $appSettings['membership_url'] ?? '';
    $membershipBlock = $membershipUrl !== ''
        ? '<p style="margin:16px 0"><a href="' . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#1a5276">'
          . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        : '';
    $salutation = mbBuildSalutation($m->firstname ?? '', $m->lastname ?? '', $m->society ?? '');
    return array_merge($salutation, [
        'firstname'            => $m->firstname ?? '',
        'lastname'             => $m->lastname  ?? '',
        'email'                => $m->email,
        'year'                 => (string)$year,
        'membership_url'       => $membershipUrl,
        'membership_url_block' => $membershipBlock,
        'org_name'             => $appSettings['org_name']    ?? '',
        'org_address'          => $appSettings['org_address'] ?? '',
        'org_city'             => $appSettings['org_city']    ?? '',
        'org_web'              => $appSettings['org_web']     ?? '',
        'contact_email'        => $contactEmail,
    ]);
}

// ── sendCotisationReminderOne ─────────────────────────────────────────────────
if ($_cotiAction === 'sendCotisationReminderOne') {
    $userId = (int)($_REQUEST['user_id'] ?? 0);
    if ($userId <= 0) { echo json_encode(['ok' => false, 'error' => 'missing_user_id']); exit; }

    $m = $pdo->prepare("SELECT id, firstname, lastname, society, email FROM users WHERE id = ? AND status = 1");
    $m->execute([$userId]);
    $member = $m->fetch(PDO::FETCH_OBJ);
    if (!$member) { echo json_encode(['ok' => false, 'error' => 'not_found']); exit; }
    if (trim($member->email) === '') { echo json_encode(['ok' => false, 'error' => 'no_email']); exit; }

    $vars   = _cotiReminderVars($member, $year, $appSettings);
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
$stmt = $pdo->prepare("
    SELECT u.id, u.firstname, u.lastname, u.society, u.email
    FROM users u
    WHERE u.status = 1
      $noCotiClause
      AND EXISTS (
          SELECT 1 FROM compta c
          WHERE c.user_id = u.id AND c.type_id IN ($ph)
            AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
      )
      AND NOT EXISTS (
          SELECT 1 FROM compta c
          WHERE c.user_id = u.id AND c.type_id IN ($ph)
            AND COALESCE(c.cotisation_year, YEAR(FROM_UNIXTIME(c.date))) = ?
      )
    ORDER BY u.lastname, u.firstname, u.society
");
$params = array_merge(
    array_values($cotiTypeIds), [$year - 1],
    array_values($cotiTypeIds), [$year]
);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_OBJ);

if (empty($members)) {
    echo json_encode(['ok' => true, 'sent' => 0, 'skipped' => 0, 'already' => 0]);
    exit;
}

// Fetch members who already received a reminder this year from email_log.
$memberIds  = array_map(fn($m) => (int)$m->id, $members);
$phIds      = implode(',', array_fill(0, count($memberIds), '?'));
$alreadyMap = [];
try {
    $alreadySent = $pdo->prepare(
        "SELECT user_id, MAX(created_at) AS sent_at
         FROM email_log
         WHERE tpl_key = 'tpl_cotisation_reminder'
           AND YEAR(created_at) = ?
           AND user_id IN ($phIds)
         GROUP BY user_id"
    );
    $alreadySent->execute(array_merge([$year], $memberIds));
    foreach ($alreadySent->fetchAll(PDO::FETCH_OBJ) as $row) {
        $alreadyMap[(int)$row->user_id] = $row->sent_at;
    }
} catch (\Throwable) {}

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

    $vars   = _cotiReminderVars($m, $year, $appSettings);
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
