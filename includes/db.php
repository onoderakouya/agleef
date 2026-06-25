<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initialize_database($pdo);

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL DEFAULT \'\',
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    ensure_users_table($pdo);
    ensure_crop_field_tables($pdo);
    ensure_diaries_table($pdo);
    ensure_expense_tables($pdo);
    ensure_sales_table($pdo);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute([':username' => 'demo']);

    if ((int)$stmt->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)');
        $insert->execute([
            ':username' => 'demo',
            ':email' => 'demo@example.com',
            ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
        ]);
    }
}

function ensure_users_table(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $columnNames = array_map(static fn(array $col): string => $col['name'], $columns);

    if (!in_array('is_admin', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
        $columnNames[] = 'is_admin';
    }

    if (!in_array('email', $columnNames, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT NOT NULL DEFAULT ''");
        $columnNames[] = 'email';
    }

    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique ON users(lower(email)) WHERE email <> ''");

    if (!in_array('updated_at', $columnNames, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN updated_at TEXT');
        $pdo->exec("UPDATE users SET updated_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE updated_at IS NULL OR updated_at = ''");
    }
}

function ensure_crop_field_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS crops (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, name)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, name)
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_crops_user_id ON crops(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fields_user_id ON fields(user_id)');
}

function ensure_diaries_table(PDO $pdo): void
{
    $tableExists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'diaries'")->fetchColumn() > 0;

    if (!$tableExists) {
        $pdo->exec(
            'CREATE TABLE diaries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                crop_id INTEGER,
                field_id INTEGER,
                work_date TEXT NOT NULL,
                weather TEXT,
                work_content TEXT NOT NULL,
                photo_path TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
                FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL
            )'
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id)');
        return;
    }

    $columns = $pdo->query('PRAGMA table_info(diaries)')->fetchAll();
    $columnNames = array_map(static fn(array $col): string => $col['name'], $columns);

    if (!in_array('photo_path', $columnNames, true)) {
        $pdo->exec('ALTER TABLE diaries ADD COLUMN photo_path TEXT');
        $columnNames[] = 'photo_path';
    }

    if (in_array('work_date', $columnNames, true)
        && in_array('weather', $columnNames, true)
        && in_array('crop_id', $columnNames, true)
        && in_array('field_id', $columnNames, true)
        && in_array('updated_at', $columnNames, true)
        && in_array('photo_path', $columnNames, true)
    ) {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id)');
        return;
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'CREATE TABLE diaries_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                crop_id INTEGER,
                field_id INTEGER,
                work_date TEXT NOT NULL,
                weather TEXT,
                work_content TEXT NOT NULL,
                photo_path TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
                FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL
            )'
        );

        $hasDate = in_array('date', $columnNames, true);
        $hasWorkDate = in_array('work_date', $columnNames, true);
        $hasWeather = in_array('weather', $columnNames, true);
        $hasCreatedAt = in_array('created_at', $columnNames, true);
        $hasUpdatedAt = in_array('updated_at', $columnNames, true);
        $hasCropId = in_array('crop_id', $columnNames, true);
        $hasFieldId = in_array('field_id', $columnNames, true);
        $hasPhotoPath = in_array('photo_path', $columnNames, true);

        $workDateColumn = $hasWorkDate ? 'work_date' : ($hasDate ? 'date' : "date('now')");
        $weatherColumn = $hasWeather ? 'weather' : 'NULL';
        $createdAtColumn = $hasCreatedAt ? 'created_at' : 'CURRENT_TIMESTAMP';
        $updatedAtColumn = $hasUpdatedAt ? 'updated_at' : $createdAtColumn;
        $cropIdColumn = $hasCropId ? 'crop_id' : 'NULL';
        $fieldIdColumn = $hasFieldId ? 'field_id' : 'NULL';
        $photoPathColumn = $hasPhotoPath ? 'photo_path' : 'NULL';

        $pdo->exec(
            "INSERT INTO diaries_new (id, user_id, crop_id, field_id, work_date, weather, work_content, photo_path, created_at, updated_at)
             SELECT id, user_id, {$cropIdColumn}, {$fieldIdColumn}, {$workDateColumn}, {$weatherColumn}, work_content, {$photoPathColumn}, COALESCE({$createdAtColumn}, CURRENT_TIMESTAMP), COALESCE({$updatedAtColumn}, {$createdAtColumn}, CURRENT_TIMESTAMP)
             FROM diaries"
        );

        $pdo->exec('DROP TABLE diaries');
        $pdo->exec('ALTER TABLE diaries_new RENAME TO diaries');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id)');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}


function ensure_expense_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS expense_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, name)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            expense_date TEXT NOT NULL,
            category_id INTEGER,
            crop_id INTEGER,
            field_id INTEGER,
            payee TEXT,
            description TEXT NOT NULL,
            amount INTEGER NOT NULL,
            payment_method TEXT,
            receipt_path TEXT,
            memo TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
            FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
            FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL,
            CHECK (amount > 0),
            CHECK (length(trim(expense_date)) > 0),
            CHECK (length(trim(description)) > 0)
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expense_categories_user_order ON expense_categories(user_id, sort_order, name)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses(user_id, expense_date DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_category_id ON expenses(category_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_crop_id ON expenses(crop_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_expenses_field_id ON expenses(field_id)');
}


function ensure_sales_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            sale_date TEXT NOT NULL,
            crop_id INTEGER,
            field_id INTEGER,
            buyer TEXT,
            sales_channel TEXT,
            product_name TEXT NOT NULL,
            quantity REAL,
            unit TEXT,
            unit_price INTEGER,
            gross_amount INTEGER NOT NULL,
            fee_amount INTEGER DEFAULT 0,
            shipping_amount INTEGER DEFAULT 0,
            net_amount INTEGER,
            payment_status TEXT DEFAULT \'未入金\',
            payment_date TEXT,
            document_path TEXT,
            memo TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
            FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL,
            CHECK (length(trim(sale_date)) > 0),
            CHECK (length(trim(product_name)) > 0),
            CHECK (gross_amount >= 0),
            CHECK (fee_amount >= 0),
            CHECK (shipping_amount >= 0),
            CHECK (net_amount IS NULL OR net_amount >= 0)
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_user_date ON sales(user_id, sale_date DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_crop_id ON sales(crop_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_field_id ON sales(field_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_channel ON sales(user_id, sales_channel)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sales_payment_status ON sales(user_id, payment_status)');
}
