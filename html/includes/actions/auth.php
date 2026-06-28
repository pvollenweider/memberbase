<?php
/**
 * Action handler for authentication: logout, password change, and app user management.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: logout, changePassword, createAppUser, deleteAppUser,
//          resetUserPassword, flushAuditLog

$action = $_REQUEST['action'];

if ($action === 'logout') {
    authLogout();
    header('Location: login.php');
    exit;

} elseif ($action === 'changePassword') {
    $currentUser = authUser();
    $pwNew       = $_POST['pw_new']     ?? '';
    $pwConfirm   = $_POST['pw_confirm'] ?? '';
    $pwCurrent   = $_POST['pw_current'] ?? '';
    $forced      = $currentUser->force_password_change;
    $errParam    = '';

    if (strlen($pwNew) < 8) {
        $errParam = urlencode('Le mot de passe doit contenir au moins 8 caractères.');
    } elseif ($pwNew !== $pwConfirm) {
        $errParam = urlencode('Les deux mots de passe ne correspondent pas.');
    } elseif (!$forced) {
        $row = $pdo->prepare("SELECT password_hash FROM app_users WHERE id = ?");
        $row->execute([$currentUser->id]);
        $hash = $row->fetchColumn();
        if (!$hash || !password_verify($pwCurrent, $hash)) {
            $errParam = urlencode('Mot de passe actuel incorrect.');
        }
    }

    if ($errParam) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=changePassword&pw_error=' . $errParam);
        exit;
    }

    $newHash = password_hash($pwNew, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE app_users SET password_hash=?, force_password_change=0 WHERE id=?")
        ->execute([$newHash, $currentUser->id]);
    $_SESSION['force_password_change'] = false;
    auditLog($pdo, 'changePassword', "user={$currentUser->username}");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;

} elseif ($action === 'createAppUser') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $auUsername    = trim($_POST['au_username']    ?? '');
    $auDisplayName = trim($_POST['au_display_name'] ?? '');
    $auEmail       = trim($_POST['au_email']       ?? '');
    $auRole        = in_array($_POST['au_role'] ?? '', ['admin','user']) ? $_POST['au_role'] : 'user';
    $auPasswordRaw = trim($_POST['au_password'] ?? '');
    $errParam      = '';

    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $auUsername)) {
        $errParam = urlencode('Identifiant invalide (lettres, chiffres, ., -, _ uniquement).');
    } else {
        try {
            if ($auPasswordRaw === '') {
                $token     = bin2hex(random_bytes(32));
                $expires   = date('Y-m-d H:i:s', strtotime('+7 days'));
                $pdo->prepare(
                    "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change, reset_token, token_expires_at)
                     VALUES (?, ?, ?, '', ?, 1, ?, ?)"
                )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null, $auRole, $token, $expires]);
                $newId = (int)$pdo->lastInsertId();
                $_SESSION['invite_token_flash'] = ['uid' => $newId, 'token' => $token];
                auditLog($pdo, 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=invitation");
            } else {
                $pdo->prepare(
                    "INSERT INTO app_users (username, display_name, email, password_hash, role, force_password_change)
                     VALUES (?, ?, ?, ?, ?, 1)"
                )->execute([$auUsername, $auDisplayName ?: null, $auEmail ?: null,
                            password_hash($auPasswordRaw, PASSWORD_DEFAULT), $auRole]);
                $newId = (int)$pdo->lastInsertId();
                auditLog($pdo, 'createAppUser', "id=$newId username=$auUsername role=$auRole mode=password");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errParam = urlencode('Cet identifiant est déjà utilisé.');
            } else {
                throw $e;
            }
        }
    }
    if ($errParam) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers&au_error=' . $errParam);
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
    }
    exit;

} elseif ($action === 'deleteAppUser') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $targetId = (int)($_POST['target_id'] ?? 0);
    if ($targetId === (int)$_SESSION['app_user_id']) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
        exit;
    }
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM app_users WHERE role='admin' AND is_active=1")->fetchColumn();
    $targetRole = $pdo->prepare("SELECT role FROM app_users WHERE id=?");
    $targetRole->execute([$targetId]);
    $role = $targetRole->fetchColumn();
    if ($role === 'admin' && $adminCount <= 1) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers&au_error=' . urlencode('Impossible de supprimer le dernier administrateur.'));
        exit;
    }
    $deletedUsername = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
    $deletedUsername->execute([$targetId]);
    auditLog($pdo, 'deleteAppUser', "id=$targetId username=" . ($deletedUsername->fetchColumn() ?: ''));
    $pdo->prepare("DELETE FROM app_users WHERE id=?")->execute([$targetId]);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
    exit;

} elseif ($action === 'resetUserPassword') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $targetId  = (int)($_POST['target_id'] ?? 0);
    $tempPw    = bin2hex(random_bytes(6));
    $pdo->prepare("UPDATE app_users SET password_hash=?, force_password_change=1 WHERE id=?")
        ->execute([password_hash($tempPw, PASSWORD_DEFAULT), $targetId]);
    $_SESSION['reset_pw_flash'] = ['uid' => $targetId, 'pw' => $tempPw];
    $resetUsername = $pdo->prepare("SELECT username FROM app_users WHERE id=?");
    $resetUsername->execute([$targetId]);
    auditLog($pdo, 'resetUserPassword', "id=$targetId username=" . ($resetUsername->fetchColumn() ?: ''));
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=manageAppUsers');
    exit;

} elseif ($action === 'flushAuditLog') {
    if (!isAdmin()) { http_response_code(403); exit; }
    $days = isset($_POST['keep_days']) ? (int)$_POST['keep_days'] : 0;
    if ($days > 0) {
        $pdo->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
        auditLog($pdo, 'flushAuditLog', "kept_last={$days}_days");
    } else {
        auditLog($pdo, 'flushAuditLog', 'all');
        $pdo->exec("DELETE FROM audit_log");
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?view=auditLog&flushed=1');
    exit;
}
