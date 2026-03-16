<?php
// アプリ共通設定
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', '農業日誌アプリ');
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads');
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}
