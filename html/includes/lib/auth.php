<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Authentication helpers — session management, login, CSRF, and access guards.
 *
 * Loaded at the very top of every protected page, before any output.
 * login.php handles session_start() itself; all other pages go through here.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function authUser(): ?object
{
    if (empty($_SESSION['app_user_id'])) return null;
    return (object)[
        'id'                    => $_SESSION['app_user_id'],
        'username'              => $_SESSION['app_user_username'],
        'display_name'          => $_SESSION['app_user_display_name'],
        'role'                  => $_SESSION['app_user_role'],
        'force_password_change' => !empty($_SESSION['force_password_change']),
    ];
}

function isLoggedIn(): bool { return authUser() !== null; }
function isAdmin(): bool    { return ($_SESSION['app_user_role'] ?? '') === 'admin'; }

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * After login, if the user must change their password, block every view
 * except changePassword and the logout action.
 */
function requirePasswordChange(): void
{
    if (!empty($_SESSION['force_password_change'])) {
        $action = $_REQUEST['action'] ?? '';
        $view   = $_REQUEST['view']   ?? '';
        if ($view !== 'changePassword' && $action !== 'changePassword' && $action !== 'logout') {
            header('Location: index.php?view=changePassword');
            exit;
        }
    }
}

function authLogin(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare(
        "SELECT id, username, display_name, password_hash, role, force_password_change
         FROM app_users WHERE username = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([trim($username)]);
    $user = $stmt->fetchObject();
    if (!$user || !password_verify($password, $user->password_hash)) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['app_user_id']           = $user->id;
    $_SESSION['app_user_username']     = $user->username;
    $_SESSION['app_user_display_name'] = $user->display_name ?: $user->username;
    $_SESSION['app_user_role']         = $user->role;
    $_SESSION['force_password_change'] = (bool)$user->force_password_change;
    $pdo->prepare("UPDATE app_users SET last_login = NOW() WHERE id = ?")->execute([$user->id]);
    return true;
}

function authLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
