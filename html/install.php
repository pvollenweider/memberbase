<?php
declare(strict_types=1);
/**
 * Web installer — first-run setup wizard.
 *
 * Steps:
 *   1    Prerequisites check
 *   2    Database connection → writes conf/db.php
 *   3    Schema initialisation (CREATE TABLE IF NOT EXISTS)
 *   4    Organisation settings + seed data
 *   5    Admin account creation
 *   done Redirect to app
 *
 * Guard: if conf/db.php exists AND app_users has an admin → redirect to index.php.
 *
 * @license AGPL-3.0-or-later
 */

define('APP_ENTRY', true);
define('INSTALL_MODE', true);

// Locale strings — no DB dependency, safe before the schema exists.
require_once __DIR__ . '/locales/resources_fr.php';

$confFile = __DIR__ . '/../conf/db.php';

// Full schema — embedded so install.php has no external file dependency.
// All statements use CREATE TABLE IF NOT EXISTS (idempotent).
$schemaSql = <<<'SQL'
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id`               int(8)       NOT NULL AUTO_INCREMENT,
  `lastname`         varchar(255) NOT NULL DEFAULT '',
  `firstname`        varchar(255) NOT NULL DEFAULT '',
  `society`          varchar(255) NOT NULL DEFAULT '',
  `address`          varchar(255) NOT NULL DEFAULT '',
  `npa`              varchar(255) NOT NULL DEFAULT '',
  `tel`              varchar(255) NOT NULL DEFAULT '',
  `telprof`          varchar(255) NOT NULL DEFAULT '',
  `portable`         varchar(255) NOT NULL DEFAULT '',
  `fax`              varchar(255) NOT NULL DEFAULT '',
  `email`            varchar(255) NOT NULL DEFAULT '',
  `email_alt`        varchar(255) NOT NULL DEFAULT '',
  `web`              varchar(255) NOT NULL DEFAULT '',
  `sexe`             varchar(8)   NOT NULL DEFAULT 'na',
  `title`            varchar(255) NOT NULL DEFAULT '',
  `comment`          mediumtext   NOT NULL,
  `birthday`         int(16)      NOT NULL DEFAULT 0,
  `creationDate`     int(16)      NOT NULL DEFAULT 0,
  `modificationDate` int(16)      NOT NULL DEFAULT 0,
  `status`           tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `lastname`         (`lastname`(250)),
  KEY `firstname`        (`firstname`(250)),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `segment` (
  `id`     int(11)     NOT NULL AUTO_INCREMENT,
  `name`   varchar(64) NOT NULL DEFAULT '',
  `hidden` tinyint(1)  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id`         (`id`, `name`),
  KEY `idx_hidden` (`hidden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_properties` (
  `id`        int(8)       NOT NULL DEFAULT 0,
  `user_id`   int(8)       NOT NULL DEFAULT 0,
  `parameter` varchar(64)  NOT NULL DEFAULT '',
  `date`      int(16)      NOT NULL DEFAULT 0,
  `value`     varchar(255) NOT NULL DEFAULT '',
  KEY `parameter`      (`parameter`),
  KEY `id`             (`id`),
  KEY `idx_user_param` (`user_id`, `parameter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_segment` (
  `user_id` int NOT NULL,
  `segment_id` int NOT NULL,
  PRIMARY KEY (`user_id`, `segment_id`),
  KEY `idx_user_segment_segment_id` (`segment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `metagroup` (
  `id`         int(11)      NOT NULL,
  `name`       varchar(255) DEFAULT NULL,
  `segmentid`  int(11)      DEFAULT NULL,
  `is_filter`  tinyint(1)   NOT NULL DEFAULT 1,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  KEY `idx_segmentid` (`segmentid`),
  KEY `idx_id_name` (`id`, `name`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compta_type` (
  `id`                        int(11)      NOT NULL AUTO_INCREMENT,
  `label`                     varchar(255) NOT NULL,
  `color`                     varchar(64)  NOT NULL DEFAULT 'bg-light',
  `sort_order`                int(11)      NOT NULL DEFAULT 0,
  `is_cotisation`             tinyint(1)   NOT NULL DEFAULT 0,
  `is_excluded_from_donation` tinyint(1)   NOT NULL DEFAULT 0,
  `is_institutional`          tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compta` (
  `id`                int(8)       NOT NULL AUTO_INCREMENT,
  `user_id`           int(8)       NOT NULL DEFAULT 0,
  `date`              int(16)      NOT NULL DEFAULT 0,
  `libele`            varchar(255) NOT NULL DEFAULT '',
  `sum`               decimal(10,2) NOT NULL DEFAULT 0.00,
  `quittance`         varchar(64)  NOT NULL DEFAULT '',
  `type_id`           int(11)      DEFAULT NULL,
  `wants_attestation` tinyint(1)   NOT NULL DEFAULT 0,
  `notified_at`       datetime     DEFAULT NULL,
  `cotisation_year`   smallint(4)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id`             (`user_id`),
  KEY `user_id_2`           (`user_id`, `date`),
  KEY `idx_type_id`         (`type_id`),
  KEY `idx_date`            (`date`),
  KEY `idx_notified_at`     (`notified_at`),
  KEY `idx_cotisation_year` (`cotisation_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maxval` (
  `parameter` varchar(64) NOT NULL DEFAULT '',
  `value`     int(8)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`parameter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `key`   varchar(64)  NOT NULL,
  `value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_users` (
  `id`                    int(11)      NOT NULL AUTO_INCREMENT,
  `username`              varchar(100) NOT NULL,
  `display_name`          varchar(200) DEFAULT NULL,
  `email`                 varchar(200) DEFAULT NULL,
  `password_hash`         varchar(255) NOT NULL,
  `role`                  enum('admin','manager','user','readonly') NOT NULL DEFAULT 'user',
`locale`                varchar(5)   NOT NULL DEFAULT 'fr',
  `force_password_change` tinyint(1)   NOT NULL DEFAULT 1,
  `is_active`             tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`            timestamp    NOT NULL DEFAULT current_timestamp(),
  `last_login`            timestamp    NULL DEFAULT NULL,
  `reset_token`           varchar(64)  DEFAULT NULL,
  `token_expires_at`      datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `created_at`      datetime     NOT NULL DEFAULT current_timestamp(),
  `app_user_id`     int(11)      DEFAULT NULL,
  `username`        varchar(100) DEFAULT NULL,
  `action`          varchar(100) NOT NULL,
  `detail`          text         DEFAULT NULL,
  `subject_user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at`   (`created_at`),
  KEY `idx_subject_user` (`subject_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurable email templates
CREATE TABLE IF NOT EXISTS `email_templates` (
  `key`        varchar(64)  NOT NULL,
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `body_text`  text         NOT NULL,
  `body_html`  longtext     NOT NULL DEFAULT '',
  `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email send log
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NULL,
  `tpl_key`    varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
  `to_email`   varchar(255) NOT NULL DEFAULT '',
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `status`     enum('sent','error') NOT NULL DEFAULT 'sent',
  `error_msg`  text         DEFAULT NULL,
  `body_text`  text         NOT NULL DEFAULT '',
  `body_html`  longtext     NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_tpl_key`   (`tpl_key`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`    varchar(255) NOT NULL,
  `applied_at` int(11)      NOT NULL DEFAULT 0,
  `checksum`   char(64)     NOT NULL DEFAULT '',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
SQL;
$step       = $_GET['step'] ?? '1';
$errors     = [];

// ---------- Guard — already installed ----------
if (file_exists($confFile)) {
    require_once $confFile;
    try {
        $h = defined('DB_HOST') ? DB_HOST : 'localhost';
        $u = defined('DB_USER') ? DB_USER : '';
        $p = defined('DB_PASS') ? DB_PASS : '';
        $d = defined('DB_NAME') ? DB_NAME : '';
        $chk = new PDO("mysql:host=$h;dbname=$d;charset=utf8mb4", $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $adminCount = (int)$chk->query("SELECT COUNT(*) FROM app_users WHERE role='admin' AND is_active=1")->fetchColumn();
        if ($adminCount > 0) {
            header('Location: index.php');
            exit;
        }
        if (in_array($step, ['1', '2', '3'], true)) {
            header('Location: install.php?step=4');
            exit;
        }
    } catch (Exception) {
        // conf exists but connection fails — continue installer
    }
}

// ---------- Helper: get PDO from conf ----------
function installerPdo(): PDO {
    $h   = defined('DB_HOST') ? DB_HOST : 'localhost';
    $prt = defined('DB_PORT') ? DB_PORT : 3306;
    $u   = defined('DB_USER') ? DB_USER : '';
    $p   = defined('DB_PASS') ? DB_PASS : '';
    $d   = defined('DB_NAME') ? DB_NAME : '';
    return new PDO("mysql:host=$h;port=$prt;dbname=$d;charset=utf8mb4", $u, $p, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ]);
}

// ---------- Step 2 POST — test DB + write conf/db.php ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    if (!$dbName) $errors[] = $GLOBAL['dbNameRequired'];
    if (!$dbUser) $errors[] = $GLOBAL['dbUserRequired'];

    if (!$errors) {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $confDir = dirname($confFile);
            if (!is_dir($confDir)) mkdir($confDir, 0750, true);

            $confContent = sprintf(
                "<?php\n// Generated by installer — %s\ndefine('DB_HOST', %s);\ndefine('DB_PORT', %d);\ndefine('DB_NAME', %s);\ndefine('DB_USER', %s);\ndefine('DB_PASS', %s);\n",
                date('Y-m-d H:i:s'),
                var_export($dbHost, true), $dbPort,
                var_export($dbName, true),
                var_export($dbUser, true),
                var_export($dbPass, true)
            );
            if (file_put_contents($confFile, $confContent) === false) {
                $errors[] = sprintf($GLOBAL['cannotWriteConf'], htmlspecialchars($confFile, ENT_QUOTES, 'UTF-8'));
            } else {
                header('Location: install.php?step=3');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = sprintf($GLOBAL['connectionFailed'], htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}

// ---------- Step 3 POST — run schema ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '3') {
    if (!file_exists($confFile)) { header('Location: install.php?step=2'); exit; }

    if (!$errors) {
        require_once $confFile;
        try {
            $pdo = installerPdo();
            $statements = array_filter(
                array_map('trim', preg_split('/;\s*\n/', $schemaSql)),
                fn($s) => $s !== '' && !str_starts_with(ltrim($s), '--')
            );
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }
            // Baseline des migrations : le schéma embarqué ci-dessus est déjà à
            // jour, donc on marque toutes les migrations existantes comme
            // appliquées (un fresh install ne doit pas les rejouer).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `schema_migrations` (
                    `version` VARCHAR(255) NOT NULL,
                    `applied_at` INT(11) NOT NULL DEFAULT 0,
                    `checksum` CHAR(64) NOT NULL DEFAULT '',
                    PRIMARY KEY (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $migFiles = glob(__DIR__ . '/migrations/*.sql') ?: [];
            if ($migFiles) {
                $insMig = $pdo->prepare("INSERT IGNORE INTO schema_migrations (version, applied_at, checksum) VALUES (?, ?, ?)");
                foreach ($migFiles as $migFile) {
                    $mc = @file_get_contents($migFile);
                    $insMig->execute([basename($migFile, '.sql'), time(), $mc === false ? '' : hash('sha256', $mc)]);
                }
            }
            header('Location: install.php?step=4');
            exit;
        } catch (PDOException $e) {
            $errors[] = sprintf($GLOBAL['sqlError'], htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}

// ---------- Step 4 POST — organisation + seed ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '4') {
    if (!file_exists($confFile)) { header('Location: install.php?step=2'); exit; }
    require_once $confFile;

    $orgName    = trim($_POST['org_name'] ?? '');
    $orgAddress = trim($_POST['org_address'] ?? '');
    $orgNpa     = trim($_POST['org_npa'] ?? '');
    $orgCity    = trim($_POST['org_city'] ?? '');
    $orgCountry = trim($_POST['org_country'] ?? 'Suisse');
    $memberPrefix = trim($_POST['membre_team_prefix'] ?? 'Membre');

    if (!$orgName) $errors[] = $GLOBAL['orgNameRequired'];

    if (!$errors) {
        try {
            $pdo = installerPdo();

            // app_settings
            $settings = [
                'org_name'           => $orgName,
                'org_address'        => $orgAddress,
                'org_npa'            => $orgNpa,
                'org_city'           => $orgCity,
                'org_country'        => $orgCountry,
                'membre_team_prefix' => $memberPrefix ?: 'Membre',
                'default_team'       => '0',
                'membre_team'        => '0',
                'member_no_coti_team' => '0',
            ];
            $upsert = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            foreach ($settings as $k => $v) {
                $upsert->execute([$k, $v]);
            }

            // Create default member group for current year and set as default_team + membre_team
            $currentYear = (int)date('Y');
            $prefix = $memberPrefix ?: 'Membre';
            $insertTeam = $pdo->prepare("INSERT INTO segment (name, hidden) VALUES (?, 0)");
            $findTeam   = $pdo->prepare("SELECT id FROM segment WHERE name = ?");

            // Previous year team (for delta display in resume)
            $prevGroupName = $prefix . ' ' . ($currentYear - 1);
            $findTeam->execute([$prevGroupName]);
            $prevTeamId = (int)$findTeam->fetchColumn();
            if (!$prevTeamId) {
                $insertTeam->execute([$prevGroupName]);
                $prevTeamId = (int)$pdo->lastInsertId();
            }

            // Current year team
            $defaultGroupName = $prefix . ' ' . $currentYear;
            $findTeam->execute([$defaultGroupName]);
            $defaultTeamId = (int)$findTeam->fetchColumn();
            if (!$defaultTeamId) {
                $insertTeam->execute([$defaultGroupName]);
                $defaultTeamId = (int)$pdo->lastInsertId();
            }
            $setTeam = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            $setTeam->execute(['default_team', (string)$defaultTeamId]);
            $setTeam->execute(['membre_team',  (string)$defaultTeamId]);

            // Create "Membres" metagroup category and assign both teams
            $mvRow = $pdo->query("SELECT value FROM maxval WHERE parameter='metagroup_id'")->fetch(PDO::FETCH_OBJ);
            $metaId = $mvRow ? (int)$mvRow->value + 1 : 1;
            $existingMeta = (int)$pdo->query("SELECT COUNT(*) FROM metagroup WHERE name='Membres'")->fetchColumn();
            if (!$existingMeta) {
                $pdo->prepare("INSERT INTO metagroup (id, name, segmentid, is_filter, sort_order) VALUES (?, ?, NULL, 0, 1)")->execute([$metaId, 'Membres']);
                $pdo->prepare("INSERT INTO metagroup (id, name, segmentid, is_filter, sort_order) VALUES (?, NULL, ?, 0, 0)")->execute([$metaId, $prevTeamId]);
                $pdo->prepare("INSERT INTO metagroup (id, name, segmentid, is_filter, sort_order) VALUES (?, NULL, ?, 0, 0)")->execute([$metaId, $defaultTeamId]);
                $pdo->prepare("UPDATE maxval SET value=? WHERE parameter='metagroup_id'")->execute([$metaId]);
            }

            // Seed minimal compta_type if table is empty
            $typeCount = (int)$pdo->query("SELECT COUNT(*) FROM compta_type")->fetchColumn();
            if ($typeCount === 0) {
                $seedTypes = [
                    ['Cotisation',   'bg-light',           1, 1, 1, 0],
                    ['Don',          'bg-info-subtle',     2, 0, 0, 0],
                    ['Evénementiel', 'bg-primary-subtle',  3, 0, 1, 0],
                    ['Institutionnel','bg-warning-subtle', 4, 0, 0, 1],
                ];
                $ins = $pdo->prepare("INSERT INTO compta_type (label, color, sort_order, is_cotisation, is_excluded_from_donation, is_institutional) VALUES (?,?,?,?,?,?)");
                foreach ($seedTypes as $t) $ins->execute($t);
            }

            // Seed maxval if empty
            $mvCount = (int)$pdo->query("SELECT COUNT(*) FROM maxval")->fetchColumn();
            if ($mvCount === 0) {
                $pdo->exec("INSERT INTO maxval (parameter, value) VALUES ('userpropertiesid', 0), ('metagroup_id', 0)");
            }

            header('Location: install.php?step=5');
            exit;
        } catch (PDOException $e) {
            $errors[] = sprintf($GLOBAL['genericError'], htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}

// ---------- Step 5 POST — create admin ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '5') {
    if (!file_exists($confFile)) { header('Location: install.php?step=2'); exit; }
    require_once $confFile;

    $username    = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password2'] ?? '';

    if (!$username)            $errors[] = $GLOBAL['usernameRequired'];
    if (!preg_match('/^[a-zA-Z0-9._-]{2,50}$/', $username)) $errors[] = $GLOBAL['usernameInvalid'];
    if (strlen($password) < 8) $errors[] = $GLOBAL['installPasswordTooShort'];
    if ($password !== $password2) $errors[] = $GLOBAL['installPasswordsMismatch'];

    if (!$errors) {
        try {
            $pdo = installerPdo();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change) VALUES (?,?,?,?,'admin',0)"
            )->execute([$username, $displayName ?: $username, $email ?: null, $hash]);
            header('Location: install.php?step=done');
            exit;
        } catch (PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate')
                ? sprintf($GLOBAL['usernameTakenNamed'], $username)
                : sprintf($GLOBAL['genericError'], htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }
}

// ---------- Prerequisites ----------
$prereqs = [
    ['label' => $GLOBAL['prereqPhpVersion'], 'ok' => PHP_VERSION_ID >= 80100,         'detail' => 'PHP ' . PHP_VERSION],
    ['label' => $GLOBAL['prereqPdoMysql'],   'ok' => extension_loaded('pdo_mysql'),   'detail' => extension_loaded('pdo_mysql')   ? $GLOBAL['statusOk'] : $GLOBAL['statusMissing']],
    ['label' => $GLOBAL['prereqMbstring'],   'ok' => extension_loaded('mbstring'),    'detail' => extension_loaded('mbstring')    ? $GLOBAL['statusOk'] : $GLOBAL['statusMissing']],
    ['label' => $GLOBAL['prereqConfWritable'], 'ok' => is_writable(dirname($confFile)) || !is_dir(dirname($confFile)),
                                           'detail' => is_writable(dirname($confFile)) ? $GLOBAL['statusOk'] : (is_dir(dirname($confFile)) ? $GLOBAL['statusNotWritable'] : $GLOBAL['statusDirMissing'])],
];
$prereqsOk = array_reduce($prereqs, fn($c, $p) => $c && $p['ok'], true);

// Prefill for step 2
$prefill = [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
];
// Pre-fill from environment variables (Docker / 12-factor) or existing conf file
if (file_exists($confFile)) {
    require_once $confFile;
}
if (defined('DB_HOST')) $prefill['db_host'] = DB_HOST;
elseif (getenv('DB_HOST')) $prefill['db_host'] = getenv('DB_HOST');

if (defined('DB_PORT')) $prefill['db_port'] = (string)DB_PORT;
elseif (getenv('DB_PORT')) $prefill['db_port'] = getenv('DB_PORT');

if (defined('DB_NAME')) $prefill['db_name'] = DB_NAME;
elseif (getenv('DB_NAME')) $prefill['db_name'] = getenv('DB_NAME');

if (defined('DB_USER')) $prefill['db_user'] = DB_USER;
elseif (getenv('DB_USER')) $prefill['db_user'] = getenv('DB_USER');
$prefill = array_map(fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $prefill);

$steps = ['1' => $GLOBAL['stepPrereqs'], '2' => $GLOBAL['stepDatabase'], '3' => $GLOBAL['stepSchema'], '4' => $GLOBAL['stepOrganisation'], '5' => $GLOBAL['stepAdminAccount']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $GLOBAL['installTitle'] ?></title>
<link rel="stylesheet" href="css/vendor/bootstrap.min.css">
<style>
  body { background: #f0f4f8; }
  .install-card { max-width: 580px; margin: 3rem auto 2rem; }
  .prereq-row { display:flex; justify-content:space-between; align-items:center; padding:.35rem 0; border-bottom:1px solid #e9ecef; font-size:.9rem; }
  .prereq-row:last-child { border-bottom:none; }
  .seed-type { font-size:.82rem; color:#495057; }
</style>
</head>
<body>
<div class="install-card px-3">
  <div class="text-center mb-4">
    <h1 class="h4 fw-bold">MemberBase</h1>
    <p class="text-muted small mb-0"><?= $GLOBAL['installWizardSubtitle'] ?></p>
  </div>

  <?php if ($step !== 'done'): ?>
  <div class="d-flex gap-1 mb-4">
    <?php foreach ($steps as $s => $label):
      $idx = array_search($s, array_keys($steps));
      $curIdx = array_search($step, array_keys($steps));
      $cls = $idx < $curIdx ? 'bg-success text-white' : ($s === $step ? 'bg-primary text-white' : 'bg-light text-muted');
    ?>
    <div class="flex-fill text-center rounded py-2 small fw-semibold <?= $cls ?>" style="font-size:.75rem">
      <?= (string)($idx + 1) ?>. <?= $label ?>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach ?></ul>
  </div>
  <?php endif ?>

  <?php /* ===== STEP 1 ===== */ if ($step === '1'): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-3"><?= $GLOBAL['prereqsServerTitle'] ?></h2>
      <div class="mb-3">
        <?php foreach ($prereqs as $pr): ?>
        <div class="prereq-row">
          <span><?= $pr['label'] ?></span>
          <span class="fw-semibold <?= $pr['ok'] ? 'text-success' : 'text-danger' ?>">
            <?= $pr['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($pr['detail'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>
        <?php endforeach ?>
      </div>
      <?php if ($prereqsOk): ?>
        <a href="install.php?step=2" class="btn btn-primary w-100"><?= $GLOBAL['continueBtn'] ?></a>
      <?php else: ?>
        <div class="alert alert-warning small mb-2"><?= $GLOBAL['fixPrereqsWarning'] ?></div>
        <button class="btn btn-secondary w-100" disabled><?= $GLOBAL['continueBtn'] ?></button>
      <?php endif ?>
    </div>
  </div>

  <?php /* ===== STEP 2 ===== */ elseif ($step === '2'): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= $GLOBAL['dbConnectionTitle'] ?></h2>
      <p class="text-muted small mb-3"><?= $GLOBAL['dbConnectionHint'] ?></p>
      <form method="post" action="install.php?step=2">
        <div class="row g-2 mb-2">
          <div class="col-8">
            <label class="form-label small fw-semibold" for="db_host"><?= $GLOBAL['hostLabel'] ?></label>
            <input type="text" class="form-control form-control-sm" id="db_host" name="db_host" value="<?= $prefill['db_host'] ?>" required>
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold" for="db_port"><?= $GLOBAL['portLabel'] ?></label>
            <input type="number" class="form-control form-control-sm" id="db_port" name="db_port" value="<?= $prefill['db_port'] ?>" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="db_name"><?= $GLOBAL['dbNameLabel'] ?></label>
          <input type="text" class="form-control form-control-sm" id="db_name" name="db_name" value="<?= $prefill['db_name'] ?>" placeholder="members" required>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="db_user"><?= $GLOBAL['user'] ?></label>
          <input type="text" class="form-control form-control-sm" id="db_user" name="db_user" value="<?= $prefill['db_user'] ?>" placeholder="members" required autocomplete="username">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="db_pass"><?= $GLOBAL['password'] ?></label>
          <input type="password" class="form-control form-control-sm" id="db_pass" name="db_pass" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $GLOBAL['testConnectionBtn'] ?></button>
      </form>
    </div>
  </div>

  <?php /* ===== STEP 3 ===== */ elseif ($step === '3'): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= $GLOBAL['schemaInitTitle'] ?></h2>
      <p class="text-muted small mb-2"><?= $GLOBAL['schemaInitHint'] ?></p>
      <div class="alert alert-light small mb-3">
        <strong><?= $GLOBAL['tablesCreated'] ?></strong> users, segment, user_properties, user_segment, metagroup, compta, compta_type, maxval, app_settings, app_users, audit_log
      </div>
      <form method="post" action="install.php?step=3">
        <button type="submit" class="btn btn-primary w-100"><?= $GLOBAL['createTablesBtn'] ?></button>
      </form>
    </div>
  </div>

  <?php /* ===== STEP 4 ===== */ elseif ($step === '4'): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= $GLOBAL['orgConfigTitle'] ?></h2>
      <p class="text-muted small mb-3"><?= $GLOBAL['orgConfigHint'] ?></p>
      <form method="post" action="install.php?step=4">
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="org_name"><?= $GLOBAL['orgNameLabel'] ?> <span class="text-danger">*</span></label>
          <input type="text" class="form-control form-control-sm" id="org_name" name="org_name"
                 value="<?= htmlspecialchars($_POST['org_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="<?= $GLOBAL['orgNamePlaceholder'] ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="org_address"><?= $GLOBAL['address'] ?></label>
          <input type="text" class="form-control form-control-sm" id="org_address" name="org_address"
                 value="<?= htmlspecialchars($_POST['org_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4">
            <label class="form-label small fw-semibold" for="org_npa"><?= $GLOBAL['npaLabel'] ?></label>
            <input type="text" class="form-control form-control-sm" id="org_npa" name="org_npa"
                   value="<?= htmlspecialchars($_POST['org_npa'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="col-8">
            <label class="form-label small fw-semibold" for="org_city"><?= $GLOBAL['city'] ?></label>
            <input type="text" class="form-control form-control-sm" id="org_city" name="org_city"
                   value="<?= htmlspecialchars($_POST['org_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="org_country"><?= $GLOBAL['country'] ?></label>
          <input type="text" class="form-control form-control-sm" id="org_country" name="org_country"
                 value="<?= htmlspecialchars($_POST['org_country'] ?? 'Suisse', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <hr>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="membre_team_prefix"><?= $GLOBAL['memberPrefixLabel'] ?></label>
          <input type="text" class="form-control form-control-sm" id="membre_team_prefix" name="membre_team_prefix"
                 value="<?= htmlspecialchars($_POST['membre_team_prefix'] ?? 'Membre', ENT_QUOTES, 'UTF-8') ?>" style="max-width:180px">
          <div class="form-text"><?= $GLOBAL['memberPrefixHint'] ?></div>
        </div>
        <hr>
        <p class="small fw-semibold mb-1"><?= $GLOBAL['seedTypesTitle'] ?></p>
        <p class="text-muted small mb-2"><?= $GLOBAL['seedTypesHint'] ?></p>
        <div class="seed-type d-flex flex-column gap-1 mb-3 ps-2">
          <div><span class="badge bg-light text-dark border me-1">Cotisation</span> — <?= $GLOBAL['seedCotisationDesc'] ?></div>
          <div><span class="badge bg-info-subtle text-dark border me-1">Don</span> — <?= $GLOBAL['seedDonDesc'] ?></div>
          <div><span class="badge bg-primary-subtle text-dark border me-1">Evénementiel</span> — <?= $GLOBAL['seedEventDesc'] ?></div>
          <div><span class="badge bg-warning-subtle text-dark border me-1">Institutionnel</span> — <?= $GLOBAL['seedInstitDesc'] ?></div>
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $GLOBAL['saveAndContinueBtn'] ?></button>
      </form>
    </div>
  </div>

  <?php /* ===== STEP 5 ===== */ elseif ($step === '5'): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h5 mb-1"><?= $GLOBAL['adminAccountTitle'] ?></h2>
      <p class="text-muted small mb-3"><?= $GLOBAL['adminAccountHint'] ?></p>
      <form method="post" action="install.php?step=5">
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="username"><?= $GLOBAL['username'] ?></label>
          <input type="text" class="form-control form-control-sm" id="username" name="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="admin" required autocomplete="username" pattern="[a-zA-Z0-9._-]{2,50}">
          <div class="form-text"><?= $GLOBAL['usernameFormatHint'] ?></div>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="display_name"><?= $GLOBAL['displayNameLabel'] ?></label>
          <input type="text" class="form-control form-control-sm" id="display_name" name="display_name"
                 value="<?= htmlspecialchars($_POST['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $GLOBAL['adminDisplayNamePlaceholder'] ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="email"><?= $GLOBAL['emailLong'] ?> <span class="text-muted fw-normal"><?= $GLOBAL['optionalSuffix'] ?></span></label>
          <input type="email" class="form-control form-control-sm" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold" for="password"><?= $GLOBAL['password'] ?></label>
          <input type="password" class="form-control form-control-sm" id="password" name="password"
                 required minlength="8" autocomplete="new-password">
          <div class="form-text"><?= $GLOBAL['minPasswordChars'] ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="password2"><?= $GLOBAL['confirmPassword'] ?></label>
          <input type="password" class="form-control form-control-sm" id="password2" name="password2"
                 required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $GLOBAL['createAccountBtn'] ?></button>
      </form>
    </div>
  </div>

  <?php /* ===== DONE ===== */ elseif ($step === 'done'): ?>
  <div class="card shadow-sm border-success">
    <div class="card-body text-center py-4">
      <div class="display-6 mb-2 text-success">✓</div>
      <h2 class="h5 mb-1"><?= $GLOBAL['installDoneTitle'] ?></h2>
      <p class="text-muted small mb-3">
        <?= $GLOBAL['installDoneMessage'] ?><br>
        <?= $GLOBAL['deleteInstallHint'] ?>
      </p>
      <a href="index.php" class="btn btn-success px-4"><?= $GLOBAL['goToAppBtn'] ?></a>
    </div>
  </div>
  <?php endif ?>

  <p class="text-center text-muted mt-3" style="font-size:.75rem">
    MemberBase — <a href="https://pvollenweider.github.io/memberbase/" class="text-muted">AGPL-3.0</a>
  </p>
</div>
</body>
</html>
