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

$newUsername = $user['username'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    }

    $newUsername = trim((string)($_POST['username'] ?? ''));
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $usernameLength = function_exists('mb_strlen') ? mb_strlen($newUsername, 'UTF-8') : strlen($newUsername);

    if ($newUsername === '') {
        $errors[] = '新しいユーザー名を入力してください。';
    } elseif ($usernameLength < 3 || $usernameLength > 50) {
        $errors[] = 'ユーザー名は3文字以上50文字以内で入力してください。';
    }

    if ($currentPassword === '') {
        $errors[] = '現在のパスワードを入力してください。';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = '現在のパスワードが正しくありません。';
    }

    if ($errors === [] && $newUsername !== $user['username']) {
        $duplicate = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :username AND id <> :id');
        $duplicate->execute([
            ':username' => $newUsername,
            ':id' => current_user_id(),
        ]);

        if ((int)$duplicate->fetchColumn() > 0) {
            $errors[] = 'このユーザー名はすでに使われています。';
        }
    }

    if ($errors === []) {
        if ($newUsername === $user['username']) {
            set_flash('success', 'ユーザー名に変更はありません。');
            redirect('account.php');
        }

        $update = db()->prepare('UPDATE users SET username = :username, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([
            ':username' => $newUsername,
            ':id' => current_user_id(),
        ]);

        $_SESSION['username'] = $newUsername;
        set_flash('success', 'ユーザー名を変更しました。');
        redirect('account.php');
    }
}

$pageTitle = 'ユーザー名変更 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>ユーザー名変更</h2>
  <p class="description">新しいユーザー名を入力してください。安全のため、変更には現在のパスワード確認が必要です。</p>

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
      新しいユーザー名
      <input type="text" name="username" value="<?= e($newUsername) ?>" minlength="3" maxlength="50" required>
    </label>

    <label>
      現在のパスワード
      <input type="password" name="current_password" autocomplete="current-password" required>
    </label>

    <div class="button-row">
      <button class="primary" type="submit">ユーザー名を変更する</button>
      <a class="btn" href="account.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
