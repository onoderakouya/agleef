<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);

$monthStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$monthStmt->execute([':user_id' => $userId, ':start' => date('Y-m-01'), ':end' => date('Y-m-t')]);
$monthExpenseTotal = (int)$monthStmt->fetchColumn();

$yearStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$yearStmt->execute([':user_id' => $userId, ':start' => date('Y-01-01'), ':end' => date('Y-12-31')]);
$yearExpenseTotal = (int)$yearStmt->fetchColumn();

$monthSaleStmt = db()->prepare('SELECT COALESCE(SUM(gross_amount), 0) FROM sales WHERE user_id = :user_id AND sale_date BETWEEN :start AND :end');
$monthSaleStmt->execute([':user_id' => $userId, ':start' => date('Y-m-01'), ':end' => date('Y-m-t')]);
$monthSaleTotal = (int)$monthSaleStmt->fetchColumn();

$yearSaleStmt = db()->prepare('SELECT COALESCE(SUM(gross_amount), 0) FROM sales WHERE user_id = :user_id AND sale_date BETWEEN :start AND :end');
$yearSaleStmt->execute([':user_id' => $userId, ':start' => date('Y-01-01'), ':end' => date('Y-12-31')]);
$yearSaleTotal = (int)$yearSaleStmt->fetchColumn();

$onboardingTables = [
    'crops' => 'crops',
    'fields' => 'fields',
    'diaries' => 'diaries',
    'expenses' => 'expenses',
    'sales' => 'sales',
];
$onboardingCounts = [];
foreach ($onboardingTables as $key => $table) {
    $countStmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :user_id");
    $countStmt->execute([':user_id' => $userId]);
    $onboardingCounts[$key] = (int)$countStmt->fetchColumn();
}
$hasViewedAnnualSummary = !empty($_SESSION['onboarding_' . $userId . '_annual_summary_viewed']);
$hasViewedExport = !empty($_SESSION['onboarding_' . $userId . '_export_viewed']);
$onboardingItems = [
    ['label' => '作物を登録する', 'href' => 'crops.php', 'done' => $onboardingCounts['crops'] > 0],
    ['label' => '圃場を登録する', 'href' => 'fields.php', 'done' => $onboardingCounts['fields'] > 0],
    ['label' => '日誌を登録する', 'href' => 'diary_create.php', 'done' => $onboardingCounts['diaries'] > 0],
    ['label' => '経費を登録する', 'href' => 'expense_create.php', 'done' => $onboardingCounts['expenses'] > 0],
    ['label' => '売上を登録する', 'href' => 'sale_create.php', 'done' => $onboardingCounts['sales'] > 0],
    ['label' => '年間集計を見る', 'href' => 'annual_summary.php', 'done' => $hasViewedAnnualSummary],
    ['label' => 'CSV出力を試す', 'href' => 'export.php', 'done' => $hasViewedExport],
];

$pageTitle = 'ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>ダッシュボード</h2>
  <p class="description">日誌・作物・圃場・経費・売上を管理できます。ログイン中のユーザーのデータだけが表示されます。</p>
  <p><strong><?= e(current_user_name()) ?></strong> さん、ようこそ！</p>
  <button type="button" class="btn small onboarding-restore" data-onboarding-restore>「はじめにやること」を再表示</button>
</section>


<section class="card beginner-card" data-onboarding-card data-onboarding-key="agleef:onboarding:hidden:<?= e((string)$userId) ?>">
  <div class="card-title-row">
    <h3>はじめにやること</h3>
    <button type="button" class="btn small" data-onboarding-hide>非表示にする</button>
  </div>
  <p class="description">アグリーフを使い始めるために、まずは以下の順番で登録してみましょう。</p>
  <div class="onboarding-checklist">
    <?php foreach ($onboardingItems as $item): ?>
      <a class="onboarding-item <?= e((string)($item['done'] ? 'is-complete' : '')) ?>" href="<?= e($item['href']) ?>">
        <span class="onboarding-status" aria-hidden="true"><?= e((string)($item['done'] ? '✅' : '')) ?></span>
        <span class="onboarding-label"><?= e($item['label']) ?></span>
        <span class="status-badge <?= e((string)($item['done'] ? 'complete' : 'incomplete')) ?>"><?= e((string)($item['done'] ? '完了' : '未完了')) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="description">詳しい流れは <a href="guide.php">使い方ページ</a> でも確認できます。</p>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var onboardingCard = document.querySelector('[data-onboarding-card]');
  var hideButton = document.querySelector('[data-onboarding-hide]');
  var restoreButton = document.querySelector('[data-onboarding-restore]');

  if (!onboardingCard || !hideButton || !restoreButton) {
    return;
  }

  var storageKey = onboardingCard.getAttribute('data-onboarding-key');

  function setOnboardingVisibility(isHidden) {
    onboardingCard.hidden = isHidden;
    restoreButton.classList.toggle('is-visible', isHidden);
  }

  try {
    setOnboardingVisibility(window.localStorage.getItem(storageKey) === '1');

    hideButton.addEventListener('click', function () {
      window.localStorage.setItem(storageKey, '1');
      setOnboardingVisibility(true);
    });

    restoreButton.addEventListener('click', function () {
      window.localStorage.removeItem(storageKey);
      setOnboardingVisibility(false);
    });
  } catch (error) {
    setOnboardingVisibility(false);
  }
});
</script>

<section class="card">
  <h3>簡易サマリー</h3>
  <p class="description">差引は「売上合計 − 経費合計」の簡易目安です。本格的な所得計算ではありません。</p>
  <div class="summary-grid">
    <div class="summary-card"><span>今月の売上</span><strong><?= e(format_yen($monthSaleTotal)) ?></strong></div>
    <div class="summary-card"><span>今月の経費</span><strong><?= e(format_yen($monthExpenseTotal)) ?></strong></div>
    <div class="summary-card"><span>今月の差引</span><strong><?= e(format_yen($monthSaleTotal - $monthExpenseTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の売上</span><strong><?= e(format_yen($yearSaleTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の経費</span><strong><?= e(format_yen($yearExpenseTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の差引</span><strong><?= e(format_yen($yearSaleTotal - $yearExpenseTotal)) ?></strong></div>
  </div>
</section>

<?php if (current_user_is_admin()): ?>
<section class="card admin-hero">
  <h3>運営者メニュー</h3>
  <p class="description">管理者権限を持つユーザーだけに表示されます。</p>
  <div class="button-row">
    <a class="btn primary" href="admin_dashboard.php">管理画面へ</a>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <h3>メニュー</h3>
  <div class="button-row dashboard-actions">
    <a class="btn primary" href="diary_create.php">＋ 日誌登録</a>
    <a class="btn" href="diary_list.php">日誌一覧</a>
    <a class="btn" href="crops.php">作物管理</a>
    <a class="btn" href="fields.php">圃場管理</a>
    <a class="btn primary" href="expense_create.php">＋ 経費を登録する</a>
    <a class="btn" href="expense_list.php">経費一覧を見る</a>
    <a class="btn primary" href="sale_create.php">＋ 売上を登録する</a>
    <a class="btn" href="sale_list.php">売上一覧を見る</a>
    <a class="btn primary" href="annual_summary.php">年間集計を見る</a>
    <a class="btn primary" href="export.php">CSV出力</a>
    <a class="btn" href="expense_category.php">経費カテゴリを管理する</a>
    <a class="btn" href="account.php">アカウント情報を確認する</a>
    <a class="btn" href="contact.php">お問い合わせ・改善要望を送る</a>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
