<?php
require_once __DIR__ . '/functions.php';

function current_user_is_admin(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $stmt = db()->prepare('SELECT is_admin FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => current_user_id()]);
    $isAdmin = $stmt->fetchColumn();

    if ($isAdmin === false) {
        $_SESSION = [];
        return false;
    }

    $_SESSION['is_admin'] = (int)$isAdmin;
    return (int)$isAdmin === 1;
}

function current_user_is_suspended(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $stmt = db()->prepare('SELECT is_suspended FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => current_user_id()]);
    $isSuspended = $stmt->fetchColumn();

    if ($isSuspended === false) {
        $_SESSION = [];
        return true;
    }

    $_SESSION['is_suspended'] = (int)$isSuspended;
    return (int)$isSuspended === 1;
}

function require_active_user(): void
{
    require_login();

    if (current_user_is_suspended()) {
        set_flash('error', 'このアカウントは現在停止されています。心当たりがない場合はお問い合わせください。');
        redirect('logout.php');
    }
}

function require_admin(): void
{
    require_active_user();

    if (!current_user_is_admin()) {
        set_flash('error', '管理者権限が必要です。');
        redirect('dashboard.php');
    }
}
