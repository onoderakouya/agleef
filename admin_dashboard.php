<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();

$totalUsersStmt = $pdo->prepare('SELECT COUNT(*) FROM users');
$totalUsersStmt->execute();
$totalUsers = (int)$totalUsersStmt->fetchColumn();

$todayUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE date(created_at) = date('now')");
$todayUsersStmt->execute();
$todayUsers = (int)$todayUsersStmt->fetchColumn();

$monthUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')");
$monthUsersStmt->execute();
$monthUsers = (int)$monthUsersStmt->fetchColumn();

$adminUsersStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_admin = 1');
$adminUsersStmt->execute();
$adminUsers = (int)$adminUsersStmt->fetchColumn();
$regularUsers = max(0, $totalUsers - $adminUsers);

$latestUsersStmt = $pdo->prepare(
    'SELECT id, username, is_admin, created_at, updated_at
     FROM users
     ORDER BY datetime(created_at) DESC, id DESC
     LIMIT 5'
);
$latestUsersStmt->execute();
$latestUsers = $latestUsersStmt->fetchAll();

$pageTitle = '管理者ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card admin-hero">
  <h2>管理者ダッシュボード</h2>
  <p class="description">運営者専用の管理画面です。登録ユーザー数と最新登録ユーザーの概要を確認できます。</p>
  <div class="button-row">
    <a class="btn primary" href="admin_users.php">ユーザー一覧を見る</a>
    <a class="btn primary" href="admin_contacts.php">お問い合わせ一覧を見る</a>
    <a class="btn" href="dashboard.php">通常ダッシュボードへ戻る</a>
  </div>
</section>

<section class="card">
  <h3>ユーザーサマリー</h3>
  <div class="summary-grid admin-summary-grid">
    <div class="summary-card"><span>総登録ユーザー数</span><strong><?= e((string)$totalUsers) ?></strong></div>
    <div class="summary-card"><span>今日登録されたユーザー数</span><strong><?= e((string)$todayUsers) ?></strong></div>
    <div class="summary-card"><span>今月登録されたユーザー数</span><strong><?= e((string)$monthUsers) ?></strong></div>
    <div class="summary-card"><span>管理者ユーザー数</span><strong><?= e((string)$adminUsers) ?></strong></div>
    <div class="summary-card"><span>一般ユーザー数</span><strong><?= e((string)$regularUsers) ?></strong></div>
  </div>
</section>

<section class="card">
  <h3>最新登録ユーザー5件</h3>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ユーザーID</th>
          <th>ユーザー名</th>
          <th>登録日時</th>
          <th>更新日時</th>
          <th>権限</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($latestUsers === []): ?>
          <tr><td colspan="5">登録ユーザーはまだいません。</td></tr>
        <?php endif; ?>
        <?php foreach ($latestUsers as $user): ?>
          <tr>
            <td data-label="ユーザーID"><?= e((string)$user['id']) ?></td>
            <td data-label="ユーザー名"><?= e($user['username']) ?></td>
            <td data-label="登録日時"><?= e($user['created_at']) ?></td>
            <td data-label="更新日時"><?= e($user['updated_at'] ?? '-') ?></td>
            <td data-label="権限"><span class="badge <?= e((string)(((int)$user['is_admin'] === 1) ? 'badge-admin' : 'badge-user')) ?>"><?= e((string)(((int)$user['is_admin'] === 1) ? '管理者' : '一般ユーザー')) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
