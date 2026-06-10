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
        set_flash('error', 'ログインが必要です。');
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

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        set_flash($key, $message);
        return null;
    }

    return get_flash($key);
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
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}


function get_query_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    if (is_array($value)) {
        return $default;
    }

    return (string)$value;
}

function is_valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
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

function get_upload_public_path(string $filename): string
{
    return 'assets/uploads/' . $filename;
}

function validate_image_upload(array $file): string
{
    if (empty($file['name']) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
        return '';
    }

    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('写真のサイズは3MB以下にしてください。');
        }
        throw new RuntimeException('写真アップロードに失敗しました。もう一度お試しください。');
    }

    if (($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('写真ファイルを正しく選択してください。');
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('写真のサイズは3MB以下にしてください。');
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('アップロード可能な画像形式は JPG / JPEG / PNG / WEBP のみです。');
    }

    $allowedMimeTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file((string)$file['tmp_name']);
    if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('画像ファイルとして確認できませんでした。JPG / JPEG / PNG / WEBP を選択してください。');
    }

    if (!in_array($extension, $allowedMimeTypes[$mimeType], true)) {
        throw new RuntimeException('ファイルの拡張子と画像形式が一致しません。');
    }

    return $extension === 'jpeg' ? 'jpg' : $extension;
}

function save_diary_photo(array $file, int $userId): ?string
{
    $extension = validate_image_upload($file);
    if ($extension === '') {
        return null;
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('写真の保存先ディレクトリを作成できませんでした。');
    }

    $datePart = date('YmdHis');
    $randomPart = bin2hex(random_bytes(8));
    $filename = sprintf('diary_%d_%s_%s.%s', $userId, $datePart, $randomPart, $extension);
    $destination = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('写真の保存に失敗しました。');
    }

    return get_upload_public_path($filename);
}

function handle_photo_upload(array $file): ?string
{
    return save_diary_photo($file, current_user_id());
}

function delete_diary_photo(?string $photoPath): void
{
    if ($photoPath === null || trim($photoPath) === '') {
        return;
    }

    $normalizedPath = str_replace('\\', '/', $photoPath);
    $prefix = 'assets/uploads/';
    if (!str_starts_with($normalizedPath, $prefix)) {
        return;
    }

    $filename = substr($normalizedPath, strlen($prefix));
    if ($filename === '' || basename($filename) !== $filename) {
        return;
    }

    $uploadDir = realpath(UPLOAD_DIR);
    if ($uploadDir === false) {
        return;
    }

    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($targetPath)) {
        return;
    }

    $targetRealPath = realpath($targetPath);
    if ($targetRealPath === false || !str_starts_with($targetRealPath, $uploadDir . DIRECTORY_SEPARATOR)) {
        return;
    }

    unlink($targetRealPath);
}
