<?php
/**
 * 役割:
 * - 日誌の詳細表示ページ
 * - 日誌削除処理を実行
 * - 編集・一覧ページへの導線を表示
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('error', '日誌が見つかりません。');
    redirect('diary_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_list.php');
    }

    if (($_POST['action'] ?? '') === 'delete') {
        $photoStmt = db()->prepare('SELECT photo_path FROM diaries WHERE id = :id AND user_id = :user_id');
        $photoStmt->execute([':id' => $id, ':user_id' => $userId]);
        $photoPath = $photoStmt->fetchColumn();

        $stmt = db()->prepare('DELETE FROM diaries WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            delete_diary_photo(is_string($photoPath) ? $photoPath : null);
        }

        set_flash('success', '日誌を削除しました。');
        redirect('diary_list.php');
    }
}

$stmt = db()->prepare(
    'SELECT d.*, c.name AS crop_name, f.name AS field_name
     FROM diaries d
     LEFT JOIN crops c ON c.id = d.crop_id AND c.user_id = d.user_id
     LEFT JOIN fields f ON f.id = d.field_id AND f.user_id = d.user_id
     WHERE d.id = :id AND d.user_id = :user_id'
);
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$diary = $stmt->fetch();

if (!$diary) {
    set_flash('error', '日誌が見つかりません。');
    redirect('diary_list.php');
}

$pageTitle = '日誌詳細 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>日誌詳細</h2>
  <dl class="detail-grid">
    <dt>作業日</dt><dd><?= e($diary['work_date']) ?></dd>
    <dt>作物</dt><dd><?= e($diary['crop_name'] ?? '-') ?></dd>
    <dt>圃場</dt><dd><?= e($diary['field_name'] ?? '-') ?></dd>
    <dt>天気</dt><dd><?= e((string)($diary['weather'] ?? '-')) ?></dd>
    <dt>作業内容</dt><dd><?= nl2br(e($diary['work_content'])) ?></dd>
    <dt>写真</dt>
    <dd>
      <?php if (!empty($diary['photo_path'])): ?>
        <img class="diary-photo" src="<?= e($diary['photo_path']) ?>" alt="日誌写真">
      <?php else: ?>
        写真なし
      <?php endif; ?>
    </dd>
    <dt>作成日時</dt><dd><?= e($diary['created_at']) ?></dd>
    <dt>更新日時</dt><dd><?= e($diary['updated_at'] ?? $diary['created_at']) ?></dd>
  </dl>

  <div class="button-row">
    <a class="btn" href="diary_edit.php?id=<?= e((string)((int)$diary['id'])) ?>">編集</a>
    <a class="btn" href="diary_list.php">一覧に戻る</a>
    <form method="post" onsubmit="return confirm('この日誌を削除しますか？');">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= e((string)((int)$diary['id'])) ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn danger" type="submit">削除</button>
    </form>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
