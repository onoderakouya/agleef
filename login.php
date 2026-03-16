<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', '不正なリクエストです。');
        redirect('login.php');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        flash('success', 'ログインしました。');
        redirect('dashboard.php');
    }

    flash('error', 'ユーザー名またはパスワードが違います。');
    redirect('login.php');
}

$pageTitle = 'ログイン | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>ログイン</h2>
  <p class="description">まずはログインしてください。</p>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>ユーザー名
      <input type="text" name="username" required>
    </label>
    <label>パスワード
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="primary">ログイン</button>
  </form>
  <p class="description">初期ユーザー: <code>demo</code> / <code>password</code></p>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
