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

function ensure_diaries_table(PDO $pdo): void
{
    $tableExists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'diaries'")->fetchColumn() > 0;

    if (!$tableExists) {
        $pdo->exec(
            'CREATE TABLE diaries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                work_date TEXT NOT NULL,
                weather TEXT,
                work_content TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        return;
    }

    $columns = $pdo->query('PRAGMA table_info(diaries)')->fetchAll();
    $columnNames = array_map(static fn(array $col): string => $col['name'], $columns);

    if (in_array('work_date', $columnNames, true) && in_array('weather', $columnNames, true)) {
        return;
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec(
            'CREATE TABLE diaries_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                work_date TEXT NOT NULL,
                weather TEXT,
                work_content TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        $hasDate = in_array('date', $columnNames, true);
        $dateColumn = $hasDate ? 'date' : "date('now')";

        $pdo->exec(
            "INSERT INTO diaries_new (id, user_id, work_date, weather, work_content, created_at)
             SELECT id, user_id, {$dateColumn}, NULL, work_content, COALESCE(created_at, CURRENT_TIMESTAMP)
             FROM diaries"
        );

        $pdo->exec('DROP TABLE diaries');
        $pdo->exec('ALTER TABLE diaries_new RENAME TO diaries');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
