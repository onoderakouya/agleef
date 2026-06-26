<?php
require_once __DIR__ . '/includes/auth.php';

$userId = is_logged_in() ? current_user_id() : null;
$name = is_logged_in() ? current_user_name() : '';
$email = '';
$category = '機能要望';
$message = '';

if ($userId !== null) {
    $userStmt = db()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $email = (string)($userStmt->fetchColumn() ?: '');
}

$categories = ['機能要望', '改善提案', '不具合報告', '使い方の相談', 'その他'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('contact.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $errors = [];

    if ($name === '') {
        $errors[] = 'お名前を入力してください。';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'お名前は100文字以内で入力してください。';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'メールアドレスの形式が正しくありません。';
    } elseif (mb_strlen($email) > 255) {
        $errors[] = 'メールアドレスは255文字以内で入力してください。';
    }

    if (!in_array($category, $categories, true)) {
        $errors[] = 'お問い合わせ種別を選択してください。';
    }

    if ($message === '') {
        $errors[] = '内容を入力してください。';
    } elseif (mb_strlen($message) > 3000) {
        $errors[] = '内容は3000文字以内で入力してください。';
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        $insert = db()->prepare(
            'INSERT INTO contact_requests (user_id, name, email, category, message)
             VALUES (:user_id, :name, :email, :category, :message)'
        );
        $insert->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':email' => $email !== '' ? $email : null,
            ':category' => $category,
            ':message' => $message,
        ]);

        set_flash('success', 'お問い合わせを送信しました。いただいた内容は今後の改善材料として大切に確認します。');
        redirect('contact.php');
    }
}

$pageTitle = 'お問い合わせ | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow contact-card">
  <h2>お問い合わせ</h2>
  <p class="description">AGLEEF-アグリーフ-をより使いやすくするため、機能要望・改善提案・困りごとをお聞かせください。いただいた内容は今後の改良材料として活用します。</p>

  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>お名前 <span aria-hidden="true">*</span>
      <input type="text" name="name" value="<?= e($name) ?>" maxlength="100" required>
    </label>

    <label>メールアドレス
      <input type="email" name="email" value="<?= e($email) ?>" maxlength="255" placeholder="返信が必要な場合は入力してください">
      <span class="form-help">未入力でも送信できます。返信が必要な内容の場合は入力してください。</span>
    </label>

    <label>お問い合わせ種別 <span aria-hidden="true">*</span>
      <select name="category" required>
        <?php foreach ($categories as $option): ?>
          <option value="<?= e($option) ?>" <?= e((string)($category === $option ? 'selected' : '')) ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>内容 <span aria-hidden="true">*</span>
      <textarea name="message" maxlength="3000" required placeholder="例：日誌入力で追加してほしい項目、使いづらい画面、集計で見たい数字など"><?= e($message) ?></textarea>
      <span class="form-help">3000文字以内で入力してください。</span>
    </label>

    <div class="button-row">
      <button type="submit" class="primary">送信する</button>
      <?php if (is_logged_in()): ?>
        <a class="btn" href="dashboard.php">ダッシュボードへ戻る</a>
      <?php else: ?>
        <a class="btn" href="index.php">トップへ戻る</a>
      <?php endif; ?>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
