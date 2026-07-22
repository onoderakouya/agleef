<?php
require_once __DIR__.'/includes/auth.php';require_once __DIR__.'/includes/email_delivery.php';require_admin();$pdo=db();
$id=(int)get_query_param('id','0');$campaign=null;if($id){$s=$pdo->prepare('SELECT * FROM email_campaigns WHERE id=:id');$s->execute([':id'=>$id]);$campaign=$s->fetch();if(!$campaign){http_response_code(404);exit('キャンペーンが見つかりません。');}}
$errors=[];$subject=(string)($campaign['subject']??'');$body=(string)($campaign['body_text']??'');
$targetCount=(int)$pdo->query("SELECT COUNT(*) FROM users u JOIN email_subscriptions s ON s.user_id=u.id WHERE s.status='subscribed' AND COALESCE(u.is_suspended,0)=0 AND u.email<>''")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf_token($_POST['csrf_token']??null))$errors[]='不正なリクエストです。';
 $action=(string)($_POST['action']??'save');$subject=trim(preg_replace('/[\r\n]+/',' ',(string)($_POST['subject']??''))??'');$body=trim((string)($_POST['body_text']??''));
 if($subject===''||mb_strlen($subject)>200)$errors[]='件名は1～200文字で入力してください。';if($body===''||mb_strlen($body)>20000)$errors[]='本文は1～20,000文字で入力してください。';
 if($campaign&&$campaign['status']!=='draft')$errors[]='キュー登録済みの内容は変更できません。';
 if($errors===[]){
  if(!$campaign){$s=$pdo->prepare("INSERT INTO email_campaigns(subject,body_text,status,created_by,from_name,from_address,reply_to)VALUES(:subject,:body,'draft',:uid,:fn,:fa,:rt)");$s->execute([':subject'=>$subject,':body'=>$body,':uid'=>current_user_id(),':fn'=>MAIL_FROM_NAME,':fa'=>MAIL_FROM_ADDRESS,':rt'=>MAIL_REPLY_TO]);$id=(int)$pdo->lastInsertId();log_admin_action(current_user_id(),'メールキャンペーン作成','email_campaign',$id);}
  else{$pdo->prepare('UPDATE email_campaigns SET subject=:subject,body_text=:body,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status=\'draft\'')->execute([':subject'=>$subject,':body'=>$body,':id'=>$id]);log_admin_action(current_user_id(),'メール下書き更新','email_campaign',$id);}
  if($action==='test'){
   $test=trim((string)($_POST['test_email']??''));if($test==='' ){$u=$pdo->prepare('SELECT email FROM users WHERE id=:id');$u->execute([':id'=>current_user_id()]);$test=(string)$u->fetchColumn();}
   if(!filter_var($test,FILTER_VALIDATE_EMAIL)){$errors[]='テスト送信先が正しくありません。';}else{try{$sub=email_subscription_for_user(current_user_id());$testCampaign=['body_text'=>$body];send_individual_mail($test,'【テスト】AgriMore '.$subject,campaign_message($testCampaign,$sub));log_admin_action(current_user_id(),'メールテスト送信','email_campaign',$id);set_flash('success','テストメールを送信しました。');redirect('admin_email_campaign.php?id='.$id);}catch(Throwable $e){$errors[]='テスト送信に失敗しました。設定を確認してください。';}}
  }elseif($action==='confirm'){redirect('admin_email_confirm.php?id='.$id);}else{set_flash('success','下書きを保存しました。');redirect('admin_email_campaign.php?id='.$id);}
 }
}
$pageTitle='メール作成 | 管理者画面 | '.APP_NAME;include __DIR__.'/includes/header.php';?>
<section class="card admin-hero"><h2>配信メール作成</h2><p class="description">配信対象は配信同意済みの有効ユーザーのみ（現在 <?=e($targetCount)?> 人）です。本文はプレーンテキストで送信します。</p></section>
<?php if($errors):?><div class="alert error"><ul><?php foreach($errors as $e):?><li><?=e($e)?></li><?php endforeach?></ul></div><?php endif?>
<section class="card"><form method="post" class="stack"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>件名<input name="subject" maxlength="200" required value="<?=e($subject)?>"></label><label>本文<textarea name="body_text" rows="15" maxlength="20000" required><?=e($body)?></textarea></label><label>配信対象<input value="配信同意済みの有効ユーザー（<?=e($targetCount)?>人）" disabled></label><label>テスト送信先（空欄ならログイン中管理者）<input type="email" name="test_email"></label><div class="button-row"><button class="btn" name="action" value="save">下書き保存</button><button class="btn" name="action" value="test">テスト送信</button><button class="primary" name="action" value="confirm">プレビュー・送信確認へ</button><a class="btn" href="admin_emails.php">戻る</a></div></form></section>
<?php include __DIR__.'/includes/footer.php';
