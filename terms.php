<?php
require_once __DIR__ . '/includes/auth.php';
$contactEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '';
$pageTitle = '利用規約 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card public-page legal-page">
  <h2>利用規約</h2>
  <p class="description">本規約は、AGRIMORE（以下「本サービス」）をβ版として利用する際の基本的なルールを定めるものです。</p>
  <section class="legal-section"><h3>1. サービス概要</h3><p>本サービスは、農作業日誌、作物・圃場、経費、売上、年間集計、CSV出力を記録・確認するための農業日誌WEBアプリです。</p></section>
  <section class="legal-section"><h3>2. アカウント登録</h3><p>利用者は正確な情報で登録し、ユーザー名、メールアドレス、パスワードを自己の責任で管理してください。</p></section>
  <section class="legal-section"><h3>3. 禁止事項</h3><p>不正アクセス、他者の権利侵害、虚偽情報の登録、過度な負荷をかける行為、法令や公序良俗に反する行為を禁止します。</p></section>
  <section class="legal-section"><h3>4. データ入力内容の責任</h3><p>入力した日誌、売上、経費、画像などの内容と正確性は利用者自身の責任で管理してください。</p></section>
  <section class="legal-section"><h3>5. 税務・確定申告に関する注意</h3><p class="notice-box-app">AGRIMOREの集計機能は、入力データをもとにした簡易集計です。確定申告や税務上の最終判断は、税理士・税務署等へ確認してください。</p></section>
  <section class="legal-section"><h3>6. サービス停止・変更</h3><p>運営者は、保守、障害、機能改善、運用上の都合により、サービス内容を変更・停止・終了する場合があります。</p></section>
  <section class="legal-section"><h3>7. 免責事項</h3><p>本サービスは記録と集計を補助するものであり、入力内容、集計結果、申告結果、利用により生じた損害について、法令上認められる範囲で責任を負いません。</p></section>
  <section class="legal-section"><h3>8. 規約変更</h3><p>本規約は必要に応じて変更する場合があります。重要な変更はサービス内でお知らせします。</p></section>
  <section class="legal-section"><h3>9. お問い合わせ</h3><p>本規約に関するお問い合わせは、お問い合わせページからご連絡ください。<?php if ($contactEmail !== ''): ?>連絡先メール：<a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a><?php endif; ?></p></section>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
