<?php
/**
 * auth.inc — Application authentication helpers.
 * Loaded at the very top of index.php (before any HTML output).
 * login.php handles session_start() itself; all other pages go through here.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
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
