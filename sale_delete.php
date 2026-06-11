<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', '削除は一覧または詳細画面から実行してください。');
    redirect('sale_list.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', '不正なリクエストです。');
    redirect('sale_list.php');
}

$userId = current_user_id();
$id = (int)($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT id, document_path FROM sales WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$sale = $stmt->fetch();
if (!$sale) {
    set_flash('error', '削除対象の売上が見つかりません。');
    redirect('sale_list.php');
}
$delete = db()->prepare('DELETE FROM sales WHERE id = :id AND user_id = :user_id');
$delete->execute([':id' => $id, ':user_id' => $userId]);
delete_uploaded_file_safely($sale['document_path'] ?? null);
set_flash('success', '売上を削除しました。');
redirect('sale_list.php');
