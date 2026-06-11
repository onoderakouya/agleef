<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$channels = sales_channel_options();
$paymentStatuses = payment_status_options();
$units = sale_unit_options();

$saleDate = $_POST['sale_date'] ?? date('Y-m-d');
$salesChannel = $_POST['sales_channel'] ?? '';
$cropId = $_POST['crop_id'] ?? '';
$fieldId = $_POST['field_id'] ?? '';
$buyer = trim((string)($_POST['buyer'] ?? ''));
$productName = trim((string)($_POST['product_name'] ?? ''));
$quantity = trim((string)($_POST['quantity'] ?? ''));
$unit = $_POST['unit'] ?? '';
$unitPrice = trim((string)($_POST['unit_price'] ?? ''));
$grossAmount = trim((string)($_POST['gross_amount'] ?? ''));
$feeAmount = trim((string)($_POST['fee_amount'] ?? ''));
$shippingAmount = trim((string)($_POST['shipping_amount'] ?? ''));
$netAmount = trim((string)($_POST['net_amount'] ?? ''));
$paymentStatus = $_POST['payment_status'] ?? '未入金';
$paymentDate = $_POST['payment_date'] ?? '';
$memo = trim((string)($_POST['memo'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
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
    if ($feeAmount === '') {
        $feeAmount = '0';
    }
    if ($shippingAmount === '') {
        $shippingAmount = '0';
    }
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
        if ((int)$check->fetchColumn() === 0) {
            $errors[] = '選択した作物が不正です。';
        }
    }
    if ($fieldId !== '') {
        $check = db()->prepare('SELECT COUNT(*) FROM fields WHERE id = :id AND user_id = :user_id');
        $check->execute([':id' => (int)$fieldId, ':user_id' => $userId]);
        if ((int)$check->fetchColumn() === 0) {
            $errors[] = '選択した圃場が不正です。';
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        $documentPath = null;
        try {
            $documentPath = save_sale_document($_FILES['document'] ?? [], $userId);
            $insert = db()->prepare('INSERT INTO sales (user_id, sale_date, crop_id, field_id, buyer, sales_channel, product_name, quantity, unit, unit_price, gross_amount, fee_amount, shipping_amount, net_amount, payment_status, payment_date, document_path, memo) VALUES (:user_id, :sale_date, :crop_id, :field_id, :buyer, :sales_channel, :product_name, :quantity, :unit, :unit_price, :gross_amount, :fee_amount, :shipping_amount, :net_amount, :payment_status, :payment_date, :document_path, :memo)');
            $insert->execute([
                ':user_id' => $userId, ':sale_date' => $saleDate, ':crop_id' => $cropId !== '' ? (int)$cropId : null, ':field_id' => $fieldId !== '' ? (int)$fieldId : null,
                ':buyer' => $buyer !== '' ? $buyer : null, ':sales_channel' => $salesChannel !== '' ? $salesChannel : null, ':product_name' => $productName,
                ':quantity' => $quantity !== '' ? (float)$quantity : null, ':unit' => $unit !== '' ? $unit : null, ':unit_price' => $unitPrice !== '' ? (int)$unitPrice : null,
                ':gross_amount' => (int)$grossAmount, ':fee_amount' => (int)$feeAmount, ':shipping_amount' => (int)$shippingAmount, ':net_amount' => (int)$netAmount,
                ':payment_status' => $paymentStatus, ':payment_date' => $paymentDate !== '' ? $paymentDate : null, ':document_path' => $documentPath, ':memo' => $memo !== '' ? $memo : null,
            ]);
            set_flash('success', '売上を登録しました。');
            redirect('sale_detail.php?id=' . (int)db()->lastInsertId());
        } catch (Throwable $e) {
            delete_uploaded_file_safely($documentPath);
            if ($e instanceof RuntimeException) {
                set_flash('error', $e->getMessage());
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = '売上登録 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card narrow">
  <h2>売上登録</h2>
  <p class="description">売上総額は必須です。手数料・送料が未入力の場合は0円として差引入金額を自動計算します。</p>
  <form method="post" class="stack sale-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <label>売上日 <span aria-hidden="true">*</span><input type="date" name="sale_date" value="<?= e($saleDate) ?>" required></label>
    <label>販売経路<select name="sales_channel"><option value="">選択しない</option><?php foreach ($channels as $channel): ?><option value="<?= e($channel) ?>" <?= $salesChannel === $channel ? 'selected' : '' ?>><?= e($channel) ?></option><?php endforeach; ?></select></label>
    <label>作物<select name="crop_id"><option value="">指定しない</option><?php foreach ($crops as $crop): ?><option value="<?= (int)$crop['id'] ?>" <?= $cropId !== '' && (int)$cropId === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option><?php endforeach; ?></select></label>
    <label>圃場<select name="field_id"><option value="">指定しない</option><?php foreach ($fields as $field): ?><option value="<?= (int)$field['id'] ?>" <?= $fieldId !== '' && (int)$fieldId === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option><?php endforeach; ?></select></label>
    <label>販売先<input type="text" name="buyer" value="<?= e($buyer) ?>" placeholder="JA・直売所・個人名など"></label>
    <label>品目 <span aria-hidden="true">*</span><input type="text" name="product_name" value="<?= e($productName) ?>" required></label>
    <div class="form-grid-3"><label>数量<input type="number" name="quantity" value="<?= e($quantity) ?>" min="0" step="0.001" inputmode="decimal"></label><label>単位<select name="unit"><option value="">選択しない</option><?php foreach ($units as $unitOption): ?><option value="<?= e($unitOption) ?>" <?= $unit === $unitOption ? 'selected' : '' ?>><?= e($unitOption) ?></option><?php endforeach; ?></select></label><label>単価<input type="number" name="unit_price" value="<?= e($unitPrice) ?>" min="0" step="1" inputmode="numeric"></label></div>
    <label>売上総額 <span aria-hidden="true">*</span><input type="number" name="gross_amount" value="<?= e($grossAmount) ?>" min="0" step="1" inputmode="numeric" required></label>
    <div class="form-grid-3"><label>手数料<input type="number" name="fee_amount" value="<?= e($feeAmount) ?>" min="0" step="1" inputmode="numeric"></label><label>送料<input type="number" name="shipping_amount" value="<?= e($shippingAmount) ?>" min="0" step="1" inputmode="numeric"></label><label>差引入金額<input type="number" name="net_amount" value="<?= e($netAmount) ?>" min="0" step="1" inputmode="numeric"></label></div>
    <label>入金状況<select name="payment_status"><?php foreach ($paymentStatuses as $status): ?><option value="<?= e($status) ?>" <?= $paymentStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
    <label>入金日<input type="date" name="payment_date" value="<?= e($paymentDate) ?>"></label>
    <label>売上明細・伝票写真<input type="file" name="document" accept="image/jpeg,image/png,image/webp"><span class="description">JPG / JPEG / PNG / WEBP、最大3MB。</span></label>
    <label>メモ<textarea name="memo" rows="4"><?= e($memo) ?></textarea></label>
    <div class="button-row"><button class="primary" type="submit">登録する</button><a class="btn" href="sale_list.php">一覧へ戻る</a></div>
  </form>
</section>
<script>
(function(){
  const form = document.querySelector('.sale-form'); if (!form) return;
  const q=form.quantity, u=form.unit_price, g=form.gross_amount, f=form.fee_amount, s=form.shipping_amount, n=form.net_amount;
  let grossTouched=false, netTouched=false;
  g.addEventListener('input',()=>{grossTouched=true; calcNet();}); n.addEventListener('input',()=>{netTouched=true;});
  function num(el){return parseFloat(el.value || '0') || 0;}
  function calcGross(){ if(!grossTouched && q.value !== '' && u.value !== '') { g.value = Math.round(num(q)*num(u)); calcNet(); } }
  function calcNet(){ if(!netTouched && g.value !== '') n.value = Math.max(0, Math.round(num(g)-num(f)-num(s))); }
  [q,u].forEach(el=>el.addEventListener('input',calcGross)); [f,s].forEach(el=>el.addEventListener('input',calcNet));
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
