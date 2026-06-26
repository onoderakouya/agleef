<?php
require_once __DIR__ . '/includes/auth.php';
$contactEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '';
$pageTitle = 'プライバシーポリシー | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card public-page legal-page">
  <h2>プライバシーポリシー</h2>
  <p class="description">本プライバシーポリシーはβ版運用時点の内容です。正式公開時には必要に応じて見直します。</p>
  <section class="legal-section"><h3>1. 取得する情報</h3><p>ユーザー名、メールアドレス、パスワード（ハッシュ化して保存）、作物・圃場・日誌・経費・売上の入力内容、添付写真、お問い合わせ内容、利用に必要なセッション情報を取得します。</p></section>
  <section class="legal-section"><h3>2. 利用目的</h3><p>アカウント管理、日誌・経費・売上・年間集計・CSV出力などの機能提供、不具合対応、削除依頼対応、サービス改善、重要なお知らせのために利用します。</p></section>
  <section class="legal-section"><h3>3. 保存されるデータ</h3><p>利用者が入力した農作業記録、作物・圃場、売上、経費、画像ファイル、問い合わせ内容は、サービス提供と運用に必要な範囲で保存します。</p></section>
  <section class="legal-section"><h3>4. 第三者提供について</h3><p>法令に基づく場合、本人の同意がある場合、サービス運営に必要な委託先へ必要最小限の範囲で共有する場合を除き、個人情報を第三者へ提供しません。</p></section>
  <section class="legal-section"><h3>5. データの管理</h3><p>不正アクセスや漏えいを防ぐため、アクセス権限の管理、パスワードのハッシュ化、CSRF対策など、β版として可能な安全管理に努めます。</p></section>
  <section class="legal-section"><h3>6. Cookie・セッションについて</h3><p>ログイン状態の維持や安全な操作のため、Cookieおよびセッションを利用します。これらはサービス利用に必要な範囲で使用します。</p></section>
  <section class="legal-section"><h3>7. お問い合わせ先</h3><p>個人情報、登録情報、保存データの削除に関する相談は、お問い合わせページからご連絡ください。<?php if ($contactEmail !== ''): ?>連絡先メール：<a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a><?php endif; ?></p></section>
  <section class="legal-section"><h3>8. 改定について</h3><p>本ポリシーは、機能追加、運用状況、法令等に応じて変更する場合があります。重要な変更はサービス内でお知らせします。</p></section>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
