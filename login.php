<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('login.php');
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        set_flash('error', 'ユーザー名とパスワードを入力してください。');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT id, username, password_hash, is_admin FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (int)$user['is_admin'];
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
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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

  <?php if (is_registration_allowed()): ?>
    <div class="auth-links">
      <p>アカウントをお持ちでない方</p>
      <a href="register.php">新規登録はこちら</a>
    </div>
  <?php endif; ?>

  <p class="description">デモ用: <code>demo</code> / <code>password</code></p>

  <nav class="auth-guide-links" aria-label="ログイン前の案内リンク">
    <a href="guide.php">使い方</a>
    <a href="faq.php">よくある質問</a>
    <a href="contact.php">お問い合わせ</a>
    <a href="privacy.php">プライバシーポリシー</a>
    <a href="terms.php">利用規約</a>
  </nav>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
