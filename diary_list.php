<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_list.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $delete = db()->prepare('DELETE FROM diaries WHERE id = :id AND user_id = :user_id');
    $delete->execute([':id' => $id, ':user_id' => $userId]);

    set_flash('success', '日誌を削除しました。');
    redirect('diary_list.php');
}

$stmt = db()->prepare(
    'SELECT id, work_date, weather, work_content, created_at
     FROM diaries
     WHERE user_id = :user_id
     ORDER BY work_date DESC, id DESC'
);
$stmt->execute([':user_id' => $userId]);
$diaries = $stmt->fetchAll();

$pageTitle = '日誌一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <div class="button-row" style="justify-content: space-between; margin-top: 0;">
    <h2 style="margin-bottom:0;">日誌一覧</h2>
    <a class="btn primary" href="diary_create.php">＋ 新規登録</a>
  </div>
  <p class="description">ログイン中のユーザーの日誌のみ表示されます。</p>

  <div class="table-wrap">
    <table class="diary-table">
      <thead>
        <tr>
          <th>作業日</th>
          <th>天気</th>
          <th>作業内容</th>
          <th>登録日時</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$diaries): ?>
          <tr><td colspan="5">日誌がまだありません。</td></tr>
        <?php else: ?>
          <?php foreach ($diaries as $row): ?>
            <tr>
              <td data-label="作業日"><?= e($row['work_date']) ?></td>
              <td data-label="天気"><?= e($row['weather'] ?: '-') ?></td>
              <td data-label="作業内容"><?= nl2br(e($row['work_content'])) ?></td>
              <td data-label="登録日時"><?= e($row['created_at']) ?></td>
              <td data-label="操作" class="actions-cell">
                <div class="inline-actions">
                  <a class="btn small" href="diary_edit.php?id=<?= (int)$row['id'] ?>">編集</a>
                  <form method="post" onsubmit="return confirm('この日誌を削除しますか？');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="btn small danger" type="submit">削除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
