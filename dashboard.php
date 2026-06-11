<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);

$monthStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$monthStmt->execute([':user_id' => $userId, ':start' => date('Y-m-01'), ':end' => date('Y-m-t')]);
$monthExpenseTotal = (int)$monthStmt->fetchColumn();

$yearStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$yearStmt->execute([':user_id' => $userId, ':start' => date('Y-01-01'), ':end' => date('Y-12-31')]);
$yearExpenseTotal = (int)$yearStmt->fetchColumn();

$pageTitle = 'ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>ダッシュボード</h2>
  <p class="description">日誌・作物・圃場・経費を管理できます。ログイン中のユーザーのデータだけが表示されます。</p>
  <p><strong><?= e(current_user_name()) ?></strong> さん、ようこそ！</p>
</section>

<section class="card">
  <h3>経費サマリー</h3>
  <div class="summary-grid">
    <div class="summary-card"><span>今月の経費合計</span><strong><?= e(format_yen($monthExpenseTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の経費合計</span><strong><?= e(format_yen($yearExpenseTotal)) ?></strong></div>
  </div>
</section>

<section class="card">
  <h3>メニュー</h3>
  <div class="button-row dashboard-actions">
    <a class="btn primary" href="diary_create.php">＋ 日誌登録</a>
    <a class="btn" href="diary_list.php">日誌一覧</a>
    <a class="btn" href="crops.php">作物管理</a>
    <a class="btn" href="fields.php">圃場管理</a>
    <a class="btn primary" href="expense_create.php">＋ 経費を登録する</a>
    <a class="btn" href="expense_list.php">経費一覧を見る</a>
    <a class="btn" href="expense_category.php">経費カテゴリを管理する</a>
    <a class="btn" href="account.php">アカウント情報を確認する</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
