<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Bootstrap file — database connection, app settings, shared utility functions.
 *
 * Included by every page before any business logic or output.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

date_default_timezone_set("Europe/Zurich");

// Single source of truth for the installed application version (footer + health page).
const APP_VERSION = '5.3.0';

// Pure helpers (date/string), extracted for unit testing — no side effects.
require_once __DIR__ . '/pure.php';

// Load DB config from conf/db.php if present (traditional install),
// otherwise fall back to environment variables (Docker / 12-factor).
$_confFile = __DIR__ . '/../../../conf/db.php';
if (file_exists($_confFile)) {
    require_once $_confFile;
}
unset($_confFile);

$hostname = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
$username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'members');
$password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: 'members');
$database = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'members');

/**
 * DB unreachable or schema missing. HTML pages are sent to the installer;
 * API endpoints (APP_API defined in api/_bootstrap.php) get a JSON 503 —
 * a JSON client must never receive an HTML redirect.
 */
function mbDbUnavailable(): never
{
    if (defined('APP_API')) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Service unavailable']);
        exit;
    }
    header('Location: install.php');
    exit;
}

$dsn = "mysql:host={$hostname};dbname={$database};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    mbDbUnavailable();
}

// Returns the shared PDO connection. Use this instead of `global $pdo` inside functions and methods.
function db(): PDO { global $pdo; return $pdo; }

/**
 * Self-URL for forms, links and redirects. Replaces PHP_SELF,
 * which reflects attacker-controlled PATH_INFO (/index.php/"><script>...)
 * straight into the page (XSS). SCRIPT_NAME is server-resolved; basename +
 * escaping make the value safe in both HTML and Location headers.
 */
function appUrl(): string
{
    return htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'), ENT_QUOTES, 'UTF-8');
}

// Schema not yet initialized → installer (HTML) or 503 (API)
try {
    $pdo->query("SELECT 1 FROM compta_type LIMIT 1");
} catch (PDOException $e) {
    mbDbUnavailable();
}

// Compta types
// default_libele may not exist yet on a not-yet-migrated DB (pre-0021): the
// admin must still be able to reach the pending-migrations screen.
try {
    $_ctRows = $pdo->query("SELECT id, label, color, default_libele, sort_order, is_cotisation, is_excluded_from_donation, is_institutional FROM compta_type ORDER BY sort_order ASC, label ASC")->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $_ctRows = $pdo->query("SELECT id, label, color, '' AS default_libele, sort_order, is_cotisation, is_excluded_from_donation, is_institutional FROM compta_type ORDER BY sort_order ASC, label ASC")->fetchAll(PDO::FETCH_OBJ);
}
$comptaTypes = [];
foreach ($_ctRows as $_ct) { $comptaTypes[(int)$_ct->id] = $_ct; }
unset($_ctRows, $_ct);

// App settings
$_settingsRows = $pdo->query("SELECT `key`, `value` FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appSettings = array_merge([
    'default_segment'    => '0',
    'membre_segment'     => '0',
    'archive_id'         => '0',
    'org_name'           => '',
    'org_address'        => '',
    'org_npa'            => '',
    'org_city'           => '',
    'org_country'        => '',
    'org_ide'            => '',
    'org_iban'           => '',
    'org_coti_amount_desc' => '',
    'org_purpose'        => '',
    'org_tax_status'     => '',
    'membre_segment_prefix' => 'Membre',
    // SMTP — all defaults empty; smtp_enc_key generated on first save
    'smtp_host'       => '',
    'smtp_port'       => '587',
    'smtp_encryption' => 'starttls',
    'smtp_auth'       => '0',
    'smtp_user'       => '',
    'smtp_password'   => '',
    'smtp_from_email' => '',
    'smtp_from_name'  => '',
    'smtp_reply_to'   => '',
], $_settingsRows);
unset($_settingsRows);

// Named constants for virtual filter IDs used in view_users.inc
// Negative integers — never collide with real segment IDs (always > 0)
const FILTER_ALL_EXCEPT_ARCHIVES  = -3;
const FILTER_UNPAID_COTI_CURRENT  = -4;
const FILTER_UNPAID_COTI_3Y       = -3333;
const FILTER_NO_ACTIVITY_10Y      = -5555;
const FILTER_NON_INSTIT_LAST_YEAR = -6666;
// Generic sequence counter (see maxval table). No callers left as of the
// combined_segment_member split (#142) and the contact_properties AUTO_INCREMENT
// migration (#20) — kept as a reusable primitive for any future need.
function updateAndGetMaxVal(string $parameter): int
{
    // Atomic under concurrency: LAST_INSERT_ID(expr) records the incremented
    // value for THIS connection, so two parallel requests can never read the
    // same counter value. The upsert also seeds the row on first use.
    db()->prepare(
        "INSERT INTO maxval (parameter, value) VALUES (?, LAST_INSERT_ID(2))
         ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)"
    )->execute([$parameter]);
    return (int) db()->query("SELECT LAST_INSERT_ID()")->fetchColumn();
}

/**
 * Retourne la liste des migrations en attente (non enregistrées dans
 * schema_migrations), triée par nom. Vide si la base est à jour.
 *
 * Robuste : si la table de suivi n'existe pas encore (instance jamais migrée
 * avec le runner), toutes les migrations présentes sont considérées en attente.
 * Toute autre erreur est avalée pour ne jamais casser le rendu d'une page.
 */
function pendingMigrations(PDO $pdo): array
{
    $migrationsDir = __DIR__ . '/../../migrations';  // html/migrations
    $files = glob($migrationsDir . '/*.sql') ?: [];
    if (!$files) {
        return [];
    }
    sort($files, SORT_STRING);
    $all = array_map(static fn($f) => basename($f, '.sql'), $files);

    try {
        $applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Table de suivi absente → aucune migration enregistrée.
        $applied = [];
    }
    $applied = array_flip($applied);

    return array_values(array_filter($all, static fn($v) => !isset($applied[$v])));
}

/**
 * Retourne les migrations « en dérive » : appliquées, mais dont le fichier
 * actuel ne correspond plus au checksum enregistré (fichier modifié après coup).
 * Robuste : table/colonne absente → tableau vide. Ne casse jamais le rendu.
 */
function migrationDrift(PDO $pdo): array
{
    $migrationsDir = __DIR__ . '/../../migrations';  // html/migrations
    $byVersion = [];
    foreach (glob($migrationsDir . '/*.sql') ?: [] as $f) {
        $byVersion[basename($f, '.sql')] = $f;
    }
    try {
        $rows = $pdo->query("SELECT version, checksum FROM schema_migrations")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return []; // table ou colonne checksum absente → rien à comparer
    }
    $drift = [];
    foreach ($rows as $version => $stored) {
        if ($stored === '' || $stored === null) continue;   // checksum jamais enregistré
        if (!isset($byVersion[$version]))        continue;   // fichier retiré (pas une dérive)
        $current = @file_get_contents($byVersion[$version]);
        if ($current !== false && hash('sha256', $current) !== $stored) {
            $drift[] = $version;
        }
    }
    sort($drift, SORT_STRING);
    return $drift;
}

function auditLog(PDO $pdo, string $action, string $detail = '', ?int $subjectUserId = null): void
{
    $uid      = isset($_SESSION['app_user_id']) ? (int)$_SESSION['app_user_id'] : null;
    $username = $_SESSION['app_user_username'] ?? null;
    $pdo->prepare(
        "INSERT INTO audit_log (app_user_id, username, action, detail, subject_user_id) VALUES (?, ?, ?, ?, ?)"
    )->execute([$uid, $username, $action, $detail ?: null, $subjectUserId]);
}
