<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int)get_query_param('id', '0');

$stmt = db()->prepare('SELECT e.*, ec.name AS category_name, c.name AS crop_name, f.name AS field_name
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id
    LEFT JOIN crops c ON c.id = e.crop_id AND c.user_id = e.user_id
    LEFT JOIN fields f ON f.id = e.field_id AND f.user_id = e.user_id
    WHERE e.id = :id AND e.user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$expense = $stmt->fetch();

if (!$expense) {
    set_flash('error', '経費が見つかりません。');
    redirect('expense_list.php');
}

$pageTitle = '経費詳細 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>経費詳細</h2>
  <dl class="detail-grid">
    <dt>支払日</dt><dd><?= e($expense['expense_date']) ?></dd>
    <dt>経費カテゴリ</dt><dd><?= e($expense['category_name'] ?? '未分類') ?></dd>
    <dt>作物</dt><dd><?= e($expense['crop_name'] ?? '-') ?></dd>
    <dt>圃場</dt><dd><?= e($expense['field_name'] ?? '-') ?></dd>
    <dt>支払先</dt><dd><?= e($expense['payee'] ?? '-') ?></dd>
    <dt>内容</dt><dd><?= nl2br(e($expense['description'])) ?></dd>
    <dt>金額</dt><dd><strong><?= e(format_yen((int)$expense['amount'])) ?></strong></dd>
    <dt>支払方法</dt><dd><?= e($expense['payment_method'] ?? '-') ?></dd>
    <dt>メモ</dt><dd><?= $expense['memo'] !== null && $expense['memo'] !== '' ? nl2br(e($expense['memo'])) : '-' ?></dd>
    <dt>作成日時</dt><dd><?= e($expense['created_at']) ?></dd>
    <dt>更新日時</dt><dd><?= e($expense['updated_at']) ?></dd>
  </dl>
</section>

<section class="card">
  <h3>領収書写真</h3>
  <?php if (!empty($expense['receipt_path'])): ?>
    <div class="photo-box"><img class="diary-photo" src="<?= e($expense['receipt_path']) ?>" alt="領収書写真"></div>
  <?php else: ?>
    <p class="description">領収書写真なし</p>
  <?php endif; ?>
</section>

<section class="card">
  <div class="button-row">
    <a class="btn primary" href="expense_edit.php?id=<?= (int)$expense['id'] ?>">編集する</a>
    <a class="btn" href="expense_list.php">一覧へ戻る</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
