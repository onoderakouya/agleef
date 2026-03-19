<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', '対象の日誌が見つかりません。');
    redirect('diary_list.php');
}

$select = db()->prepare('SELECT * FROM diaries WHERE id = :id AND user_id = :user_id');
$select->execute([':id' => $id, ':user_id' => $userId]);
$diary = $select->fetch();

if (!$diary) {
    set_flash('error', '対象の日誌が見つかりません。');
    redirect('diary_list.php');
}

$cropStmt = db()->prepare('SELECT id, name FROM crops WHERE user_id = :user_id ORDER BY name ASC');
$cropStmt->execute([':user_id' => $userId]);
$crops = $cropStmt->fetchAll();

$fieldStmt = db()->prepare('SELECT id, name FROM fields WHERE user_id = :user_id ORDER BY name ASC');
$fieldStmt->execute([':user_id' => $userId]);
$fields = $fieldStmt->fetchAll();

$hasCrops = count($crops) > 0;
$hasFields = count($fields) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_edit.php?id=' . $id);
    }

    $workDate = trim($_POST['work_date'] ?? '');
    $weather = trim($_POST['weather'] ?? '');
    $workContent = trim($_POST['work_content'] ?? '');
    $cropId = (string)($_POST['crop_id'] ?? '');
    $fieldId = (string)($_POST['field_id'] ?? '');

    if (!$hasCrops || !$hasFields) {
        set_flash('error', '先に作物と圃場を登録してください。');
    } elseif ($workDate === '' || $workContent === '' || $cropId === '' || $fieldId === '') {
        set_flash('error', '作業日・作物・圃場・作業内容は必須です。');
    } else {
        $cropCheck = db()->prepare('SELECT COUNT(*) FROM crops WHERE id = :id AND user_id = :user_id');
        $cropCheck->execute([':id' => (int)$cropId, ':user_id' => $userId]);

        $fieldCheck = db()->prepare('SELECT COUNT(*) FROM fields WHERE id = :id AND user_id = :user_id');
        $fieldCheck->execute([':id' => (int)$fieldId, ':user_id' => $userId]);

        if ((int)$cropCheck->fetchColumn() === 0 || (int)$fieldCheck->fetchColumn() === 0) {
            set_flash('error', '選択した作物または圃場が不正です。再選択してください。');
        } else {
            $update = db()->prepare(
                'UPDATE diaries
                 SET crop_id = :crop_id,
                     field_id = :field_id,
                     work_date = :work_date,
                     weather = :weather,
                     work_content = :work_content
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                ':crop_id' => (int)$cropId,
                ':field_id' => (int)$fieldId,
                ':work_date' => $workDate,
                ':weather' => $weather !== '' ? $weather : null,
                ':work_content' => $workContent,
                ':id' => $id,
                ':user_id' => $userId,
            ]);

            set_flash('success', '日誌を更新しました。');
            redirect('diary_list.php');
        }
    }

    $diary['work_date'] = $workDate;
    $diary['weather'] = $weather;
    $diary['work_content'] = $workContent;
    $diary['crop_id'] = $cropId !== '' ? (int)$cropId : null;
    $diary['field_id'] = $fieldId !== '' ? (int)$fieldId : null;
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

    <label>作物 <span aria-hidden="true">*</span>
      <select name="crop_id" required>
        <option value="">選択してください</option>
        <?php foreach ($crops as $crop): ?>
          <option value="<?= (int)$crop['id'] ?>" <?= (int)($diary['crop_id'] ?? 0) === (int)$crop['id'] ? 'selected' : '' ?>>
            <?= e($crop['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!$hasCrops): ?>
      <p class="description">先に作物を登録してください。<a href="crops.php">作物管理へ</a></p>
    <?php endif; ?>

    <label>圃場 <span aria-hidden="true">*</span>
      <select name="field_id" required>
        <option value="">選択してください</option>
        <?php foreach ($fields as $field): ?>
          <option value="<?= (int)$field['id'] ?>" <?= (int)($diary['field_id'] ?? 0) === (int)$field['id'] ? 'selected' : '' ?>>
            <?= e($field['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!$hasFields): ?>
      <p class="description">先に圃場を登録してください。<a href="fields.php">圃場管理へ</a></p>
    <?php endif; ?>

    <label>天気
      <input type="text" name="weather" value="<?= e((string)($diary['weather'] ?? '')) ?>">
    </label>

    <label>作業内容
      <textarea name="work_content" rows="5" required><?= e($diary['work_content']) ?></textarea>
    </label>

    <div class="button-row">
      <button class="primary" type="submit" <?= !$hasCrops || !$hasFields ? 'disabled' : '' ?>>更新</button>
      <a class="btn" href="diary_list.php">戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
