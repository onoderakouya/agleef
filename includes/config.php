<?php
// アプリ共通設定
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', '農業日誌アプリ');
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads');
define('EXPENSE_UPLOAD_DIR', UPLOAD_DIR . '/expenses');
define('SALE_UPLOAD_DIR', UPLOAD_DIR . '/sales');
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB

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
