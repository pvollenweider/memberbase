<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Bootstrap file — database connection, app settings, shared utility functions.
 *
 * Included by every page before any business logic or output.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

date_default_timezone_set("Europe/Zurich");

// Single source of truth for the installed application version (footer + health page).
const APP_VERSION = '4.0.0';

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

$dsn = "mysql:host={$hostname};dbname={$database};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    header('Location: install.php'); exit;
}

// Returns the shared PDO connection. Use this instead of `global $pdo` inside functions and methods.
function db(): PDO { global $pdo; return $pdo; }

// Redirect to installer if schema not yet initialized
try {
    $pdo->query("SELECT 1 FROM compta_type LIMIT 1");
} catch (PDOException $e) {
    header('Location: install.php'); exit;
}

// Compta types
$_ctRows = $pdo->query("SELECT id, label, color, sort_order, is_cotisation, is_excluded_from_donation, is_institutional FROM compta_type ORDER BY sort_order ASC, label ASC")->fetchAll(PDO::FETCH_OBJ);
$comptaTypes = [];
foreach ($_ctRows as $_ct) { $comptaTypes[(int)$_ct->id] = $_ct; }
unset($_ctRows, $_ct);

// App settings
$_settingsRows = $pdo->query("SELECT `key`, `value` FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appSettings = array_merge([
    'default_team'       => '0',
    'membre_team'        => '0',
    'archive_id'         => '0',
    'org_name'           => '',
    'org_address'        => '',
    'org_npa'            => '',
    'org_city'           => '',
    'org_country'        => '',
    'org_ide'            => '',
    'org_purpose'        => '',
    'org_tax_status'     => '',
    'membre_team_prefix' => 'Membre',
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
// Sequence counter — still used for metagroup_id and userpropertiesid only.
// segment/users/compta are now native AUTO_INCREMENT.
// metagroup cannot use AUTO_INCREMENT (id shared across header + member rows).
// contact_properties.id is broken (83k rows with id=0), left for later refactor.
function getMaxVal(PDO $pdo, string $parameter): int
{
    $stmt = $pdo->prepare("SELECT value FROM maxval WHERE parameter=?");
    $stmt->execute([$parameter]);
    $row = $stmt->fetchObject();
    if ($row !== false) {
        return (int) $row->value;
    }
    $pdo->prepare("INSERT INTO maxval (parameter,value) VALUES (?,1)")->execute([$parameter]);
    return 1;
}

function updateAndGetMaxVal(string $parameter): int
{
    $value = getMaxVal(db(), $parameter) + 1;
    db()->prepare("UPDATE maxval SET value=? WHERE parameter=?")->execute([$value, $parameter]);
    return $value;
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
