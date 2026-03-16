<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();

$countStmt = db()->prepare('SELECT COUNT(*) FROM diaries WHERE user_id = :user_id');
$countStmt->execute([':user_id' => $userId]);
$diaryCount = (int)$countStmt->fetchColumn();

$latestStmt = db()->prepare(
    'SELECT d.id, d.date, c.name AS crop_name, f.name AS field_name, d.work_content
     FROM diaries d
     LEFT JOIN crops c ON c.id = d.crop_id
     LEFT JOIN fields f ON f.id = d.field_id
     WHERE d.user_id = :user_id
     ORDER BY d.date DESC, d.id DESC
     LIMIT 5'
);
$latestStmt->execute([':user_id' => $userId]);
$latestDiaries = $latestStmt->fetchAll();

$pageTitle = 'ホーム | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>ようこそ、<?= e(current_user_name()) ?>さん</h2>
  <p class="description">今日も現場記録をすばやく残しましょう。</p>
  <div class="button-row">
    <a class="btn primary" href="diary_create.php">＋ 日誌を記録する</a>
    <a class="btn" href="diary_list.php">日誌一覧を見る</a>
  </div>
  <p><strong>登録済み日誌:</strong> <?= $diaryCount ?> 件</p>
</section>

<section class="card">
  <h3>最新の日誌（5件）</h3>
  <?php if (!$latestDiaries): ?>
    <p>まだ日誌がありません。<a href="diary_create.php">最初の記録を作成</a>してください。</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>日付</th><th>作物</th><th>圃場</th><th>作業内容</th></tr>
        </thead>
        <tbody>
        <?php foreach ($latestDiaries as $row): ?>
          <tr>
            <td><?= e($row['date']) ?></td>
            <td><?= e($row['crop_name'] ?? '-') ?></td>
            <td><?= e($row['field_name'] ?? '-') ?></td>
            <td><a href="diary_detail.php?id=<?= (int)$row['id'] ?>"><?= e($row['work_content']) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
