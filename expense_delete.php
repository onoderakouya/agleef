<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', '削除は一覧または詳細画面から実行してください。');
    redirect('expense_list.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', '不正なリクエストです。');
    redirect('expense_list.php');
}

$userId = current_user_id();
$id = (int)($_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT id, receipt_path FROM expenses WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$expense = $stmt->fetch();

if (!$expense) {
    set_flash('error', '削除対象の経費が見つかりません。');
    redirect('expense_list.php');
}

$delete = db()->prepare('DELETE FROM expenses WHERE id = :id AND user_id = :user_id');
$delete->execute([':id' => $id, ':user_id' => $userId]);

delete_uploaded_file_safely($expense['receipt_path'] ?? null);
set_flash('success', '経費を削除しました。');
redirect('expense_list.php');
