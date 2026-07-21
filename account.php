<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email_delivery.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { set_flash('error','不正なリクエストです。'); redirect('account.php'); }
    $value=$_POST['email_subscription'] ?? '0';
    if (!in_array($value,['0','1'],true)) { set_flash('error','配信設定が正しくありません。'); redirect('account.php'); }
    $mailStmt=db()->prepare('SELECT email FROM users WHERE id=:id'); $mailStmt->execute([':id'=>current_user_id()]);
    update_email_subscription(current_user_id(), (string)$mailStmt->fetchColumn(), $value==='1', 'account');
    set_flash('success','メール配信設定を更新しました。'); redirect('account.php');
}

$stmt = db()->prepare('SELECT id, username, email, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => current_user_id()]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'ユーザー情報が見つかりません。もう一度ログインしてください。');
    redirect('logout.php');
}
$subscription=email_subscription_for_user(current_user_id());

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
<section class="card">
  <h2>メール配信設定</h2>
  <p class="description">機能追加、メンテナンス情報、AGRIMOREの活用方法などをお送りします。</p>
  <form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label class="checkbox-label"><input type="checkbox" name="email_subscription" value="1" <?= $subscription['status']==='subscribed'?'checked':'' ?>> AGRIMOREからのお知らせを受け取る</label>
    <button class="primary" type="submit">配信設定を保存する</button>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
