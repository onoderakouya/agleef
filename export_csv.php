<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

function export_request_value(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function redirect_export_error(string $message): void
{
    set_flash('error', $message);
    redirect('export.php');
}

$type = export_request_value('type', '');
$allowedTypes = get_export_types();
if (!isset($allowedTypes[$type])) {
    redirect_export_error('出力対象が正しくありません。');
}

$currentYear = (int)date('Y');
$yearValue = export_request_value('year', (string)$currentYear);
if (!preg_match('/\A\d{4}\z/', $yearValue)) {
    redirect_export_error('対象年が正しくありません。');
}
$year = (int)$yearValue;
if ($year < 2000 || $year > $currentYear + 1) {
    redirect_export_error('対象年は2000年から' . ($currentYear + 1) . '年までで指定してください。');
}

$dateFrom = export_request_value('date_from');
$dateTo = export_request_value('date_to');
if ($dateFrom !== '' && !validate_date($dateFrom)) {
    redirect_export_error('開始日は正しい日付で入力してください。');
}
if ($dateTo !== '' && !validate_date($dateTo)) {
    redirect_export_error('終了日は正しい日付で入力してください。');
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    redirect_export_error('開始日は終了日以前の日付を指定してください。');
}

$encoding = export_request_value('encoding', 'utf8_bom');
if (!isset(get_export_encodings()[$encoding])) {
    redirect_export_error('文字コードが正しくありません。');
}

if ($type === 'annual_summary') {
    $dateFrom = '';
    $dateTo = '';
}

$userId = current_user_id();
$range = get_export_date_range($year, $dateFrom, $dateTo);
$startDate = $range['start'];
$endDate = $range['end'];
$netSql = 'COALESCE(net_amount, COALESCE(gross_amount, 0) - COALESCE(fee_amount, 0) - COALESCE(shipping_amount, 0))';
$rows = [];

if ($type === 'sales') {
    $rows[] = ['売上日', '販売経路', '作物', '圃場', '販売先', '品目', '数量', '単位', '単価', '売上総額', '手数料', '送料', '差引入金額', '入金状況', '入金日', '明細写真パス', 'メモ', '作成日時', '更新日時'];
    $stmt = db()->prepare(
        "SELECT s.sale_date, s.sales_channel, c.name AS crop_name, f.name AS field_name, s.buyer, s.product_name,
                s.quantity, s.unit, s.unit_price, s.gross_amount, COALESCE(s.fee_amount, 0) AS fee_amount,
                COALESCE(s.shipping_amount, 0) AS shipping_amount, $netSql AS net_amount, s.payment_status,
                s.payment_date, s.document_path, s.memo, s.created_at, s.updated_at
         FROM sales s
         LEFT JOIN crops c ON c.id = s.crop_id AND c.user_id = s.user_id
         LEFT JOIN fields f ON f.id = s.field_id AND f.user_id = s.user_id
         WHERE s.user_id = :user_id AND s.sale_date >= :start_date AND s.sale_date <= :end_date
         ORDER BY s.sale_date ASC, s.id ASC"
    );
    $stmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['sale_date'], $row['sales_channel'] ?? '', $row['crop_name'] ?? '', $row['field_name'] ?? '',
            $row['buyer'] ?? '', $row['product_name'], $row['quantity'] ?? '', $row['unit'] ?? '',
            $row['unit_price'] ?? '', (int)$row['gross_amount'], (int)$row['fee_amount'], (int)$row['shipping_amount'],
            (int)$row['net_amount'], $row['payment_status'] ?? '', $row['payment_date'] ?? '', $row['document_path'] ?? '',
            $row['memo'] ?? '', $row['created_at'], $row['updated_at'],
        ];
    }
} elseif ($type === 'expenses') {
    $rows[] = ['支払日', '経費カテゴリ', '作物', '圃場', '支払先', '内容', '金額', '支払方法', '領収書写真パス', 'メモ', '作成日時', '更新日時'];
    $stmt = db()->prepare(
        'SELECT e.expense_date, ec.name AS category_name, c.name AS crop_name, f.name AS field_name, e.payee,
                e.description, e.amount, e.payment_method, e.receipt_path, e.memo, e.created_at, e.updated_at
         FROM expenses e
         LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id
         LEFT JOIN crops c ON c.id = e.crop_id AND c.user_id = e.user_id
         LEFT JOIN fields f ON f.id = e.field_id AND f.user_id = e.user_id
         WHERE e.user_id = :user_id AND e.expense_date >= :start_date AND e.expense_date <= :end_date
         ORDER BY e.expense_date ASC, e.id ASC'
    );
    $stmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['expense_date'], $row['category_name'] ?? '', $row['crop_name'] ?? '', $row['field_name'] ?? '',
            $row['payee'] ?? '', $row['description'], (int)$row['amount'], $row['payment_method'] ?? '',
            $row['receipt_path'] ?? '', $row['memo'] ?? '', $row['created_at'], $row['updated_at'],
        ];
    }
} elseif ($type === 'diaries') {
    $rows[] = ['作業日', '作物', '圃場', '天気', '作業内容', '写真パス', '作成日時', '更新日時'];
    $stmt = db()->prepare(
        'SELECT d.work_date, c.name AS crop_name, f.name AS field_name, d.weather, d.work_content,
                d.photo_path, d.created_at, d.updated_at
         FROM diaries d
         LEFT JOIN crops c ON c.id = d.crop_id AND c.user_id = d.user_id
         LEFT JOIN fields f ON f.id = d.field_id AND f.user_id = d.user_id
         WHERE d.user_id = :user_id AND d.work_date >= :start_date AND d.work_date <= :end_date
         ORDER BY d.work_date ASC, d.id ASC'
    );
    $stmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            $row['work_date'], $row['crop_name'] ?? '', $row['field_name'] ?? '', $row['weather'] ?? '',
            $row['work_content'], $row['photo_path'] ?? '', $row['created_at'], $row['updated_at'],
        ];
    }
} elseif ($type === 'annual_summary') {
    $yearStart = sprintf('%04d-01-01', $year);
    $yearEnd = sprintf('%04d-12-31', $year);

    $salesSummaryStmt = db()->prepare("SELECT COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total, COALESCE(SUM($netSql), 0) AS net_total FROM sales WHERE user_id = :user_id AND sale_date >= :start_date AND sale_date <= :end_date");
    $salesSummaryStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    $salesSummary = $salesSummaryStmt->fetch() ?: ['gross_total' => 0, 'net_total' => 0];
    $grossTotal = (int)$salesSummary['gross_total'];
    $netTotal = (int)$salesSummary['net_total'];

    $expenseSummaryStmt = db()->prepare('SELECT COALESCE(SUM(COALESCE(amount, 0)), 0) FROM expenses WHERE user_id = :user_id AND expense_date >= :start_date AND expense_date <= :end_date');
    $expenseSummaryStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    $expenseTotal = (int)$expenseSummaryStmt->fetchColumn();

    $rows[] = ['セクション1：年間サマリー'];
    $rows[] = ['対象年', '売上総額', '差引入金額', '経費合計', '売上総額ベース簡易差引', '入金額ベース簡易差引'];
    $rows[] = [$year, $grossTotal, $netTotal, $expenseTotal, $grossTotal - $expenseTotal, $netTotal - $expenseTotal];
    $rows[] = [];

    $monthly = [];
    for ($month = 1; $month <= 12; $month++) {
        $monthly[$month] = ['gross_total' => 0, 'net_total' => 0, 'expense_total' => 0];
    }
    $monthlySalesStmt = db()->prepare("SELECT CAST(strftime('%m', sale_date) AS INTEGER) AS month, COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total, COALESCE(SUM($netSql), 0) AS net_total FROM sales WHERE user_id = :user_id AND sale_date >= :start_date AND sale_date <= :end_date GROUP BY strftime('%m', sale_date)");
    $monthlySalesStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    foreach ($monthlySalesStmt->fetchAll() as $row) {
        $monthly[(int)$row['month']]['gross_total'] = (int)$row['gross_total'];
        $monthly[(int)$row['month']]['net_total'] = (int)$row['net_total'];
    }
    $monthlyExpenseStmt = db()->prepare("SELECT CAST(strftime('%m', expense_date) AS INTEGER) AS month, COALESCE(SUM(COALESCE(amount, 0)), 0) AS expense_total FROM expenses WHERE user_id = :user_id AND expense_date >= :start_date AND expense_date <= :end_date GROUP BY strftime('%m', expense_date)");
    $monthlyExpenseStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    foreach ($monthlyExpenseStmt->fetchAll() as $row) {
        $monthly[(int)$row['month']]['expense_total'] = (int)$row['expense_total'];
    }
    $rows[] = ['セクション2：月別集計'];
    $rows[] = ['月', '売上総額', '差引入金額', '経費合計', '売上総額ベース差引', '入金額ベース差引'];
    foreach ($monthly as $month => $row) {
        $rows[] = [$month . '月', $row['gross_total'], $row['net_total'], $row['expense_total'], $row['gross_total'] - $row['expense_total'], $row['net_total'] - $row['expense_total']];
    }
    $rows[] = [];

    $categoryStmt = db()->prepare('SELECT COALESCE(ec.name, "未分類") AS category_name, COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS total_amount FROM expenses e LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id WHERE e.user_id = :user_id AND e.expense_date >= :start_date AND e.expense_date <= :end_date GROUP BY e.category_id, ec.name ORDER BY total_amount DESC, category_name ASC');
    $categoryStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    $rows[] = ['セクション3：経費カテゴリ別集計'];
    $rows[] = ['経費カテゴリ', '金額', '割合'];
    foreach ($categoryStmt->fetchAll() as $row) {
        $rows[] = [$row['category_name'], (int)$row['total_amount'], format_percent((int)$row['total_amount'], $expenseTotal)];
    }
    $rows[] = [];

    $channelStmt = db()->prepare("SELECT COALESCE(NULLIF(TRIM(sales_channel), ''), '未設定') AS channel_name, COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total, COALESCE(SUM($netSql), 0) AS net_total FROM sales WHERE user_id = :user_id AND sale_date >= :start_date AND sale_date <= :end_date GROUP BY COALESCE(NULLIF(TRIM(sales_channel), ''), '未設定') ORDER BY gross_total DESC, channel_name ASC");
    $channelStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
    $rows[] = ['セクション4：販売経路別売上集計'];
    $rows[] = ['販売経路', '売上総額', '差引入金額', '割合'];
    foreach ($channelStmt->fetchAll() as $row) {
        $rows[] = [$row['channel_name'], (int)$row['gross_total'], (int)$row['net_total'], format_percent((int)$row['gross_total'], $grossTotal)];
    }
    $rows[] = [];

    $appendDimensionRows = static function (array &$rows, string $title, string $nameLabel, string $idColumn, string $tableName, int $userId, string $yearStart, string $yearEnd, string $netSql): void {
        $salesStmt = db()->prepare("SELECT COALESCE(CAST(s.$idColumn AS TEXT), 'none') AS dimension_key, COALESCE(d.name, '未設定') AS dimension_name, COALESCE(SUM(COALESCE(s.gross_amount, 0)), 0) AS gross_total, COALESCE(SUM($netSql), 0) AS net_total FROM sales s LEFT JOIN $tableName d ON d.id = s.$idColumn AND d.user_id = s.user_id WHERE s.user_id = :user_id AND s.sale_date >= :start_date AND s.sale_date <= :end_date GROUP BY s.$idColumn, d.name");
        $salesStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
        $merged = [];
        foreach ($salesStmt->fetchAll() as $row) {
            $merged[$row['dimension_key']] = ['name' => $row['dimension_name'], 'gross_total' => (int)$row['gross_total'], 'net_total' => (int)$row['net_total'], 'expense_total' => 0];
        }
        $expenseStmt = db()->prepare("SELECT COALESCE(CAST(e.$idColumn AS TEXT), 'none') AS dimension_key, COALESCE(d.name, '未設定') AS dimension_name, COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total FROM expenses e LEFT JOIN $tableName d ON d.id = e.$idColumn AND d.user_id = e.user_id WHERE e.user_id = :user_id AND e.expense_date >= :start_date AND e.expense_date <= :end_date GROUP BY e.$idColumn, d.name");
        $expenseStmt->execute([':user_id' => $userId, ':start_date' => $yearStart, ':end_date' => $yearEnd]);
        foreach ($expenseStmt->fetchAll() as $row) {
            $key = $row['dimension_key'];
            if (!isset($merged[$key])) {
                $merged[$key] = ['name' => $row['dimension_name'], 'gross_total' => 0, 'net_total' => 0, 'expense_total' => 0];
            }
            $merged[$key]['expense_total'] = (int)$row['expense_total'];
        }
        usort($merged, static function (array $a, array $b): int {
            $aAmount = max($a['gross_total'], $a['net_total'], $a['expense_total']);
            $bAmount = max($b['gross_total'], $b['net_total'], $b['expense_total']);
            return $aAmount === $bAmount ? strcmp($a['name'], $b['name']) : $bAmount <=> $aAmount;
        });
        $rows[] = [$title];
        $rows[] = [$nameLabel, '売上総額', '差引入金額', '経費合計', '売上総額ベース差引', '入金額ベース差引'];
        foreach ($merged as $row) {
            $rows[] = [$row['name'], $row['gross_total'], $row['net_total'], $row['expense_total'], $row['gross_total'] - $row['expense_total'], $row['net_total'] - $row['expense_total']];
        }
        $rows[] = [];
    };
    $appendDimensionRows($rows, 'セクション5：作物別集計', '作物', 'crop_id', 'crops', $userId, $yearStart, $yearEnd, $netSql);
    $appendDimensionRows($rows, 'セクション6：圃場別集計', '圃場', 'field_id', 'fields', $userId, $yearStart, $yearEnd, $netSql);
} elseif ($type === 'finance_all') {
    $rows[] = ['日付', '区分', 'カテゴリ・販売経路', '作物', '圃場', '取引先', '内容・品目', '入金額', '出金額', '差引', '支払方法・入金状況', '証憑パス', 'メモ'];
    $combined = [];

    $salesStmt = db()->prepare(
        "SELECT s.sale_date AS date_value, s.sales_channel, c.name AS crop_name, f.name AS field_name, s.buyer,
                s.product_name, $netSql AS net_amount, s.payment_status, s.document_path, s.memo, s.id
         FROM sales s
         LEFT JOIN crops c ON c.id = s.crop_id AND c.user_id = s.user_id
         LEFT JOIN fields f ON f.id = s.field_id AND f.user_id = s.user_id
         WHERE s.user_id = :user_id AND s.sale_date >= :start_date AND s.sale_date <= :end_date"
    );
    $salesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
    foreach ($salesStmt->fetchAll() as $row) {
        $netAmount = (int)$row['net_amount'];
        $combined[] = ['sort_date' => $row['date_value'], 'sort_type' => 1, 'sort_id' => (int)$row['id'], 'row' => [$row['date_value'], '売上', $row['sales_channel'] ?? '', $row['crop_name'] ?? '', $row['field_name'] ?? '', $row['buyer'] ?? '', $row['product_name'], $netAmount, '', $netAmount, $row['payment_status'] ?? '', $row['document_path'] ?? '', $row['memo'] ?? '']];
    }

    $expenseStmt = db()->prepare(
        'SELECT e.expense_date AS date_value, ec.name AS category_name, c.name AS crop_name, f.name AS field_name,
                e.payee, e.description, e.amount, e.payment_method, e.receipt_path, e.memo, e.id
         FROM expenses e
         LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = e.user_id
         LEFT JOIN crops c ON c.id = e.crop_id AND c.user_id = e.user_id
         LEFT JOIN fields f ON f.id = e.field_id AND f.user_id = e.user_id
         WHERE e.user_id = :user_id AND e.expense_date >= :start_date AND e.expense_date <= :end_date'
    );
    $expenseStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
    foreach ($expenseStmt->fetchAll() as $row) {
        $amount = (int)$row['amount'];
        $combined[] = ['sort_date' => $row['date_value'], 'sort_type' => 2, 'sort_id' => (int)$row['id'], 'row' => [$row['date_value'], '経費', $row['category_name'] ?? '', $row['crop_name'] ?? '', $row['field_name'] ?? '', $row['payee'] ?? '', $row['description'], '', $amount, -$amount, $row['payment_method'] ?? '', $row['receipt_path'] ?? '', $row['memo'] ?? '']];
    }

    usort($combined, static function (array $a, array $b): int {
        if ($a['sort_date'] === $b['sort_date']) {
            if ($a['sort_type'] === $b['sort_type']) {
                return $a['sort_id'] <=> $b['sort_id'];
            }
            return $a['sort_type'] <=> $b['sort_type'];
        }
        return strcmp($a['sort_date'], $b['sort_date']);
    });
    foreach ($combined as $entry) {
        $rows[] = $entry['row'];
    }
}

$filename = build_csv_filename($type, $year, $dateFrom, $dateTo);
output_csv_download($filename, $rows, $encoding);
