<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('diary_list.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $photoStmt = db()->prepare('SELECT photo_path FROM diaries WHERE id = :id AND user_id = :user_id');
    $photoStmt->execute([':id' => $id, ':user_id' => $userId]);
    $photoPath = $photoStmt->fetchColumn();

    $delete = db()->prepare('DELETE FROM diaries WHERE id = :id AND user_id = :user_id');
    $delete->execute([':id' => $id, ':user_id' => $userId]);

    if ($delete->rowCount() > 0) {
        delete_diary_photo(is_string($photoPath) ? $photoPath : null);
    }

    set_flash('success', '日誌を削除しました。');
    redirect('diary_list.php');
}

$crops = get_user_crops($userId);
$fields = get_user_fields($userId);
$cropIds = array_map('intval', array_column($crops, 'id'));
$fieldIds = array_map('intval', array_column($fields, 'id'));

$selectedCropId = null;
$selectedFieldId = null;
$dateFrom = trim(get_query_param('date_from'));
$dateTo = trim(get_query_param('date_to'));
$keyword = trim(get_query_param('keyword'));
$errors = [];
$dateRangeInvalid = false;

$cropIdParam = trim(get_query_param('crop_id'));
if ($cropIdParam !== '') {
    $cropId = filter_var($cropIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($cropId !== false && in_array((int)$cropId, $cropIds, true)) {
        $selectedCropId = (int)$cropId;
    } else {
        $errors[] = '指定された作物は選択できません。';
    }
}

$fieldIdParam = trim(get_query_param('field_id'));
if ($fieldIdParam !== '') {
    $fieldId = filter_var($fieldIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($fieldId !== false && in_array((int)$fieldId, $fieldIds, true)) {
        $selectedFieldId = (int)$fieldId;
    } else {
        $errors[] = '指定された圃場は選択できません。';
    }
}

if ($dateFrom !== '' && !is_valid_date($dateFrom)) {
    $errors[] = '開始日は正しい日付で入力してください。';
    $dateFrom = '';
}

if ($dateTo !== '' && !is_valid_date($dateTo)) {
    $errors[] = '終了日は正しい日付で入力してください。';
    $dateTo = '';
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $errors[] = '開始日は終了日以前の日付を指定してください。';
    $dateRangeInvalid = true;
}

$where = ['d.user_id = :user_id'];
$params = [':user_id' => $userId];

if ($selectedCropId !== null) {
    $where[] = 'd.crop_id = :crop_id';
    $params[':crop_id'] = $selectedCropId;
}

if ($selectedFieldId !== null) {
    $where[] = 'd.field_id = :field_id';
    $params[':field_id'] = $selectedFieldId;
}

if ($dateFrom !== '' && !$dateRangeInvalid) {
    $where[] = 'd.work_date >= :date_from';
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '' && !$dateRangeInvalid) {
    $where[] = 'd.work_date <= :date_to';
    $params[':date_to'] = $dateTo;
}

if ($keyword !== '') {
    $where[] = '(d.work_content LIKE :keyword OR d.weather LIKE :keyword)';
    $params[':keyword'] = '%' . $keyword . '%';
}

$hasSearchCondition = $selectedCropId !== null
    || $selectedFieldId !== null
    || ($dateFrom !== '' && !$dateRangeInvalid)
    || ($dateTo !== '' && !$dateRangeInvalid)
    || $keyword !== '';

$sql = 'SELECT d.id, d.work_date, d.weather, d.work_content, d.photo_path, d.created_at, d.updated_at,
               c.name AS crop_name,
               f.name AS field_name
        FROM diaries d
        LEFT JOIN crops c ON c.id = d.crop_id AND c.user_id = d.user_id
        LEFT JOIN fields f ON f.id = d.field_id AND f.user_id = d.user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY d.work_date DESC, d.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$diaries = $stmt->fetchAll();

$pageTitle = '日誌一覧 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <div class="button-row" style="justify-content: space-between; margin-top: 0;">
    <h2 style="margin-bottom:0;">日誌一覧</h2>
    <a class="btn primary" href="diary_create.php">＋ 新規登録</a>
  </div>
  <p class="description">ログイン中のユーザーの日誌のみ表示されます。</p>

  <?php foreach ($errors as $error): ?>
    <p class="alert error"><?= e($error) ?></p>
  <?php endforeach; ?>

  <form class="search-form" method="get" action="diary_list.php">
    <h3>検索条件</h3>
    <div class="filter-grid">
      <label>
        作物
        <select name="crop_id">
          <option value="">すべての作物</option>
          <?php foreach ($crops as $crop): ?>
            <option value="<?= (int)$crop['id'] ?>" <?= $selectedCropId === (int)$crop['id'] ? 'selected' : '' ?>>
              <?= e($crop['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        圃場
        <select name="field_id">
          <option value="">すべての圃場</option>
          <?php foreach ($fields as $field): ?>
            <option value="<?= (int)$field['id'] ?>" <?= $selectedFieldId === (int)$field['id'] ? 'selected' : '' ?>>
              <?= e($field['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        開始日
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>">
      </label>

      <label>
        終了日
        <input type="date" name="date_to" value="<?= e($dateTo) ?>">
      </label>

      <label>
        キーワード
        <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="例：灌水、追肥、雨">
      </label>
    </div>

    <div class="button-row search-actions">
      <button class="btn primary" type="submit">検索</button>
      <a class="btn" href="diary_list.php">リセット</a>
    </div>
  </form>

  <?php if ($hasSearchCondition): ?>
    <p class="alert success">検索条件に一致する日誌を表示しています。</p>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="diary-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>作業日</th>
          <th>写真</th>
          <th>作物名</th>
          <th>圃場名</th>
          <th>天気</th>
          <th>作業内容</th>
          <th>登録日時</th>
          <th>更新日時</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$diaries): ?>
          <tr>
            <td colspan="10">
              <?= $hasSearchCondition ? '条件に一致する日誌はありません。' : '日誌がまだありません。' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($diaries as $row): ?>
            <tr>
              <td data-label="ID"><?= (int)$row['id'] ?></td>
              <td data-label="作業日"><?= e($row['work_date']) ?></td>
              <td data-label="写真">
                <?php if (!empty($row['photo_path'])): ?>
                  <img class="diary-thumbnail" src="<?= e($row['photo_path']) ?>" alt="日誌写真サムネイル">
                <?php else: ?>
                  <span class="description">なし</span>
                <?php endif; ?>
              </td>
              <td data-label="作物名"><?= e($row['crop_name'] ?: '-') ?></td>
              <td data-label="圃場名"><?= e($row['field_name'] ?: '-') ?></td>
              <td data-label="天気"><?= e($row['weather'] ?: '-') ?></td>
              <td data-label="作業内容"><?= nl2br(e($row['work_content'])) ?></td>
              <td data-label="登録日時"><?= e($row['created_at']) ?></td>
              <td data-label="更新日時"><?= e($row['updated_at'] ?? $row['created_at']) ?></td>
              <td data-label="操作" class="actions-cell">
                <div class="inline-actions">
                  <a class="btn small" href="diary_detail.php?id=<?= (int)$row['id'] ?>">詳細</a>
                  <a class="btn small" href="diary_edit.php?id=<?= (int)$row['id'] ?>">編集</a>
                  <form method="post" onsubmit="return confirm('この日誌を削除しますか？');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="btn small danger" type="submit">削除</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
