<?php
/**
 * Shared migration + backup helpers, usable from BOTH the CLI runner
 * (tools/migrate.php) and the web admin (actions/maintenance.php).
 *
 * Guardless and side-effect-free (functions only): safe to include anywhere,
 * including from the CLI where APP_ENTRY is not defined.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/** Absolute path to the migrations directory (html/migrations), CLI or web. */
function mbMigrationsDir(): string
{
    return __DIR__ . '/../../migrations';
}

/** SHA-256 of a migration file's raw content (drift detection). */
function mbMigrationChecksum(string $file): string
{
    $c = @file_get_contents($file);
    return $c === false ? '' : hash('sha256', $c);
}

/** All migrations on disk: version => filepath, sorted by version. */
function mbAllMigrations(): array
{
    $files = glob(mbMigrationsDir() . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $all = [];
    foreach ($files as $f) {
        $all[basename($f, '.sql')] = $f;
    }
    return $all;
}

/** Applied migrations recorded in the DB: version => checksum. Empty on error. */
function mbAppliedMigrations(PDO $pdo): array
{
    try {
        return $pdo->query("SELECT version, checksum FROM schema_migrations")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException) {
        return [];
    }
}

/** Creates the tracking table + ensures the checksum column exists (MariaDB). */
function mbEnsureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version`    VARCHAR(255) NOT NULL,
            `applied_at` INT(11)      NOT NULL DEFAULT 0,
            `checksum`   CHAR(64)     NOT NULL DEFAULT '',
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try { $pdo->exec("ALTER TABLE `schema_migrations` ADD COLUMN IF NOT EXISTS `checksum` CHAR(64) NOT NULL DEFAULT ''"); }
    catch (PDOException) { /* older MySQL without IF NOT EXISTS: ignore */ }
}

/** Pending migrations: version => filepath. */
function mbPendingMigrations(PDO $pdo): array
{
    $applied = mbAppliedMigrations($pdo);
    return array_filter(mbAllMigrations(), static fn($v) => !isset($applied[$v]), ARRAY_FILTER_USE_KEY);
}

/**
 * Applies every pending migration in order. Each file runs in a transaction;
 * a DDL statement (ALTER/CREATE) implicitly commits, so we only commit when a
 * transaction is still open. Records the version + checksum on success and
 * stops at the first failure.
 *
 * @param callable|null $log  Optional progress sink, called with strings.
 * @return array{applied: string[], error: ?string, failed: ?string}
 */
function mbRunPendingMigrations(PDO $pdo, ?callable $log = null): array
{
    global $GLOBAL;
    mbEnsureMigrationsTable($pdo);
    $pending = mbPendingMigrations($pdo);
    $result = ['applied' => [], 'error' => null, 'failed' => null];
    if (!$pending) {
        return $result;
    }

    $ins = $pdo->prepare("INSERT INTO schema_migrations (version, applied_at, checksum) VALUES (?, ?, ?)");
    foreach ($pending as $version => $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            $result['error'] = sprintf($GLOBAL['migrationReadError'], $file);
            $result['failed'] = $version;
            return $result;
        }
        $checksum = hash('sha256', $sql);
        // Strip full-line SQL comments, then split into statements on ";".
        $sqlNoComments = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sqlNoComments)), static fn($s) => $s !== '');

        if ($log) { $log("→ $version … "); }
        try {
            $pdo->beginTransaction();
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }
            $ins->execute([$version, time(), $checksum]);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            if ($log) { $log("OK\n"); }
            $result['applied'][] = $version;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($log) { $log($GLOBAL['migrationFailed'] . "\n"); }
            $result['error'] = $e->getMessage();
            $result['failed'] = $version;
            return $result;
        }
    }
    return $result;
}

/**
 * Streams a full SQL dump of the database to the output buffer (echo), table by
 * table. Pure PHP — no mysqldump/exec needed, works on locked-down shared hosts.
 * The caller is responsible for auth, headers (Content-Disposition) and
 * set_time_limit(). Uses --add-drop-table semantics so the dump restores cleanly.
 */
function mbDumpDatabase(PDO $pdo): void
{
    echo "-- MemberBase SQL export — " . date('Y-m-d H:i:s') . "\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1] ?? '';
        echo "DROP TABLE IF EXISTS `$table`;\n$create;\n";
        $rows = $pdo->query("SELECT * FROM `$table`");
        $n = 0;
        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row);
            echo "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n";
            if ((++$n % 500) === 0) { flush(); }
        }
        echo "\n";
        flush();
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
}
