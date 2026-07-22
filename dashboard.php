<?php
require_once __DIR__ . '/includes/auth.php';
require_active_user();

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

$monthBalance = $monthSaleTotal - $monthExpenseTotal;
$yearBalance = $yearSaleTotal - $yearExpenseTotal;
$monthBalanceState = $monthBalance > 0 ? 'is-positive' : ($monthBalance < 0 ? 'is-negative' : 'is-zero');
$yearBalanceState = $yearBalance > 0 ? 'is-positive' : ($yearBalance < 0 ? 'is-negative' : 'is-zero');
$monthBalanceStatus = $monthBalance > 0 ? '黒字' : ($monthBalance < 0 ? '赤字' : '収支ゼロ');
$yearBalanceStatus = $yearBalance > 0 ? '黒字' : ($yearBalance < 0 ? '赤字' : '収支ゼロ');

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


<section class="card beginner-card" data-onboarding-card data-onboarding-key="agrimore:onboarding:hidden:<?= e((string)$userId) ?>">
  <div class="card-title-row">
    <h3>はじめにやること</h3>
    <button type="button" class="btn small" data-onboarding-hide>非表示にする</button>
  </div>
  <p class="description">AgriMoreを使い始めるために、まずは以下の順番で登録してみましょう。</p>
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
  // Preserve each user's onboarding preference after the service rename.
  var legacyStorageKey = ['agl', 'eef:onboarding:hidden:', <?= json_encode((string)$userId) ?>].join('');

  function setOnboardingVisibility(isHidden) {
    onboardingCard.hidden = isHidden;
    restoreButton.classList.toggle('is-visible', isHidden);
  }

  try {
    if (window.localStorage.getItem(storageKey) === null && window.localStorage.getItem(legacyStorageKey) !== null) {
      window.localStorage.setItem(storageKey, window.localStorage.getItem(legacyStorageKey));
      window.localStorage.removeItem(legacyStorageKey);
    }
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

<section class="card dashboard-summary" aria-labelledby="dashboard-summary-title">
  <h3 id="dashboard-summary-title">簡易サマリー</h3>
  <p class="dashboard-summary-description">売上－経費の簡易集計です。確定申告用の所得計算ではありません。</p>

  <div class="dashboard-summary-group" aria-labelledby="dashboard-summary-month">
    <h4 class="dashboard-summary-group-title" id="dashboard-summary-month">今月</h4>
    <div class="dashboard-summary-grid">
      <div class="dashboard-summary-card dashboard-summary-card--sales">
        <span class="dashboard-summary-label">売上</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($monthSaleTotal)) ?></strong>
      </div>
      <div class="dashboard-summary-card dashboard-summary-card--expense">
        <span class="dashboard-summary-label">経費</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($monthExpenseTotal)) ?></strong>
      </div>
      <div class="dashboard-summary-card dashboard-summary-card--balance <?= e($monthBalanceState) ?>">
        <span class="dashboard-summary-label">収支</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($monthBalance)) ?></strong>
        <span class="dashboard-summary-status"><?= e($monthBalanceStatus) ?></span>
      </div>
    </div>
  </div>

  <div class="dashboard-summary-group" aria-labelledby="dashboard-summary-year">
    <h4 class="dashboard-summary-group-title" id="dashboard-summary-year">今年</h4>
    <div class="dashboard-summary-grid">
      <div class="dashboard-summary-card dashboard-summary-card--sales">
        <span class="dashboard-summary-label">売上</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($yearSaleTotal)) ?></strong>
      </div>
      <div class="dashboard-summary-card dashboard-summary-card--expense">
        <span class="dashboard-summary-label">経費</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($yearExpenseTotal)) ?></strong>
      </div>
      <div class="dashboard-summary-card dashboard-summary-card--balance <?= e($yearBalanceState) ?>">
        <span class="dashboard-summary-label">収支</span>
        <strong class="dashboard-summary-value"><?= e(format_yen($yearBalance)) ?></strong>
        <span class="dashboard-summary-status"><?= e($yearBalanceStatus) ?></span>
      </div>
    </div>
  </div>
</section>

<?php if (current_user_is_admin()): ?>
<section class="card admin-hero">
  <h3>運営者メニュー</h3>
  <p class="description">管理者権限を持つユーザーだけに表示されます。</p>
  <div class="button-row">
    <a class="btn primary" href="admin_dashboard.php">管理画面</a>
    <a class="btn" href="admin_users.php">ユーザー一覧</a>
    <a class="btn" href="admin_contacts.php">問い合わせ一覧</a>
    <a class="btn" href="admin_settings.php">アプリ設定</a>
    <a class="btn" href="admin_logs.php">管理者操作ログ</a>
  </div>
</section>
<?php endif; ?>

<section class="card dashboard-menu-section">
  <h3>メニュー</h3>
  <div class="dashboard-quick-actions">
    <h4>クイック操作</h4>
    <div class="dashboard-quick-actions-grid">
      <a class="dashboard-quick-action" href="diary_create.php"><span class="dashboard-quick-action-icon" aria-hidden="true">✎</span><span>日誌を登録</span></a>
      <a class="dashboard-quick-action" href="expense_create.php"><span class="dashboard-quick-action-icon" aria-hidden="true">¥</span><span>経費を登録</span></a>
      <a class="dashboard-quick-action" href="sale_create.php"><span class="dashboard-quick-action-icon" aria-hidden="true">↗</span><span>売上を登録</span></a>
    </div>
  </div>

  <div class="dashboard-menu-grid">
    <section class="dashboard-menu-card" aria-labelledby="dashboard-cultivation-menu">
      <h4 id="dashboard-cultivation-menu">日誌・栽培管理</h4>
      <nav class="dashboard-menu-links" aria-label="日誌・栽培管理メニュー">
        <a class="dashboard-menu-link" href="diary_list.php"><span>日誌一覧</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="crops.php"><span>作物管理</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="fields.php"><span>圃場管理</span><span aria-hidden="true">›</span></a>
      </nav>
    </section>

    <section class="dashboard-menu-card" aria-labelledby="dashboard-management-menu">
      <h4 id="dashboard-management-menu">経営管理</h4>
      <nav class="dashboard-menu-links" aria-label="経営管理メニュー">
        <a class="dashboard-menu-link" href="expense_list.php"><span>経費一覧</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="sale_list.php"><span>売上一覧</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="annual_summary.php"><span>年間集計</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="expense_category.php"><span>経費カテゴリ管理</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="export.php"><span>CSV出力</span><span aria-hidden="true">›</span></a>
      </nav>
    </section>

    <section class="dashboard-menu-card" aria-labelledby="dashboard-support-menu">
      <h4 id="dashboard-support-menu">設定・サポート</h4>
      <nav class="dashboard-menu-links" aria-label="設定・サポートメニュー">
        <a class="dashboard-menu-link" href="account.php"><span>アカウント情報</span><span aria-hidden="true">›</span></a>
        <a class="dashboard-menu-link" href="contact.php"><span>お問い合わせ・改善要望</span><span aria-hidden="true">›</span></a>
      </nav>
    </section>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
