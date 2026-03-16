<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$editingCrop = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', '不正なリクエストです。');
        redirect('crops.php');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($action === 'create' || $action === 'update') {
        if ($name === '') {
            flash('error', '作物名を入力してください。');
            redirect('crops.php');
        }
    }

    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO crops (user_id, name, created_at, updated_at) VALUES (:user_id, :name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([':user_id' => $userId, ':name' => $name]);
        flash('success', '作物を登録しました。');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE crops SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':name' => $name, ':id' => $id, ':user_id' => $userId]);
        flash('success', '作物を更新しました。');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM crops WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        flash('success', '作物を削除しました。');
    }

    redirect('crops.php');
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id, name FROM crops WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    $editingCrop = $stmt->fetch();
}

$crops = get_user_crops($userId);

$pageTitle = '作物管理 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>作物マスタ管理</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editingCrop ? 'update' : 'create' ?>">
    <?php if ($editingCrop): ?>
      <input type="hidden" name="id" value="<?= (int)$editingCrop['id'] ?>">
    <?php endif; ?>
    <label>作物名
      <input type="text" name="name" value="<?= e($editingCrop['name'] ?? '') ?>" placeholder="例: トマト" required>
    </label>
    <div class="button-row">
      <button class="primary" type="submit"><?= $editingCrop ? '更新する' : '登録する' ?></button>
      <?php if ($editingCrop): ?><a class="btn" href="crops.php">キャンセル</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="card">
  <h3>登録済み作物</h3>
  <ul class="list">
    <?php foreach ($crops as $crop): ?>
      <li>
        <span><?= e($crop['name']) ?></span>
        <div class="inline-actions">
          <a class="btn small" href="crops.php?edit=<?= (int)$crop['id'] ?>">編集</a>
          <form method="post" onsubmit="return confirm('削除してよいですか？');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$crop['id'] ?>">
            <button type="submit" class="btn danger small">削除</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (!$crops): ?>
      <li>まだ作物が登録されていません。</li>
    <?php endif; ?>
  </ul>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
