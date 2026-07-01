<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int)get_query_param('id', '0');
$stmt = db()->prepare('SELECT * FROM sales WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$sale = $stmt->fetch();
if (!$sale) {
    set_flash('error', '売上が見つかりません。');
    redirect('sale_list.php');
}

$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$channels = sales_channel_options();
$paymentStatuses = payment_status_options();
$units = sale_unit_options();

$saleDate = $_POST['sale_date'] ?? $sale['sale_date'];
$salesChannel = $_POST['sales_channel'] ?? ($sale['sales_channel'] ?? '');
$cropId = $_POST['crop_id'] ?? ($sale['crop_id'] ?? '');
$fieldId = $_POST['field_id'] ?? ($sale['field_id'] ?? '');
$buyer = trim((string)($_POST['buyer'] ?? ($sale['buyer'] ?? '')));
$productName = trim((string)($_POST['product_name'] ?? $sale['product_name']));
$quantity = trim((string)($_POST['quantity'] ?? ($sale['quantity'] ?? '')));
$unit = $_POST['unit'] ?? ($sale['unit'] ?? '');
$unitPrice = trim((string)($_POST['unit_price'] ?? ($sale['unit_price'] ?? '')));
$grossAmount = trim((string)($_POST['gross_amount'] ?? $sale['gross_amount']));
$feeAmount = trim((string)($_POST['fee_amount'] ?? ($sale['fee_amount'] ?? '0')));
$shippingAmount = trim((string)($_POST['shipping_amount'] ?? ($sale['shipping_amount'] ?? '0')));
$netAmount = trim((string)($_POST['net_amount'] ?? ($sale['net_amount'] ?? '')));
$paymentStatus = $_POST['payment_status'] ?? ($sale['payment_status'] ?? '未入金');
$paymentDate = $_POST['payment_date'] ?? ($sale['payment_date'] ?? '');
$memo = trim((string)($_POST['memo'] ?? ($sale['memo'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $deleteDocument = isset($_POST['delete_document']);
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正なリクエストです。';
    }
    if (!is_valid_date((string)$saleDate)) {
        $errors[] = '売上日を正しく入力してください。';
    }
    if ($productName === '') {
        $errors[] = '品目を入力してください。';
    }
    if ($salesChannel !== '' && !in_array($salesChannel, $channels, true)) {
        $errors[] = '販売経路の指定が不正です。';
    }
    if ($unit !== '' && !in_array($unit, $units, true)) {
        $errors[] = '単位の指定が不正です。';
    }
    if (!in_array($paymentStatus, $paymentStatuses, true)) {
        $errors[] = '入金状況の指定が不正です。';
    }
    if ($paymentDate !== '' && !is_valid_date((string)$paymentDate)) {
        $errors[] = '入金日を正しく入力してください。';
    }
    if ($grossAmount === '' || filter_var($grossAmount, FILTER_VALIDATE_INT) === false || (int)$grossAmount < 0) {
        $errors[] = '売上総額は0円以上の整数で入力してください。';
    }
    if ($feeAmount === '') { $feeAmount = '0'; }
    if ($shippingAmount === '') { $shippingAmount = '0'; }
    if (filter_var($feeAmount, FILTER_VALIDATE_INT) === false || (int)$feeAmount < 0) {
        $errors[] = '手数料は0円以上の整数で入力してください。';
    }
    if (filter_var($shippingAmount, FILTER_VALIDATE_INT) === false || (int)$shippingAmount < 0) {
        $errors[] = '送料は0円以上の整数で入力してください。';
    }
    if ($netAmount === '' && $grossAmount !== '' && filter_var($grossAmount, FILTER_VALIDATE_INT) !== false) {
        $netAmount = (string)((int)$grossAmount - (int)$feeAmount - (int)$shippingAmount);
    }
    if ($netAmount === '' || filter_var($netAmount, FILTER_VALIDATE_INT) === false || (int)$netAmount < 0) {
        $errors[] = '差引入金額は0円以上の整数で入力してください。';
    }
    if ($quantity !== '' && !is_numeric($quantity)) {
        $errors[] = '数量は数値で入力してください。';
    }
    if ($unitPrice !== '' && (filter_var($unitPrice, FILTER_VALIDATE_INT) === false || (int)$unitPrice < 0)) {
        $errors[] = '単価は0円以上の整数で入力してください。';
    }
    if ($cropId !== '') {
        $check = db()->prepare('SELECT COUNT(*) FROM crops WHERE id = :id AND user_id = :user_id');
        $check->execute([':id' => (int)$cropId, ':user_id' => $userId]);
        if ((int)$check->fetchColumn() === 0) { $errors[] = '選択した作物が不正です。'; }
    }
    if ($fieldId !== '') {
        $check = db()->prepare('SELECT COUNT(*) FROM fields WHERE id = :id AND user_id = :user_id');
        $check->execute([':id' => (int)$fieldId, ':user_id' => $userId]);
        if ((int)$check->fetchColumn() === 0) { $errors[] = '選択した圃場が不正です。'; }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        $oldDocumentPath = $sale['document_path'] ?? null;
        $newDocumentPath = null;
        $documentPath = $oldDocumentPath;
        try {
            $newDocumentPath = save_sale_document($_FILES['document'] ?? [], $userId);
            if ($deleteDocument) { $documentPath = null; }
            if ($newDocumentPath !== null) { $documentPath = $newDocumentPath; }
            $update = db()->prepare('UPDATE sales SET sale_date = :sale_date, crop_id = :crop_id, field_id = :field_id, buyer = :buyer, sales_channel = :sales_channel, product_name = :product_name, quantity = :quantity, unit = :unit, unit_price = :unit_price, gross_amount = :gross_amount, fee_amount = :fee_amount, shipping_amount = :shipping_amount, net_amount = :net_amount, payment_status = :payment_status, payment_date = :payment_date, document_path = :document_path, memo = :memo, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
            $update->execute([
                ':sale_date' => $saleDate, ':crop_id' => $cropId !== '' ? (int)$cropId : null, ':field_id' => $fieldId !== '' ? (int)$fieldId : null, ':buyer' => $buyer !== '' ? $buyer : null, ':sales_channel' => $salesChannel !== '' ? $salesChannel : null, ':product_name' => $productName,
                ':quantity' => $quantity !== '' ? (float)$quantity : null, ':unit' => $unit !== '' ? $unit : null, ':unit_price' => $unitPrice !== '' ? (int)$unitPrice : null, ':gross_amount' => (int)$grossAmount, ':fee_amount' => (int)$feeAmount, ':shipping_amount' => (int)$shippingAmount, ':net_amount' => (int)$netAmount,
                ':payment_status' => $paymentStatus, ':payment_date' => $paymentDate !== '' ? $paymentDate : null, ':document_path' => $documentPath, ':memo' => $memo !== '' ? $memo : null, ':id' => $id, ':user_id' => $userId,
            ]);
            if (($deleteDocument || $newDocumentPath !== null) && $oldDocumentPath !== null) { delete_uploaded_file_safely($oldDocumentPath); }
            set_flash('success', '売上を更新しました。');
            redirect('sale_detail.php?id=' . $id);
        } catch (Throwable $e) {
            delete_uploaded_file_safely($newDocumentPath);
            if ($e instanceof RuntimeException) { set_flash('error', $e->getMessage()); } else { throw $e; }
        }
    }
}

$pageTitle = '売上編集 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>売上編集</h2>
  <form method="post" class="stack sale-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>売上日 <span aria-hidden="true">*</span><input type="date" name="sale_date" value="<?= e($saleDate) ?>" required></label>
    <label>販売経路<select name="sales_channel"><option value="">選択しない</option><?php foreach ($channels as $channel): ?><option value="<?= e($channel) ?>" <?= e((string)($salesChannel === $channel ? 'selected' : '')) ?>><?= e($channel) ?></option><?php endforeach; ?></select></label>
    <label>作物<select name="crop_id"><option value="">指定しない</option><?php foreach ($crops as $crop): ?><option value="<?= e((string)((int)$crop['id'])) ?>" <?= e((string)($cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '')) ?>><?= e($crop['name']) ?></option><?php endforeach; ?></select></label>
    <label>圃場<select name="field_id"><option value="">指定しない</option><?php foreach ($fields as $field): ?><option value="<?= e((string)((int)$field['id'])) ?>" <?= e((string)($fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '')) ?>><?= e($field['name']) ?></option><?php endforeach; ?></select></label>
    <label>販売先<input type="text" name="buyer" value="<?= e($buyer) ?>"></label>
    <label>品目 <span aria-hidden="true">*</span><input type="text" name="product_name" value="<?= e($productName) ?>" required></label>
    <div class="form-grid-3"><label>数量<input type="number" name="quantity" value="<?= e($quantity) ?>" min="0" step="0.001" inputmode="decimal"></label><label>単位<select name="unit"><option value="">選択しない</option><?php foreach ($units as $unitOption): ?><option value="<?= e($unitOption) ?>" <?= e((string)($unit === $unitOption ? 'selected' : '')) ?>><?= e($unitOption) ?></option><?php endforeach; ?></select></label><label>単価<input type="number" name="unit_price" value="<?= e($unitPrice) ?>" min="0" step="1" inputmode="numeric"></label></div>
    <label>売上総額 <span aria-hidden="true">*</span><input type="number" name="gross_amount" value="<?= e($grossAmount) ?>" min="0" step="1" inputmode="numeric" required></label>
    <div class="form-grid-3"><label>手数料<input type="number" name="fee_amount" value="<?= e($feeAmount) ?>" min="0" step="1" inputmode="numeric"></label><label>送料<input type="number" name="shipping_amount" value="<?= e($shippingAmount) ?>" min="0" step="1" inputmode="numeric"></label><label>差引入金額<input type="number" name="net_amount" value="<?= e($netAmount) ?>" min="0" step="1" inputmode="numeric"></label></div>
    <label>入金状況<select name="payment_status"><?php foreach ($paymentStatuses as $status): ?><option value="<?= e($status) ?>" <?= e((string)($paymentStatus === $status ? 'selected' : '')) ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
    <label>入金日<input type="date" name="payment_date" value="<?= e($paymentDate) ?>"></label>
    <div class="photo-edit-block"><p class="form-label">売上明細・伝票写真</p><?php if (!empty($sale['document_path'])): ?><div class="current-photo"><img class="diary-photo-preview" src="<?= e($sale['document_path']) ?>" alt="現在の売上明細・伝票写真"></div><label class="checkbox-label"><input type="checkbox" name="delete_document" value="1"> 現在の明細写真を削除する</label><?php else: ?><p class="description">現在、明細写真はありません。</p><?php endif; ?><label class="file-upload-field">新しい売上明細・伝票写真<span class="file-upload-box"><input type="file" name="document" accept="image/jpeg,image/png,image/webp"><span class="file-upload-button">画像を選択する</span><span class="file-upload-note">クリックして明細写真を差し替え</span></span><span class="description">新しい写真を選ぶと差し替えます。JPG / JPEG / PNG / WEBP、最大3MB。</span></label></div>
    <label>メモ<textarea name="memo" rows="4"><?= e($memo) ?></textarea></label>
    <div class="button-row"><button class="primary" type="submit">更新する</button><a class="btn" href="sale_detail.php?id=<?= e((string)((int)$id)) ?>">詳細へ戻る</a><a class="btn" href="sale_list.php">一覧へ戻る</a></div>
  </form>
</section>
<script>
(function(){
  const form = document.querySelector('.sale-form'); if (!form) return;
  const q=form.quantity, u=form.unit_price, g=form.gross_amount, f=form.fee_amount, s=form.shipping_amount, n=form.net_amount;
  let grossTouched=true, netTouched=true;
  g.addEventListener('input',()=>{grossTouched=true; calcNet();}); n.addEventListener('input',()=>{netTouched=true;});
  function num(el){return parseFloat(el.value || '0') || 0;}
  function calcGross(){ if(!grossTouched && q.value !== '' && u.value !== '') { g.value = Math.round(num(q)*num(u)); calcNet(); } }
  function calcNet(){ if(!netTouched && g.value !== '') n.value = Math.max(0, Math.round(num(g)-num(f)-num(s))); }
  [q,u].forEach(el=>el.addEventListener('input',()=>{grossTouched=false; calcGross();})); [f,s].forEach(el=>el.addEventListener('input',()=>{netTouched=false; calcNet();}));
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
