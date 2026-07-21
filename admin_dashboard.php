<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$pdo = db();
$one = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$totalUsers = $one('SELECT COUNT(*) FROM users');
$todayUsers = $one("SELECT COUNT(*) FROM users WHERE date(created_at) = date('now')");
$monthUsers = $one("SELECT COUNT(*) FROM users WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')");
$activeUsers = $one('SELECT COUNT(*) FROM users WHERE COALESCE(is_suspended,0)=0');
$suspendedUsers = $one('SELECT COUNT(*) FROM users WHERE COALESCE(is_suspended,0)=1');
$adminUsers = $one('SELECT COUNT(*) FROM users WHERE is_admin=1');
$regularUsers = max(0, $totalUsers - $adminUsers);
$unhandledContacts = $one("SELECT COUNT(*) FROM contacts WHERE status='未対応'");
$monthLoginUsers = $one("SELECT COUNT(*) FROM users WHERE last_login_at IS NOT NULL AND strftime('%Y-%m', last_login_at)=strftime('%Y-%m','now')");
$registrationEnabled = is_registration_enabled();
$latestUsers = $pdo->query('SELECT id, username, is_admin, is_suspended, created_at, last_login_at FROM users ORDER BY datetime(created_at) DESC, id DESC LIMIT 5')->fetchAll();
$latestContacts = $pdo->query('SELECT id, status, subject, name, email, created_at FROM contacts ORDER BY datetime(created_at) DESC, id DESC LIMIT 5')->fetchAll();
$latestLogs = $pdo->query('SELECT l.*, u.username FROM admin_logs l LEFT JOIN users u ON u.id=l.admin_user_id ORDER BY datetime(l.created_at) DESC, l.id DESC LIMIT 5')->fetchAll();
$pageTitle = '管理者ダッシュボード | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card admin-hero"><h2>管理者専用：運営者ダッシュボード</h2><p class="description">登録者・問い合わせ・登録受付状態・停止ユーザー・操作ログを確認します。</p><div class="button-row"><a class="btn primary" href="admin_users.php">ユーザー一覧</a><a class="btn primary" href="admin_contacts.php">問い合わせ一覧</a><a class="btn primary" href="admin_emails.php">メール配信</a><a class="btn" href="admin_settings.php">アプリ設定</a><a class="btn" href="admin_logs.php">管理者操作ログ</a><a class="btn" href="dashboard.php">通常ダッシュボードへ戻る</a></div></section>
<section class="card"><h3>運用サマリー</h3><div class="summary-grid admin-summary-grid">
<?php foreach ([['総登録ユーザー数',$totalUsers],['今日登録されたユーザー数',$todayUsers],['今月登録されたユーザー数',$monthUsers],['アクティブユーザー数',$activeUsers],['停止中ユーザー数',$suspendedUsers],['管理者ユーザー数',$adminUsers],['一般ユーザー数',$regularUsers],['新規登録受付状態',$registrationEnabled?'受付中':'停止中'],['未対応問い合わせ件数',$unhandledContacts],['今月のログインユーザー数',$monthLoginUsers]] as $card): ?><div class="summary-card"><span><?= e((string)$card[0]) ?></span><strong><?= e((string)$card[1]) ?></strong></div><?php endforeach; ?>
</div></section>
<section class="card"><h3>最新登録ユーザー5件</h3><div class="table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>ユーザー名</th><th>権限</th><th>状態</th><th>登録日時</th><th>最終ログイン</th><th>詳細</th></tr></thead><tbody><?php foreach ($latestUsers as $u): ?><tr><td><?= e($u['id']) ?></td><td><?= e($u['username']) ?></td><td><span class="badge <?= (int)$u['is_admin']===1?'badge-admin':'badge-user' ?>"><?= (int)$u['is_admin']===1?'管理者':'一般' ?></span></td><td><span class="badge <?= (int)$u['is_suspended']===1?'badge-danger':'badge-success' ?>"><?= (int)$u['is_suspended']===1?'停止中':'通常' ?></span></td><td><?= e($u['created_at']) ?></td><td><?= e($u['last_login_at'] ?? '-') ?></td><td><a class="btn small" href="admin_user_detail.php?user_id=<?= e($u['id']) ?>">詳細</a></td></tr><?php endforeach; if(!$latestUsers): ?><tr><td colspan="7">登録ユーザーはまだいません。</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><h3>最新問い合わせ5件</h3><div class="table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>状態</th><th>件名</th><th>名前</th><th>メール</th><th>作成日時</th><th>詳細</th></tr></thead><tbody><?php foreach ($latestContacts as $c): ?><tr><td><?= e($c['id']) ?></td><td><span class="badge <?= $c['status']==='未対応'?'badge-danger':'badge-user' ?>"><?= e($c['status']) ?></span></td><td><?= e($c['subject']) ?></td><td><?= e($c['name'] ?? '-') ?></td><td><?= e($c['email'] ?? '-') ?></td><td><?= e($c['created_at']) ?></td><td><a class="btn small" href="admin_contact_detail.php?id=<?= e($c['id']) ?>">詳細</a></td></tr><?php endforeach; if(!$latestContacts): ?><tr><td colspan="7">問い合わせはまだありません。</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><h3>最近の管理者操作ログ5件</h3><div class="table-wrap"><table class="admin-table"><thead><tr><th>日時</th><th>管理者</th><th>操作</th><th>対象</th><th>詳細</th></tr></thead><tbody><?php foreach ($latestLogs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e(($l['username'] ?? '-') . ' #' . $l['admin_user_id']) ?></td><td><?= e($l['action']) ?></td><td><?= e(($l['target_type'] ?? '-') . ' ' . ($l['target_id'] ?? '')) ?></td><td><?= e($l['detail'] ?? '-') ?></td></tr><?php endforeach; if(!$latestLogs): ?><tr><td colspan="5">操作ログはまだありません。</td></tr><?php endif; ?></tbody></table></div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
