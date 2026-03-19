<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', '対象の日誌が見つかりません。');
    redirect('diary_list.php');
}

$select = db()->prepare('SELECT * FROM diaries WHERE id = :id AND user_id = :user_id');
$select->execute([':id' => $id, ':user_id' => current_user_id()]);
$diary = $select->fetch();

if (!$diary) {
    set_flash('error', '対象の日誌が見つかりません。');
    redirect('diary_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_edit.php?id=' . $id);
    }

    $workDate = trim($_POST['work_date'] ?? '');
    $weather = trim($_POST['weather'] ?? '');
    $workContent = trim($_POST['work_content'] ?? '');

    if ($workDate === '' || $workContent === '') {
        set_flash('error', '作業日と作業内容は必須です。');
        $diary['work_date'] = $workDate;
        $diary['weather'] = $weather;
        $diary['work_content'] = $workContent;
    } else {
        $update = db()->prepare(
            'UPDATE diaries
             SET work_date = :work_date,
                 weather = :weather,
                 work_content = :work_content
             WHERE id = :id AND user_id = :user_id'
        );
        $update->execute([
            ':work_date' => $workDate,
            ':weather' => $weather !== '' ? $weather : null,
            ':work_content' => $workContent,
            ':id' => $id,
            ':user_id' => current_user_id(),
        ]);

        set_flash('success', '日誌を更新しました。');
        redirect('diary_list.php');
    }
}

$pageTitle = '日誌編集 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>日誌編集</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$diary['id'] ?>">

    <label>作業日
      <input type="date" name="work_date" value="<?= e($diary['work_date']) ?>" required>
    </label>

    <label>天気
      <input type="text" name="weather" value="<?= e((string)($diary['weather'] ?? '')) ?>">
    </label>

    <label>作業内容
      <textarea name="work_content" rows="5" required><?= e($diary['work_content']) ?></textarea>
    </label>

    <div class="button-row">
      <button class="primary" type="submit">更新</button>
      <a class="btn" href="diary_list.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
