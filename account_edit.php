<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$stmt = db()->prepare('SELECT id, username, email, password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => current_user_id()]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('error', 'ユーザー情報が見つかりません。もう一度ログインしてください。');
    redirect('logout.php');
}

$newUsername = $user['username'];
$newEmail = $user['email'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。もう一度お試しください。';
    }

    $newUsername = trim((string)($_POST['username'] ?? ''));
    $newEmail = trim((string)($_POST['email'] ?? ''));
    $newEmail = function_exists('mb_strtolower') ? mb_strtolower($newEmail, 'UTF-8') : strtolower($newEmail);
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $usernameLength = function_exists('mb_strlen') ? mb_strlen($newUsername, 'UTF-8') : strlen($newUsername);
    $emailLength = function_exists('mb_strlen') ? mb_strlen($newEmail, 'UTF-8') : strlen($newEmail);

    if ($newUsername === '') {
        $errors[] = '新しいユーザー名を入力してください。';
    } elseif ($usernameLength < 3 || $usernameLength > 50) {
        $errors[] = 'ユーザー名は3文字以上50文字以内で入力してください。';
    }

    if ($newEmail === '') {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    } elseif ($emailLength > 255) {
        $errors[] = 'メールアドレスは255文字以内で入力してください。';
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

    if ($errors === [] && strcasecmp($newEmail, (string)($user['email'] ?? '')) !== 0) {
        $duplicate = db()->prepare("SELECT COUNT(*) FROM users WHERE lower(email) = lower(:email) AND email <> '' AND id <> :id");
        $duplicate->execute([
            ':email' => $newEmail,
            ':id' => current_user_id(),
        ]);

        if ((int)$duplicate->fetchColumn() > 0) {
            $errors[] = 'このメールアドレスはすでに登録されています。';
        }
    }

    if ($errors === []) {
        if ($newUsername === $user['username'] && $newEmail === (string)($user['email'] ?? '')) {
            set_flash('success', '登録情報に変更はありません。');
            redirect('account.php');
        }

        $update = db()->prepare('UPDATE users SET username = :username, email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([
            ':username' => $newUsername,
            ':email' => $newEmail,
            ':id' => current_user_id(),
        ]);

        $_SESSION['username'] = $newUsername;
        set_flash('success', '登録情報を変更しました。');
        redirect('account.php');
    }
}

$pageTitle = '登録情報変更 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>登録情報変更</h2>
  <p class="description">新しいユーザー名とメールアドレスを入力してください。安全のため、変更には現在のパスワード確認が必要です。</p>

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
      メールアドレス
      <input type="email" name="email" value="<?= e($newEmail) ?>" autocomplete="email" maxlength="255" required>
    </label>

    <label>
      現在のパスワード
      <input type="password" name="current_password" autocomplete="current-password" required>
    </label>

    <div class="button-row">
      <button class="primary" type="submit">登録情報を変更する</button>
      <a class="btn" href="account.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
