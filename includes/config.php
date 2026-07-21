<?php
// アプリ共通設定
define('APP_NAME', 'AGRIMORE');
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads');
define('EXPENSE_UPLOAD_DIR', UPLOAD_DIR . '/expenses');
define('SALE_UPLOAD_DIR', UPLOAD_DIR . '/sales');
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB
define('CONTACT_EMAIL', 'info@runfirm.net');

// HTTPS enforcement.
// ローカル開発環境（localhost / 127.0.0.1 など）は除外し、公開環境では常時HTTPSへ統一します。
define('FORCE_HTTPS', true);
define('TRUST_PROXY_HTTPS_HEADER', true);
define('HSTS_MAX_AGE', 31536000);

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    if (TRUST_PROXY_HTTPS_HEADER) {
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            return in_array('https', array_map('trim', explode(',', $forwardedProto)), true);
        }

        $forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }
    }

    return false;
}

function is_local_development_host(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.localhost');
}

function enforce_https(): void
{
    if (!FORCE_HTTPS || PHP_SAPI === 'cli' || is_https_request() || is_local_development_host()) {
        return;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return;
    }

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $requestUri, true, 308);
    exit;
}

enforce_https();

if (is_https_request() && !headers_sent()) {
    header('Strict-Transport-Security: max-age=' . HSTS_MAX_AGE . '; includeSubDomains', false);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 新規ユーザー登録の受付可否。公開サーバーで一時停止したい場合は false に変更してください。
define('ALLOW_REGISTRATION', true);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}

if (!is_dir(EXPENSE_UPLOAD_DIR)) {
    mkdir(EXPENSE_UPLOAD_DIR, 0775, true);
}

if (!is_dir(SALE_UPLOAD_DIR)) {
    mkdir(SALE_UPLOAD_DIR, 0775, true);
}
