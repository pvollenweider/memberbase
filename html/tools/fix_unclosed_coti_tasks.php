<?php
declare(strict_types=1);
/**
 * Rattrapage ponctuel : ferme les tâches "Relance cotisation AAAA impayée"
 * (suivi_task.rule_key = unpaid_coti_current_AAAA) restées ouvertes alors
 * qu'un rappel a déjà été envoyé pour ce membre/année, via
 * sendCotisationReminderOne/sendCotisationReminders.
 *
 * Contexte : avant le fix de septembre 2026, l'envoi depuis la vue "Membres
 * perdus" ne fermait jamais la tâche liée (seul le bouton "Envoyer le
 * rappel" de la vue Tâches le faisait, via un task_id explicite). Ce script
 * rattrape le stock de tâches ouvertes créées avant le fix.
 *
 * L'audit_log historique de sendCotisationReminderOne/sendCotisationReminders
 * ne porte pas subject_user_id (autre bug corrigé au passage dans
 * includes/actions/cotisation_reminder.php) — le rapprochement se fait donc
 * par correspondance sur l'e-mail du membre dans le texte libre `detail`.
 * Ce script est fait pour être jeté une fois le rattrapage prod effectué.
 *
 * Usage (CLI) :
 *   php html/tools/fix_unclosed_coti_tasks.php            liste (dry-run, ne modifie rien)
 *   php html/tools/fix_unclosed_coti_tasks.php --apply     ferme réellement les tâches trouvées
 *   php html/tools/fix_unclosed_coti_tasks.php --help
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

$args  = $argv ?? [];
$apply = in_array('--apply', $args, true);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, <<<TXT
Rattrapage des tâches "Relance cotisation" non fermées malgré un rappel déjà envoyé

  php html/tools/fix_unclosed_coti_tasks.php            liste (dry-run, ne modifie rien)
  php html/tools/fix_unclosed_coti_tasks.php --apply     ferme réellement les tâches trouvées
  php html/tools/fix_unclosed_coti_tasks.php --help      cette aide

TXT);
    exit(0);
}

$repoRoot = dirname(__DIR__, 2); // .../repo (holds conf/ outside webroot)

$confFile = $repoRoot . '/conf/db.php';
if (is_file($confFile)) {
    require_once $confFile;
}
$host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'members');
$pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: 'members');
$name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'members');

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion DB impossible : {$e->getMessage()}\n");
    exit(1);
}

// Open "relance cotisation" tasks, joined to the member for email + name.
$tasks = $pdo->query("
    SELECT t.id AS task_id, t.rule_key, t.created_at AS task_created_at,
           c.id AS user_id, c.firstname, c.lastname, c.email
    FROM suivi_task t
    JOIN contact c ON c.id = t.user_id
    WHERE t.done_at IS NULL AND t.rule_key LIKE 'unpaid_coti_current_%'
    ORDER BY t.id
")->fetchAll();

if (empty($tasks)) {
    fwrite(STDOUT, "Aucune tâche ouverte 'unpaid_coti_current_*' — rien à faire.\n");
    exit(0);
}

$findReminderStmt = $pdo->prepare("
    SELECT id, created_at
    FROM audit_log
    WHERE action IN ('sendCotisationReminderOne', 'sendCotisationReminders')
      AND created_at >= ?
      AND detail LIKE CONCAT('%<', ?, '>%')
      AND detail LIKE CONCAT('%year=', ?, '%')
    ORDER BY created_at DESC
    LIMIT 1
");

$toClose = [];
foreach ($tasks as $t) {
    $year = (int)substr($t['rule_key'], strlen('unpaid_coti_current_'));
    $email = trim((string)$t['email']);
    if ($email === '') {
        continue; // no email → mbSendTemplateWithAttachment could never have succeeded for this member
    }
    $findReminderStmt->execute([$t['task_created_at'], $email, $year]);
    $hit = $findReminderStmt->fetch();
    if ($hit) {
        $toClose[] = $t + ['year' => $year, 'reminder_sent_at' => $hit['created_at']];
    }
}

if (empty($toClose)) {
    fwrite(STDOUT, "Aucune tâche ouverte ne correspond à un rappel déjà envoyé — rien à faire.\n");
    exit(0);
}

fwrite(STDOUT, count($toClose) . " tâche(s) à fermer (rappel déjà envoyé après création de la tâche) :\n");
foreach ($toClose as $t) {
    fwrite(STDOUT, sprintf(
        "  task #%d | %s %s <%s> | année %d | rappel envoyé le %s\n",
        $t['task_id'], $t['firstname'], $t['lastname'], $t['email'], $t['year'], $t['reminder_sent_at']
    ));
}

if (!$apply) {
    fwrite(STDOUT, "\nDry-run — relancer avec --apply pour fermer ces tâches.\n");
    exit(0);
}

$closeStmt = $pdo->prepare("UPDATE suivi_task SET done_at = ? WHERE id = ? AND done_at IS NULL");
$auditStmt = $pdo->prepare(
    "INSERT INTO audit_log (action, detail, subject_user_id) VALUES ('closeTask', ?, ?)"
);
$now = date('Y-m-d H:i:s');
$closed = 0;
foreach ($toClose as $t) {
    $closeStmt->execute([$now, $t['task_id']]);
    if ($closeStmt->rowCount() > 0) {
        $auditStmt->execute([
            "id={$t['task_id']} | Relance cotisation {$t['year']} impayée (rétroactif, fix_unclosed_coti_tasks.php — rappel envoyé le {$t['reminder_sent_at']})",
            $t['user_id'],
        ]);
        $closed++;
    }
}

fwrite(STDOUT, "\n$closed tâche(s) fermée(s).\n");
