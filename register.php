<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');

    if (!is_registration_allowed()) {
        $errors[] = '現在、新規登録は停止中です。';
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。';
    }

    $usernameLength = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);
    if ($username === '') {
        $errors[] = 'ユーザー名を入力してください。';
    } elseif ($usernameLength < 3 || $usernameLength > 50) {
        $errors[] = 'ユーザー名は3文字以上50文字以内で入力してください。';
    }

    if ($email === '') {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($email, 'UTF-8') : strlen($email)) > 255) {
        $errors[] = 'メールアドレスは255文字以内で入力してください。';
    }

    if ($password === '') {
        $errors[] = 'パスワードを入力してください。';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password)) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください。';
    }

    if ($passwordConfirmation === '') {
        $errors[] = 'パスワード確認を入力してください。';
    } elseif ($password !== $passwordConfirmation) {
        $errors[] = 'パスワードと確認用パスワードが一致しません。';
    }

    if ($errors === []) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);

        if ((int)$stmt->fetchColumn() > 0) {
            $errors[] = 'このユーザー名はすでに使われています。';
        }

        $emailStmt = db()->prepare("SELECT COUNT(*) FROM users WHERE lower(email) = lower(:email) AND email <> ''");
        $emailStmt->execute([':email' => $email]);

        if ((int)$emailStmt->fetchColumn() > 0) {
            $errors[] = 'このメールアドレスはすでに登録されています。';
        }
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, created_at, updated_at)
                 VALUES (:username, :email, :password_hash, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $insert->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $newUserId = (int)$pdo->lastInsertId();
            ensure_default_expense_categories($newUserId);

            $pdo->commit();
            set_flash('success', '登録が完了しました。ログインしてください。');
            redirect('login.php');
        } catch (Throwable $e) {
            $pdo->rollBack();

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $errors[] = 'このユーザー名またはメールアドレスはすでに使われています。';
            } else {
                $errors[] = '登録に失敗しました。時間をおいてもう一度お試しください。';
            }
        }
    }
}

$pageTitle = '新規登録 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow auth-card">
  <h2>新規登録</h2>
  <p class="description">アグリーフを利用するためのアカウントを作成します。</p>

  <?php if (!is_registration_allowed()): ?>
    <p class="alert error">現在、新規登録は停止中です。</p>
    <div class="button-row">
      <a class="btn" href="login.php">ログイン画面へ戻る</a>
    </div>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="alert error" role="alert">
        <ul class="message-list">
          <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="stack auth-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>
        ユーザー名
        <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" minlength="3" maxlength="50" required>
        <span class="form-help">3文字以上50文字以内で入力してください。</span>
      </label>
      <label>
        メールアドレス
        <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" maxlength="255" required>
        <span class="form-help">登録後のご案内をお届けするために使用します。</span>
      </label>
      <label>
        パスワード
        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
        <span class="form-help">8文字以上で入力してください。</span>
      </label>
      <label>
        パスワード確認
        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
      </label>
      <div class="button-row auth-actions">
        <button type="submit" class="primary">登録する</button>
        <a class="btn" href="login.php">ログイン画面へ戻る</a>
      </div>
    </form>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
