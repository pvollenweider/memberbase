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

/**
 * Load unnotified entries for a single user_id (or all users if null).
 * Returns entries grouped by user_id.
 */
function _recapLoadEntries(PDO $pdo, ?int $filterUserId = null): array
{
    $where = 'c.notified_at IS NULL AND c.sum <> 0';
    $params = [];
    if ($filterUserId !== null) {
        $where .= ' AND c.user_id = ?';
        $params[] = $filterUserId;
    }
    $stmt = $pdo->prepare(
        "SELECT c.id, c.user_id, c.date, c.libele, c.sum,
                u.firstname, u.lastname, u.email,
                COALESCE(ct.label, '') AS type_label
         FROM compta c
         JOIN users u ON u.id = c.user_id AND u.status = 1
         LEFT JOIN compta_type ct ON ct.id = c.type_id
         WHERE $where
         ORDER BY c.user_id, c.date ASC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byMember = [];
    foreach ($rows as $r) {
        $byMember[$r['user_id']][] = $r;
    }
    return $byMember;
}

/**
 * Build the template variable array and rendered entry blocks for one member.
 * Returns [vars array, entry ids array].
 */
function _recapBuildVars(array $entries, array $appSettings, array $GLOBAL): array
{
    $lastBatchRaw = null; // caller may override $sinceLine if needed
    $sendDate = strftime('%d %B %Y');
    if (!$sendDate || $sendDate === '%d %B %Y') $sendDate = date('d.m.Y');

    $lines    = [];
    $htmlRows = '';
    $total    = '0.00';
    $ids      = [];
    $odd      = true;
    foreach ($entries as $e) {
        $ids[]     = (int)$e['id'];
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
                   .   $descHtml . '</td>'
                   . '<td style="border:1px solid #dde3ea;padding:8px;font-size:14px;text-align:right">CHF ' . $amount . '</td>'
                   . '</tr>';
        $odd = !$odd;
    }
    $total = number_format(array_sum(array_column($entries, 'sum')), 2, '.', "'");

    $entriesHtml = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;font-size:14px">'
                 . '<tr style="background:#1a5276;color:#ffffff">'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Date</th>'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:left">Type / Description</th>'
                 . '<th style="border:1px solid #154360;padding:8px;font-weight:600;text-align:right">Montant</th>'
                 . '</tr>' . $htmlRows . '</table>';

    $contactEmail = $appSettings['smtp_reply_to'] ?? ($appSettings['smtp_from_email'] ?? '');
    $first = $entries[0];

    $vars = [
        'firstname'    => $first['firstname'],
        'lastname'     => $first['lastname'],
        'email'        => $first['email'],
        'entries'      => implode("\n", $lines),
        'entries_html' => $entriesHtml,
        'total'        => $total,
        'send_date'    => $sendDate,
        'since_line'   => '', // filled by caller
        'org_name'     => $appSettings['org_name']      ?? '',
        'org_address'  => $appSettings['org_address']   ?? '',
        'org_city'     => $appSettings['org_city']      ?? '',
        'org_web'      => $appSettings['org_web']       ?? '',
        'contact_email'=> $contactEmail,
    ];
    return [$vars, $ids, $total];
}

if ($action === 'sendComptaRecap') {
    if (!isManager()) { http_response_code(403); exit; }

    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);
    $redirect = static function (string $q) use ($isHtmx): void {
        $url = $_SERVER['PHP_SELF'] . '?view=comptaRecap&' . $q;
        if ($isHtmx) { header('HX-Location: ' . $url); } else { header('Location: ' . $url); }
        exit;
    };

    $byMember = _recapLoadEntries($pdo);
    if (empty($byMember)) { $redirect('recapOk=0'); }

    $lastBatchRaw = $pdo->query("SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL")->fetchColumn();
    $sinceLine    = $lastBatchRaw
        ? sprintf($GLOBAL['comptaRecapSinceLastBatch'], date('d.m.Y', strtotime($lastBatchRaw)))
        : $GLOBAL['comptaRecapSinceFirst'];

    $sentCount = 0; $skipCount = 0; $notifiedIds = [];

    foreach ($byMember as $userId => $entries) {
        $first = $entries[0];
        if (trim($first['email']) === '') {
            $skipCount++;
            foreach ($entries as $e) { $notifiedIds[] = (int)$e['id']; }
            auditLog($pdo, 'sendComptaRecap', "skip id=$userId (no email) — " . count($entries) . ' entries marked');
            continue;
        }
        [$vars, $ids, $total] = _recapBuildVars($entries, $appSettings, $GLOBAL);
        $vars['since_line']   = $sinceLine;
        $ok = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', $vars, (int)$userId);
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
    // Returns JSON {subject, html, text} for a single member's pending recap email.
    if (!isManager()) { http_response_code(403); exit; }
    error_log('[previewComptaRecap] reached, user_id=' . ($_REQUEST['user_id'] ?? '?'));
    header('Content-Type: application/json; charset=utf-8');
    $userId   = (int)($_REQUEST['user_id'] ?? 0);
    $byMember = _recapLoadEntries($pdo, $userId);
    if (empty($byMember)) { echo json_encode(['ok' => false, 'error' => 'no_entries']); exit; }
    $entries = reset($byMember);

    $lastBatchRaw = $pdo->query("SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL")->fetchColumn();
    $sinceLine    = $lastBatchRaw
        ? sprintf($GLOBAL['comptaRecapSinceLastBatch'], date('d.m.Y', strtotime($lastBatchRaw)))
        : $GLOBAL['comptaRecapSinceFirst'];

    [$vars, , ] = _recapBuildVars($entries, $appSettings, $GLOBAL);
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
    header('Content-Type: application/json; charset=utf-8');
    $userId   = (int)($_REQUEST['user_id'] ?? 0);
    $byMember = _recapLoadEntries($pdo, $userId);
    if (empty($byMember)) { echo json_encode(['ok' => false, 'error' => 'no_entries']); exit; }
    $entries = reset($byMember);
    $first   = $entries[0];

    if (trim($first['email']) === '') {
        echo json_encode(['ok' => false, 'error' => 'no_email']);
        exit;
    }

    $lastBatchRaw = $pdo->query("SELECT MAX(notified_at) FROM compta WHERE notified_at IS NOT NULL")->fetchColumn();
    $sinceLine    = $lastBatchRaw
        ? sprintf($GLOBAL['comptaRecapSinceLastBatch'], date('d.m.Y', strtotime($lastBatchRaw)))
        : $GLOBAL['comptaRecapSinceFirst'];

    [$vars, $ids, $total] = _recapBuildVars($entries, $appSettings, $GLOBAL);
    $vars['since_line'] = $sinceLine;
    $ok = mbSendTemplate($pdo, $first['email'], 'tpl_compta_recap', $vars, $userId);

    if ($ok) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE compta SET notified_at = NOW() WHERE id IN ($ph)")->execute($ids);
        auditLog($pdo, 'sendComptaRecapOne',
            "sent to {$first['firstname']} {$first['lastname']} <{$first['email']}> — "
            . count($entries) . ' entr(ies), CHF ' . $total);
    }
    echo json_encode(['ok' => $ok]);
    exit;

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
