<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Sends cotisation reminder emails to members who paid last year but not this year.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
if (($_REQUEST['action'] ?? '') !== 'sendCotisationReminders') { return; }
if (!isManager()) { http_response_code(403); exit; }

// Discard any page HTML already buffered — this action returns pure JSON.
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
if ($year <= 0) { $year = (int)date('Y'); }

require_once __DIR__ . '/../lib/mailer.php';

// Rebuild the lapsed-members query (same logic as members_lapsed.php)
$cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
if (empty($cotiTypeIds)) {
    echo json_encode(['ok' => false, 'error' => 'no_coti_types']);
    exit;
}

$ph          = implode(',', array_fill(0, count($cotiTypeIds), '?'));
$_noCotiTeam = (int)($appSettings['member_no_coti_team'] ?? 0);
$noCotiClause = $_noCotiTeam > 0
    ? "AND NOT EXISTS (SELECT 1 FROM user_properties WHERE user_id=u.id AND parameter='team_$_noCotiTeam' AND value='true')"
    : '';

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
    echo json_encode(['ok' => true, 'sent' => 0, 'skipped' => 0]);
    exit;
}

$contactEmail   = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
$membershipUrl  = $appSettings['membership_url'] ?? '';
$membershipBlock = $membershipUrl !== ''
    ? '<p style="margin:16px 0"><a href="' . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#1a5276">'
      . htmlspecialchars($membershipUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
    : '';

$sentCount = 0;
$skipCount = 0;

foreach ($members as $m) {
    if (trim($m->email) === '') {
        $skipCount++;
        auditLog($pdo, 'sendCotisationReminders', "skip id={$m->id} (no email)");
        continue;
    }

    $salutation = mbBuildSalutation($m->firstname ?? '', $m->lastname ?? '', $m->society ?? '');
    $vars = array_merge($salutation, [
        'firstname'            => $m->firstname ?? '',
        'lastname'             => $m->lastname  ?? '',
        'email'                => $m->email,
        'year'                 => (string)$year,
        'membership_url'       => $membershipUrl,
        'membership_url_block' => $membershipBlock,
        'org_name'             => $appSettings['org_name']      ?? '',
        'org_address'          => $appSettings['org_address']   ?? '',
        'org_city'             => $appSettings['org_city']      ?? '',
        'org_web'              => $appSettings['org_web']       ?? '',
        'contact_email'        => $contactEmail,
    ]);

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

auditLog($pdo, 'sendCotisationReminders', "year=$year sent=$sentCount skipped=$skipCount");
echo json_encode(['ok' => true, 'sent' => $sentCount, 'skipped' => $skipCount]);
exit;
