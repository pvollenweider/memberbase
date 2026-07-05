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
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
require_once __DIR__ . '/../lib/mailer.php';

$action = $_REQUEST['action'];

if ($action === 'sendComptaRecap') {
    if (!isManager()) { http_response_code(403); exit; }

    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect = static function (string $q) use ($isHtmx): void {
        $url = $_SERVER['PHP_SELF'] . '?view=comptaRecap&' . $q;
        if ($isHtmx) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
        exit;
    };

    // Load all unnotified compta entries joined with their member
    $rows = $pdo->query(
        "SELECT c.id, c.user_id, c.date, c.libele, c.sum,
                u.firstname, u.lastname, u.email
         FROM compta c
         JOIN users u ON u.id = c.user_id AND u.status = 1
         WHERE c.notified_at IS NULL
         ORDER BY c.user_id, c.date ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $redirect('recapOk=0');
    }

    // Group by user_id
    $byMember = [];
    foreach ($rows as $r) {
        $byMember[$r['user_id']][] = $r;
    }

    $sentCount   = 0;
    $skipCount   = 0;
    $notifiedIds = [];
    $sendDate    = strftime('%d %B %Y'); // e.g. "05 juillet 2026"
    // Fallback for environments without strftime locale support
    if (!$sendDate || $sendDate === '%d %B %Y') {
        $sendDate = date('d.m.Y');
    }

    $contactEmail = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');

    foreach ($byMember as $userId => $entries) {
        $first = $entries[0];

        if (trim($first['email']) === '') {
            $skipCount++;
            // Still mark entries as notified to avoid them piling up forever
            foreach ($entries as $e) { $notifiedIds[] = (int)$e['id']; }
            auditLog($pdo, 'sendComptaRecap', "skip id=$userId (no email) — " . count($entries) . ' entries marked');
            continue;
        }

        // Build plain-text entry block
        $lines = [];
        $total = '0.00';
        foreach ($entries as $e) {
            $d      = $e['date'] ? date('d.m.Y', (int)$e['date']) : '—';
            $label  = $e['libele'] !== '' ? $e['libele'] : '—';
            $amount = number_format((float)$e['sum'], 2, '.', "'");
            $lines[] = $d . '  ' . $label . '  CHF ' . $amount;
            $total = number_format(array_sum(array_column($entries, 'sum')), 2, '.', "'");
        }
        $entriesBlock = implode("\n", $lines);

        $ok = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', [
            'firstname'     => $first['firstname'],
            'lastname'      => $first['lastname'],
            'email'         => $first['email'],
            'entries'       => $entriesBlock,
            'total'         => $total,
            'send_date'     => $sendDate,
            'org_name'      => $appSettings['org_name']      ?? '',
            'org_address'   => $appSettings['org_address']   ?? '',
            'org_city'      => $appSettings['org_city']      ?? '',
            'org_web'       => $appSettings['org_web']       ?? '',
            'contact_email' => $contactEmail,
        ]);

        if ($ok) {
            $sentCount++;
            foreach ($entries as $e) { $notifiedIds[] = (int)$e['id']; }
        }
        // On send failure: leave notified_at NULL so it retries next batch
    }

    // Mark notified entries in one batch UPDATE
    if (!empty($notifiedIds)) {
        $ph = implode(',', array_fill(0, count($notifiedIds), '?'));
        $pdo->prepare("UPDATE compta SET notified_at = NOW() WHERE id IN ($ph)")
            ->execute($notifiedIds);
    }

    $markedCount = count($notifiedIds);
    auditLog($pdo, 'sendComptaRecap', "sent=$sentCount skipped=$skipCount entries_marked=$markedCount");
    $redirect('recapOk=' . $sentCount . '&recapSkip=' . $skipCount);

} elseif ($action === 'markAllComptaNotified') {
    // One-time bulk action (Settings → Santé) to mark all existing entries as
    // notified, preventing recap emails from flooding members with historical data.
    if (!isAdmin()) { http_response_code(403); exit; }

    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect = static function (string $q) use ($isHtmx): void {
        $url = $_SERVER['PHP_SELF'] . '?view=settings&tab=health&' . $q;
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
