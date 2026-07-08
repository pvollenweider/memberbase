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

    // Load all unnotified compta entries joined with their member and type label
    $rows = $pdo->query(
        "SELECT c.id, c.user_id, c.date, c.libele, c.sum,
                u.firstname, u.lastname, u.email,
                COALESCE(ct.label, '') AS type_label
         FROM compta c
         JOIN users u ON u.id = c.user_id AND u.status = 1
         LEFT JOIN compta_type ct ON ct.id = c.type_id
         WHERE c.notified_at IS NULL
           AND c.sum <> 0
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

    // Last batch date — used to tell the member "since your last recap of DD.MM.YYYY"
    $lastBatchRaw = $pdo->query(
        "SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL"
    )->fetchColumn();
    $sinceLine = $lastBatchRaw
        ? sprintf($GLOBAL['comptaRecapSinceLastBatch'], date('d.m.Y', strtotime($lastBatchRaw)))
        : $GLOBAL['comptaRecapSinceFirst'];

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

        // Build plain-text and HTML entry blocks
        $lines    = [];
        $htmlRows = '';
        $total    = '0.00';
        $odd      = true;
        foreach ($entries as $e) {
            $d         = $e['date'] ? date('d.m.Y', (int)$e['date']) : '—';
            $typeLabel = $e['type_label'] !== '' ? $e['type_label'] : '—';
            $desc      = $e['libele'] !== '' ? $e['libele'] : $typeLabel;
            $descHtml  = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            $typeHtml  = htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8');
            $amount    = number_format((float)$e['sum'], 2, '.', "'");
            $lines[]   = $d . '  [' . $typeLabel . ']  ' . $desc . '  CHF ' . $amount;
            $bg        = $odd ? '#f7fafd' : '#ffffff';
            $htmlRows .= '<tr style="background:' . $bg . '">'
                       . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px">' . $d . '</td>'
                       . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px">'
                       .   '<span style="display:inline-block;background:#e8f0fe;color:#1a5276;border-radius:3px;padding:1px 6px;font-size:12px;margin-right:6px">' . $typeHtml . '</span>'
                       .   $descHtml
                       . '</td>'
                       . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px;text-align:right">CHF ' . $amount . '</td>'
                       . '</tr>';
            $odd = !$odd;
            $total = number_format(array_sum(array_column($entries, 'sum')), 2, '.', "'");
        }
        $entriesBlock = implode("\n", $lines);
        $entriesHtml  = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px">'
                      . '<tr style="background:#1a5276;color:#ffffff">'
                      . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Date</th>'
                      . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Type / Description</th>'
                      . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:right">Montant</th>'
                      . '</tr>'
                      . $htmlRows
                      . '</table>';

        $ok = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', [
            'firstname'     => $first['firstname'],
            'lastname'      => $first['lastname'],
            'email'         => $first['email'],
            'entries'       => $entriesBlock,
            'entries_html'  => $entriesHtml,
            'total'         => $total,
            'send_date'     => $sendDate,
            'since_line'    => $sinceLine,
            'org_name'      => $appSettings['org_name']      ?? '',
            'org_address'   => $appSettings['org_address']   ?? '',
            'org_city'      => $appSettings['org_city']      ?? '',
            'org_web'       => $appSettings['org_web']       ?? '',
            'contact_email' => $contactEmail,
        ], (int)$userId);

        if ($ok) {
            $sentCount++;
            foreach ($entries as $e) { $notifiedIds[] = (int)$e['id']; }
            auditLog($pdo, 'sendComptaRecap',
                "sent to {$first['firstname']} {$first['lastname']} <{$first['email']}> — "
                . count($entries) . ' entr(ies), CHF ' . $total);
        } else {
            auditLog($pdo, 'sendComptaRecap',
                "FAILED for {$first['firstname']} {$first['lastname']} <{$first['email']}>");
        }
        // On send failure: leave notified_at NULL so it retries next batch
    }

    // Mark notified entries in one batch UPDATE.
    // Zero-sum entries are excluded from emails but must also be marked to keep
    // the pending count accurate — mark them unconditionally alongside sent entries.
    if (!empty($notifiedIds)) {
        $ph = implode(',', array_fill(0, count($notifiedIds), '?'));
        $pdo->prepare("UPDATE compta SET notified_at = NOW() WHERE id IN ($ph)")
            ->execute($notifiedIds);
    }
    $pdo->exec("UPDATE compta SET notified_at = NOW() WHERE notified_at IS NULL AND sum = 0");

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
