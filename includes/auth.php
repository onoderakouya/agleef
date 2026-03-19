<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'ログインが必要です。';
        header('Location: login.php');
        exit;
    }
}

function current_user_name(): string
{
    return $_SESSION['username'] ?? 'ゲスト';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function get_flash(string $type): ?string
{
    $key = 'flash_' . $type;
    $message = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);

    return $message;
}
