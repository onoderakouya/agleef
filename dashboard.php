<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>ダッシュボード</h2>
  <p class="description">日誌・作物・圃場を管理できます。ログイン中のユーザーのデータだけが表示されます。</p>
  <p><strong><?= e(current_user_name()) ?></strong> さん、ようこそ！</p>
</section>

<section class="card">
  <h3>メニュー</h3>
  <div class="button-row dashboard-actions">
    <a class="btn primary" href="diary_create.php">＋ 日誌登録</a>
    <a class="btn" href="diary_list.php">日誌一覧</a>
    <a class="btn" href="crops.php">作物管理</a>
    <a class="btn" href="fields.php">圃場管理</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
