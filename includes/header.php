<?php
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container nav-wrap">
    <h1><a href="dashboard.php"><?= e(APP_NAME) ?></a></h1>
    <?php if (is_logged_in()): ?>
      <nav>
        <a href="dashboard.php">ホーム</a>
        <a href="diary_list.php">日誌一覧</a>
        <a href="diary_create.php">記録する</a>
        <a href="crops.php">作物</a>
        <a href="fields.php">圃場</a>
        <a href="logout.php">ログアウト</a>
      </nav>
    <?php endif; ?>
  </div>
</header>
<main class="container">
<?php if ($flashSuccess = flash('success')): ?>
  <p class="alert success"><?= e($flashSuccess) ?></p>
<?php endif; ?>
<?php if ($flashError = flash('error')): ?>
  <p class="alert error"><?= e($flashError) ?></p>
<?php endif; ?>
