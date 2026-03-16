<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$editingField = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', '不正なリクエストです。');
        redirect('fields.php');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($action === 'create' || $action === 'update') {
        if ($name === '') {
            flash('error', '圃場名を入力してください。');
            redirect('fields.php');
        }
    }

    if ($action === 'create') {
        $stmt = db()->prepare('INSERT INTO fields (user_id, name, created_at, updated_at) VALUES (:user_id, :name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([':user_id' => $userId, ':name' => $name]);
        flash('success', '圃場を登録しました。');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE fields SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':name' => $name, ':id' => $id, ':user_id' => $userId]);
        flash('success', '圃場を更新しました。');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM fields WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        flash('success', '圃場を削除しました。');
    }

    redirect('fields.php');
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = db()->prepare('SELECT id, name FROM fields WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $id, ':user_id' => $userId]);
    $editingField = $stmt->fetch();
}

$fields = get_user_fields($userId);

$pageTitle = '圃場管理 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>圃場管理</h2>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editingField ? 'update' : 'create' ?>">
    <?php if ($editingField): ?>
      <input type="hidden" name="id" value="<?= (int)$editingField['id'] ?>">
    <?php endif; ?>
    <label>圃場名
      <input type="text" name="name" value="<?= e($editingField['name'] ?? '') ?>" placeholder="例: 1号ハウス" required>
    </label>
    <div class="button-row">
      <button class="primary" type="submit"><?= $editingField ? '更新する' : '登録する' ?></button>
      <?php if ($editingField): ?><a class="btn" href="fields.php">キャンセル</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="card">
  <h3>登録済み圃場</h3>
  <ul class="list">
    <?php foreach ($fields as $field): ?>
      <li>
        <span><?= e($field['name']) ?></span>
        <div class="inline-actions">
          <a class="btn small" href="fields.php?edit=<?= (int)$field['id'] ?>">編集</a>
          <form method="post" onsubmit="return confirm('削除してよいですか？');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$field['id'] ?>">
            <button type="submit" class="btn danger small">削除</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (!$fields): ?>
      <li>まだ圃場が登録されていません。</li>
    <?php endif; ?>
  </ul>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
