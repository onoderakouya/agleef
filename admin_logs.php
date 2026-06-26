<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$pdo=db(); $pageParam=get_query_param('page','1'); $page=ctype_digit($pageParam)?max(1,(int)$pageParam):1; $perPage=50; $total=(int)$pdo->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn(); $totalPages=max(1,(int)ceil($total/$perPage)); if($page>$totalPages)$page=1; $offset=($page-1)*$perPage;
$stmt=$pdo->prepare('SELECT l.*, u.username FROM admin_logs l LEFT JOIN users u ON u.id=l.admin_user_id ORDER BY datetime(l.created_at) DESC,l.id DESC LIMIT :limit OFFSET :offset');$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);$stmt->execute();$logs=$stmt->fetchAll();
$url=fn($p)=>'admin_logs.php?page='.$p; $pageTitle='管理者操作ログ | 管理者画面 | '.APP_NAME; include __DIR__.'/includes/header.php';
?>
<section class="card admin-hero"><h2>管理者専用：操作ログ</h2><div class="button-row"><a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a></div></section>
<section class="card"><h3>操作ログ一覧</h3><div class="table-wrap"><table class="admin-table"><thead><tr><th>日時</th><th>管理者ユーザーID</th><th>管理者ユーザー名</th><th>操作</th><th>対象種別</th><th>対象ID</th><th>詳細</th></tr></thead><tbody><?php foreach($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['admin_user_id']) ?></td><td><?= e($l['username'] ?? '-') ?></td><td><?= e($l['action']) ?></td><td><?= e($l['target_type'] ?? '-') ?></td><td><?= e($l['target_id'] ?? '-') ?></td><td><?= e($l['detail'] ?? '-') ?></td></tr><?php endforeach; if(!$logs): ?><tr><td colspan="7">操作ログはありません。</td></tr><?php endif; ?></tbody></table></div><?php if($totalPages>1): ?><nav class="pagination"><?php if($page>1): ?><a class="btn small" href="<?= e($url($page-1)) ?>">前へ</a><?php endif; ?><span><?= e($page) ?> / <?= e($totalPages) ?></span><?php if($page<$totalPages): ?><a class="btn small" href="<?= e($url($page+1)) ?>">次へ</a><?php endif; ?></nav><?php endif; ?></section>
<?php include __DIR__.'/includes/footer.php'; ?>
