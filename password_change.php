<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => current_user_id()]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'ユーザー情報が見つかりません。もう一度ログインしてください。');
    redirect('logout.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    }

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

    if ($currentPassword === '') {
        $errors[] = '現在のパスワードを入力してください。';
    }

    if ($newPassword === '') {
        $errors[] = '新しいパスワードを入力してください。';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = '新しいパスワードは8文字以上で入力してください。';
    }

    if ($newPasswordConfirm === '') {
        $errors[] = '新しいパスワード確認を入力してください。';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $errors[] = '新しいパスワードと確認用パスワードが一致しません。';
    }

    if ($currentPassword !== '' && !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = '現在のパスワードが正しくありません。';
    }

    if ($currentPassword !== '' && $newPassword !== '' && $currentPassword === $newPassword) {
        $errors[] = '新しいパスワードは現在のパスワードと別のものを入力してください。';
    }

    if ($errors === []) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([
            ':password_hash' => $passwordHash,
            ':id' => current_user_id(),
        ]);

        set_flash('success', 'パスワードを変更しました。');
        redirect('account.php');
    }
}

$pageTitle = 'パスワード変更 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>パスワード変更</h2>
  <p class="description">安全のため、現在のパスワードを確認してから新しいパスワードに変更します。新しいパスワードは8文字以上で入力してください。</p>

  <?php if ($errors !== []): ?>
    <div class="alert error">
      <ul class="message-list">
        <?php foreach ($errors as $error): ?>
          <li><?= e($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>
      現在のパスワード
      <input type="password" name="current_password" autocomplete="current-password" required>
    </label>

    <label>
      新しいパスワード
      <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
      <span class="form-help">8文字以上で入力してください。</span>
    </label>

    <label>
      新しいパスワード確認
      <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
    </label>

    <div class="button-row">
      <button class="primary" type="submit">パスワードを変更する</button>
      <a class="btn" href="account.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
