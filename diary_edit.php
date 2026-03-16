<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM diaries WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$diary = $stmt->fetch();

if (!$diary) {
    flash('error', '日誌が見つかりません。');
    redirect('diary_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', '不正なリクエストです。');
        redirect('diary_edit.php?id=' . $id);
    }

    $date = $_POST['date'] ?? '';
    $cropId = (int)($_POST['crop_id'] ?? 0);
    $fieldId = (int)($_POST['field_id'] ?? 0);
    $workContent = trim($_POST['work_content'] ?? '');
    $memo = trim($_POST['memo'] ?? '');
    $removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';

    if ($date === '' || $cropId <= 0 || $fieldId <= 0 || $workContent === '') {
        flash('error', '必須項目を入力してください。');
        redirect('diary_edit.php?id=' . $id);
    }

    try {
        $newPhotoPath = $diary['photo_path'];

        if ($removePhoto && $newPhotoPath) {
            $fullPath = __DIR__ . '/' . $newPhotoPath;
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
            $newPhotoPath = null;
        }

        if (!empty($_FILES['photo']['name'])) {
            $uploaded = handle_photo_upload($_FILES['photo']);
            if ($newPhotoPath) {
                $oldPath = __DIR__ . '/' . $newPhotoPath;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }
            $newPhotoPath = $uploaded;
        }

        $update = db()->prepare(
            'UPDATE diaries
             SET date = :date, crop_id = :crop_id, field_id = :field_id, work_content = :work_content,
                 memo = :memo, photo_path = :photo_path, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id'
        );
        $update->execute([
            ':date' => $date,
            ':crop_id' => $cropId,
            ':field_id' => $fieldId,
            ':work_content' => $workContent,
            ':memo' => $memo,
            ':photo_path' => $newPhotoPath,
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        flash('success', '日誌を更新しました。');
        redirect('diary_detail.php?id=' . $id);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('diary_edit.php?id=' . $id);
    }
}

$crops = get_user_crops($userId);
$fields = get_user_fields($userId);

$pageTitle = '日誌編集 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>日誌を編集</h2>
  <form method="post" enctype="multipart/form-data" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>日付
      <input type="date" name="date" value="<?= e($diary['date']) ?>" required>
    </label>
    <label>作物
      <select name="crop_id" required>
        <?php foreach ($crops as $crop): ?>
          <option value="<?= (int)$crop['id'] ?>" <?= (int)$diary['crop_id'] === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>圃場
      <select name="field_id" required>
        <?php foreach ($fields as $field): ?>
          <option value="<?= (int)$field['id'] ?>" <?= (int)$diary['field_id'] === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>作業内容
      <input type="text" name="work_content" value="<?= e($diary['work_content']) ?>" required>
    </label>
    <label>メモ
      <textarea name="memo" rows="4"><?= e($diary['memo'] ?? '') ?></textarea>
    </label>

    <?php if (!empty($diary['photo_path'])): ?>
      <div class="photo-box">
        <p>現在の写真</p>
        <img src="<?= e($diary['photo_path']) ?>" alt="現在の写真">
      </div>
      <label><input type="checkbox" name="remove_photo" value="1"> 現在の写真を削除する</label>
    <?php endif; ?>

    <label>写真を差し替える（任意）
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    </label>

    <div class="button-row">
      <button class="primary" type="submit">更新する</button>
      <a class="btn" href="diary_detail.php?id=<?= (int)$diary['id'] ?>">キャンセル</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
