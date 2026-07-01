<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$_SESSION['onboarding_' . $userId . '_annual_summary_viewed'] = true;
$selectedYear = get_selected_year();
$yearRange = get_year_range();
$startDate = sprintf('%04d-01-01', $selectedYear);
$endDate = sprintf('%04d-12-31', $selectedYear);

$netSql = 'COALESCE(net_amount, COALESCE(gross_amount, 0) - COALESCE(fee_amount, 0) - COALESCE(shipping_amount, 0))';

$salesSummaryStmt = db()->prepare(
    "SELECT
        COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total,
        COALESCE(SUM($netSql), 0) AS net_total
     FROM sales
     WHERE user_id = :user_id
       AND sale_date >= :start_date
       AND sale_date <= :end_date"
);
$salesSummaryStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$salesSummary = $salesSummaryStmt->fetch() ?: ['gross_total' => 0, 'net_total' => 0];
$grossTotal = (int)$salesSummary['gross_total'];
$netTotal = (int)$salesSummary['net_total'];

$expenseSummaryStmt = db()->prepare(
    'SELECT COALESCE(SUM(COALESCE(amount, 0)), 0) AS expense_total
     FROM expenses
     WHERE user_id = :user_id
       AND expense_date >= :start_date
       AND expense_date <= :end_date'
);
$expenseSummaryStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$expenseTotal = (int)$expenseSummaryStmt->fetchColumn();

$monthlyRows = [];
for ($month = 1; $month <= 12; $month++) {
    $monthlyRows[$month] = ['month' => $month, 'gross_total' => 0, 'net_total' => 0, 'expense_total' => 0];
}

$monthlySalesStmt = db()->prepare(
    "SELECT CAST(strftime('%m', sale_date) AS INTEGER) AS month,
        COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total,
        COALESCE(SUM($netSql), 0) AS net_total
     FROM sales
     WHERE user_id = :user_id
       AND sale_date >= :start_date
       AND sale_date <= :end_date
     GROUP BY strftime('%m', sale_date)"
);
$monthlySalesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
foreach ($monthlySalesStmt->fetchAll() as $row) {
    $month = (int)$row['month'];
    if (isset($monthlyRows[$month])) {
        $monthlyRows[$month]['gross_total'] = (int)$row['gross_total'];
        $monthlyRows[$month]['net_total'] = (int)$row['net_total'];
    }
}

$monthlyExpensesStmt = db()->prepare(
    "SELECT CAST(strftime('%m', expense_date) AS INTEGER) AS month,
        COALESCE(SUM(COALESCE(amount, 0)), 0) AS expense_total
     FROM expenses
     WHERE user_id = :user_id
       AND expense_date >= :start_date
       AND expense_date <= :end_date
     GROUP BY strftime('%m', expense_date)"
);
$monthlyExpensesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
foreach ($monthlyExpensesStmt->fetchAll() as $row) {
    $month = (int)$row['month'];
    if (isset($monthlyRows[$month])) {
        $monthlyRows[$month]['expense_total'] = (int)$row['expense_total'];
    }
}
$monthlyMax = max(1, ...array_map(static fn(array $row): int => max((int)$row['gross_total'], (int)$row['net_total'], (int)$row['expense_total']), $monthlyRows));

$categoryStmt = db()->prepare(
    'SELECT COALESCE(ec.name, "未分類") AS category_name,
        COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS total_amount
     FROM expenses e
     LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = :user_id
     WHERE e.user_id = :user_id
       AND e.expense_date >= :start_date
       AND e.expense_date <= :end_date
     GROUP BY e.category_id, ec.name
     ORDER BY total_amount DESC, category_name ASC'
);
$categoryStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$categoryTotals = $categoryStmt->fetchAll();

$channelStmt = db()->prepare(
    "SELECT COALESCE(NULLIF(TRIM(sales_channel), ''), '未設定') AS channel_name,
        COALESCE(SUM(COALESCE(gross_amount, 0)), 0) AS gross_total,
        COALESCE(SUM($netSql), 0) AS net_total
     FROM sales
     WHERE user_id = :user_id
       AND sale_date >= :start_date
       AND sale_date <= :end_date
     GROUP BY COALESCE(NULLIF(TRIM(sales_channel), ''), '未設定')
     ORDER BY gross_total DESC, channel_name ASC"
);
$channelStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$channelTotals = $channelStmt->fetchAll();

$mergeDimensionTotals = static function (array $salesRows, array $expenseRows): array {
    $merged = [];

    foreach ($salesRows as $row) {
        $key = $row['dimension_key'];
        $merged[$key] = [
            'name' => $row['dimension_name'],
            'gross_total' => (int)$row['gross_total'],
            'net_total' => (int)$row['net_total'],
            'expense_total' => 0,
        ];
    }

    foreach ($expenseRows as $row) {
        $key = $row['dimension_key'];
        if (!isset($merged[$key])) {
            $merged[$key] = [
                'name' => $row['dimension_name'],
                'gross_total' => 0,
                'net_total' => 0,
                'expense_total' => 0,
            ];
        }
        $merged[$key]['expense_total'] = (int)$row['expense_total'];
    }

    usort($merged, static function (array $a, array $b): int {
        $aAmount = max($a['gross_total'], $a['net_total'], $a['expense_total']);
        $bAmount = max($b['gross_total'], $b['net_total'], $b['expense_total']);
        if ($aAmount === $bAmount) {
            return strcmp($a['name'], $b['name']);
        }
        return $bAmount <=> $aAmount;
    });

    return $merged;
};

$cropSalesStmt = db()->prepare(
    "SELECT COALESCE(CAST(s.crop_id AS TEXT), 'none') AS dimension_key,
        COALESCE(c.name, '未設定') AS dimension_name,
        COALESCE(SUM(COALESCE(s.gross_amount, 0)), 0) AS gross_total,
        COALESCE(SUM($netSql), 0) AS net_total
     FROM sales s
     LEFT JOIN crops c ON c.id = s.crop_id AND c.user_id = :user_id
     WHERE s.user_id = :user_id
       AND s.sale_date >= :start_date
       AND s.sale_date <= :end_date
     GROUP BY s.crop_id, c.name"
);
$cropSalesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$cropExpenseStmt = db()->prepare(
    "SELECT COALESCE(CAST(e.crop_id AS TEXT), 'none') AS dimension_key,
        COALESCE(c.name, '未設定') AS dimension_name,
        COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total
     FROM expenses e
     LEFT JOIN crops c ON c.id = e.crop_id AND c.user_id = :user_id
     WHERE e.user_id = :user_id
       AND e.expense_date >= :start_date
       AND e.expense_date <= :end_date
     GROUP BY e.crop_id, c.name"
);
$cropExpenseStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$cropTotals = $mergeDimensionTotals($cropSalesStmt->fetchAll(), $cropExpenseStmt->fetchAll());

$fieldSalesStmt = db()->prepare(
    "SELECT COALESCE(CAST(s.field_id AS TEXT), 'none') AS dimension_key,
        COALESCE(f.name, '未設定') AS dimension_name,
        COALESCE(SUM(COALESCE(s.gross_amount, 0)), 0) AS gross_total,
        COALESCE(SUM($netSql), 0) AS net_total
     FROM sales s
     LEFT JOIN fields f ON f.id = s.field_id AND f.user_id = :user_id
     WHERE s.user_id = :user_id
       AND s.sale_date >= :start_date
       AND s.sale_date <= :end_date
     GROUP BY s.field_id, f.name"
);
$fieldSalesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$fieldExpenseStmt = db()->prepare(
    "SELECT COALESCE(CAST(e.field_id AS TEXT), 'none') AS dimension_key,
        COALESCE(f.name, '未設定') AS dimension_name,
        COALESCE(SUM(COALESCE(e.amount, 0)), 0) AS expense_total
     FROM expenses e
     LEFT JOIN fields f ON f.id = e.field_id AND f.user_id = :user_id
     WHERE e.user_id = :user_id
       AND e.expense_date >= :start_date
       AND e.expense_date <= :end_date
     GROUP BY e.field_id, f.name"
);
$fieldExpenseStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$fieldTotals = $mergeDimensionTotals($fieldSalesStmt->fetchAll(), $fieldExpenseStmt->fetchAll());

$unpaidSalesStmt = db()->prepare(
    "SELECT id, sale_date, buyer, product_name, COALESCE(gross_amount, 0) AS gross_amount,
        $netSql AS net_amount, payment_status, payment_date
     FROM sales
     WHERE user_id = :user_id
       AND sale_date >= :start_date
       AND sale_date <= :end_date
       AND payment_status IN ('未入金', '一部入金')
     ORDER BY sale_date DESC, id DESC"
);
$unpaidSalesStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$unpaidSales = $unpaidSalesStmt->fetchAll();

$missingReceiptStmt = db()->prepare(
    'SELECT e.id, e.expense_date, COALESCE(ec.name, "未分類") AS category_name, e.payee, e.description, COALESCE(e.amount, 0) AS amount
     FROM expenses e
     LEFT JOIN expense_categories ec ON ec.id = e.category_id AND ec.user_id = :user_id
     WHERE e.user_id = :user_id
       AND e.expense_date >= :start_date
       AND e.expense_date <= :end_date
       AND (e.receipt_path IS NULL OR TRIM(e.receipt_path) = "")
     ORDER BY e.expense_date DESC, e.id DESC'
);
$missingReceiptStmt->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$missingReceiptExpenses = $missingReceiptStmt->fetchAll();


$showAnnualAiAdvice = false; // 将来的にAI分析機能を再開する場合は true に変更します。

if ($showAnnualAiAdvice) {
    $unpaidTotal = array_sum(array_map(static fn(array $row): int => (int)$row['net_amount'], $unpaidSales));
    $missingReceiptTotal = array_sum(array_map(static fn(array $row): int => (int)$row['amount'], $missingReceiptExpenses));
    $negativeMonths = array_values(array_filter($monthlyRows, static fn(array $row): bool => ((int)$row['net_total'] - (int)$row['expense_total']) < 0));
    $activeMonths = array_values(array_filter($monthlyRows, static fn(array $row): bool => (int)$row['gross_total'] > 0 || (int)$row['expense_total'] > 0));

    $annualAdvice = [];
    $annualAdviceError = '';
    $openAiApiKey = trim((string)getenv('OPENAI_API_KEY'));
    $openAiModel = trim((string)(getenv('OPENAI_MODEL') ?: 'gpt-5.4-mini'));

    if ($openAiApiKey === '') {
        $annualAdviceError = 'OPENAI_API_KEY が未設定のため、AIによるリアルタイム分析は実行されていません。サーバー環境変数にAPIキーを設定すると、表示のたびにAIが最新データを分析して文章を生成します。';
    } else {
        $monthlyForAi = array_map(static function (array $row): array {
            return [
                'month' => (int)$row['month'],
                'gross_total' => (int)$row['gross_total'],
                'net_total' => (int)$row['net_total'],
                'expense_total' => (int)$row['expense_total'],
                'gross_profit' => (int)$row['gross_total'] - (int)$row['expense_total'],
                'net_profit' => (int)$row['net_total'] - (int)$row['expense_total'],
            ];
        }, array_values($monthlyRows));

        $analysisData = [
            'year' => $selectedYear,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'gross_total' => $grossTotal,
                'net_total' => $netTotal,
                'expense_total' => $expenseTotal,
                'gross_profit' => $grossTotal - $expenseTotal,
                'net_profit' => $netTotal - $expenseTotal,
                'expense_ratio_percent' => $grossTotal > 0 ? round(($expenseTotal / $grossTotal) * 100, 1) : null,
                'net_collection_ratio_percent' => $grossTotal > 0 ? round(($netTotal / $grossTotal) * 100, 1) : null,
            ],
            'monthly' => $monthlyForAi,
            'top_expense_categories' => array_map(static fn(array $row): array => [
                'name' => (string)$row['category_name'],
                'amount' => (int)$row['total_amount'],
            ], array_slice($categoryTotals, 0, 5)),
            'top_sales_channels' => array_map(static fn(array $row): array => [
                'name' => (string)$row['channel_name'],
                'gross_total' => (int)$row['gross_total'],
                'net_total' => (int)$row['net_total'],
            ], array_slice($channelTotals, 0, 5)),
            'checks' => [
                'unpaid_sales_count' => count($unpaidSales),
                'unpaid_net_total' => $unpaidTotal,
                'missing_receipt_count' => count($missingReceiptExpenses),
                'missing_receipt_total' => $missingReceiptTotal,
                'negative_month_count' => count($negativeMonths),
                'active_month_count' => count($activeMonths),
            ],
        ];

        $payload = [
            'model' => $openAiModel,
            'store' => false,
            'input' => [
                [
                    'role' => 'developer',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'あなたは農業経営の記録アプリに組み込まれた分析アシスタントです。入力データだけを根拠に、日本語で簡潔かつ実務的に助言してください。税務・会計・法務の断定は避け、必要に応じて専門家確認を促してください。出力はJSONのみ。形式: {"advice":[{"type":"success|info|warning|danger","title":"20文字程度","body":"120文字以内"}]}。最大5件。',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => "次の年間集計データを分析し、優先度の高い順にアドバイスを作成してください。\n" . json_encode($analysisData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]],
                ],
            ],
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openAiApiKey,
                ],
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents('https://api.openai.com/v1/responses', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        $statusCode = preg_match('/\s(\d{3})\s/', $statusLine, $matches) ? (int)$matches[1] : 0;

        if ($responseBody === false || $statusCode < 200 || $statusCode >= 300) {
            $annualAdviceError = 'AI分析の取得に失敗しました。時間をおいて再度お試しください。';
        } else {
            $responseJson = json_decode($responseBody, true);
            $outputText = is_array($responseJson) ? (string)($responseJson['output_text'] ?? '') : '';

            if ($outputText === '' && is_array($responseJson['output'] ?? null)) {
                foreach ($responseJson['output'] as $outputItem) {
                    foreach (($outputItem['content'] ?? []) as $contentItem) {
                        $outputText .= (string)($contentItem['text'] ?? '');
                    }
                }
            }

            $decodedAdvice = json_decode($outputText, true);
            if (is_array($decodedAdvice['advice'] ?? null)) {
                foreach (array_slice($decodedAdvice['advice'], 0, 5) as $item) {
                    $type = in_array($item['type'] ?? '', ['success', 'info', 'warning', 'danger'], true) ? (string)$item['type'] : 'info';
                    $title = trim((string)($item['title'] ?? ''));
                    $body = trim((string)($item['body'] ?? ''));
                    if ($title !== '' && $body !== '') {
                        $annualAdvice[] = ['type' => $type, 'title' => $title, 'body' => $body];
                    }
                }
            }

            if (!$annualAdvice) {
                $annualAdviceError = 'AI分析の結果を読み取れませんでした。時間をおいて再度お試しください。';
            }
        }
    }

}

$yenClass = static fn(int $amount): string => $amount < 0 ? 'amount-negative' : 'amount-positive';

$pageTitle = '年間集計 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card annual-hero">
  <div class="annual-title-row">
    <div>
      <h2>年間集計</h2>
      <p class="description"><?= e((string)$selectedYear) ?>年（<?= e($startDate) ?>〜<?= e($endDate) ?>）の売上・経費・差引を確認できます。</p>
    </div>
    <form class="year-select-form" method="get" action="annual_summary.php">
      <label for="year">対象年</label>
      <select id="year" name="year">
        <?php foreach ($yearRange as $year): ?>
          <option value="<?= e((string)((int)$year)) ?>" <?= e((string)($year === $selectedYear ? 'selected' : '')) ?>><?= e((string)((int)$year)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" type="submit">表示</button>
    </form>
  </div>
  <p class="alert annual-note">本サービスの集計は入力データをもとにした簡易集計です。 確定申告や税務上の最終判断は、税理士・税務署等へご確認ください。</p>
  <div class="button-row annual-export-actions">
    <a class="btn primary" href="export_csv.php?type=annual_summary&amp;year=<?= e((string)((int)$selectedYear)) ?>&amp;encoding=utf8_bom">この年の年間集計CSVを出力する</a>
    <a class="btn" href="export_csv.php?type=sales&amp;year=<?= e((string)((int)$selectedYear)) ?>&amp;encoding=utf8_bom">この年の売上CSVを出力する</a>
    <a class="btn" href="export_csv.php?type=expenses&amp;year=<?= e((string)((int)$selectedYear)) ?>&amp;encoding=utf8_bom">この年の経費CSVを出力する</a>
  </div>
</section>

<section class="card">
  <h3>年間サマリー</h3>
  <div class="summary-grid annual-summary-grid">
    <div class="summary-card annual-summary-card"><span>売上総額</span><strong><?= e(format_yen($grossTotal)) ?></strong></div>
    <div class="summary-card annual-summary-card"><span>差引入金額</span><strong><?= e(format_yen($netTotal)) ?></strong></div>
    <div class="summary-card annual-summary-card"><span>経費合計</span><strong><?= e(format_yen($expenseTotal)) ?></strong></div>
    <div class="summary-card annual-summary-card"><span>売上総額ベース簡易差引</span><strong class="<?= e($yenClass($grossTotal - $expenseTotal)) ?>"><?= e(format_yen($grossTotal - $expenseTotal)) ?></strong></div>
    <div class="summary-card annual-summary-card"><span>入金額ベース簡易差引</span><strong class="<?= e($yenClass($netTotal - $expenseTotal)) ?>"><?= e(format_yen($netTotal - $expenseTotal)) ?></strong></div>
  </div>
</section>


<?php if ($showAnnualAiAdvice): ?>
<section class="card annual-ai-advice-card">
  <div class="card-title-row">
    <div>
      <h3>AIアドバイス</h3>
      <p class="description">入力済みの売上・経費・未入金・領収書状況から、次に確認したいポイントを自動で提案します。</p>
    </div>
    <span class="ai-advice-badge">OpenAI分析</span>
  </div>
  <?php if ($annualAdvice): ?>
    <div class="ai-advice-list">
      <?php foreach ($annualAdvice as $advice): ?>
        <article class="ai-advice-item ai-advice-item--<?= e($advice['type']) ?>">
          <h4><?= e($advice['title']) ?></h4>
          <p><?= e($advice['body']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="alert ai-advice-error"><?= e($annualAdviceError) ?></p>
  <?php endif; ?>
  <p class="description">※ OpenAI APIを使って表示時点の入力データを分析します。経営・税務上の最終判断は専門家へご相談ください。</p>
</section>

<?php endif; ?>

<section class="card">
  <h3>月別集計</h3>
  <div class="table-wrap annual-table-wrap">
    <table class="annual-table monthly-table">
      <thead><tr><th>月</th><th>売上総額</th><th>差引入金額</th><th>経費合計</th><th>売上総額ベース差引</th><th>入金額ベース差引</th><th>簡易バー</th></tr></thead>
      <tbody>
        <?php foreach ($monthlyRows as $row): ?>
          <?php
            $monthGross = (int)$row['gross_total'];
            $monthNet = (int)$row['net_total'];
            $monthExpense = (int)$row['expense_total'];
          ?>
          <tr>
            <td data-label="月"><?= e((string)((int)$row['month'])) ?>月</td>
            <td data-label="売上総額"><?= e(format_yen($monthGross)) ?></td>
            <td data-label="差引入金額"><?= e(format_yen($monthNet)) ?></td>
            <td data-label="経費合計"><?= e(format_yen($monthExpense)) ?></td>
            <td data-label="売上総額ベース差引" class="<?= e($yenClass($monthGross - $monthExpense)) ?>"><?= e(format_yen($monthGross - $monthExpense)) ?></td>
            <td data-label="入金額ベース差引" class="<?= e($yenClass($monthNet - $monthExpense)) ?>"><?= e(format_yen($monthNet - $monthExpense)) ?></td>
            <td data-label="簡易バー">
              <div class="mini-bars" aria-label="<?= e((string)((int)$row['month'])) ?>月の簡易バー">
                <span class="mini-bar sales" style="width: <?= e((string)(max(2, (int)round($monthGross / $monthlyMax * 100)))) ?>%"></span>
                <span class="mini-bar expense" style="width: <?= e((string)(max(2, (int)round($monthExpense / $monthlyMax * 100)))) ?>%"></span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="description mini-bar-legend"><span class="legend-sales">■</span>売上総額 <span class="legend-expense">■</span>経費合計</p>
</section>

<section class="card">
  <h3>経費カテゴリ別集計</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>カテゴリ名</th><th>金額</th><th>経費全体に占める割合</th></tr></thead><tbody>
    <?php if (!$categoryTotals): ?><tr><td colspan="3">対象年の経費はありません。</td></tr><?php endif; ?>
    <?php foreach ($categoryTotals as $row): ?><tr><td data-label="カテゴリ名"><?= e($row['category_name']) ?></td><td data-label="金額"><strong><?= e(format_yen((int)$row['total_amount'])) ?></strong></td><td data-label="割合"><?= e(format_percent((int)$row['total_amount'], $expenseTotal)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="card">
  <h3>販売経路別売上集計</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>販売経路</th><th>売上総額</th><th>差引入金額</th><th>売上全体に占める割合</th></tr></thead><tbody>
    <?php if (!$channelTotals): ?><tr><td colspan="4">対象年の売上はありません。</td></tr><?php endif; ?>
    <?php foreach ($channelTotals as $row): ?><tr><td data-label="販売経路"><?= e($row['channel_name']) ?></td><td data-label="売上総額"><strong><?= e(format_yen((int)$row['gross_total'])) ?></strong></td><td data-label="差引入金額"><?= e(format_yen((int)$row['net_total'])) ?></td><td data-label="割合"><?= e(format_percent((int)$row['gross_total'], $grossTotal)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="card">
  <h3>作物別集計</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>作物名</th><th>売上総額</th><th>差引入金額</th><th>経費合計</th><th>売上総額ベース差引</th><th>入金額ベース差引</th></tr></thead><tbody>
    <?php if (!$cropTotals): ?><tr><td colspan="6">対象年の作物別データはありません。</td></tr><?php endif; ?>
    <?php foreach ($cropTotals as $row): ?><?php $simpleGross = (int)$row['gross_total'] - (int)$row['expense_total']; $simpleNet = (int)$row['net_total'] - (int)$row['expense_total']; ?><tr><td data-label="作物名"><?= e($row['name']) ?></td><td data-label="売上総額"><?= e(format_yen((int)$row['gross_total'])) ?></td><td data-label="差引入金額"><?= e(format_yen((int)$row['net_total'])) ?></td><td data-label="経費合計"><?= e(format_yen((int)$row['expense_total'])) ?></td><td data-label="売上総額ベース差引" class="<?= e($yenClass($simpleGross)) ?>"><?= e(format_yen($simpleGross)) ?></td><td data-label="入金額ベース差引" class="<?= e($yenClass($simpleNet)) ?>"><?= e(format_yen($simpleNet)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="card">
  <h3>圃場別集計</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>圃場名</th><th>売上総額</th><th>差引入金額</th><th>経費合計</th><th>売上総額ベース差引</th><th>入金額ベース差引</th></tr></thead><tbody>
    <?php if (!$fieldTotals): ?><tr><td colspan="6">対象年の圃場別データはありません。</td></tr><?php endif; ?>
    <?php foreach ($fieldTotals as $row): ?><?php $simpleGross = (int)$row['gross_total'] - (int)$row['expense_total']; $simpleNet = (int)$row['net_total'] - (int)$row['expense_total']; ?><tr><td data-label="圃場名"><?= e($row['name']) ?></td><td data-label="売上総額"><?= e(format_yen((int)$row['gross_total'])) ?></td><td data-label="差引入金額"><?= e(format_yen((int)$row['net_total'])) ?></td><td data-label="経費合計"><?= e(format_yen((int)$row['expense_total'])) ?></td><td data-label="売上総額ベース差引" class="<?= e($yenClass($simpleGross)) ?>"><?= e(format_yen($simpleGross)) ?></td><td data-label="入金額ベース差引" class="<?= e($yenClass($simpleNet)) ?>"><?= e(format_yen($simpleNet)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="card">
  <h3>未入金の売上一覧</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>売上日</th><th>販売先</th><th>品目</th><th>売上総額</th><th>差引入金額</th><th>入金状況</th><th>入金日</th><th>詳細</th></tr></thead><tbody>
    <?php if (!$unpaidSales): ?><tr><td colspan="8">未入金の売上はありません。</td></tr><?php endif; ?>
    <?php foreach ($unpaidSales as $row): ?><tr><td data-label="売上日"><?= e($row['sale_date']) ?></td><td data-label="販売先"><?= e($row['buyer'] ?? '-') ?></td><td data-label="品目"><?= e($row['product_name']) ?></td><td data-label="売上総額"><?= e(format_yen((int)$row['gross_amount'])) ?></td><td data-label="差引入金額"><?= e(format_yen((int)$row['net_amount'])) ?></td><td data-label="入金状況"><?= e($row['payment_status'] ?? '未入金') ?></td><td data-label="入金日"><?= e($row['payment_date'] ?? '-') ?></td><td data-label="詳細"><a class="btn small" href="sale_detail.php?id=<?= e((string)((int)$row['id'])) ?>">詳細</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="card">
  <h3>領収書写真なしの経費一覧</h3>
  <div class="table-wrap annual-table-wrap"><table class="annual-table"><thead><tr><th>支払日</th><th>カテゴリ</th><th>支払先</th><th>内容</th><th>金額</th><th>詳細</th></tr></thead><tbody>
    <?php if (!$missingReceiptExpenses): ?><tr><td colspan="6">領収書写真なしの経費はありません。</td></tr><?php endif; ?>
    <?php foreach ($missingReceiptExpenses as $row): ?><tr><td data-label="支払日"><?= e($row['expense_date']) ?></td><td data-label="カテゴリ"><?= e($row['category_name']) ?></td><td data-label="支払先"><?= e($row['payee'] ?? '-') ?></td><td data-label="内容"><?= e($row['description']) ?></td><td data-label="金額"><strong><?= e(format_yen((int)$row['amount'])) ?></strong></td><td data-label="詳細"><a class="btn small" href="expense_detail.php?id=<?= e((string)((int)$row['id'])) ?>">詳細</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
