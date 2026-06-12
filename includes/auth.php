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

function require_admin(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'ログインが必要です。');
        redirect('login.php');
    }

    if (!current_user_is_admin()) {
        set_flash('error', '管理者権限が必要です。');
        redirect('dashboard.php');
    }
}
