<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler: send compta recap emails to members with unnotified entries.
 *
 * Groups compta rows where notified_at IS NULL by member, sends one email per
 * member (who has an email address), then marks the included rows as notified.
 *
 * Future: this action will be triggerable as a scheduled task (issue #117).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/compta_recap.php';

$action = $_REQUEST['action'];

if ($action === 'sendComptaRecap') {
    if (!isManager()) { http_response_code(403); exit; }

    $recapYear = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $isHtmx    = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect  = static function (string $q) use ($isHtmx, $recapYear): void {
        $url = appUrl() . '?view=comptaRecap&year=' . $recapYear . '&' . $q;
        if ($isHtmx) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
        exit;
    };

    $byMember = mbRecapLoadEntries($pdo, null, $recapYear);
    if (empty($byMember)) { $redirect('recapOk=0'); }

    $sinceLine = mbRecapSinceLine($pdo, $recapYear, false);

    $sentCount = 0; $skipCount = 0; $notifiedIds = [];

    foreach ($byMember as $userId => $entries) {
        $first = $entries[0];
        if (trim($first['email']) === '') {
            $skipCount++;
            foreach ($entries as $e) { $notifiedIds[] = (int)$e['id']; }
            auditLog($pdo, 'sendComptaRecap', "skip id=$userId (no email) — " . count($entries) . ' entries marked');
            continue;
        }
        [$vars, $ids, $total] = mbRecapBuildVars($entries, $appSettings);
        $vars['since_line']   = $sinceLine;
        $ok = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', $vars, (int)$userId) === true;
        if ($ok) {
            $sentCount++;
            $notifiedIds = array_merge($notifiedIds, $ids);
            auditLog($pdo, 'sendComptaRecap',
                "sent to {$first['firstname']} {$first['lastname']} <{$first['email']}> — "
                . count($entries) . ' entr(ies), CHF ' . $total);
        } else {
            auditLog($pdo, 'sendComptaRecap',
                "FAILED for {$first['firstname']} {$first['lastname']} <{$first['email']}>");
        }
    }

    if (!empty($notifiedIds)) {
        $ph = implode(',', array_fill(0, count($notifiedIds), '?'));
        $pdo->prepare("UPDATE compta SET notified_at = NOW() WHERE id IN ($ph)")->execute($notifiedIds);
    }
    $pdo->exec("UPDATE compta SET notified_at = NOW() WHERE notified_at IS NULL AND sum = 0");
    auditLog($pdo, 'sendComptaRecap', "sent=$sentCount skipped=$skipCount entries_marked=" . count($notifiedIds));
    $redirect('recapOk=' . $sentCount . '&recapSkip=' . $skipCount);

} elseif ($action === 'previewComptaRecap') {
    // Returns JSON {subject, html, text} for a single member's recap email.
    if (!isManager()) { http_response_code(403); exit; }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $userId    = (int)($_REQUEST['user_id'] ?? 0);
    $recapYear = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $force     = !empty($_REQUEST['force']);
    $byMember  = mbRecapLoadEntries($pdo, $userId, $recapYear, $force);
    if (empty($byMember)) { echo json_encode(['ok' => false, 'error' => 'no_entries']); exit; }
    $entries = reset($byMember);

    $sinceLine = mbRecapSinceLine($pdo, $recapYear, $force);

    [$vars, , ] = mbRecapBuildVars($entries, $appSettings);
    $vars['since_line'] = $sinceLine;

    $tpl      = mbGetTemplate($pdo, 'tpl_compta_recap');
    $subject  = mbRenderTemplate($tpl->subject,   $vars);
    $bodyText = mbRenderTemplate($tpl->body_text, $vars);
    $bodyHtml = isset($tpl->body_html) ? mbRenderTemplate($tpl->body_html, $vars) : '';
    echo json_encode(['ok' => true, 'subject' => $subject, 'html' => $bodyHtml, 'text' => $bodyText]);
    exit;

} elseif ($action === 'sendComptaRecapOne') {
    // Sends recap email to a single member and marks their entries as notified.
    if (!isManager()) { http_response_code(403); exit; }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $userId    = (int)($_REQUEST['user_id'] ?? 0);
    $recapYear = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');
    $force     = !empty($_REQUEST['force']);
    $byMember  = mbRecapLoadEntries($pdo, $userId, $recapYear, $force);
    if (empty($byMember)) { echo json_encode(['ok' => false, 'error' => 'no_entries']); exit; }
    $entries = reset($byMember);
    $first   = $entries[0];

    if (trim($first['email']) === '') {
        echo json_encode(['ok' => false, 'error' => 'no_email']);
        exit;
    }

    $sinceLine = mbRecapSinceLine($pdo, $recapYear, $force);

    [$vars, $ids, $total] = mbRecapBuildVars($entries, $appSettings);
    $vars['since_line'] = $sinceLine;
    $result = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', $vars, $userId);
    $ok     = $result === true;

    if ($ok) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE compta SET notified_at = NOW() WHERE id IN ($ph)")->execute($ids);
        auditLog($pdo, 'sendComptaRecapOne',
            "sent to {$first['firstname']} {$first['lastname']} <{$first['email']}> (year=$recapYear force=" . ($force ? '1' : '0') . ") — "
            . count($entries) . ' entr(ies), CHF ' . $total);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => is_string($result) ? $result : 'send_failed']);
    }
    exit;

} elseif ($action === 'markAllComptaNotified') {
    // One-time bulk action (Settings → Santé) to mark all existing entries as
    // notified, preventing recap emails from flooding members with historical data.
    if (!isAdmin()) { http_response_code(403); exit; }

    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect = static function (string $q) use ($isHtmx): void {
        $url = appUrl() . '?view=settings&tab=health&' . $q;
        if ($isHtmx) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
        exit;
    };

    if (empty($_REQUEST['confirm_bulk'])) {
        $redirect('bulkComptaErr=noConfirm');
    }

    $pdo->exec("UPDATE compta SET notified_at = NOW() WHERE notified_at IS NULL");
    $n = (int)$pdo->query("SELECT COUNT(*) FROM compta WHERE notified_at IS NOT NULL")->fetchColumn();
    auditLog($pdo, 'markAllComptaNotified', "marked $n entries");
    $redirect('bulkComptaOk=' . $n);
}
