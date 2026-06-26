<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$_SESSION['onboarding_' . $userId . '_export_viewed'] = true;

$currentYear = (int)date('Y');
$yearRange = get_year_range();
$exportTypes = get_export_types();
$encodings = get_export_encodings();

$selectedType = get_query_param('type', 'sales');
if (!isset($exportTypes[$selectedType])) {
    $selectedType = 'sales';
}

$selectedYear = get_selected_year();
$dateFrom = get_query_param('date_from');
$dateTo = get_query_param('date_to');
$selectedEncoding = get_query_param('encoding', 'utf8_bom');
if (!isset($encodings[$selectedEncoding])) {
    $selectedEncoding = 'utf8_bom';
}

$pageTitle = 'CSV出力 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card export-hero">
  <h2>CSV出力</h2>
  <p class="description">登録した売上・経費・日誌データをCSVで出力できます。Excelや会計ソフトへの取り込み、税理士への共有、確定申告準備に活用できます。</p>
  <p class="alert annual-note">本サービスの集計は入力データをもとにした簡易集計です。 確定申告や税務上の最終判断は、税理士・税務署等へご確認ください。</p>
</section>

<section class="card">
  <h3>出力条件</h3>
  <form class="export-form" method="get" action="export_csv.php">
    <div class="export-form-grid">
      <label>
        出力対象
        <select name="type" required>
          <?php foreach ($exportTypes as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= e((string)($value === $selectedType ? 'selected' : '')) ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        対象年
        <select name="year" required>
          <?php foreach ($yearRange as $year): ?>
            <option value="<?= e((string)((int)$year)) ?>" <?= e((string)((int)$year === $selectedYear ? 'selected' : '')) ?>><?= e((string)((int)$year)) ?>年</option>
          <?php endforeach; ?>
        </select>
        <span class="form-help">年間集計CSVでは対象年を使用します。</span>
      </label>

      <label>
        開始日
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
        <span class="form-help">任意。指定時は対象年より期間指定を優先します。</span>
      </label>

      <label>
        終了日
        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
      </label>

      <label>
        文字コード
        <select name="encoding" required>
          <?php foreach ($encodings as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= e((string)($value === $selectedEncoding ? 'selected' : '')) ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-help">Excelで開きやすいよう、初期値はUTF-8 BOM付きです。</span>
      </label>
    </div>

    <div class="button-row export-actions">
      <button class="btn primary" type="submit">CSVをダウンロード</button>
      <a class="btn" href="dashboard.php">ダッシュボードへ戻る</a>
    </div>
  </form>
</section>

<section class="card">
  <h3>出力できるCSV</h3>
  <ul class="export-note-list">
    <li>売上CSV：売上日、販売経路、作物、圃場、販売先、品目、金額、入金状況などを出力します。</li>
    <li>経費CSV：支払日、経費カテゴリ、作物、圃場、支払先、内容、金額、支払方法などを出力します。</li>
    <li>日誌CSV：作業日、作物、圃場、天気、作業内容、写真パスなどを出力します。</li>
    <li>年間集計CSV：年間サマリー、月別、カテゴリ別、販売経路別、作物別、圃場別の集計を出力します。</li>
    <li>売上・経費まとめCSV：売上と経費を日付順にまとめ、簡易出納帳のように確認できます。</li>
  </ul>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
