<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);

$categories = get_user_expense_categories($userId);
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);

$dateFrom = get_query_param('date_from');
$dateTo = get_query_param('date_to');
$categoryId = get_query_param('category_id');
$cropId = get_query_param('crop_id');
$fieldId = get_query_param('field_id');
$keyword = trim(get_query_param('keyword'));
$errors = [];

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
    <div class="filter-grid">
      <label>開始日<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
      <label>終了日<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
      <label>経費カテゴリ
        <select name="category_id">
          <option value="">すべて</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>" <?= $categoryId !== '' && (int)$categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>作物
        <select name="crop_id">
          <option value="">すべて</option>
          <?php foreach ($crops as $crop): ?>
            <option value="<?= (int)$crop['id'] ?>" <?= $cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>圃場
        <select name="field_id">
          <option value="">すべて</option>
          <?php foreach ($fields as $field): ?>
            <option value="<?= (int)$field['id'] ?>" <?= $fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option>
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
          <th>支払日</th><th>カテゴリ</th><th>作物</th><th>圃場</th><th>支払先</th><th>内容</th><th>金額</th><th>支払方法</th><th>領収書</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$expenses): ?>
          <tr><td colspan="10"><?= $hasSearchCondition ? '条件に一致する経費はありません。' : '経費がまだありません。' ?></td></tr>
        <?php else: ?>
          <?php foreach ($expenses as $row): ?>
            <tr>
              <td data-label="支払日"><?= e($row['expense_date']) ?></td>
              <td data-label="カテゴリ"><?= e($row['category_name'] ?? '未分類') ?></td>
              <td data-label="作物"><?= e($row['crop_name'] ?? '-') ?></td>
              <td data-label="圃場"><?= e($row['field_name'] ?? '-') ?></td>
              <td data-label="支払先"><?= e($row['payee'] ?? '-') ?></td>
              <td data-label="内容"><span class="cell-note"><?= e($row['description']) ?></span></td>
              <td data-label="金額"><strong><?= e(format_yen((int)$row['amount'])) ?></strong></td>
              <td data-label="支払方法"><?= e($row['payment_method'] ?? '-') ?></td>
              <td data-label="領収書"><?= !empty($row['receipt_path']) ? 'あり' : 'なし' ?></td>
              <td data-label="操作" class="actions-cell">
                <div class="inline-actions">
                  <a class="btn small" href="expense_detail.php?id=<?= (int)$row['id'] ?>">詳細</a>
                  <a class="btn small" href="expense_edit.php?id=<?= (int)$row['id'] ?>">編集</a>
                  <form method="post" action="expense_delete.php" onsubmit="return confirm('この経費を削除しますか？');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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
