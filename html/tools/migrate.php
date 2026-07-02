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

// --- Table de suivi ---
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version`    VARCHAR(255) NOT NULL,
        `applied_at` INT(11)      NOT NULL DEFAULT 0,
        `checksum`   CHAR(64)     NOT NULL DEFAULT '',
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
// Add the checksum column on tables created before it existed (MariaDB).
try { $pdo->exec("ALTER TABLE `schema_migrations` ADD COLUMN IF NOT EXISTS `checksum` CHAR(64) NOT NULL DEFAULT ''"); }
catch (PDOException) { /* older MySQL without IF NOT EXISTS: ignore if already present */ }

/** SHA-256 of a migration file's raw content, for drift detection. */
function migrationChecksum(string $file): string
{
    $c = @file_get_contents($file);
    return $c === false ? '' : hash('sha256', $c);
}

// --- Inventaire ---
$appliedRows = $pdo->query("SELECT version, checksum FROM schema_migrations")->fetchAll(PDO::FETCH_KEY_PAIR);
$applied = array_flip(array_keys($appliedRows)); // version => index (presence map)

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
    $drift = 0;
    foreach ($all as $version => $file) {
        if (!isset($applied[$version])) {
            fwrite(STDOUT, "  [ ] EN ATTENTE  $version\n");
            continue;
        }
        // Applied: flag drift if the recorded checksum no longer matches the file.
        $stored = $appliedRows[$version] ?? '';
        if ($stored !== '' && $stored !== migrationChecksum($file)) {
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
        if (!isset($applied[$version])) { $ins->execute([$version, time(), migrationChecksum($file)]); $n++; }
    }
    fwrite(STDOUT, "Baseline : $n migration(s) marquée(s) comme appliquée(s).\n");
    exit(0);
}

// --- Application des migrations en attente ---
if (!$pending) {
    fwrite(STDOUT, "Base à jour — aucune migration en attente.\n");
    exit(0);
}

$ins = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at, checksum) VALUES (?, ?, ?)");
$done = 0;
foreach ($pending as $version => $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Lecture impossible : $file\n");
        exit(1);
    }
    $checksum = hash('sha256', $sql);
    // Strip full-line SQL comments first, THEN split into statements on ";".
    // (A previous version filtered out any segment starting with "--", which
    // silently dropped a whole statement when a comment preceded it on the same
    // segment — e.g. a leading comment above an ALTER.)
    $sqlNoComments = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(
        array_map('trim', explode(';', $sqlNoComments)),
        static fn($s) => $s !== ''
    );

    fwrite(STDOUT, "→ $version … ");
    try {
        $pdo->beginTransaction();
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        // A DDL statement (ALTER/CREATE) implicitly commits and ends the
        // transaction in MySQL/MariaDB, so there may be no active transaction
        // left here. Record the version and only commit if one is still open —
        // otherwise commit()/rollBack() would throw "no active transaction".
        $ins->execute([$version, time(), $checksum]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
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
