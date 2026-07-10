<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for authentication: logout, password change, and app user management.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: logout, changePassword, createAppUser, deleteAppUser,
//          resetUserPassword, flushAuditLog

$action = $_REQUEST['action'];

if ($action === 'logout') {
    authLogout();
    header('Location: login.php');
    exit;

} elseif ($action === 'changeLocale') {
    // Self-service UI language switch — persisted on the account, applied to
    // the session immediately.
    $currentUser = authUser();
    $newLocale   = mbNormalizeLocale($_POST['locale'] ?? '');
    db()->prepare("UPDATE app_users SET locale = ? WHERE id = ?")
        ->execute([$newLocale, $currentUser->id]);
    $_SESSION['app_user_locale'] = $newLocale;
    auditLog(db(), 'changeLocale', 'locale=' . $newLocale);
    header('Location: ' . appUrl() . '?view=changePassword');
    exit;

} elseif ($action === 'changePassword') {
    $currentUser = authUser();
    $pwNew       = $_POST['pw_new']     ?? '';
    $pwConfirm   = $_POST['pw_confirm'] ?? '';
    $pwCurrent   = $_POST['pw_current'] ?? '';
    $forced      = $currentUser->force_password_change;
    $errParam    = '';

    if (strlen($pwNew) < 8) {
        $errParam = urlencode($GLOBAL['passwordTooShort']);
    } elseif ($pwNew !== $pwConfirm) {
        $errParam = urlencode($GLOBAL['passwordMismatch']);
    } elseif (!$forced) {
        $row = db()->prepare("SELECT password_hash FROM app_users WHERE id = ?");
        $row->execute([$currentUser->id]);
        $hash = $row->fetchColumn();
        if (!$hash || !password_verify($pwCurrent, $hash)) {
            $errParam = urlencode($GLOBAL['currentPasswordIncorrect']);
        }
    }

    if ($errParam) {
        header('Location: ' . appUrl() . '?view=changePassword&pw_error=' . $errParam);
        exit;
    }

    $newHash = password_hash($pwNew, PASSWORD_DEFAULT);
    db()->prepare("UPDATE app_users SET password_hash=?, force_password_change=0 WHERE id=?")
        ->execute([$newHash, $currentUser->id]);
    $_SESSION['force_password_change'] = false;
    auditLog(db(), 'changePassword', "user={$currentUser->username}");
    header('Location: ' . appUrl());
    exit;

} elseif ($action === 'createAppUser') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $auUsername    = trim($_POST['au_username']    ?? '');
    $auDisplayName = trim($_POST['au_display_name'] ?? '');
    $auEmail       = trim($_POST['au_email']       ?? '');
    $auRole        = in_array($_POST['au_role'] ?? '', ['admin','manager','user','readonly'], true) ? $_POST['au_role'] : 'user';
    $auPasswordRaw = trim($_POST['au_password'] ?? '');
    $errParam      = '';

    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $auUsername)) {
        $errParam = urlencode($GLOBAL['invalidUsername']);
    } else {
        try {
            if ($auPasswordRaw === '') {
                $token     = bin2hex(random_bytes(32));
                $expires   = date('Y-m-d H:i:s', strtotime('+7 days'));
                db()->prepare(
                    "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change, reset_token, token_expires_at)
                     VALUES (?, ?, ?, '', ?, 1, ?, ?)"
                )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null, $auRole, $token, $expires]);
                $newId = (int)db()->lastInsertId();
                $_SESSION['invite_token_flash'] = ['uid' => $newId, 'token' => $token];
                auditLog(db(), 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=invitation");
            } else {
                db()->prepare(
                    "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change)
                     VALUES (?, ?, ?, ?, ?, 1)"
                )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null,
                            password_hash($auPasswordRaw, PASSWORD_DEFAULT), $auRole]);
                $newId = (int)db()->lastInsertId();
                auditLog(db(), 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=password");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errParam = urlencode($GLOBAL['usernameTaken']);
            } else {
                throw $e;
            }
        }
    }
    if ($errParam) {
        header('Location: ' . appUrl() . '?view=manageAppUsers&au_error=' . $errParam);
    } else {
        header('Location: ' . appUrl() . '?view=manageAppUsers');
    }
    exit;

} elseif ($action === 'updateAppUser') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $targetId    = (int)($_POST['target_id'] ?? 0);
    $displayName = trim($_POST['au_display_name'] ?? '');
    $email       = trim($_POST['au_email'] ?? '');
    $role        = in_array($_POST['au_role'] ?? '', ['readonly','user','manager','admin']) ? $_POST['au_role'] : 'user';
    $isActive    = isset($_POST['au_is_active']) ? 1 : 0;
    $isSelf      = $targetId === (int)$_SESSION['app_user_id'];
    // Prevent self-demotion or self-deactivation
    if ($isSelf) {
        $role     = 'admin';
        $isActive = 1;
    }
    // Prevent removing last admin
    if ($role !== 'admin') {
        $adminCount = (int)db()->query("SELECT COUNT(*) FROM app_users WHERE role='admin' AND is_active=1")->fetchColumn();
        $curRole    = db()->prepare("SELECT role FROM app_users WHERE id=?");
        $curRole->execute([$targetId]);
        if ($curRole->fetchColumn() === 'admin' && $adminCount <= 1) {
            header('Location: ' . appUrl() . '?view=manageAppUsers&au_error=' . urlencode($GLOBAL['cannotDemoteLastAdmin']));
            exit;
        }
    }
    db()->prepare("UPDATE app_users SET display_name=?, email=?, role=?, is_active=? WHERE id=?")
        ->execute([$displayName ?: null, $email ?: null, $role, $isActive, $targetId]);
    auditLog(db(), 'updateAppUser', "id=$targetId role=$role active=$isActive");
    header('Location: ' . appUrl() . '?view=manageAppUsers');
    exit;

} elseif ($action === 'deleteAppUser') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $targetId = (int)($_POST['target_id'] ?? 0);
    if ($targetId === (int)$_SESSION['app_user_id']) {
        header('Location: ' . appUrl() . '?view=manageAppUsers');
        exit;
    }
    $adminCount = (int)db()->query("SELECT COUNT(*) FROM app_users WHERE role='admin' AND is_active=1")->fetchColumn();
    $targetRole = db()->prepare("SELECT role FROM app_users WHERE id=?");
    $targetRole->execute([$targetId]);
    $role = $targetRole->fetchColumn();
    if ($role === 'admin' && $adminCount <= 1) {
        header('Location: ' . appUrl() . '?view=manageAppUsers&au_error=' . urlencode($GLOBAL['cannotDeleteLastAdmin']));
        exit;
    }
    $deletedUsername = db()->prepare("SELECT username FROM app_users WHERE id=?");
    $deletedUsername->execute([$targetId]);
    auditLog(db(), 'deleteAppUser', "id=$targetId username=" . ($deletedUsername->fetchColumn() ?: ''));
    db()->prepare("DELETE FROM app_users WHERE id=?")->execute([$targetId]);
    header('Location: ' . appUrl() . '?view=manageAppUsers');
    exit;

} elseif ($action === 'resetUserPassword') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $targetId  = (int)($_POST['target_id'] ?? 0);
    $tempPw    = bin2hex(random_bytes(6));
    db()->prepare("UPDATE app_users SET password_hash=?, force_password_change=1 WHERE id=?")
        ->execute([password_hash($tempPw, PASSWORD_DEFAULT), $targetId]);
    $_SESSION['reset_pw_flash'] = ['uid' => $targetId, 'pw' => $tempPw];
    $resetUsername = db()->prepare("SELECT username FROM app_users WHERE id=?");
    $resetUsername->execute([$targetId]);
    auditLog(db(), 'resetUserPassword', "id=$targetId username=" . ($resetUsername->fetchColumn() ?: ''));
    header('Location: ' . appUrl() . '?view=manageAppUsers');
    exit;

} elseif ($action === 'flushAuditLog') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $days = isset($_POST['keep_days']) ? (int)$_POST['keep_days'] : 0;
    if ($days > 0) {
        db()->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        auditLog(db(), 'flushAuditLog', "kept_last={$days}_days");
    } else {
        auditLog(db(), 'flushAuditLog', 'all');
        db()->exec("DELETE FROM audit_log");
    }
    header('Location: ' . appUrl() . '?view=auditLog&flushed=1');
    exit;
}
