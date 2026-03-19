<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .layout { max-width: 860px; margin: 0 auto; padding: 0 16px; }
    .site-header { position: static; }
    .site-header nav { display: flex; gap: 8px; flex-wrap: wrap; }
    .site-footer { text-align: center; color: #607268; font-size: 13px; padding: 20px 0 30px; }
    .badge { background: #e8f6ee; color: #1f6b46; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
  </style>
</head>
<body>
<header class="site-header">
  <div class="layout nav-wrap">
    <h1><a href="index.php"><?= e(APP_NAME) ?></a></h1>
    <nav>
      <?php if (is_logged_in()): ?>
        <span class="badge">こんにちは、<?= e(current_user_name()) ?> さん</span>
        <a class="btn small" href="dashboard.php">ダッシュボード</a>
        <a class="btn small danger" href="logout.php">ログアウト</a>
      <?php else: ?>
        <a class="btn small primary" href="login.php">ログイン</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="layout">
<?php if ($msg = get_flash('success')): ?>
  <p class="alert success"><?= e($msg) ?></p>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
  <p class="alert error"><?= e($msg) ?></p>
<?php endif; ?>
