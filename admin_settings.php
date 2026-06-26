<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf_token($_POST['csrf_token']??null)){set_flash('error','不正なリクエストです。');redirect('admin_settings.php');}
 $enabled=(string)($_POST['registration_enabled']??''); if(!in_array($enabled,['0','1'],true)){set_flash('error','指定が正しくありません。');redirect('admin_settings.php');}
 set_app_setting('registration_enabled',$enabled); log_admin_action(current_user_id(),$enabled==='1'?'新規登録受付ON':'新規登録受付OFF','app_setting',null,'registration_enabled='.$enabled); set_flash('success','新規登録受付状態を更新しました。'); redirect('admin_settings.php');
}
$setting=app_setting('registration_enabled','1')==='1'; $effective=is_registration_enabled(); $configBlocks=defined('ALLOW_REGISTRATION') && ALLOW_REGISTRATION===false;
$pageTitle='アプリ設定 | 管理者画面 | '.APP_NAME; include __DIR__.'/includes/header.php';
?>
<section class="card admin-hero"><h2>管理者専用：アプリ設定</h2><p class="description">新規登録受付状態を切り替えます。</p><div class="button-row"><a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a></div></section>
<section class="card"><h3>新規登録受付</h3><p>現在の登録受付状態：<strong><span class="badge <?= $effective?'badge-success':'badge-danger' ?>"><?= $effective?'受付中':'停止中' ?></span></strong></p><p class="description">管理画面設定：<?= $setting?'ON':'OFF' ?></p><?php if($configBlocks): ?><p class="alert error">config.php の ALLOW_REGISTRATION が false のため、管理画面で受付中にしても新規登録は停止されます。</p><?php else: ?><p class="notice-box-app">config.php の ALLOW_REGISTRATION が false の場合、管理画面で受付中にしても新規登録は停止されます。</p><?php endif; ?><form method="post" class="button-row"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn primary" name="registration_enabled" value="1">新規登録受付をONにする</button><button class="btn danger" name="registration_enabled" value="0">新規登録受付をOFFにする</button></form></section>
<?php include __DIR__.'/includes/footer.php'; ?>
