<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Authentication helpers — session management, login, CSRF, and access guards.
 *
 * Loaded at the very top of every protected page, before any output.
 * login.php handles session_start() itself; all other pages go through here.
 *
 * @copyright 2026 Philippe Vollenweider
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

/**
 * Returns the per-session CSRF token, generating it on first use.
 * Distinct from the login form token ('csrf_login').
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Validates a submitted CSRF token against the session one, in constant time.
 * The token is read from the `csrf` POST field, the `X-CSRF-Token` header
 * (htmx / fetch requests), or the `csrf` query string param. The GET fallback
 * exists for mutating links that must survive outside of an htmx-boosted
 * click (browser link prefetch, middle-click/ctrl-click new tab, no-JS) —
 * e.g. the segment assign/unassign pills and the "undo" toast link, which
 * embed the token in their own href rather than relying on htmx to inject
 * the header. Still requires the real per-session secret, so a forged
 * external link/image tag (the actual CSRF threat) has no way to guess it.
 */
function csrfCheck(): bool
{
    if (empty($_SESSION['csrf'])) {
        return false;
    }
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf'] ?? ''));
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf'], $sent);
}

function isLoggedIn(): bool  { return authUser() !== null; }
function isAdmin(): bool    { return ($_SESSION['app_user_role'] ?? '') === 'admin'; }
function isManager(): bool  { return in_array($_SESSION['app_user_role'] ?? '', ['admin', 'manager'], true); }
function canWrite(): bool   { return in_array($_SESSION['app_user_role'] ?? '', ['admin', 'manager', 'user'], true); }
function canRead(): bool    { return in_array($_SESSION['app_user_role'] ?? '', ['admin', 'manager', 'user', 'readonly'], true); }

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
    // SELECT * so login keeps working on a not-yet-migrated DB (e.g. before
    // app_users.locale exists) — the admin must be able to log in to reach
    // the pending-migrations screen.
    $stmt = $pdo->prepare(
        "SELECT * FROM app_users WHERE username = ? AND is_active = 1 LIMIT 1"
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
    $_SESSION['app_user_locale']       = $user->locale ?? 'fr';
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
