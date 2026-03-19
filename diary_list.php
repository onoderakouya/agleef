<?php
/**
 * 役割:
 * - 日誌の一覧表示ページ
 * - 日付 / 作物 / 圃場で検索
 * - 詳細・編集ページへの導線を表示
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user_id();
$crops = get_user_crops($userId);
$fields = get_user_fields($userId);

$filterDate = trim($_GET['date'] ?? '');
$filterCrop = (int)($_GET['crop_id'] ?? 0);
$filterField = (int)($_GET['field_id'] ?? 0);

$sql = 'SELECT d.id, d.date, d.work_content, c.name AS crop_name, f.name AS field_name
        FROM diaries d
        LEFT JOIN crops c ON c.id = d.crop_id
        LEFT JOIN fields f ON f.id = d.field_id
        WHERE d.user_id = :user_id';
$params = [':user_id' => $userId];

if ($filterDate !== '') {
    $sql .= ' AND d.date = :date';
    $params[':date'] = $filterDate;
}
if ($filterCrop > 0) {
    $sql .= ' AND d.crop_id = :crop_id';
    $params[':crop_id'] = $filterCrop;
}
if ($filterField > 0) {
    $sql .= ' AND d.field_id = :field_id';
    $params[':field_id'] = $filterField;
}

$sql .= ' ORDER BY d.date DESC, d.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$diaries = $stmt->fetchAll();

$pageTitle = '日誌一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>日誌一覧</h2>
  <form method="get" class="filter-grid">
    <label>日付
      <input type="date" name="date" value="<?= e($filterDate) ?>">
    </label>
    <label>作物
      <select name="crop_id">
        <option value="">すべて</option>
        <?php foreach ($crops as $crop): ?>
          <option value="<?= (int)$crop['id'] ?>" <?= $filterCrop === (int)$crop['id'] ? 'selected' : '' ?>><?= e($crop['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>圃場
      <select name="field_id">
        <option value="">すべて</option>
        <?php foreach ($fields as $field): ?>
          <option value="<?= (int)$field['id'] ?>" <?= $filterField === (int)$field['id'] ? 'selected' : '' ?>><?= e($field['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="button-row">
      <button class="primary" type="submit">検索</button>
      <a class="btn" href="diary_list.php">リセット</a>
    </div>
  </form>
</section>

<section class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>日付</th><th>作物</th><th>圃場</th><th>作業内容</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php if (!$diaries): ?>
        <tr><td colspan="5">該当する日誌がありません。</td></tr>
      <?php else: ?>
        <?php foreach ($diaries as $row): ?>
          <tr>
            <td><?= e($row['date']) ?></td>
            <td><?= e($row['crop_name'] ?? '-') ?></td>
            <td><?= e($row['field_name'] ?? '-') ?></td>
            <td><?= e($row['work_content']) ?></td>
            <td>
              <a class="btn small" href="diary_detail.php?id=<?= (int)$row['id'] ?>">詳細</a>
              <a class="btn small" href="diary_edit.php?id=<?= (int)$row['id'] ?>">編集</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
