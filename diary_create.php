<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$workDate = date('Y-m-d');
$weather = '';
$workContent = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_create.php');
    }

    $workDate = trim($_POST['work_date'] ?? '');
    $weather = trim($_POST['weather'] ?? '');
    $workContent = trim($_POST['work_content'] ?? '');

    if ($workDate === '' || $workContent === '') {
        set_flash('error', '作業日と作業内容は必須です。');
    } else {
        $insert = db()->prepare(
            'INSERT INTO diaries (user_id, work_date, weather, work_content)
             VALUES (:user_id, :work_date, :weather, :work_content)'
        );
        $insert->execute([
            ':user_id' => current_user_id(),
            ':work_date' => $workDate,
            ':weather' => $weather !== '' ? $weather : null,
            ':work_content' => $workContent,
        ]);

        set_flash('success', '日誌を登録しました。');
        redirect('diary_list.php');
    }
}

$pageTitle = '日誌登録 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>日誌登録</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>作業日
      <input type="date" name="work_date" value="<?= e($workDate) ?>" required>
    </label>

    <label>天気
      <input type="text" name="weather" value="<?= e($weather) ?>" placeholder="例: 晴れ / 曇り / 雨">
    </label>

    <label>作業内容
      <textarea name="work_content" rows="5" required><?= e($workContent) ?></textarea>
    </label>

    <div class="button-row">
      <button class="primary" type="submit">登録</button>
      <a class="btn" href="diary_list.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
