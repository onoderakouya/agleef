<?php
/**
 * 役割:
 * - 日誌の新規登録ページ
 * - 入力値を検証し、日誌を保存
 * - 写真1枚のアップロードを処理
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', '不正なリクエストです。');
        redirect('diary_create.php');
    }

    $date = $_POST['date'] ?? '';
    $cropId = (int)($_POST['crop_id'] ?? 0);
    $fieldId = (int)($_POST['field_id'] ?? 0);
    $workContent = trim($_POST['work_content'] ?? '');
    $memo = trim($_POST['memo'] ?? '');

    if ($date === '' || $cropId <= 0 || $fieldId <= 0 || $workContent === '') {
        flash('error', '必須項目を入力してください。');
        redirect('diary_create.php');
    }

    try {
        $cropCheck = db()->prepare('SELECT COUNT(*) FROM crops WHERE id = :id AND user_id = :user_id');
        $cropCheck->execute([':id' => $cropId, ':user_id' => $userId]);
        $fieldCheck = db()->prepare('SELECT COUNT(*) FROM fields WHERE id = :id AND user_id = :user_id');
        $fieldCheck->execute([':id' => $fieldId, ':user_id' => $userId]);

        if ((int)$cropCheck->fetchColumn() === 0 || (int)$fieldCheck->fetchColumn() === 0) {
            throw new RuntimeException('作物または圃場の選択が不正です。');
        }

        $photoPath = handle_photo_upload($_FILES['photo'] ?? []);

        $stmt = db()->prepare(
            'INSERT INTO diaries (user_id, date, crop_id, field_id, work_content, memo, photo_path, created_at, updated_at)
             VALUES (:user_id, :date, :crop_id, :field_id, :work_content, :memo, :photo_path, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':date' => $date,
            ':crop_id' => $cropId,
            ':field_id' => $fieldId,
            ':work_content' => $workContent,
            ':memo' => $memo,
            ':photo_path' => $photoPath,
        ]);

        flash('success', '日誌を登録しました。');
        redirect('diary_list.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('diary_create.php');
    }
}

$pageTitle = '日誌作成 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>日誌を記録する</h2>
  <?php if (!$crops || !$fields): ?>
    <p>日誌登録の前に<a href="crops.php">作物</a>と<a href="fields.php">圃場</a>を登録してください。</p>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>日付
      <input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" required>
    </label>
    <label>作物
      <select name="crop_id" required>
        <option value="">選択してください</option>
        <?php foreach ($crops as $crop): ?>
          <option value="<?= (int)$crop['id'] ?>"><?= e($crop['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>圃場
      <select name="field_id" required>
        <option value="">選択してください</option>
        <?php foreach ($fields as $field): ?>
          <option value="<?= (int)$field['id'] ?>"><?= e($field['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>作業内容
      <input type="text" name="work_content" placeholder="例: 収穫・灌水・誘引" required>
    </label>
    <label>メモ
      <textarea name="memo" rows="4" placeholder="気温や気づきがあれば入力"></textarea>
    </label>
    <label>写真（1枚、JPG/PNG/WEBP、3MBまで）
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    </label>
    <button class="primary" type="submit">登録する</button>
  </form>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
