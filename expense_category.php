<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = current_user_id();
ensure_default_expense_categories($userId);

$editId = (int)get_query_param('edit_id', '0');
$editCategory = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM expense_categories WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => $editId, ':user_id' => $userId]);
    $editCategory = $stmt->fetch();
    if (!$editCategory) {
        set_flash('error', '編集対象のカテゴリが見つかりません。');
        redirect('expense_category.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', '不正なリクエストです。');
        redirect('expense_category.php');
    }

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sortOrder = trim($_POST['sort_order'] ?? '0');
    $categoryId = (int)($_POST['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $errors = [];
        if ($name === '') {
            $errors[] = 'カテゴリ名を入力してください。';
        }
        if ($sortOrder === '' || !preg_match('/^-?\d+$/', $sortOrder)) {
            $errors[] = '表示順は整数で入力してください。';
        }
        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect($action === 'update' ? 'expense_category.php?edit_id=' . $categoryId : 'expense_category.php');
        }

        try {
            if ($action === 'create') {
                $insert = db()->prepare('INSERT INTO expense_categories (user_id, name, sort_order) VALUES (:user_id, :name, :sort_order)');
                $insert->execute([':user_id' => $userId, ':name' => $name, ':sort_order' => (int)$sortOrder]);
                set_flash('success', '経費カテゴリを追加しました。');
            } else {
                $update = db()->prepare('UPDATE expense_categories SET name = :name, sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
                $update->execute([':name' => $name, ':sort_order' => (int)$sortOrder, ':id' => $categoryId, ':user_id' => $userId]);
                if ($update->rowCount() === 0) {
                    set_flash('error', '編集対象のカテゴリが見つかりません。');
                } else {
                    set_flash('success', '経費カテゴリを更新しました。');
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                set_flash('error', '同じ名前のカテゴリはすでに登録されています。');
            } else {
                throw $e;
            }
        }
        redirect('expense_category.php');
    }

    if ($action === 'delete') {
        $used = db()->prepare('SELECT COUNT(*) FROM expenses WHERE user_id = :user_id AND category_id = :category_id');
        $used->execute([':user_id' => $userId, ':category_id' => $categoryId]);
        if ((int)$used->fetchColumn() > 0) {
            set_flash('error', 'このカテゴリは経費で使用されているため削除できません。');
            redirect('expense_category.php');
        }

        $delete = db()->prepare('DELETE FROM expense_categories WHERE id = :id AND user_id = :user_id');
        $delete->execute([':id' => $categoryId, ':user_id' => $userId]);
        set_flash('success', '経費カテゴリを削除しました。');
        redirect('expense_category.php');
    }
}

$stmt = db()->prepare('SELECT ec.*, COUNT(e.id) AS expense_count
    FROM expense_categories ec
    LEFT JOIN expenses e ON e.category_id = ec.id AND e.user_id = ec.user_id
    WHERE ec.user_id = :user_id
    GROUP BY ec.id
    ORDER BY ec.sort_order ASC, ec.name ASC');
$stmt->execute([':user_id' => $userId]);
$categories = $stmt->fetchAll();

$pageTitle = '経費カテゴリ管理 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h2>経費カテゴリ管理</h2>
  <p class="description">カテゴリはユーザーごとに独立しています。初期カテゴリも必要に応じて名前や表示順を変更できます。</p>
</section>

<section class="card narrow">
  <h3><?= e((string)($editCategory ? 'カテゴリ編集' : 'カテゴリ追加')) ?></h3>
  <form method="post" class="stack">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= e((string)($editCategory ? 'update' : 'create')) ?>">
    <?php if ($editCategory): ?><input type="hidden" name="id" value="<?= e((string)((int)$editCategory['id'])) ?>"><?php endif; ?>
    <label>カテゴリ名<input type="text" name="name" value="<?= e($editCategory['name'] ?? '') ?>" required></label>
    <label>表示順<input type="number" name="sort_order" value="<?= e((string)($editCategory['sort_order'] ?? '0')) ?>" step="1"></label>
    <div class="button-row">
      <button class="primary" type="submit"><?= e((string)($editCategory ? '更新する' : '追加する')) ?></button>
      <?php if ($editCategory): ?><a class="btn" href="expense_category.php">追加フォームに戻る</a><?php endif; ?>
      <a class="btn" href="expense_list.php">経費一覧へ</a>
    </div>
  </form>
</section>

<section class="card">
  <h3>カテゴリ一覧</h3>
  <ul class="list">
    <?php foreach ($categories as $category): ?>
      <li>
        <div>
          <strong><?= e($category['name']) ?></strong>
          <span class="description">表示順: <?= e((string)((int)$category['sort_order'])) ?> / 使用件数: <?= e((string)((int)$category['expense_count'])) ?>件</span>
        </div>
        <div class="inline-actions">
          <a class="btn small" href="expense_category.php?edit_id=<?= e((string)((int)$category['id'])) ?>">編集</a>
          <form method="post" onsubmit="return confirm('このカテゴリを削除しますか？');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e((string)((int)$category['id'])) ?>">
            <button class="btn small danger" type="submit" <?= e((string)((int)$category['expense_count'] > 0 ? 'disabled' : '')) ?>>削除</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
