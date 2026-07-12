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

  php html/tools/cron.php     exécute toutes les tâches (digest de tâches en retard/à échéance)
  php html/tools/cron.php --help

TXT);
    exit(0);
}

define('APP_ENTRY', true);

require_once __DIR__ . '/../includes/lib/locale.php';
mbLoadLocale(null);
require_once __DIR__ . '/../includes/lib/bootstrap.php';
require_once __DIR__ . '/../classes/suivi_task_class.php';
require_once __DIR__ . '/../includes/lib/mailer.php';

$exitCode = 0;

// ── Job: digest of overdue/soon-due tasks, sent to the team ────────────────
fwrite(STDOUT, "[task-digest] ");
$recipient = trim($appSettings['smtp_reply_to'] ?? '');
if ($recipient === '') {
    fwrite(STDOUT, "skip (app_settings.smtp_reply_to non configuré)\n");
} else {
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
            $textLines[] = "- {$due}{$flag} — {$t->title} ({$who})";
            $htmlRows[]  = '<tr' . ($overdue ? ' style="color:#c0392b"' : '') . '>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($due, ENT_QUOTES, 'UTF-8') . ($overdue ? ' <strong>(en retard)</strong>' : '') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($t->title, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . htmlspecialchars($who, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
        $vars = [
            'org_name'   => $appSettings['org_name'] ?? '',
            'count'      => (string)count($tasks),
            'tasks_text' => implode("\n", $textLines),
            'tasks_html' => '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;width:100%">'
                . '<tr><th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Échéance</th>'
                . '<th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Tâche</th>'
                . '<th style="text-align:left;padding:4px 8px;border-bottom:2px solid #ccc">Membre</th></tr>'
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
