<?php
/**
 * Login page — handles credential validation and session creation.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

$charset = "UTF-8";

// Auth bootstrap (before any output)
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>$isHttps]);
    session_start();
}

// Already logged in → redirect
if (isLoggedIn() && empty($_SESSION['force_password_change'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/declarations.php';

$error = '';
$csrfToken = $_SESSION['csrf_login'] ?? (function() {
    $t = bin2hex(random_bytes(16));
    $_SESSION['csrf_login'] = $t;
    return $t;
})();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($csrfToken, $_POST['csrf'] ?? '')) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (authLogin($pdo, $username, $password)) {
            unset($_SESSION['csrf_login']);
            if (!empty($_SESSION['force_password_change'])) {
                header('Location: index.php?view=changePassword');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            // Small delay to slow brute force
            usleep(500000);
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= htmlspecialchars($appSettings['org_name'] ?: 'Gestion des membres', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="css/vendor/inter.css">
    <link rel="stylesheet" href="css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <style>
        body { background: var(--ca-bg, #f5f5f5); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 380px; }
        .login-logo { font-size: 1.4rem; font-weight: 700; color: var(--ca-primary, #c0392b); letter-spacing: -0.02em; }
    </style>
</head>
<body>
<div class="login-card p-4">
    <div class="text-center mb-4">
        <div class="login-logo mb-1"><?= htmlspecialchars($appSettings['org_name'] ?: 'Gestion des membres', ENT_QUOTES, 'UTF-8') ?></div>
        <div class="text-muted" style="font-size:0.8rem">Gestion des membres</div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2" style="font-size:0.875rem" role="alert">
        <i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif ?>

    <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="username" class="form-label" style="font-size:0.875rem">Identifiant</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="username" autocapitalize="none" autocorrect="off" autofocus required>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label" style="font-size:0.875rem">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Se connecter</button>
    </form>
</div>
<link rel="stylesheet" href="plugins/font-awesome/css/all.min.css">
</body>
</html>
