<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? APP_NAME;
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
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
      <a href="<?= e((string)(is_logged_in() ? 'dashboard.php' : 'index.php')) ?>" class="brand">
        <span class="brand-title">AGRIMORE</span>
        <span class="brand-subtitle">農業日誌アプリ</span>
      </a>
    </h1>

    <?php if (is_logged_in()): ?>
      <button type="button" class="nav-menu-toggle" aria-expanded="false" aria-controls="main-nav">メニュー</button>
    <?php endif; ?>

    <nav id="main-nav" class="main-nav" aria-label="メインナビゲーション">
      <?php if (is_logged_in()): ?>
        <a class="nav-link" href="dashboard.php">ダッシュボード</a>

        <div class="nav-dropdown">
          <button type="button" class="nav-dropdown-toggle" aria-expanded="false">
            日誌管理 <span aria-hidden="true">▼</span>
          </button>
          <div class="nav-dropdown-menu">
            <a href="diary_list.php">日誌一覧</a>
            <a href="diary_create.php">日誌登録</a>
            <a href="crops.php">作物管理</a>
            <a href="fields.php">圃場管理</a>
          </div>
        </div>

        <div class="nav-dropdown">
          <button type="button" class="nav-dropdown-toggle" aria-expanded="false">
            経営管理 <span aria-hidden="true">▼</span>
          </button>
          <div class="nav-dropdown-menu">
            <a href="expense_list.php">経費一覧</a>
            <a href="expense_create.php">経費登録</a>
            <a href="expense_category.php">経費カテゴリ管理</a>
            <a href="sale_list.php">売上一覧</a>
            <a href="sale_create.php">売上登録</a>
            <a href="annual_summary.php">年間集計</a>
            <a href="export.php">CSV出力</a>
          </div>
        </div>

        <div class="nav-dropdown">
          <button type="button" class="nav-dropdown-toggle" aria-expanded="false">
            設定 <span aria-hidden="true">▼</span>
          </button>
          <div class="nav-dropdown-menu nav-dropdown-menu-right">
            <a href="account.php">アカウント</a>
            <a href="guide.php">使い方</a>
            <a href="faq.php">よくある質問</a>
            <a href="contact.php">お問い合わせ</a>
            <?php if (current_user_is_admin()): ?>
              <a href="admin_dashboard.php">管理画面</a>
              <a href="admin_users.php">ユーザー一覧</a>
              <a href="admin_contacts.php">問い合わせ一覧</a>
              <a href="admin_settings.php">アプリ設定</a>
              <a href="admin_logs.php">管理者操作ログ</a>
            <?php endif; ?>
          </div>
        </div>

        <a class="nav-link logout-link" href="logout.php">ログアウト</a>
      <?php else: ?>
        <a class="nav-link" href="guide.php">使い方</a>
        <a class="nav-link" href="faq.php">よくある質問</a>
        <a class="nav-link" href="contact.php">お問い合わせ</a>
        <?php if ($currentPage !== 'login.php'): ?>
          <a class="btn small primary" href="login.php">ログイン</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var menuToggle = document.querySelector('.nav-menu-toggle');
  var mainNav = document.querySelector('.main-nav');
  var dropdowns = document.querySelectorAll('.nav-dropdown');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  dropdowns.forEach(function (dropdown) {
    var button = dropdown.querySelector('.nav-dropdown-toggle');

    if (!button) {
      return;
    }

    button.addEventListener('click', function (event) {
      event.stopPropagation();
      var isOpen = dropdown.classList.toggle('is-open');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

      dropdowns.forEach(function (otherDropdown) {
        if (otherDropdown === dropdown) {
          return;
        }
        otherDropdown.classList.remove('is-open');
        var otherButton = otherDropdown.querySelector('.nav-dropdown-toggle');
        if (otherButton) {
          otherButton.setAttribute('aria-expanded', 'false');
        }
      });
    });
  });

  document.addEventListener('click', function () {
    dropdowns.forEach(function (dropdown) {
      dropdown.classList.remove('is-open');
      var button = dropdown.querySelector('.nav-dropdown-toggle');
      if (button) {
        button.setAttribute('aria-expanded', 'false');
      }
    });
  });
});
</script>
<main class="layout">
<?php if ($msg = get_flash('success')): ?>
  <p class="alert success"><?= e($msg) ?></p>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
  <p class="alert error"><?= e($msg) ?></p>
<?php endif; ?>
