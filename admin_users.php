<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = db();
$keyword = trim(get_query_param('q'));
$role = get_query_param('role', 'all');
if (!in_array($role, ['all', 'admin', 'user'], true)) {
    $role = 'all';
}

$page = max(1, (int)get_query_param('page', '1'));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($keyword !== '') {
    $where[] = '(u.username LIKE :keyword OR u.email LIKE :keyword)';
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($role === 'admin') {
    $where[] = 'u.is_admin = 1';
} elseif ($role === 'user') {
    $where[] = 'u.is_admin = 0';
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "SELECT
            u.id,
            u.username,
            u.email,
            u.is_admin,
            u.created_at,
            u.updated_at,
            COUNT(DISTINCT d.id) AS diary_count,
            COUNT(DISTINCT c.id) AS crop_count,
            COUNT(DISTINCT f.id) AS field_count,
            COUNT(DISTINCT e.id) AS expense_count,
            COUNT(DISTINCT s.id) AS sale_count
        FROM users u
        LEFT JOIN diaries d ON d.user_id = u.id
        LEFT JOIN crops c ON c.user_id = u.id
        LEFT JOIN fields f ON f.user_id = u.id
        LEFT JOIN expenses e ON e.user_id = u.id
        LEFT JOIN sales s ON s.user_id = u.id
        {$whereSql}
        GROUP BY u.id
        ORDER BY datetime(u.created_at) DESC, u.id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$queryBase = ['q' => $keyword, 'role' => $role];
$buildPageUrl = static function (int $targetPage) use ($queryBase): string {
    return 'admin_users.php?' . http_build_query(array_merge($queryBase, ['page' => $targetPage]));
};

$pageTitle = 'ユーザー一覧 | 管理者画面 | ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="card admin-hero">
  <h2>管理者専用：登録ユーザー一覧</h2>
  <p class="description">登録ユーザーの基本情報と利用状況の概要を確認できます。パスワード情報は表示しません。</p>
  <div class="button-row">
    <a class="btn" href="admin_dashboard.php">管理者ダッシュボードへ戻る</a>
    <a class="btn" href="dashboard.php">通常ダッシュボードへ戻る</a>
  </div>
</section>

<section class="card">
  <h3>検索・絞り込み</h3>
  <form method="get" class="search-form">
    <div class="filter-grid">
      <label>
        ユーザー検索
        <input type="text" name="q" value="<?= e($keyword) ?>" placeholder="ユーザー名またはメールアドレスの一部">
      </label>
      <label>
        権限
        <select name="role">
          <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>すべて</option>
          <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>管理者</option>
          <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>一般ユーザー</option>
        </select>
      </label>
    </div>
    <div class="search-actions button-row">
      <button type="submit" class="primary">検索する</button>
      <a class="btn" href="admin_users.php">条件をクリア</a>
    </div>
  </form>
  <p class="description">該当件数: <?= e((string)$totalRows) ?>件 / <?= e((string)$page) ?>ページ目</p>
</section>

<section class="card">
  <h3>ユーザー一覧</h3>
  <div class="table-wrap">
    <table class="admin-table admin-users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>ユーザー名</th>
          <th>メールアドレス</th>
          <th>権限</th>
          <th>登録日時</th>
          <th>更新日時</th>
          <th>日誌</th>
          <th>作物</th>
          <th>圃場</th>
          <th>経費</th>
          <th>売上</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($users === []): ?>
          <tr><td colspan="12">条件に一致するユーザーはいません。</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $user): ?>
          <tr>
            <td data-label="ID"><?= e((string)$user['id']) ?></td>
            <td data-label="ユーザー名"><?= e($user['username']) ?></td>
            <td data-label="メールアドレス"><?= e($user['email'] ?? '-') ?></td>
            <td data-label="権限"><span class="badge <?= ((int)$user['is_admin'] === 1) ? 'badge-admin' : 'badge-user' ?>"><?= ((int)$user['is_admin'] === 1) ? '管理者' : '一般ユーザー' ?></span></td>
            <td data-label="登録日時"><?= e($user['created_at']) ?></td>
            <td data-label="更新日時"><?= e($user['updated_at'] ?? '-') ?></td>
            <td data-label="日誌件数"><?= e((string)$user['diary_count']) ?></td>
            <td data-label="作物件数"><?= e((string)$user['crop_count']) ?></td>
            <td data-label="圃場件数"><?= e((string)$user['field_count']) ?></td>
            <td data-label="経費件数"><?= e((string)$user['expense_count']) ?></td>
            <td data-label="売上件数"><?= e((string)$user['sale_count']) ?></td>
            <td data-label="詳細"><a class="btn small" href="admin_user_detail.php?user_id=<?= (int)$user['id'] ?>">概要</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="ページネーション">
      <?php if ($page > 1): ?>
        <a class="btn small" href="<?= e($buildPageUrl($page - 1)) ?>">前へ</a>
      <?php endif; ?>
      <span><?= e((string)$page) ?> / <?= e((string)$totalPages) ?></span>
      <?php if ($page < $totalPages): ?>
        <a class="btn small" href="<?= e($buildPageUrl($page + 1)) ?>">次へ</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
