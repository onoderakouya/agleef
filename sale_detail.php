<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
$id = (int)get_query_param('id', '0');
$stmt = db()->prepare('SELECT s.*, c.name AS crop_name, f.name AS field_name
    FROM sales s
    LEFT JOIN crops c ON c.id = s.crop_id AND c.user_id = s.user_id
    LEFT JOIN fields f ON f.id = s.field_id AND f.user_id = s.user_id
    WHERE s.id = :id AND s.user_id = :user_id');
$stmt->execute([':id' => $id, ':user_id' => $userId]);
$sale = $stmt->fetch();
if (!$sale) {
    set_flash('error', '売上が見つかりません。');
    redirect('sale_list.php');
}
$pageTitle = '売上詳細 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>売上詳細</h2>
  <dl class="detail-grid">
    <dt>売上日</dt><dd><?= e($sale['sale_date']) ?></dd>
    <dt>販売経路</dt><dd><?= e($sale['sales_channel'] ?? '-') ?></dd>
    <dt>作物</dt><dd><?= e($sale['crop_name'] ?? '-') ?></dd>
    <dt>圃場</dt><dd><?= e($sale['field_name'] ?? '-') ?></dd>
    <dt>販売先</dt><dd><?= e($sale['buyer'] ?? '-') ?></dd>
    <dt>品目</dt><dd><?= e($sale['product_name']) ?></dd>
    <dt>数量</dt><dd><?= e((string)($sale['quantity'] !== null && $sale['quantity'] !== '' ? format_quantity((float)$sale['quantity']) : '-')) ?></dd>
    <dt>単位</dt><dd><?= e($sale['unit'] ?? '-') ?></dd>
    <dt>単価</dt><dd><?= e((string)($sale['unit_price'] !== null ? format_yen((int)$sale['unit_price']) : '-')) ?></dd>
    <dt>売上総額</dt><dd><strong><?= e(format_yen((int)$sale['gross_amount'])) ?></strong></dd>
    <dt>手数料</dt><dd><?= e(format_yen((int)$sale['fee_amount'])) ?></dd>
    <dt>送料</dt><dd><?= e(format_yen((int)$sale['shipping_amount'])) ?></dd>
    <dt>差引入金額</dt><dd><strong><?= e(format_yen((int)$sale['net_amount'])) ?></strong></dd>
    <dt>入金状況</dt><dd><?= e($sale['payment_status'] ?? '未入金') ?></dd>
    <dt>入金日</dt><dd><?= e($sale['payment_date'] ?? '-') ?></dd>
    <dt>メモ</dt><dd><?= $sale['memo'] !== null && $sale['memo'] !== '' ? nl2br(e($sale['memo'])) : '-' ?></dd>
    <dt>作成日時</dt><dd><?= e($sale['created_at']) ?></dd>
    <dt>更新日時</dt><dd><?= e($sale['updated_at']) ?></dd>
  </dl>
</section>
<section class="card">
  <h3>売上明細・伝票写真</h3>
  <?php if (!empty($sale['document_path'])): ?>
    <div class="photo-box">
      <button type="button" class="photo-zoom-trigger" aria-haspopup="dialog" aria-controls="sale-document-modal">
        <img class="diary-photo" src="<?= e($sale['document_path']) ?>" alt="売上明細・伝票写真">
        <span class="photo-zoom-hint">クリックして拡大</span>
      </button>
    </div>
    <div id="sale-document-modal" class="photo-modal" role="dialog" aria-modal="true" aria-label="売上明細・伝票写真の拡大表示" hidden>
      <div class="photo-modal-backdrop" data-photo-modal-close></div>
      <div class="photo-modal-content">
        <button type="button" class="photo-modal-close" data-photo-modal-close aria-label="拡大表示を閉じる">×</button>
        <img class="photo-modal-image" src="<?= e($sale['document_path']) ?>" alt="売上明細・伝票写真の拡大表示">
      </div>
    </div>
  <?php else: ?>
    <p class="description">明細写真なし</p>
  <?php endif; ?>
</section>
<section class="card"><div class="button-row"><a class="btn primary" href="sale_edit.php?id=<?= e((string)((int)$sale['id'])) ?>">編集する</a><a class="btn" href="sale_list.php">一覧へ戻る</a></div></section>
<?php if (!empty($sale['document_path'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var trigger = document.querySelector('.photo-zoom-trigger');
  var modal = document.getElementById('sale-document-modal');
  var closeButtons = document.querySelectorAll('[data-photo-modal-close]');

  if (!trigger || !modal) {
    return;
  }

  function openModal() {
    modal.hidden = false;
    document.body.classList.add('has-photo-modal');
    var closeButton = modal.querySelector('.photo-modal-close');
    if (closeButton) {
      closeButton.focus();
    }
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove('has-photo-modal');
    trigger.focus();
  }

  trigger.addEventListener('click', openModal);

  closeButtons.forEach(function (button) {
    button.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
