<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$stmt = db()->prepare('SELECT id, username, email, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => current_user_id()]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'ユーザー情報が見つかりません。もう一度ログインしてください。');
    redirect('logout.php');
}

$pageTitle = 'アカウント情報 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>アカウント情報</h2>
  <p class="description">ログイン中のユーザー情報を確認できます。ユーザー名やパスワードを変更する場合は、下のボタンから操作してください。</p>

  <dl class="detail-grid account-detail">
    <dt>ユーザーID</dt>
    <dd><?= e((string)$user['id']) ?></dd>
    <dt>ユーザー名</dt>
    <dd><?= e($user['username']) ?></dd>
    <dt>メールアドレス</dt>
    <dd><?= e($user['email'] ?? '') ?></dd>
    <dt>登録日時</dt>
    <dd><?= e($user['created_at']) ?></dd>
    <dt>更新日時</dt>
    <dd><?= e($user['updated_at'] ?? $user['created_at']) ?></dd>
  </dl>

  <div class="button-row">
    <a class="btn primary" href="account_edit.php">登録情報を変更する</a>
    <a class="btn" href="password_change.php">パスワードを変更する</a>
    <a class="btn" href="dashboard.php">ダッシュボードへ戻る</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
