<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
$pdo=db(); $id=get_query_param('id'); if($id===''||!ctype_digit($id)||(int)$id<=0){set_flash('error','問い合わせIDが正しくありません。');redirect('admin_contacts.php');} $contactId=(int)$id;
$allowed=['未対応','対応中','対応済み','保留'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf_token($_POST['csrf_token']??null)){set_flash('error','不正なリクエストです。');redirect('admin_contact_detail.php?id='.$contactId);} $status=(string)($_POST['status']??''); if(!in_array($status,$allowed,true)){set_flash('error','状態が正しくありません。');redirect('admin_contact_detail.php?id='.$contactId);} $memo=trim((string)($_POST['admin_memo']??''));
 $old=$pdo->prepare('SELECT status FROM contacts WHERE id=:id');$old->execute([':id'=>$contactId]);$oldStatus=$old->fetchColumn();
 $up=$pdo->prepare('UPDATE contacts SET status=:status, admin_memo=:memo, updated_at=CURRENT_TIMESTAMP WHERE id=:id');$up->execute([':status'=>$status,':memo'=>$memo!==''?$memo:null,':id'=>$contactId]);
 log_admin_action(current_user_id(),'問い合わせステータス変更','contact',$contactId,'状態: '.$oldStatus.' → '.$status); set_flash('success','問い合わせを更新しました。'); redirect('admin_contact_detail.php?id='.$contactId);
}
$stmt=$pdo->prepare('SELECT * FROM contacts WHERE id=:id');$stmt->execute([':id'=>$contactId]);$c=$stmt->fetch(); if(!$c){set_flash('error','問い合わせが見つかりません。');redirect('admin_contacts.php');}
$pageTitle='問い合わせ詳細 | 管理者画面 | '.APP_NAME; include __DIR__.'/includes/header.php';
?>
<section class="card admin-hero"><h2>管理者専用：問い合わせ詳細</h2><div class="button-row"><a class="btn" href="admin_contacts.php">問い合わせ一覧へ戻る</a><a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a></div></section>
<section class="card"><h3>問い合わせ内容</h3><dl class="detail-grid admin-detail-grid"><dt>ID</dt><dd><?= e($c['id']) ?></dd><dt>状態</dt><dd><span class="badge <?= $c['status']==='未対応'?'badge-danger':'badge-user' ?>"><?= e($c['status']) ?></span></dd><dt>名前</dt><dd><?= e($c['name'] ?? '-') ?></dd><dt>メールアドレス</dt><dd><?= e($c['email'] ?? '-') ?></dd><dt>ユーザーID</dt><dd><?= e($c['user_id'] ?? '-') ?></dd><dt>件名</dt><dd><?= e($c['subject']) ?></dd><dt>お問い合わせ内容</dt><dd><?= nl2br(e($c['message'])) ?></dd><dt>管理者メモ</dt><dd><?= nl2br(e($c['admin_memo'] ?? '-')) ?></dd><dt>作成日時</dt><dd><?= e($c['created_at']) ?></dd><dt>更新日時</dt><dd><?= e($c['updated_at']) ?></dd></dl></section>
<section class="card"><h3>状態変更・管理者メモ</h3><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>状態<select name="status"><?php foreach($allowed as $s): ?><option value="<?= e($s) ?>" <?= $c['status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></label><label>管理者メモ<textarea name="admin_memo" rows="6"><?= e($c['admin_memo'] ?? '') ?></textarea></label><button class="primary" type="submit">更新する</button></form></section>
<?php include __DIR__.'/includes/footer.php'; ?>
