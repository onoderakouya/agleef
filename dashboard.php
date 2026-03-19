<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>ダッシュボード</h2>
  <p class="description">ログイン状態でのみ表示されるページです。</p>
  <p><strong><?= e(current_user_name()) ?></strong> さん、ようこそ！</p>
</section>

<section class="card">
  <h3>次にできること</h3>
  <ul>
    <li>共通レイアウトでページを追加する</li>
    <li>SQLiteテーブルを増やしてデータ管理を拡張する</li>
    <li>このページをホームとして機能追加する</li>
  </ul>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
