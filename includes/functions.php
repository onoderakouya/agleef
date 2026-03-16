<?php
require_once __DIR__ . '/db.php';

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
        header('Location: login.php');
        exit;
    }
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['username'] ?? '';
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_user_crops(int $userId): array
{
    $stmt = db()->prepare('SELECT id, name FROM crops WHERE user_id = :user_id ORDER BY name ASC');
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_user_fields(int $userId): array
{
    $stmt = db()->prepare('SELECT id, name FROM fields WHERE user_id = :user_id ORDER BY name ASC');
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function handle_photo_upload(array $file): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('写真アップロードに失敗しました。');
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('写真サイズは3MB以下にしてください。');
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowedMime[$mime])) {
        throw new RuntimeException('アップロード可能な画像形式は JPG / PNG / WEBP のみです。');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
    $destination = UPLOAD_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('画像の保存に失敗しました。');
    }

    return 'assets/uploads/' . $filename;
}
