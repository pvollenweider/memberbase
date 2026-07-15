<?php
declare(strict_types=1);
/**
 * Scheduled jobs entry point (issue #150). Meant to be called from the
 * system crontab, e.g. once a day:
 *
 *   0 7 * * * php /path/to/html/tools/cron.php >> /var/log/memberbase-cron.log 2>&1
 *
 * Jobs run unconditionally each invocation — none of them are so frequent
 * that they need their own schedule yet. Split into separate cron lines
 * later if that changes.
 *
 * Usage (CLI) :
 *   php html/tools/cron.php            runs all jobs
 *   php html/tools/cron.php --help
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

$args = $argv ?? [];
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, <<<TXT
Tâches planifiées MemberBase

  php html/tools/cron.php     génère les tâches auto (cotisation impayée,
                              notification de versement) puis envoie le
                              digest de tâches en retard/à échéance
  php html/tools/cron.php --help

TXT);
    exit(0);
}

define('APP_ENTRY', true);

require_once __DIR__ . '/../includes/lib/locale.php';
mbLoadLocale(null);
require_once __DIR__ . '/../includes/lib/bootstrap.php';
require_once __DIR__ . '/../classes/member_filter_class.php';
require_once __DIR__ . '/../classes/suivi_task_class.php';
require_once __DIR__ . '/../includes/lib/mailer.php';

$exitCode = 0;

// Job: auto-generate reminder tasks (unpaid cotisation, pending payment
// notifications) — same logic as the admin "Générer" buttons
// (includes/actions/suivi_tasks.php), called directly here since cron has no
// HTTP session/CSRF to go through. Runs before the digest below so anything
// just created is included in the same email.
$_cronYear = (int)date('Y');
$_cotiGen  = SuiviTask::generateUnpaidCotiTasks($_cronYear, $appSettings, null);
fwrite(STDOUT, "[unpaid-coti-gen] créées: {$_cotiGen['created']} | closes: {$_cotiGen['closed']}\n");
if ($_cotiGen['created'] > 0 || $_cotiGen['closed'] > 0) {
    auditLog($pdo, 'generateUnpaidCotiTasks', "année: $_cronYear | créées: {$_cotiGen['created']} | closes (résolues ailleurs): {$_cotiGen['closed']} (cron)");
}
$_recapGen = SuiviTask::generateComptaRecapTasks($_cronYear, null);
fwrite(STDOUT, "[compta-recap-gen] créées: {$_recapGen['created']} | closes: {$_recapGen['closed']}\n");
if ($_recapGen['created'] > 0 || $_recapGen['closed'] > 0) {
    auditLog($pdo, 'generateComptaRecapTasks', "année: $_cronYear | créées: {$_recapGen['created']} | closes (résolues ailleurs): {$_recapGen['closed']} (cron)");
}

// ── Job: digest of overdue/soon-due tasks, sent to the team ────────────────
fwrite(STDOUT, "[task-digest] ");
$recipient = trim($appSettings['smtp_reply_to'] ?? '');
if ($recipient === '') {
    fwrite(STDOUT, "skip (app_settings.smtp_reply_to non configuré)\n");
} else {
    // Base URL for clickable action links in the digest — optional (Réglages →
    // Email → URL publique de l'application). Without it, the digest stays
    // plain text/HTML with no links rather than emitting broken relative URLs
    // (this script runs in CLI, there's no HTTP host to infer one from).
    $baseUrl = rtrim(trim($appSettings['app_base_url'] ?? ''), '/');

    $tasks = SuiviTask::dueSoonOrOverdue(3);
    if (!$tasks) {
        fwrite(STDOUT, "rien à signaler (0 tâche en retard ou à échéance proche)\n");
    } else {
        $today = date('Y-m-d');
        $textLines = [];
        $htmlRows  = [];
        foreach ($tasks as $t) {
            $due     = date('d.m.Y', strtotime($t->due_date));
            $overdue = $t->due_date < $today;
            $who     = $t->firstname !== null
                ? trim(($t->society ? $t->society . ' ' : '') . $t->lastname . ' ' . $t->firstname)
                : 'Tâche générale';
            $flag = $overdue ? ' [EN RETARD]' : '';
            $taskUrl = $baseUrl !== ''
                ? $baseUrl . '/index.php?view=' . ($t->user_id ? 'memberTasks&userid=' . (int)$t->user_id : 'tasks')
                : null;
            $textLines[] = "- {$due}{$flag} — {$t->title} ({$who})" . ($taskUrl ? " → {$taskUrl}" : '');
            $actionCell = $taskUrl
                ? '<a href="' . htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:3px 10px;background:#2563eb;color:#fff;border-radius:4px;text-decoration:none;font-size:12px">Traiter</a>'
                : '';
            $htmlRows[]  = '<tr' . ($overdue ? ' style="color:#c0392b"' : '') . '>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($due, ENT_QUOTES, 'UTF-8') . ($overdue ? ' <strong>(en retard)</strong>' : '') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($t->title, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . $actionCell . '</td>'
                . '</tr>';
        }
        $vars = [
            'org_name'   => $appSettings['org_name'] ?? '',
            'count'      => (string)count($tasks),
            'tasks_text' => implode("\n", $textLines),
            'tasks_html' => '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;width:100%">'
                . '<tr><th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Échéance</th>'
                . '<th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Tâche</th>'
                . '<th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Membre</th>'
                . '<th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc"></th></tr>'
                . implode('', $htmlRows) . '</table>',
        ];
        $result = mbSendTemplate($pdo, $recipient, 'tpl_task_digest', $vars);
        if ($result === true) {
            auditLog($pdo, 'cronTaskDigest', 'sent to ' . $recipient . ' | ' . count($tasks) . ' tâche(s)');
            fwrite(STDOUT, "envoyé à {$recipient} (" . count($tasks) . " tâche(s))\n");
        } else {
            auditLog($pdo, 'cronTaskDigest', 'FAILED to ' . $recipient . ': ' . (is_string($result) ? $result : 'unknown error'));
            fwrite(STDERR, "ÉCHEC d'envoi à {$recipient} : " . (is_string($result) ? $result : 'erreur inconnue') . "\n");
            $exitCode = 1;
        }
    }
}

exit($exitCode);
