<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);

$categories = get_user_expense_categories($userId);
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$paymentMethods = ['現金', 'クレジットカード', '銀行振込', '口座振替', '電子マネー', 'その他'];

$dateFrom = get_query_param('date_from');
$dateTo = get_query_param('date_to');
$categoryId = get_query_param('category_id');
$cropId = get_query_param('crop_id');
$fieldId = get_query_param('field_id');
$keyword = trim(get_query_param('keyword'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update') {
    $bulkErrors = [];
    $selectedIds = [];
    foreach (($_POST['expense_ids'] ?? []) as $rawId) {
        if (is_scalar($rawId)) {
            $selectedIds[] = (int)$rawId;
        }
    }
    $selectedIds = array_values(array_unique(array_filter($selectedIds, static fn(int $value): bool => $value > 0)));

    $applyCategory = isset($_POST['apply_category']);
    $applyPayee = isset($_POST['apply_payee']);
    $applyPaymentMethod = isset($_POST['apply_payment_method']);
    $bulkCategoryId = (string)($_POST['bulk_category_id'] ?? '');
    $bulkPayee = trim((string)($_POST['bulk_payee'] ?? ''));
    $bulkPaymentMethod = (string)($_POST['bulk_payment_method'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $bulkErrors[] = '不正なリクエストです。';
    }
    if (!$selectedIds) {
        $bulkErrors[] = '一括編集する経費を選択してください。';
    }
    if (!$applyCategory && !$applyPayee && !$applyPaymentMethod) {
        $bulkErrors[] = '一括編集する項目を選択してください。';
    }
    if ($applyCategory) {
        if ($bulkCategoryId === '' || !ctype_digit($bulkCategoryId)) {
            $bulkErrors[] = '経費カテゴリを選択してください。';
        } else {
            $categoryCheck = db()->prepare('SELECT COUNT(*) FROM expense_categories WHERE id = :id AND user_id = :user_id');
            $categoryCheck->execute([':id' => (int)$bulkCategoryId, ':user_id' => $userId]);
            if ((int)$categoryCheck->fetchColumn() === 0) {
                $bulkErrors[] = '選択した経費カテゴリが不正です。';
            }
        }
    }
    if ($applyPaymentMethod && $bulkPaymentMethod !== '' && !in_array($bulkPaymentMethod, $paymentMethods, true)) {
        $bulkErrors[] = '支払方法の指定が不正です。';
    }

    if ($bulkErrors) {
        set_flash('error', implode(' ', $bulkErrors));
    } else {
        $placeholders = [];
        $bulkParams = [':user_id' => $userId];
        foreach ($selectedIds as $index => $selectedId) {
            $placeholder = ':id' . $index;
            $placeholders[] = $placeholder;
            $bulkParams[$placeholder] = $selectedId;
        }

        $setParts = [];
        if ($applyCategory) {
            $setParts[] = 'category_id = :bulk_category_id';
            $bulkParams[':bulk_category_id'] = (int)$bulkCategoryId;
        }
        if ($applyPayee) {
            $setParts[] = 'payee = :bulk_payee';
            $bulkParams[':bulk_payee'] = $bulkPayee !== '' ? $bulkPayee : null;
        }
        if ($applyPaymentMethod) {
            $setParts[] = 'payment_method = :bulk_payment_method';
            $bulkParams[':bulk_payment_method'] = $bulkPaymentMethod !== '' ? $bulkPaymentMethod : null;
        }
        $setParts[] = 'updated_at = CURRENT_TIMESTAMP';

        $bulkUpdate = db()->prepare('UPDATE expenses SET ' . implode(', ', $setParts) . ' WHERE user_id = :user_id AND id IN (' . implode(', ', $placeholders) . ')');
        $bulkUpdate->execute($bulkParams);
        set_flash('success', (string)$bulkUpdate->rowCount() . '件の経費を一括更新しました。');
    }

    $redirectQuery = $_GET ? '?' . http_build_query($_GET) : '';
    redirect('expense_list.php' . $redirectQuery);
}

$where = ['e.user_id = :user_id'];
$params = [':user_id' => $userId];

if ($dateFrom !== '') {
    if (is_valid_date($dateFrom)) {
        $where[] = 'e.expense_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    } else {
        $errors[] = '開始日の形式が不正です。';
    }
}
if ($dateTo !== '') {
    if (is_valid_date($dateTo)) {
        $where[] = 'e.expense_date <= :date_to';
        $params[':date_to'] = $dateTo;
    } else {
        $errors[] = '終了日の形式が不正です。';
    }
}
if ($categoryId !== '') {
    if (ctype_digit($categoryId)) {
        $where[] = 'e.category_id = :category_id';
        $params[':category_id'] = (int)$categoryId;
    } else {
        $errors[] = '経費カテゴリの指定が不正です。';
    }
}
if ($cropId !== '') {
    if (ctype_digit($cropId)) {
        $where[] = 'e.crop_id = :crop_id';
        $params[':crop_id'] = (int)$cropId;
    } else {
        $errors[] = '作物の指定が不正です。';
    }
}
if ($fieldId !== '') {
    if (ctype_digit($fieldId)) {
        $where[] = 'e.field_id = :field_id';
        $params[':field_id'] = (int)$fieldId;
    } else {
        $errors[] = '圃場の指定が不正です。';
    }
}
if ($keyword !== '') {
    $where[] = '(e.payee LIKE :keyword OR e.description LIKE :keyword OR e.memo LIKE :keyword)';
    $params[':keyword'] = '%' . $keyword . '%';
}

$whereSql = implode(' AND ', $where);
$sql = 'SELECT e.*, ec.name AS category_name, c.name AS crop_name, f.name AS field_name
        FROM expenses e
        LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id
        LEFT JOIN crops c ON c.id = e.crop_id AND c.user_id = e.user_id
        LEFT JOIN fields f ON f.id = e.field_id AND f.user_id = e.user_id
        WHERE ' . $whereSql . '
        ORDER BY e.expense_date DESC, e.created_at DESC, e.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$total = array_sum(array_map(static fn(array $row): int => (int)$row['amount'], $expenses));

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$monthStmt->execute([':user_id' => $userId, ':start' => $monthStart, ':end' => $monthEnd]);
$monthTotal = (int)$monthStmt->fetchColumn();

$yearStart = date('Y-01-01');
$yearEnd = date('Y-12-31');
$yearStmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start AND :end');
$yearStmt->execute([':user_id' => $userId, ':start' => $yearStart, ':end' => $yearEnd]);
$yearTotal = (int)$yearStmt->fetchColumn();

$categoryStmt = db()->prepare('SELECT COALESCE(ec.name, "未分類") AS category_name, COALESCE(SUM(e.amount), 0) AS total_amount
    FROM expenses e
    LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id
    WHERE ' . $whereSql . '
    GROUP BY e.category_id, ec.name
    ORDER BY total_amount DESC, category_name ASC');
$categoryStmt->execute($params);
$categoryTotals = $categoryStmt->fetchAll();
$categoryChartColors = ['#2f855a', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];
$categoryChartSegments = [];
$categoryChartPosition = 0.0;
if ($total > 0) {
    foreach ($categoryTotals as $index => $row) {
        $amount = (int)$row['total_amount'];
        if ($amount <= 0) {
            continue;
        }
        $percentage = ($amount / $total) * 100;
        $nextPosition = $categoryChartPosition + $percentage;
        $color = $categoryChartColors[$index % count($categoryChartColors)];
        $categoryChartSegments[] = sprintf('%s %.4F%% %.4F%%', $color, $categoryChartPosition, $nextPosition);
        $categoryChartPosition = $nextPosition;
    }
}
$categoryChartStyle = $categoryChartSegments ? 'background: conic-gradient(' . implode(', ', $categoryChartSegments) . ');' : '';

$monthFilterLinks = month_filter_links('expense_list.php', $dateFrom, $dateTo);
$hasSearchCondition = $dateFrom !== '' || $dateTo !== '' || $categoryId !== '' || $cropId !== '' || $fieldId !== '' || $keyword !== '';

$pageTitle = '経費一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <div class="button-row" style="justify-content: space-between; margin-top: 0;">
    <h2 style="margin-bottom:0;">経費一覧</h2>
    <a class="btn primary" href="expense_create.php">＋ 経費登録</a>
  </div>
  <p class="description">ログイン中のユーザーの経費だけを表示します。確定申告準備用のメモとして日々の支出を残せます。</p>

  <?php foreach ($errors as $error): ?>
    <p class="alert error"><?= e($error) ?></p>
  <?php endforeach; ?>

  <div class="summary-grid">
    <div class="summary-card"><span>表示中の合計</span><strong><?= e(format_yen((int)$total)) ?></strong></div>
    <div class="summary-card"><span>今月の合計</span><strong><?= e(format_yen($monthTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の合計</span><strong><?= e(format_yen($yearTotal)) ?></strong></div>
  </div>

  <form class="search-form" method="get" action="expense_list.php">
    <h3>絞り込み</h3>
    <div class="month-filter" aria-label="月別の期間絞り込み">
      <?php foreach ($monthFilterLinks as $monthFilterLink): ?>
        <a class="btn small<?= e((string)($monthFilterLink['is_active'] ? ' primary' : '')) ?>" href="<?= e($monthFilterLink['url']) ?>"><?= e($monthFilterLink['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="filter-grid">
      <label>開始日<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
      <label>終了日<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
      <label>経費カテゴリ
        <select name="category_id">
          <option value="">すべて</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e((string)((int)$category['id'])) ?>" <?= e((string)($categoryId !== '' && (int)$categoryId === (int)$category['id'] ? 'selected' : '')) ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>作物
        <select name="crop_id">
          <option value="">すべて</option>
          <?php foreach ($crops as $crop): ?>
            <option value="<?= e((string)((int)$crop['id'])) ?>" <?= e((string)($cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '')) ?>><?= e($crop['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>圃場
        <select name="field_id">
          <option value="">すべて</option>
          <?php foreach ($fields as $field): ?>
            <option value="<?= e((string)((int)$field['id'])) ?>" <?= e((string)($fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '')) ?>><?= e($field['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>キーワード<input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="支払先・内容・メモ"></label>
    </div>
    <div class="button-row search-actions">
      <button class="btn primary" type="submit">検索</button>
      <a class="btn" href="expense_list.php">リセット</a>
      <a class="btn" href="expense_category.php">カテゴリ管理</a>
    </div>
  </form>

  <?php if ($hasSearchCondition): ?>
    <p class="alert success">条件に一致する経費を表示しています。</p>
  <?php endif; ?>

  <form id="bulk-edit-form" class="bulk-edit-form" method="post" action="expense_list.php<?= e((string)($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . ($_SERVER['QUERY_STRING'] ?? '') : '') ?>" data-bulk-edit-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="bulk_update">
    <div class="bulk-edit-panel">
      <div class="bulk-edit-header">
        <h3>選択した経費を一括編集</h3>
        <p>一覧のチェックを付けた経費だけ、「カテゴリ」「支払先」「支払い方法」をまとめて更新できます。</p>
      </div>
      <div class="bulk-edit-grid">
        <label class="bulk-edit-field"><span class="checkbox-label"><input type="checkbox" name="apply_category" value="1"> カテゴリを変更</span><select name="bulk_category_id"><option value="">選択してください</option><?php foreach ($categories as $category): ?><option value="<?= e((string)((int)$category['id'])) ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
        <label class="bulk-edit-field"><span class="checkbox-label"><input type="checkbox" name="apply_payee" value="1"> 支払先を変更</span><input type="text" name="bulk_payee" placeholder="空欄で支払先を空にする"></label>
        <label class="bulk-edit-field"><span class="checkbox-label"><input type="checkbox" name="apply_payment_method" value="1"> 支払い方法を変更</span><select name="bulk_payment_method"><option value="">選択しない（空にする）</option><?php foreach ($paymentMethods as $method): ?><option value="<?= e($method) ?>"><?= e($method) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="button-row bulk-edit-actions"><button class="btn primary" type="submit">選択した経費を一括更新</button><span class="bulk-selected-count" data-bulk-selected-count>0件選択中</span></div>
    </div>
  </form>

  <?php if ($categoryTotals): ?>
    <div class="category-summary">
      <div class="category-summary-header">
        <h3>カテゴリ別合計（表示条件内）</h3>
        <p>表示中の合計に対する経費カテゴリごとの割合を確認できます。</p>
      </div>
      <?php if ($categoryChartStyle !== ''): ?>
        <div class="category-chart-wrap">
          <div class="category-pie-chart" style="<?= e($categoryChartStyle) ?>" role="img" aria-label="経費カテゴリ別割合の円グラフ"></div>
          <ul class="category-chart-legend">
            <?php foreach ($categoryTotals as $index => $row): ?>
              <?php
                $amount = (int)$row['total_amount'];
                $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
                $color = $categoryChartColors[$index % count($categoryChartColors)];
              ?>
              <li>
                <span class="category-color" style="background-color: <?= e($color) ?>;"></span>
                <span class="category-name"><?= e($row['category_name']) ?></span>
                <strong><?= e(number_format($percentage, 1)) ?>%</strong>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <ul class="category-total-list">
        <?php foreach ($categoryTotals as $row): ?>
          <li><span><?= e($row['category_name']) ?></span><strong><?= e(format_yen((int)$row['total_amount'])) ?></strong></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="expense-table">
      <thead>
        <tr>
          <th><label class="table-check-label"><input type="checkbox" data-bulk-select-all aria-label="経費をすべて選択"></label></th><th>支払日</th><th>カテゴリ</th><th>作物</th><th>圃場</th><th>支払先</th><th>内容</th><th>金額</th><th>支払方法</th><th>領収書</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$expenses): ?>
          <tr><td colspan="11"><?= e((string)($hasSearchCondition ? '条件に一致する経費はありません。' : '経費がまだありません。')) ?></td></tr>
        <?php else: ?>
          <?php foreach ($expenses as $row): ?>
            <tr>
              <td data-label="選択"><label class="table-check-label"><input type="checkbox" name="expense_ids[]" value="<?= e((string)((int)$row['id'])) ?>" form="bulk-edit-form" data-bulk-expense-checkbox aria-label="<?= e(format_date_with_weekday($row['expense_date']) . ' の経費を選択') ?>"></label></td>
              <td data-label="支払日"><?= e(format_date_with_weekday($row['expense_date'])) ?></td>
              <td data-label="カテゴリ"><?= e($row['category_name'] ?? '未分類') ?></td>
              <td data-label="作物"><?= e($row['crop_name'] ?? '-') ?></td>
              <td data-label="圃場"><?= e($row['field_name'] ?? '-') ?></td>
              <td data-label="支払先"><?= e($row['payee'] ?? '-') ?></td>
              <td data-label="内容"><span class="cell-note"><?= e($row['description']) ?></span></td>
              <td data-label="金額"><strong><?= e(format_yen((int)$row['amount'])) ?></strong></td>
              <td data-label="支払方法"><?= e($row['payment_method'] ?? '-') ?></td>
              <td data-label="領収書"><?= e((string)(!empty($row['receipt_path']) ? 'あり' : 'なし')) ?></td>
              <td data-label="操作" class="actions-cell">
                <div class="inline-actions">
                  <a class="btn small" href="expense_detail.php?id=<?= e((string)((int)$row['id'])) ?>">詳細</a>
                  <a class="btn small" href="expense_edit.php?id=<?= e((string)((int)$row['id'])) ?>">編集</a>
                  <form method="post" action="expense_delete.php" onsubmit="return confirm('この経費を削除しますか？');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string)((int)$row['id'])) ?>">
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
<script>
(function(){
  var form = document.querySelector('[data-bulk-edit-form]');
  var selectAll = document.querySelector('[data-bulk-select-all]');
  var checkboxes = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-expense-checkbox]'));
  var countLabel = document.querySelector('[data-bulk-selected-count]');
  if (!form || !countLabel) return;

  function updateCount() {
    checkboxes.forEach(function(checkbox){
      var row = checkbox.closest ? checkbox.closest('tr') : null;
      if (row) {
        row.classList.toggle('is-selected', checkbox.checked);
      }
    });
    var selectedCount = checkboxes.filter(function(checkbox){ return checkbox.checked; }).length;
    countLabel.textContent = selectedCount + '件選択中';
    if (selectAll) {
      selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function(){
      checkboxes.forEach(function(checkbox){ checkbox.checked = selectAll.checked; });
      updateCount();
    });
  }
  checkboxes.forEach(function(checkbox){ checkbox.addEventListener('change', updateCount); });
  form.addEventListener('submit', function(event){
    var selectedCount = checkboxes.filter(function(checkbox){ return checkbox.checked; }).length;
    if (selectedCount === 0) {
      event.preventDefault();
      alert('一括編集する経費を選択してください。');
      return;
    }
    if (!confirm(selectedCount + '件の経費を一括更新しますか？')) {
      event.preventDefault();
    }
  });
  updateCount();
})();
</script>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
