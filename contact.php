<?php
require_once __DIR__ . '/includes/auth.php';

$contactEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '';
$subject = rawurlencode('アグリーフへのお問い合わせ');
$body = rawurlencode("お問い合わせ種別：\nお名前：\n登録メールアドレス（任意）：\n\n内容：\n");
$mailto = $contactEmail !== '' ? 'mailto:' . $contactEmail . '?subject=' . $subject . '&body=' . $body : '';
$pageTitle = 'お問い合わせ | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card public-page contact-page">
  <div class="public-hero">
    <p class="eyebrow">Contact</p>
    <h2>お問い合わせ</h2>
    <p class="description">不具合報告、使い方の質問、登録情報・データ削除の相談などは、運営者までメールでご連絡ください。</p>
  </div>

  <div class="public-card-grid public-card-grid--compact">
    <section class="info-card"><h3>不具合報告</h3><p>画面が表示されない、登録できない、集計が想定と違うなど。</p></section>
    <section class="info-card"><h3>使い方の質問</h3><p>作物・圃場・日誌・経費・売上・CSV出力などの操作相談。</p></section>
    <section class="info-card"><h3>登録情報・データ削除の相談</h3><p>アカウント情報や保存データの削除についてのご相談。</p></section>
    <section class="info-card"><h3>その他</h3><p>改善要望、β版へのご意見、その他のお問い合わせ。</p></section>
  </div>

  <div class="notice-box-app">
    <p>返信が必要な場合は、登録メールアドレスや連絡先を本文に記載してください。</p>
    <?php if ($mailto !== ''): ?>
      <p>送信先：<a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p>
      <div class="button-row"><a class="btn primary" href="<?= e($mailto) ?>">お問い合わせメールを送る</a></div>
    <?php else: ?>
      <p>現在、お問い合わせフォーム機能を準備中です。公開時に連絡先を掲載します。</p>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
