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
</head>
<body>
<header class="site-header">
  <div class="layout nav-wrap">
    <h1 class="brand-heading">
      <a href="<?= is_logged_in() ? 'dashboard.php' : 'index.php' ?>" class="brand">
        <span class="brand-title">AGLEEF-アグリーフ-</span>
        <span class="brand-subtitle">農業日誌アプリ</span>
      </a>
    </h1>
    <nav>
      <?php if (is_logged_in()): ?>
        <a class="btn small" href="dashboard.php">ダッシュボード</a>
        <a class="btn small" href="diary_list.php">日誌一覧</a>
        <a class="btn small" href="crops.php">作物管理</a>
        <a class="btn small" href="fields.php">圃場管理</a>
        <a class="btn small" href="expense_list.php">経費管理</a>
        <a class="btn small" href="sale_list.php">売上管理</a>
        <a class="btn small" href="annual_summary.php">年間集計</a>
        <a class="btn small" href="export.php">CSV出力</a>
        <?php if (current_user_is_admin()): ?>
          <a class="btn small admin-link" href="admin_dashboard.php">管理画面</a>
        <?php endif; ?>
        <a class="btn small" href="account.php">アカウント</a>
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
