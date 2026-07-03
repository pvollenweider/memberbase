<?php
declare(strict_types=1);
/**
 * Runner de migrations de base de données — versionné, rejouable, traçé.
 *
 * Usage (CLI) :
 *   php html/tools/migrate.php            applique les migrations en attente
 *   php html/tools/migrate.php --status   liste appliquées / en attente
 *   php html/tools/migrate.php --baseline marque TOUTES les migrations comme
 *                                         appliquées sans les exécuter
 *                                         (fresh install : le schéma de base
 *                                         est déjà à jour)
 *   php html/tools/migrate.php --help
 *
 * Les fichiers de migration vivent dans `html/migrations/` (sous le webroot,
 * pour être déployés avec l'app ; accès HTTP refusé par un .htaccess), nommés
 * `NNNN_description.sql` et appliqués dans l'ordre du nom.
 * L'état est suivi dans la table `schema_migrations`.
 *
 * ⚠️ MySQL/MariaDB valide implicitement le DDL (CREATE/ALTER) : une migration
 * DDL ne peut PAS être annulée par un ROLLBACK. Faire une sauvegarde avant de
 * migrer en production. La transaction ci-dessous protège les migrations DML.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute uniquement en ligne de commande.\n");
}

$args = $argv ?? [];
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, <<<TXT
Migrations MemberBase

  php html/tools/migrate.php            applique les migrations en attente
  php html/tools/migrate.php --status   affiche l'état (appliquées / en attente)
  php html/tools/migrate.php --baseline marque tout comme appliqué (fresh install)
  php html/tools/migrate.php --help     cette aide

TXT);
    exit(0);
}

$repoRoot      = dirname(__DIR__, 2);            // .../repo (holds conf/ outside webroot)
$migrationsDir = dirname(__DIR__) . '/migrations'; // html/migrations (ships with the webroot)

// --- Résolution de la config DB (même source que bootstrap.php, sans effets de bord) ---
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

// --- Shared runner logic (also used by the web admin) ---
// Locale is needed because migrations.php resolves its user-visible strings via $GLOBAL.
require_once __DIR__ . '/../locales/resources_fr.php';
require_once __DIR__ . '/../includes/lib/migrations.php';

mbEnsureMigrationsTable($pdo);
$appliedRows = mbAppliedMigrations($pdo); // version => checksum
$all         = mbAllMigrations();         // version => filepath
$pending     = mbPendingMigrations($pdo);

// --- Mode --status ---
if (in_array('--status', $args, true)) {
    fwrite(STDOUT, "Migrations (" . count($all) . " au total) :\n");
    $drift = 0;
    foreach ($all as $version => $file) {
        if (!isset($appliedRows[$version])) {
            fwrite(STDOUT, "  [ ] EN ATTENTE  $version\n");
            continue;
        }
        // Applied: flag drift if the recorded checksum no longer matches the file.
        $stored = $appliedRows[$version] ?? '';
        if ($stored !== '' && $stored !== mbMigrationChecksum($file)) {
            fwrite(STDOUT, "  [!] DÉRIVE     $version  (le fichier a changé après application)\n");
            $drift++;
        } else {
            fwrite(STDOUT, "  [x] appliquée   $version\n");
        }
    }
    if (!$all) { fwrite(STDOUT, "  (aucun fichier dans migrations/)\n"); }
    if ($drift) { fwrite(STDOUT, "\n⚠️ $drift migration(s) en DÉRIVE : un fichier déjà appliqué a été modifié.\n"); }
    exit($drift ? 2 : 0);
}

// --- Mode --baseline : marque tout comme appliqué sans exécuter ---
if (in_array('--baseline', $args, true)) {
    $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, applied_at, checksum) VALUES (?, ?, ?)");
    $n = 0;
    foreach ($all as $version => $file) {
        if (!isset($appliedRows[$version])) { $ins->execute([$version, time(), mbMigrationChecksum($file)]); $n++; }
    }
    fwrite(STDOUT, "Baseline : $n migration(s) marquée(s) comme appliquée(s).\n");
    exit(0);
}

// --- Application des migrations en attente ---
if (!$pending) {
    fwrite(STDOUT, "Base à jour — aucune migration en attente.\n");
    exit(0);
}

$res = mbRunPendingMigrations($pdo, static fn(string $m) => fwrite(STDOUT, $m));
if ($res['error'] !== null) {
    fwrite(STDERR, "  {$res['error']}\n");
    fwrite(STDERR, "Migration {$res['failed']} non enregistrée. Corrigez puis relancez.\n");
    fwrite(STDERR, "⚠️ Si la migration contenait du DDL (ALTER/CREATE), il a pu être partiellement validé (MySQL auto-commit le DDL) : vérifiez l'état / restaurez depuis une sauvegarde.\n");
    exit(1);
}
fwrite(STDOUT, count($res['applied']) . " migration(s) appliquée(s).\n");
exit(0);
