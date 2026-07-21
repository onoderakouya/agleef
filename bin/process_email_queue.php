<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/email_delivery.php';
$lock=fopen(sys_get_temp_dir().'/agrimore-email-queue.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"queue worker already running\n");exit(0);}
$pdo=db();$processed=0;
while($processed<MAIL_BATCH_SIZE){
 $pdo->beginTransaction();
 try{
  $s=$pdo->prepare("SELECT r.*,c.subject,c.body_text,c.status campaign_status FROM email_campaign_recipients r JOIN email_campaigns c ON c.id=r.campaign_id WHERE r.status='queued' AND (r.next_attempt_at IS NULL OR datetime(r.next_attempt_at)<=CURRENT_TIMESTAMP) AND c.status IN ('queued','sending') ORDER BY r.id LIMIT 1");$s->execute();$job=$s->fetch();
  if(!$job){$pdo->commit();break;}
  $claim=$pdo->prepare("UPDATE email_campaign_recipients SET status='processing',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='queued'");$claim->execute([':id'=>$job['id']]);if($claim->rowCount()!==1){$pdo->rollBack();continue;}
  $pdo->prepare("UPDATE email_campaigns SET status='sending',started_at=COALESCE(started_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='queued'")->execute([':id'=>$job['campaign_id']]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 try{
  $check=$pdo->prepare("SELECT s.*,u.email current_email FROM email_subscriptions s JOIN users u ON u.id=s.user_id WHERE s.user_id=:uid AND s.status='subscribed' AND COALESCE(u.is_suspended,0)=0 AND u.email=:email");$check->execute([':uid'=>$job['user_id'],':email'=>$job['email']]);$sub=$check->fetch();
  $campaignNow=$pdo->prepare('SELECT status FROM email_campaigns WHERE id=:id');$campaignNow->execute([':id'=>$job['campaign_id']]);
  if(!$sub||!in_array($campaignNow->fetchColumn(),['queued','sending'],true)){$pdo->prepare("UPDATE email_campaign_recipients SET status='skipped',last_error='recipient no longer eligible',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='processing'")->execute([':id'=>$job['id']]);}
  else{send_individual_mail($job['email'],$job['subject'],campaign_message($job,$sub));$pdo->prepare("UPDATE email_campaign_recipients SET status='sent',attempt_count=attempt_count+1,sent_at=CURRENT_TIMESTAMP,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='processing'")->execute([':id'=>$job['id']]);}
 }catch(Throwable $e){$attempt=(int)$job['attempt_count']+1;$final=$attempt>=MAIL_MAX_ATTEMPTS;$pdo->prepare("UPDATE email_campaign_recipients SET status=:status,attempt_count=:attempt,last_error=:error,next_attempt_at=".($final?'NULL':"datetime(CURRENT_TIMESTAMP,'+5 minutes')").",updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='processing'")->execute([':status'=>$final?'failed':'queued',':attempt'=>$attempt,':error'=>substr(preg_replace('/[\r\n]+/',' ',get_class($e).': '.$e->getMessage()),0,500),':id'=>$job['id']]);}
 refresh_campaign_counts((int)$job['campaign_id']);
 $left=$pdo->prepare("SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id=:id AND status IN ('queued','processing')");$left->execute([':id'=>$job['campaign_id']]);if((int)$left->fetchColumn()===0)$pdo->prepare("UPDATE email_campaigns SET status='completed',completed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='sending'")->execute([':id'=>$job['campaign_id']]);
 $processed++;
}
fwrite(STDOUT,"processed={$processed}\n");flock($lock,LOCK_UN);fclose($lock);
