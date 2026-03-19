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
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    ensure_crop_field_tables($pdo);
    ensure_diaries_table($pdo);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute([':username' => 'demo']);

    if ((int)$stmt->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $insert->execute([
            ':username' => 'demo',
            ':password_hash' => password_hash('password', PASSWORD_DEFAULT),
        ]);
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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

    if (in_array('work_date', $columnNames, true)
        && in_array('weather', $columnNames, true)
        && in_array('crop_id', $columnNames, true)
        && in_array('field_id', $columnNames, true)
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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE SET NULL,
                FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL
            )'
        );

        $hasDate = in_array('date', $columnNames, true);
        $hasWorkDate = in_array('work_date', $columnNames, true);
        $hasWeather = in_array('weather', $columnNames, true);
        $hasCreatedAt = in_array('created_at', $columnNames, true);
        $hasCropId = in_array('crop_id', $columnNames, true);
        $hasFieldId = in_array('field_id', $columnNames, true);

        $workDateColumn = $hasWorkDate ? 'work_date' : ($hasDate ? 'date' : "date('now')");
        $weatherColumn = $hasWeather ? 'weather' : 'NULL';
        $createdAtColumn = $hasCreatedAt ? 'created_at' : 'CURRENT_TIMESTAMP';
        $cropIdColumn = $hasCropId ? 'crop_id' : 'NULL';
        $fieldIdColumn = $hasFieldId ? 'field_id' : 'NULL';

        $pdo->exec(
            "INSERT INTO diaries_new (id, user_id, crop_id, field_id, work_date, weather, work_content, created_at)
             SELECT id, user_id, {$cropIdColumn}, {$fieldIdColumn}, {$workDateColumn}, {$weatherColumn}, work_content, COALESCE({$createdAtColumn}, CURRENT_TIMESTAMP)
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
