<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$channels = sales_channel_options();
$paymentStatuses = payment_status_options();
$errors = [];

$dateFrom = get_query_param('date_from');
$dateTo = get_query_param('date_to');
$salesChannel = get_query_param('sales_channel');
$cropId = get_query_param('crop_id');
$fieldId = get_query_param('field_id');
$paymentStatus = get_query_param('payment_status');
$keyword = trim(get_query_param('keyword'));

$where = ['s.user_id = :user_id'];
$params = [':user_id' => $userId];

if ($dateFrom !== '') {
    if (is_valid_date($dateFrom)) {
        $where[] = 's.sale_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    } else {
        $errors[] = '開始日の形式が正しくありません。';
        $dateFrom = '';
    }
}
if ($dateTo !== '') {
    if (is_valid_date($dateTo)) {
        $where[] = 's.sale_date <= :date_to';
        $params[':date_to'] = $dateTo;
    } else {
        $errors[] = '終了日の形式が正しくありません。';
        $dateTo = '';
    }
}
if ($salesChannel !== '') {
    if (in_array($salesChannel, $channels, true)) {
        $where[] = 's.sales_channel = :sales_channel';
        $params[':sales_channel'] = $salesChannel;
    } else {
        $errors[] = '販売経路の指定が不正です。';
        $salesChannel = '';
    }
}
if ($cropId !== '') {
    $where[] = 's.crop_id = :crop_id';
    $params[':crop_id'] = (int)$cropId;
}
if ($fieldId !== '') {
    $where[] = 's.field_id = :field_id';
    $params[':field_id'] = (int)$fieldId;
}
if ($paymentStatus !== '') {
    if (in_array($paymentStatus, $paymentStatuses, true)) {
        $where[] = 's.payment_status = :payment_status';
        $params[':payment_status'] = $paymentStatus;
    } else {
        $errors[] = '入金状況の指定が不正です。';
        $paymentStatus = '';
    }
}
if ($keyword !== '') {
    $where[] = '(s.buyer LIKE :keyword OR s.product_name LIKE :keyword OR s.memo LIKE :keyword)';
    $params[':keyword'] = '%' . $keyword . '%';
}

$whereSql = implode(' AND ', $where);
$stmt = db()->prepare("SELECT s.*, c.name AS crop_name, f.name AS field_name
    FROM sales s
    LEFT JOIN crops c ON c.id = s.crop_id AND c.user_id = s.user_id
    LEFT JOIN fields f ON f.id = s.field_id AND f.user_id = s.user_id
    WHERE {$whereSql}
    ORDER BY s.sale_date DESC, s.id DESC");
$stmt->execute($params);
$sales = $stmt->fetchAll();

$summaryStmt = db()->prepare("SELECT COALESCE(SUM(gross_amount), 0) AS gross_total, COALESCE(SUM(net_amount), 0) AS net_total FROM sales s WHERE {$whereSql}");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();
$totalGross = (int)($summary['gross_total'] ?? 0);
$totalNet = (int)($summary['net_total'] ?? 0);

$monthStmt = db()->prepare('SELECT COALESCE(SUM(gross_amount), 0) FROM sales WHERE user_id = :user_id AND sale_date BETWEEN :start AND :end');
$monthStmt->execute([':user_id' => $userId, ':start' => date('Y-m-01'), ':end' => date('Y-m-t')]);
$monthGrossTotal = (int)$monthStmt->fetchColumn();
$yearStmt = db()->prepare('SELECT COALESCE(SUM(gross_amount), 0) FROM sales WHERE user_id = :user_id AND sale_date BETWEEN :start AND :end');
$yearStmt->execute([':user_id' => $userId, ':start' => date('Y-01-01'), ':end' => date('Y-12-31')]);
$yearGrossTotal = (int)$yearStmt->fetchColumn();

$channelStmt = db()->prepare("SELECT COALESCE(NULLIF(sales_channel, ''), '未設定') AS channel_name, COALESCE(SUM(gross_amount), 0) AS total_amount FROM sales s WHERE {$whereSql} GROUP BY channel_name ORDER BY total_amount DESC, channel_name ASC");
$channelStmt->execute($params);
$channelTotals = $channelStmt->fetchAll();

$hasSearchCondition = $dateFrom !== '' || $dateTo !== '' || $salesChannel !== '' || $cropId !== '' || $fieldId !== '' || $paymentStatus !== '' || $keyword !== '';
$pageTitle = '売上一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <div class="button-row" style="justify-content: space-between; margin-top: 0;">
    <h2 style="margin-bottom:0;">売上一覧</h2>
    <a class="btn primary" href="sale_create.php">＋ 売上登録</a>
  </div>
  <p class="description">ログイン中のユーザーの売上だけを表示します。確定申告準備や経営の振り返りに使える売上メモです。</p>

  <?php foreach ($errors as $error): ?><p class="alert error"><?= e($error) ?></p><?php endforeach; ?>

  <div class="summary-grid">
    <div class="summary-card"><span>表示中の売上総額合計</span><strong><?= e(format_yen($totalGross)) ?></strong></div>
    <div class="summary-card"><span>表示中の差引入金額合計</span><strong><?= e(format_yen($totalNet)) ?></strong></div>
    <div class="summary-card"><span>今月の売上総額合計</span><strong><?= e(format_yen($monthGrossTotal)) ?></strong></div>
    <div class="summary-card"><span>今年の売上総額合計</span><strong><?= e(format_yen($yearGrossTotal)) ?></strong></div>
  </div>

  <form class="search-form" method="get" action="sale_list.php">
    <h3>絞り込み</h3>
    <div class="filter-grid">
      <label>開始日<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
      <label>終了日<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
      <label>販売経路<select name="sales_channel"><option value="">すべて</option><?php foreach ($channels as $channel): ?><option value="<?= e($channel) ?>" <?= $salesChannel === $channel ? 'selected' : '' ?>><?= e($channel) ?></option><?php endforeach; ?></select></label>
      <label>作物<select name="crop_id"><option value="">すべて</option><?php foreach ($crops as $crop): ?><option value="<?= (int)$crop['id'] ?>" <?= $cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option><?php endforeach; ?></select></label>
      <label>圃場<select name="field_id"><option value="">すべて</option><?php foreach ($fields as $field): ?><option value="<?= (int)$field['id'] ?>" <?= $fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option><?php endforeach; ?></select></label>
      <label>入金状況<select name="payment_status"><option value="">すべて</option><?php foreach ($paymentStatuses as $status): ?><option value="<?= e($status) ?>" <?= $paymentStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
      <label>キーワード<input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="販売先・品目・メモ"></label>
    </div>
    <div class="button-row search-actions"><button class="btn primary" type="submit">検索</button><a class="btn" href="sale_list.php">リセット</a></div>
  </form>

  <?php if ($hasSearchCondition): ?><p class="alert success">条件に一致する売上を表示しています。</p><?php endif; ?>

  <?php if ($channelTotals): ?>
    <div class="category-summary"><h3>販売経路別合計（表示条件内）</h3><ul><?php foreach ($channelTotals as $row): ?><li><span><?= e($row['channel_name']) ?></span><strong><?= e(format_yen((int)$row['total_amount'])) ?></strong></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="table-wrap"><table class="sale-table"><thead><tr><th>売上日</th><th>販売経路</th><th>作物</th><th>圃場</th><th>販売先</th><th>品目</th><th>数量</th><th>売上総額</th><th>手数料</th><th>送料</th><th>差引入金額</th><th>入金状況</th><th>明細</th><th>操作</th></tr></thead><tbody>
    <?php if (!$sales): ?><tr><td colspan="14"><?= $hasSearchCondition ? '条件に一致する売上はありません。' : '売上がまだありません。' ?></td></tr><?php else: ?>
      <?php foreach ($sales as $row): ?>
        <tr>
          <td data-label="売上日"><?= e($row['sale_date']) ?></td><td data-label="販売経路"><?= e($row['sales_channel'] ?? '-') ?></td><td data-label="作物"><?= e($row['crop_name'] ?? '-') ?></td><td data-label="圃場"><?= e($row['field_name'] ?? '-') ?></td><td data-label="販売先"><?= e($row['buyer'] ?? '-') ?></td><td data-label="品目"><span class="cell-note"><?= e($row['product_name']) ?></span></td><td data-label="数量"><?= $row['quantity'] !== null && $row['quantity'] !== '' ? e(format_quantity((float)$row['quantity']) . ($row['unit'] ? ' ' . $row['unit'] : '')) : '-' ?></td><td data-label="売上総額"><strong><?= e(format_yen((int)$row['gross_amount'])) ?></strong></td><td data-label="手数料"><?= e(format_yen((int)$row['fee_amount'])) ?></td><td data-label="送料"><?= e(format_yen((int)$row['shipping_amount'])) ?></td><td data-label="差引入金額"><strong><?= e(format_yen((int)$row['net_amount'])) ?></strong></td><td data-label="入金状況"><?= e($row['payment_status'] ?? '未入金') ?></td><td data-label="明細"><?= !empty($row['document_path']) ? 'あり' : 'なし' ?></td>
          <td data-label="操作" class="actions-cell"><div class="inline-actions"><a class="btn small" href="sale_detail.php?id=<?= (int)$row['id'] ?>">詳細</a><a class="btn small" href="sale_edit.php?id=<?= (int)$row['id'] ?>">編集</a><form method="post" action="sale_delete.php" onsubmit="return confirm('この売上を削除しますか？');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn small danger" type="submit">削除</button></form></div></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody></table></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
