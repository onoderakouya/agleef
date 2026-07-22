<?php
require_once __DIR__ . '/functions.php';

function email_subscription_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT s.* FROM email_subscriptions s WHERE s.user_id=:user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();
    if ($row) return $row;
    $user = db()->prepare('SELECT email FROM users WHERE id=:id');
    $user->execute([':id' => $userId]);
    $email = (string)$user->fetchColumn();
    db()->prepare("INSERT OR IGNORE INTO email_subscriptions(user_id,email,status,consent_source) VALUES(:user_id,:email,'not_subscribed','account')")
        ->execute([':user_id'=>$userId, ':email'=>$email]);
    return email_subscription_for_user($userId);
}

function update_email_subscription(int $userId, string $email, bool $enabled, string $source): void
{
    $status = $enabled ? 'subscribed' : 'unsubscribed';
    $sql = "INSERT INTO email_subscriptions(user_id,email,status,consent_source,subscribed_at,unsubscribed_at)
        VALUES(:user_id,:email,:status,:source," . ($enabled ? 'CURRENT_TIMESTAMP,NULL' : 'NULL,CURRENT_TIMESTAMP') . ")
        ON CONFLICT(user_id) DO UPDATE SET email=excluded.email,status=excluded.status,
        consent_source=excluded.consent_source,subscribed_at=" . ($enabled ? 'CURRENT_TIMESTAMP' : 'email_subscriptions.subscribed_at') . ",
        unsubscribed_at=" . ($enabled ? 'NULL' : 'CURRENT_TIMESTAMP') . ",updated_at=CURRENT_TIMESTAMP";
    db()->prepare($sql)->execute([':user_id'=>$userId, ':email'=>$email, ':status'=>$status, ':source'=>$source]);
}

function sync_subscription_email(int $userId, string $email): void
{
    db()->prepare('UPDATE email_subscriptions SET email=:email,updated_at=CURRENT_TIMESTAMP WHERE user_id=:user_id')
        ->execute([':email'=>$email, ':user_id'=>$userId]);
}

function mail_setting(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false || trim((string)$value) === '' ? $default : trim((string)$value);
}

function app_base_url(): string
{
    $configured = rtrim(mail_setting('APP_URL'), '/');
    if ($configured !== '') return $configured;
    $scheme = is_https_request() ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:[\]-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    return $scheme . '://' . $host . ($dir === '/' ? '' : rtrim($dir, '/'));
}

function unsubscribe_secret(): string
{
    return mail_setting('APP_UNSUBSCRIBE_SECRET');
}

function unsubscribe_token(int $subscriptionId): string
{
    $secret = unsubscribe_secret();
    if (strlen($secret) < 32) throw new RuntimeException('配信停止用秘密鍵が設定されていません。');
    $payload = rtrim(strtr(base64_encode(pack('J', $subscriptionId)), '+/', '-_'), '=');
    return $payload . '.' . hash_hmac('sha256', $payload, $secret);
}

function subscription_from_token(string $token): ?array
{
    if (!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/', $token, $m) || strlen(unsubscribe_secret()) < 32) return null;
    if (!hash_equals(hash_hmac('sha256', $m[1], unsubscribe_secret()), $m[2])) return null;
    $raw = base64_decode(strtr($m[1], '-_', '+/'), true);
    if ($raw === false || strlen($raw) !== 8) return null;
    $id = unpack('Jid', $raw)['id'] ?? 0;
    $stmt = db()->prepare('SELECT * FROM email_subscriptions WHERE id=:id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    return $stmt->fetch() ?: null;
}

function campaign_message(array $campaign, array $subscription): string
{
    $url = app_base_url() . '/unsubscribe.php?token=' . rawurlencode(unsubscribe_token((int)$subscription['id']));
    $operator = mail_setting('APP_OPERATOR_NAME', APP_NAME);
    $support = mail_setting('SUPPORT_EMAIL', CONTACT_EMAIL);
    return rtrim((string)$campaign['body_text']) . "\n\n――――――――――\n"
        . "このメールは、AgriMoreからのお知らせメールを希望された方へお送りしています。\n\n"
        . "配信停止はこちら：\n{$url}\n\nお問い合わせ：\n{$support}\n\n{$operator}\n――――――――――\n";
}

function send_individual_mail(string $to, string $subject, string $body): void
{
    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
    foreach ([$to, MAIL_FROM_ADDRESS, MAIL_REPLY_TO] as $address) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)) throw new RuntimeException('メール設定が正しくありません。');
    }
    if (MAIL_TRANSPORT !== 'mb_send_mail' || !function_exists('mb_send_mail')) throw new RuntimeException('利用可能なメール送信方式が設定されていません。');
    $fromName = preg_replace('/[\r\n]+/', ' ', MAIL_FROM_NAME) ?? APP_NAME;
    $headers = 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . MAIL_FROM_ADDRESS . ">\r\nReply-To: " . MAIL_REPLY_TO;
    if (!mb_send_mail($to, $subject, $body, $headers)) throw new RuntimeException('メール送信に失敗しました。');
}

function refresh_campaign_counts(int $campaignId): void
{
    $stmt=db()->prepare("SELECT COUNT(*) total,SUM(status='queued') queued,SUM(status='sent') sent,SUM(status='failed') failed,SUM(status='skipped') skipped FROM email_campaign_recipients WHERE campaign_id=:id");
    $stmt->execute([':id'=>$campaignId]); $c=$stmt->fetch();
    db()->prepare('UPDATE email_campaigns SET total_count=:total,queued_count=:queued,sent_count=:sent,failed_count=:failed,skipped_count=:skipped,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
        ->execute([':total'=>(int)$c['total'],':queued'=>(int)$c['queued'],':sent'=>(int)$c['sent'],':failed'=>(int)$c['failed'],':skipped'=>(int)$c['skipped'],':id'=>$campaignId]);
}
