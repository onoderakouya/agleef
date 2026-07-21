<?php
require_once __DIR__ . '/includes/auth.php';

$isLoggedIn = is_logged_in();
$pageTitle = 'AGRIMORE | 農業の記録を、もっとかんたんに。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AGRIMOREは、日々の作業日誌・売上・経費をまとめて記録できる、農家のためのシンプルな農業日誌アプリです。">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/css/lp.css">
</head>
<body>
  <header class="lp-header">
    <div class="lp-container lp-header__inner">
      <a class="lp-brand" href="index.php" aria-label="AGRIMORE トップページ">
        <span class="lp-brand__title">AGRIMORE</span>
        <span class="lp-brand__subtitle">農業日誌アプリ</span>
      </a>
      <nav class="lp-nav" aria-label="ランディングページ内ナビゲーション">
        <a href="#features">機能</a>
        <a href="guide.php">使い方</a>
        <a href="faq.php">よくある質問</a>
        <a href="contact.php">お問い合わせ</a>
        <a href="privacy.php">プライバシーポリシー</a>
        <a href="terms.php">利用規約</a>
        <a href="login.php">ログイン</a>
        <a class="lp-nav__cta" href="register.php">無料で始める</a>
        <?php if ($isLoggedIn): ?>
          <a class="lp-nav__dashboard" href="dashboard.php">ダッシュボード</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="lp-container hero__grid">
        <div class="hero__content">
          <p class="eyebrow">農家のためのシンプルな記録アプリ</p>
          <h1>農業の記録を、もっとかんたんに。</h1>
          <p class="hero__lead">AGRIMOREは、日々の作業日誌・売上・経費をまとめて記録できる、農家のためのシンプルな農業日誌アプリです。</p>
          <div class="hero__actions">
            <a class="lp-button lp-button--primary" href="register.php">無料で始める</a>
            <a class="lp-button lp-button--secondary" href="login.php">ログイン</a>
            <a class="lp-button lp-button--ghost" href="guide.php">使い方を見る</a>
            <?php if ($isLoggedIn): ?>
              <a class="lp-button lp-button--ghost" href="dashboard.php">ダッシュボードへ</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="hero__panel" aria-label="AGRIMOREで記録できる内容">
          <div class="hero-card hero-card--main">
            <span class="hero-card__icon">🌱</span>
            <div>
              <strong>今日の作業</strong>
              <p>作物・圃場・天気・写真をまとめて保存</p>
            </div>
          </div>
          <div class="hero__mini-cards">
            <div class="hero-card">
              <span>💰</span>
              <p>売上・経費を整理</p>
            </div>
            <div class="hero-card">
              <span>📊</span>
              <p>年間集計とCSV出力</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="problems-title">
      <div class="lp-container">
        <div class="section-heading">
          <p class="eyebrow">こんなお悩みはありませんか？</p>
          <h2 id="problems-title">農業の記録とお金の整理を、ひとつに。</h2>
        </div>
        <div class="problem-grid">
          <div class="problem-card">作業記録をノートやメモ帳に書いていて見返しづらい</div>
          <div class="problem-card">写真付きで生育状況を残したい</div>
          <div class="problem-card">経費の領収書を後で探すのが大変</div>
          <div class="problem-card">売上と経費をあとから整理するのが面倒</div>
          <div class="problem-card">確定申告前に慌てて集計している</div>
          <div class="problem-card">作物別・圃場別に数字を見たい</div>
        </div>
      </div>
    </section>

    <section id="features" class="section" aria-labelledby="features-title">
      <div class="lp-container">
        <div class="section-heading">
          <p class="eyebrow">機能紹介</p>
          <h2 id="features-title">日誌から売上・経費・集計までまとめて管理</h2>
          <p>農業の現場で必要な記録を、シンプルな画面で続けやすく整理できます。</p>
        </div>
        <div class="feature-grid">
          <article class="feature-card">
            <div class="feature-card__icon">📝</div>
            <h3>日誌記録</h3>
            <ul>
              <li>作業日</li>
              <li>作物</li>
              <li>圃場</li>
              <li>天気</li>
              <li>作業内容</li>
              <li>写真添付</li>
            </ul>
            <p>日々の作業を写真付きで記録できます。作物や圃場ごとに記録を残せるので、後から振り返りやすくなります。</p>
          </article>
          <article class="feature-card">
            <div class="feature-card__icon">🌾</div>
            <h3>作物・圃場管理</h3>
            <p>作物や圃場を登録して、日誌・売上・経費と紐づけられます。</p>
          </article>
          <article class="feature-card">
            <div class="feature-card__icon">🧾</div>
            <h3>経費記録</h3>
            <p>肥料・農薬・資材・燃料代などの農業経費を記録できます。領収書写真も添付できます。</p>
          </article>
          <article class="feature-card">
            <div class="feature-card__icon">🍅</div>
            <h3>売上記録</h3>
            <p>JA出荷、直売所、個人販売などの売上を記録できます。販売経路や入金状況も管理できます。</p>
          </article>
          <article class="feature-card">
            <div class="feature-card__icon">📈</div>
            <h3>年間集計</h3>
            <p>年間の売上・経費・簡易差引を確認できます。月別・作物別・圃場別の集計にも対応します。</p>
          </article>
          <article class="feature-card">
            <div class="feature-card__icon">📁</div>
            <h3>CSV出力</h3>
            <p>売上・経費・日誌・年間集計をCSV出力できます。Excel確認や確定申告準備に活用できます。</p>
          </article>
        </div>
      </div>
    </section>

    <section id="how-to-use" class="section section--soft" aria-labelledby="how-to-title">
      <div class="lp-container">
        <div class="section-heading">
          <p class="eyebrow">使い方</p>
          <h2 id="how-to-title">はじめ方は3ステップ</h2>
          <p>まずは作物と圃場を登録し、日々の作業やお金の流れを記録していきます。難しい会計知識がなくても、農業の記録を整理できます。</p>
        </div>
        <div class="step-grid">
          <article class="step-card">
            <span class="step-card__number">1</span>
            <h3>アカウント作成</h3>
            <p>無料登録からユーザー名とパスワードを設定します。</p>
          </article>
          <article class="step-card">
            <span class="step-card__number">2</span>
            <h3>作物・圃場を登録</h3>
            <p>記録を整理しやすいように、栽培している作物と圃場を登録します。</p>
          </article>
          <article class="step-card">
            <span class="step-card__number">3</span>
            <h3>日誌・売上・経費を記録</h3>
            <p>日々の作業やお金の動きを入力し、年間集計やCSV出力に活用します。</p>
          </article>
        </div>
      </div>
    </section>

    <section class="section notice-section" aria-labelledby="notice-title">
      <div class="lp-container">
        <div class="notice-box">
          <h2 id="notice-title">確定申告サポートについて</h2>
          <p>本サービスの集計は入力データをもとにした簡易集計です。 確定申告や税務上の最終判断は、税理士・税務署等へご確認ください。</p>
        </div>
      </div>
    </section>

    <section class="cta-section" aria-labelledby="cta-title">
      <div class="lp-container cta-card">
        <p class="eyebrow">さっそく記録を始める</p>
        <h2 id="cta-title">まずは今日の作業記録から始めましょう。</h2>
        <div class="hero__actions hero__actions--center">
          <a class="lp-button lp-button--primary" href="register.php">無料で始める</a>
          <a class="lp-button lp-button--secondary" href="login.php">ログイン</a>
          <?php if ($isLoggedIn): ?>
            <a class="lp-button lp-button--ghost" href="dashboard.php">ダッシュボードへ</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="lp-footer">
    <div class="lp-container">
      <p>© 2026 AGRIMORE</p>
      <p class="lp-footer__links">
        <a href="guide.php">使い方</a>
        <span aria-hidden="true">/</span>
        <a href="faq.php">よくある質問</a>
        <span aria-hidden="true">/</span>
        <a href="privacy.php">プライバシーポリシー</a>
        <span aria-hidden="true">/</span>
        <a href="terms.php">利用規約</a>
        <span aria-hidden="true">/</span>
        <a href="contact.php">お問い合わせ・改善要望</a>
      </p>
      <p>農業日誌アプリ</p>
    </div>
  </footer>
</body>
</html>
