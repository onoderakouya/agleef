<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$stmt = db()->prepare(
    'SELECT cr.id, cr.name, cr.email, cr.category, cr.message, cr.created_at, u.username
     FROM contact_requests cr
     LEFT JOIN users u ON u.id = cr.user_id
     ORDER BY datetime(cr.created_at) DESC, cr.id DESC
     LIMIT 100'
);
$stmt->execute();
$contacts = $stmt->fetchAll();

$pageTitle = 'お問い合わせ一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card admin-hero">
  <h2>お問い合わせ一覧</h2>
  <p class="description">ユーザーから届いた機能要望・改善提案・困りごとを確認できます。直近100件を新しい順に表示します。</p>
  <div class="button-row">
    <a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a>
  </div>
</section>

<section class="card">
  <h3>受信内容</h3>
  <div class="table-wrap">
    <table class="contact-table">
      <thead>
        <tr>
          <th>日時</th>
          <th>種別</th>
          <th>お名前</th>
          <th>ユーザー</th>
          <th>メール</th>
          <th>内容</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($contacts === []): ?>
          <tr><td colspan="6">お問い合わせはまだありません。</td></tr>
        <?php endif; ?>
        <?php foreach ($contacts as $contact): ?>
          <tr>
            <td data-label="日時"><?= e($contact['created_at']) ?></td>
            <td data-label="種別"><?= e($contact['category']) ?></td>
            <td data-label="お名前"><?= e($contact['name']) ?></td>
            <td data-label="ユーザー"><?= e($contact['username'] ?? '未ログイン') ?></td>
            <td data-label="メール"><?= e($contact['email'] ?? '-') ?></td>
            <td data-label="内容"><?= nl2br(e($contact['message'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
