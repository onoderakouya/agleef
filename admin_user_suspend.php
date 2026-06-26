<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){set_flash('error','不正なリクエストです。');redirect('admin_users.php');}
if(!verify_csrf_token($_POST['csrf_token']??null)){set_flash('error','不正なリクエストです。');redirect('admin_users.php');}
$userId=(int)($_POST['user_id']??0); $action=(string)($_POST['action']??''); if($userId<=0||!in_array($action,['suspend','unsuspend'],true)){set_flash('error','指定が正しくありません。');redirect('admin_users.php');}
$pdo=db(); $stmt=$pdo->prepare('SELECT id,username,is_admin,is_suspended FROM users WHERE id=:id');$stmt->execute([':id'=>$userId]);$user=$stmt->fetch(); if(!$user){set_flash('error','ユーザーが見つかりません。');redirect('admin_users.php');}
if($userId===current_user_id() || (int)$user['is_admin']===1){set_flash('error','自分自身または管理者ユーザーは停止できません。');redirect('admin_user_detail.php?user_id='.$userId);}
if($action==='suspend'){$reason=trim((string)($_POST['suspended_reason']??'')); if($reason===''){set_flash('error','停止理由を入力してください。');redirect('admin_user_detail.php?user_id='.$userId);} $up=$pdo->prepare('UPDATE users SET is_suspended=1,suspended_at=CURRENT_TIMESTAMP,suspended_reason=:reason,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$up->execute([':reason'=>$reason,':id'=>$userId]); log_admin_action(current_user_id(),'ユーザー停止','user',$userId,'対象: '.$user['username'].' / 理由: '.$reason); set_flash('success','ユーザーを停止しました。');}
else{$up=$pdo->prepare('UPDATE users SET is_suspended=0,suspended_at=NULL,suspended_reason=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id');$up->execute([':id'=>$userId]); log_admin_action(current_user_id(),'ユーザー停止解除','user',$userId,'対象: '.$user['username']); set_flash('success','ユーザーの停止を解除しました。');}
redirect('admin_user_detail.php?user_id='.$userId);
