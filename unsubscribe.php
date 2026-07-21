<?php
require_once __DIR__.'/includes/email_delivery.php';$token=is_array($_GET['token']??null)?'':(string)($_GET['token']??'');$subscription=subscription_from_token($token);$valid=$subscription!==null;$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$valid||!verify_csrf_token($_POST['csrf_token']??null)){$valid=false;}else{db()->prepare("UPDATE email_subscriptions SET status='unsubscribed',unsubscribed_at=COALESCE(unsubscribed_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status<>'unsubscribed'")->execute([':id'=>$subscription['id']]);$done=true;}
}
$pageTitle='メール配信停止 | '.APP_NAME;include __DIR__.'/includes/header.php';?>
<section class="card narrow"><h2>メール配信停止</h2><?php if($done):?><p class="alert success">AGRIMOREからのお知らせメールの配信を停止しました。</p><p>AGRIMOREのアカウントやサービス利用に必要な通知には影響しません。</p><?php elseif(!$valid):?><p class="alert error">このリンクは利用できません。アカウント設定から配信設定をご確認ください。</p><?php else:?><p>AGRIMOREからのお知らせメールの配信を停止しますか？</p><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><button class="btn danger">配信を停止する</button></form><?php endif?></section><?php include __DIR__.'/includes/footer.php';
