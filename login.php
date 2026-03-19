<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        set_flash('error', 'ユーザー名とパスワードを入力してください。');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        set_flash('success', 'ログインしました。');
        redirect('dashboard.php');
    }

    set_flash('error', 'ユーザー名またはパスワードが正しくありません。');
    redirect('login.php');
}

$pageTitle = 'ログイン | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>ログイン</h2>
  <p class="description">ログインしてダッシュボードを表示します。</p>

  <form method="post" class="stack">
    <label>
      ユーザー名
      <input type="text" name="username" autocomplete="username" required>
    </label>
    <label>
      パスワード
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="primary">ログイン</button>
  </form>

  <p class="description">デモ用: <code>demo</code> / <code>password</code></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
