<?php
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$sent = false;
$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($subject === '') {
        $errors[] = '件名を入力してください。';
    }
    if ($message === '') {
        $errors[] = 'お問い合わせ内容を入力してください。';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    }

    if ($errors === []) {
        $stmt = db()->prepare('INSERT INTO contacts (user_id, name, email, subject, message, status, created_at, updated_at)
            VALUES (:user_id, :name, :email, :subject, :message, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([
            ':user_id' => is_logged_in() ? current_user_id() : null,
            ':name' => $name !== '' ? $name : null,
            ':email' => $email !== '' ? $email : null,
            ':subject' => $subject,
            ':message' => $message,
            ':status' => '未対応',
        ]);
        $sent = true;
        $name = $email = $subject = $message = '';
    }
}

$pageTitle = 'お問い合わせ | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card public-page contact-page">
  <div class="public-hero">
    <p class="eyebrow">Contact</p>
    <h2>お問い合わせ</h2>
    <p class="description">不具合報告、使い方の質問、改善要望などを送信できます。メール送信は行わず、運営者が管理画面で確認します。</p>
  </div>

  <?php if ($sent): ?>
    <p class="alert success">お問い合わせを受け付けました。</p>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="stack contact-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>お名前<input type="text" name="name" value="<?= e($name) ?>" maxlength="100"></label>
    <label>メールアドレス<input type="email" name="email" value="<?= e($email) ?>" maxlength="255"></label>
    <label>件名 <span class="required">必須</span><input type="text" name="subject" value="<?= e($subject) ?>" maxlength="200" required></label>
    <label>お問い合わせ内容 <span class="required">必須</span><textarea name="message" rows="8" required><?= e($message) ?></textarea></label>
    <div class="button-row"><button type="submit" class="primary">送信する</button></div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
