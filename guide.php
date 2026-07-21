<?php
require_once __DIR__ . '/includes/auth.php';

$isLoggedIn = is_logged_in();
$loginTarget = static fn (string $path): string => $isLoggedIn ? $path : 'login.php';
$pageTitle = '使い方 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card public-page guide-page">
  <div class="public-hero">
    <p class="eyebrow">はじめての方へ</p>
    <h2>AGRIMOREの使い方</h2>
    <p class="description">AGRIMOREは、日々の農作業・売上・経費をまとめて記録できる農業日誌アプリです。まずは作物と圃場を登録し、日誌・経費・売上を少しずつ記録していきましょう。</p>
    <div class="button-row">
      <?php if ($isLoggedIn): ?>
        <a class="btn primary" href="dashboard.php">ダッシュボードへ</a>
      <?php else: ?>
        <a class="btn primary" href="register.php">無料で始める</a>
        <a class="btn" href="login.php">ログイン</a>
      <?php endif; ?>
    </div>
  </div>

  <section class="public-section">
    <h3>はじめにやること</h3>
    <ol class="step-list">
      <li><span>1</span>アカウントを作成する</li>
      <li><span>2</span>作物を登録する</li>
      <li><span>3</span>圃場を登録する</li>
      <li><span>4</span>日誌を登録する</li>
      <li><span>5</span>経費を登録する</li>
      <li><span>6</span>売上を登録する</li>
      <li><span>7</span>年間集計を確認する</li>
      <li><span>8</span>CSV出力でデータを保存する</li>
    </ol>
  </section>

  <div class="public-card-grid">
    <section class="info-card">
      <h3>作物・圃場を登録する</h3>
      <p>日誌・売上・経費を整理しやすくするために、最初に作物と圃場を登録します。</p>
      <div class="button-row"><a class="btn" href="<?= e($loginTarget('crops.php')) ?>">作物管理へ</a><a class="btn" href="<?= e($loginTarget('fields.php')) ?>">圃場管理へ</a></div>
    </section>
    <section class="info-card">
      <h3>日誌を登録する</h3>
      <p>作業日、作物、圃場、天気、作業内容、写真を記録できます。生育状況や作業内容を後から見返すために使います。</p>
      <div class="button-row"><a class="btn" href="<?= e($loginTarget('diary_create.php')) ?>">日誌登録へ</a><a class="btn" href="<?= e($loginTarget('diary_list.php')) ?>">日誌一覧へ</a></div>
    </section>
    <section class="info-card">
      <h3>経費を登録する</h3>
      <p>肥料、農薬、資材、燃料代などの農業経費を記録できます。領収書写真も添付できます。</p>
      <div class="button-row"><a class="btn" href="<?= e($loginTarget('expense_create.php')) ?>">経費登録へ</a><a class="btn" href="<?= e($loginTarget('expense_list.php')) ?>">経費一覧へ</a></div>
    </section>
    <section class="info-card">
      <h3>売上を登録する</h3>
      <p>JA出荷、直売所、個人販売などの売上を記録できます。販売経路や入金状況も管理できます。</p>
      <div class="button-row"><a class="btn" href="<?= e($loginTarget('sale_create.php')) ?>">売上登録へ</a><a class="btn" href="<?= e($loginTarget('sale_list.php')) ?>">売上一覧へ</a></div>
    </section>
    <section class="info-card info-card--wide">
      <h3>年間集計・CSV出力</h3>
      <p>入力した売上・経費をもとに、年間の簡易集計を確認できます。CSV出力を使うことで、Excelでの確認や確定申告準備にも活用できます。</p>
      <div class="button-row"><a class="btn" href="<?= e($loginTarget('annual_summary.php')) ?>">年間集計へ</a><a class="btn" href="<?= e($loginTarget('export.php')) ?>">CSV出力へ</a></div>
    </section>
  </div>

  <div class="notice-box-app">AGRIMOREの集計機能は、入力されたデータをもとにした簡易集計です。確定申告や税務上の最終判断は、税理士・税務署等へ確認してください。</div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
