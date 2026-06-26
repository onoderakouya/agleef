<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$userIdParam = get_query_param('user_id');
if ($userIdParam === '' || !ctype_digit($userIdParam) || (int)$userIdParam <= 0) {
    set_flash('error', 'ユーザーIDが正しくありません。');
    redirect('admin_users.php');
}
$targetUserId = (int)$userIdParam;
$pdo = db();

$userStmt = $pdo->prepare('SELECT id, username, email, is_admin, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
$userStmt->execute([':id' => $targetUserId]);
$user = $userStmt->fetch();

if (!$user) {
    $pageTitle = 'ユーザーが見つかりません | 管理者画面 | ' . APP_NAME;
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="card admin-hero">
      <h2>ユーザーが見つかりません</h2>
      <p class="alert error">指定されたユーザーIDのユーザーは存在しません。</p>
      <div class="button-row">
        <a class="btn primary" href="admin_users.php">ユーザー一覧へ戻る</a>
        <a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a>
      </div>
    </section>
    <?php include __DIR__ . '/includes/footer.php'; ?>
    <?php
    exit;
}

$countTables = [
    'diary_count' => 'diaries',
    'crop_count' => 'crops',
    'field_count' => 'fields',
    'expense_count' => 'expenses',
    'sale_count' => 'sales',
];
$counts = [];
foreach ($countTables as $key => $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $targetUserId]);
    $counts[$key] = (int)$stmt->fetchColumn();
}

$expenseTotalStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id');
$expenseTotalStmt->execute([':user_id' => $targetUserId]);
$expenseTotal = (int)$expenseTotalStmt->fetchColumn();

$saleTotalStmt = $pdo->prepare('SELECT COALESCE(SUM(gross_amount), 0) FROM sales WHERE user_id = :user_id');
$saleTotalStmt->execute([':user_id' => $targetUserId]);
$saleTotal = (int)$saleTotalStmt->fetchColumn();

$latestDiaryStmt = $pdo->prepare(
    'SELECT d.id, d.work_date, d.weather, d.work_content, c.name AS crop_name, f.name AS field_name
     FROM diaries d
     LEFT JOIN crops c ON c.id = d.crop_id
     LEFT JOIN fields f ON f.id = d.field_id
     WHERE d.user_id = :user_id
     ORDER BY d.work_date DESC, datetime(d.created_at) DESC, d.id DESC
     LIMIT 5'
);
$latestDiaryStmt->execute([':user_id' => $targetUserId]);
$latestDiaries = $latestDiaryStmt->fetchAll();

$latestExpenseStmt = $pdo->prepare(
    'SELECT e.id, e.expense_date, e.description, e.amount, ec.name AS category_name
     FROM expenses e
     LEFT JOIN expense_categories ec ON ec.id = e.category_id
     WHERE e.user_id = :user_id
     ORDER BY e.expense_date DESC, datetime(e.created_at) DESC, e.id DESC
     LIMIT 5'
);
$latestExpenseStmt->execute([':user_id' => $targetUserId]);
$latestExpenses = $latestExpenseStmt->fetchAll();

$latestSaleStmt = $pdo->prepare(
    'SELECT id, sale_date, product_name, buyer, gross_amount, COALESCE(net_amount, gross_amount - fee_amount - shipping_amount) AS net_amount
     FROM sales
     WHERE user_id = :user_id
     ORDER BY sale_date DESC, datetime(created_at) DESC, id DESC
     LIMIT 5'
);
$latestSaleStmt->execute([':user_id' => $targetUserId]);
$latestSales = $latestSaleStmt->fetchAll();

$truncate = static function (?string $value, int $length = 80): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $length ? mb_substr($value, 0, $length, 'UTF-8') . '…' : $value;
    }
    return strlen($value) > $length ? substr($value, 0, $length) . '…' : $value;
};

$pageTitle = 'ユーザー概要 | 管理者画面 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card admin-hero">
  <h2>管理者専用：ユーザー概要</h2>
  <p class="description">ユーザーの基本情報と利用状況の概要です。詳細な個人データやパスワード情報は表示しません。</p>
  <div class="button-row">
    <a class="btn" href="admin_users.php">ユーザー一覧へ戻る</a>
    <a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a>
  </div>
</section>

<section class="card">
  <h3>基本情報</h3>
  <dl class="detail-grid admin-detail-grid">
    <dt>ユーザーID</dt><dd><?= e((string)$user['id']) ?></dd>
    <dt>ユーザー名</dt><dd><?= e($user['username']) ?></dd>
    <dt>メールアドレス</dt><dd><?= e($user['email'] ?? '-') ?></dd>
    <dt>権限</dt><dd><span class="badge <?= e((string)(((int)$user['is_admin'] === 1) ? 'badge-admin' : 'badge-user')) ?>"><?= e((string)(((int)$user['is_admin'] === 1) ? '管理者' : '一般ユーザー')) ?></span></dd>
    <dt>登録日時</dt><dd><?= e($user['created_at']) ?></dd>
    <dt>更新日時</dt><dd><?= e($user['updated_at'] ?? '-') ?></dd>
  </dl>
</section>

<section class="card">
  <h3>利用状況サマリー</h3>
  <div class="summary-grid admin-summary-grid">
    <div class="summary-card"><span>日誌件数</span><strong><?= e((string)$counts['diary_count']) ?></strong></div>
    <div class="summary-card"><span>作物件数</span><strong><?= e((string)$counts['crop_count']) ?></strong></div>
    <div class="summary-card"><span>圃場件数</span><strong><?= e((string)$counts['field_count']) ?></strong></div>
    <div class="summary-card"><span>経費件数</span><strong><?= e((string)$counts['expense_count']) ?></strong></div>
    <div class="summary-card"><span>売上件数</span><strong><?= e((string)$counts['sale_count']) ?></strong></div>
    <div class="summary-card"><span>経費合計</span><strong><?= e(format_yen($expenseTotal)) ?></strong></div>
    <div class="summary-card"><span>売上合計</span><strong><?= e(format_yen($saleTotal)) ?></strong></div>
  </div>
</section>

<section class="card">
  <h3>最新日誌5件の概要</h3>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>作業日</th><th>天気</th><th>作物</th><th>圃場</th><th>概要</th></tr></thead>
      <tbody>
        <?php if ($latestDiaries === []): ?><tr><td colspan="6">日誌はまだありません。</td></tr><?php endif; ?>
        <?php foreach ($latestDiaries as $diary): ?>
          <tr>
            <td data-label="ID"><?= e((string)$diary['id']) ?></td>
            <td data-label="作業日"><?= e($diary['work_date']) ?></td>
            <td data-label="天気"><?= e($diary['weather'] ?? '-') ?></td>
            <td data-label="作物"><?= e($diary['crop_name'] ?? '-') ?></td>
            <td data-label="圃場"><?= e($diary['field_name'] ?? '-') ?></td>
            <td data-label="概要"><?= e($truncate($diary['work_content'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h3>最新経費5件の概要</h3>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>支払日</th><th>カテゴリ</th><th>内容</th><th>金額</th></tr></thead>
      <tbody>
        <?php if ($latestExpenses === []): ?><tr><td colspan="5">経費はまだありません。</td></tr><?php endif; ?>
        <?php foreach ($latestExpenses as $expense): ?>
          <tr>
            <td data-label="ID"><?= e((string)$expense['id']) ?></td>
            <td data-label="支払日"><?= e($expense['expense_date']) ?></td>
            <td data-label="カテゴリ"><?= e($expense['category_name'] ?? '-') ?></td>
            <td data-label="内容"><?= e($truncate($expense['description'] ?? '')) ?></td>
            <td data-label="金額"><?= e(format_yen((int)$expense['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h3>最新売上5件の概要</h3>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>売上日</th><th>品目</th><th>販売先</th><th>売上総額</th><th>差引入金額</th></tr></thead>
      <tbody>
        <?php if ($latestSales === []): ?><tr><td colspan="6">売上はまだありません。</td></tr><?php endif; ?>
        <?php foreach ($latestSales as $sale): ?>
          <tr>
            <td data-label="ID"><?= e((string)$sale['id']) ?></td>
            <td data-label="売上日"><?= e($sale['sale_date']) ?></td>
            <td data-label="品目"><?= e($truncate($sale['product_name'] ?? '', 40)) ?></td>
            <td data-label="販売先"><?= e($truncate($sale['buyer'] ?? '', 40)) ?></td>
            <td data-label="売上総額"><?= e(format_yen((int)$sale['gross_amount'])) ?></td>
            <td data-label="差引入金額"><?= e(format_yen((int)$sale['net_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
