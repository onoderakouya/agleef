<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);
$id = (int)get_query_param('id', '0');

$expenseStmt = db()->prepare('SELECT * FROM expenses WHERE id = :id AND user_id = :user_id');
$expenseStmt->execute([':id' => $id, ':user_id' => $userId]);
$expense = $expenseStmt->fetch();
if (!$expense) {
    set_flash('error', '経費が見つかりません。');
    redirect('expense_list.php');
}

$categories = get_user_expense_categories($userId);
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$paymentMethods = ['現金', 'クレジットカード', '銀行振込', '口座振替', '電子マネー', 'その他'];

$expenseDate = $expense['expense_date'];
$categoryId = (string)($expense['category_id'] ?? '');
$cropId = (string)($expense['crop_id'] ?? '');
$fieldId = (string)($expense['field_id'] ?? '');
$payee = (string)($expense['payee'] ?? '');
$description = (string)$expense['description'];
$amount = (string)$expense['amount'];
$paymentMethod = (string)($expense['payment_method'] ?? '');
$memo = (string)($expense['memo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('expense_edit.php?id=' . $id);
    }

    $expenseDate = trim($_POST['expense_date'] ?? '');
    $categoryId = (string)($_POST['category_id'] ?? '');
    $cropId = (string)($_POST['crop_id'] ?? '');
    $fieldId = (string)($_POST['field_id'] ?? '');
    $payee = trim($_POST['payee'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $memo = trim($_POST['memo'] ?? '');
    $deleteReceipt = isset($_POST['delete_receipt']);

    $errors = [];
    if ($expenseDate === '' || !is_valid_date($expenseDate)) {
        $errors[] = '支払日を正しい日付で入力してください。';
    }
    if ($categoryId === '') {
        $errors[] = '経費カテゴリを選択してください。';
    }
    if ($description === '') {
        $errors[] = '内容を入力してください。';
    }
    if ($amount === '' || !ctype_digit($amount) || (int)$amount < 1) {
        $errors[] = '金額は1円以上の整数で入力してください。';
    }
    if ($paymentMethod !== '' && !in_array($paymentMethod, $paymentMethods, true)) {
        $errors[] = '支払方法の選択が不正です。';
    }

    $categoryCheck = db()->prepare('SELECT COUNT(*) FROM expense_categories WHERE id = :id AND user_id = :user_id');
    $categoryCheck->execute([':id' => (int)$categoryId, ':user_id' => $userId]);
    if ($categoryId !== '' && (int)$categoryCheck->fetchColumn() === 0) {
        $errors[] = '選択した経費カテゴリが不正です。';
    }

    if ($cropId !== '') {
        $cropCheck = db()->prepare('SELECT COUNT(*) FROM crops WHERE id = :id AND user_id = :user_id');
        $cropCheck->execute([':id' => (int)$cropId, ':user_id' => $userId]);
        if ((int)$cropCheck->fetchColumn() === 0) {
            $errors[] = '選択した作物が不正です。';
        }
    }
    if ($fieldId !== '') {
        $fieldCheck = db()->prepare('SELECT COUNT(*) FROM fields WHERE id = :id AND user_id = :user_id');
        $fieldCheck->execute([':id' => (int)$fieldId, ':user_id' => $userId]);
        if ((int)$fieldCheck->fetchColumn() === 0) {
            $errors[] = '選択した圃場が不正です。';
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        $oldReceiptPath = $expense['receipt_path'] ?? null;
        $newReceiptPath = null;
        $receiptPath = $oldReceiptPath;
        try {
            $newReceiptPath = save_expense_receipt($_FILES['receipt'] ?? [], $userId);
            if ($deleteReceipt) {
                $receiptPath = null;
            }
            if ($newReceiptPath !== null) {
                $receiptPath = $newReceiptPath;
            }

            $update = db()->prepare('UPDATE expenses
                SET expense_date = :expense_date,
                    category_id = :category_id,
                    crop_id = :crop_id,
                    field_id = :field_id,
                    payee = :payee,
                    description = :description,
                    amount = :amount,
                    payment_method = :payment_method,
                    receipt_path = :receipt_path,
                    memo = :memo,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND user_id = :user_id');
            $update->execute([
                ':expense_date' => $expenseDate,
                ':category_id' => (int)$categoryId,
                ':crop_id' => $cropId !== '' ? (int)$cropId : null,
                ':field_id' => $fieldId !== '' ? (int)$fieldId : null,
                ':payee' => $payee !== '' ? $payee : null,
                ':description' => $description,
                ':amount' => (int)$amount,
                ':payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                ':receipt_path' => $receiptPath,
                ':memo' => $memo !== '' ? $memo : null,
                ':id' => $id,
                ':user_id' => $userId,
            ]);

            if (($deleteReceipt || $newReceiptPath !== null) && $oldReceiptPath !== null) {
                delete_uploaded_file_safely($oldReceiptPath);
            }
            set_flash('success', '経費を更新しました。');
            redirect('expense_detail.php?id=' . $id);
        } catch (Throwable $e) {
            delete_uploaded_file_safely($newReceiptPath);
            if ($e instanceof RuntimeException) {
                set_flash('error', $e->getMessage());
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = '経費編集 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>経費編集</h2>
  <form method="post" class="stack" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label>支払日 <span aria-hidden="true">*</span><input type="date" name="expense_date" value="<?= e($expenseDate) ?>" required></label>
    <label>経費カテゴリ <span aria-hidden="true">*</span>
      <select name="category_id" required>
        <option value="">選択してください</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id'] ?>" <?= $categoryId !== '' && (int)$categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>作物
      <select name="crop_id"><option value="">指定しない</option><?php foreach ($crops as $crop): ?><option value="<?= (int)$crop['id'] ?>" <?= $cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>圃場
      <select name="field_id"><option value="">指定しない</option><?php foreach ($fields as $field): ?><option value="<?= (int)$field['id'] ?>" <?= $fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>支払先・購入先<input type="text" name="payee" value="<?= e($payee) ?>"></label>
    <label>内容 <span aria-hidden="true">*</span><input type="text" name="description" value="<?= e($description) ?>" required></label>
    <label>金額 <span aria-hidden="true">*</span><input type="number" name="amount" value="<?= e($amount) ?>" min="1" step="1" inputmode="numeric" required></label>
    <label>支払方法
      <select name="payment_method"><option value="">選択しない</option><?php foreach ($paymentMethods as $method): ?><option value="<?= e($method) ?>" <?= $paymentMethod === $method ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?></select>
    </label>

    <div class="photo-edit-block">
      <p class="form-label">領収書写真</p>
      <?php if (!empty($expense['receipt_path'])): ?>
        <div class="current-photo"><img class="diary-photo-preview" src="<?= e($expense['receipt_path']) ?>" alt="現在の領収書写真"></div>
        <label class="checkbox-label"><input type="checkbox" name="delete_receipt" value="1"> 現在の領収書写真を削除する</label>
      <?php else: ?>
        <p class="description">現在、領収書写真はありません。</p>
      <?php endif; ?>
      <label>新しい領収書写真
        <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp">
        <span class="description">新しい写真を選ぶと差し替えます。JPG / JPEG / PNG / WEBP、最大3MB。</span>
      </label>
    </div>

    <label>メモ<textarea name="memo" rows="4"><?= e($memo) ?></textarea></label>

    <div class="button-row">
      <button class="primary" type="submit">更新する</button>
      <a class="btn" href="expense_detail.php?id=<?= (int)$id ?>">詳細へ戻る</a>
      <a class="btn" href="expense_list.php">一覧へ戻る</a>
    </div>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
