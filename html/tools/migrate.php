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
 * Les fichiers de migration vivent dans `migrations/` (racine du dépôt, hors
 * webroot), nommés `NNNN_description.sql` et appliqués dans l'ordre du nom.
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

$repoRoot      = dirname(__DIR__, 2);            // .../repo
$migrationsDir = $repoRoot . '/migrations';

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

// --- Table de suivi ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version`    VARCHAR(255) NOT NULL,
        `applied_at` INT(11)      NOT NULL DEFAULT 0,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

// --- Inventaire ---
$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);
$all = [];
foreach ($files as $f) {
    $all[basename($f, '.sql')] = $f;
}

$pending = array_filter(
    $all,
    static fn($version) => !isset($applied[$version]),
    ARRAY_FILTER_USE_KEY
);

// --- Mode --status ---
if (in_array('--status', $args, true)) {
    fwrite(STDOUT, "Migrations (" . count($all) . " au total) :\n");
    foreach ($all as $version => $_f) {
        $mark = isset($applied[$version]) ? '[x] appliquée ' : '[ ] EN ATTENTE';
        fwrite(STDOUT, "  $mark  $version\n");
    }
    if (!$all) { fwrite(STDOUT, "  (aucun fichier dans migrations/)\n"); }
    exit(0);
}

// --- Mode --baseline : marque tout comme appliqué sans exécuter ---
if (in_array('--baseline', $args, true)) {
    $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES (?, ?)");
    $n = 0;
    foreach ($all as $version => $_f) {
        if (!isset($applied[$version])) { $ins->execute([$version, time()]); $n++; }
    }
    fwrite(STDOUT, "Baseline : $n migration(s) marquée(s) comme appliquée(s).\n");
    exit(0);
}

// --- Application des migrations en attente ---
if (!$pending) {
    fwrite(STDOUT, "Base à jour — aucune migration en attente.\n");
    exit(0);
}

$ins = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)");
$done = 0;
foreach ($pending as $version => $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Lecture impossible : $file\n");
        exit(1);
    }
    // Découpe en instructions (";" en fin de ligne), en ignorant commentaires et vides.
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/', $sql)),
        static fn($s) => $s !== '' && !preg_match('/^--/', $s)
    );

    fwrite(STDOUT, "→ $version … ");
    try {
        $pdo->beginTransaction();
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        $ins->execute([$version, time()]);
        $pdo->commit();
        fwrite(STDOUT, "OK\n");
        $done++;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        fwrite(STDERR, "ÉCHEC\n  {$e->getMessage()}\n");
        fwrite(STDERR, "Migration $version non enregistrée. Corrigez puis relancez.\n");
        fwrite(STDERR, "⚠️ Si la migration contenait du DDL (ALTER/CREATE), il a pu être partiellement validé (MySQL auto-commit le DDL) : vérifiez l'état / restaurez depuis une sauvegarde.\n");
        exit(1);
    }
}
fwrite(STDOUT, "$done migration(s) appliquée(s).\n");
exit(0);
