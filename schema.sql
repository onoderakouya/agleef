PRAGMA foreign_keys = ON;

-- =========================================================
-- users: ログインユーザーを管理するテーブル
-- 役割: 誰のデータかを判定するための親テーブル
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (length(trim(username)) BETWEEN 3 AND 50),
  CHECK (length(password_hash) >= 20),
  UNIQUE (username)
);

-- =========================================================
-- crops: 作物マスタを管理するテーブル
-- 役割: 「何を育てたか」を候補から選べるようにする
-- =========================================================
CREATE TABLE IF NOT EXISTS crops (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (length(trim(name)) BETWEEN 1 AND 100),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, name)
);

-- =========================================================
-- fields: 圃場マスタを管理するテーブル
-- 役割: 「どこで作業したか」を候補から選べるようにする
-- =========================================================
CREATE TABLE IF NOT EXISTS fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (length(trim(name)) BETWEEN 1 AND 100),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, name)
);

-- =========================================================
-- diaries: 日々の作業記録を保存するテーブル
-- 役割: 1件の日誌に「日付・作物・圃場・作業内容」を紐づける
-- =========================================================
CREATE TABLE IF NOT EXISTS diaries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  date TEXT NOT NULL,
  crop_id INTEGER NOT NULL,
  field_id INTEGER NOT NULL,
  work_content TEXT NOT NULL,
  memo TEXT,
  photo_path TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (date GLOB '[1-2][0-9][0-9][0-9]-[0-1][0-9]-[0-3][0-9]'),
  CHECK (length(trim(work_content)) BETWEEN 1 AND 2000),
  CHECK (memo IS NULL OR length(memo) <= 5000),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE RESTRICT
);

-- =========================================================
-- インデックス設計
-- =========================================================
-- マスタ参照用（ユーザーごとの候補一覧を高速化）
CREATE INDEX IF NOT EXISTS idx_crops_user_id ON crops(user_id);
CREATE INDEX IF NOT EXISTS idx_fields_user_id ON fields(user_id);

-- 日誌の主要検索を高速化
CREATE INDEX IF NOT EXISTS idx_diaries_user_date ON diaries(user_id, date DESC);
CREATE INDEX IF NOT EXISTS idx_diaries_user_crop ON diaries(user_id, crop_id);
CREATE INDEX IF NOT EXISTS idx_diaries_user_field ON diaries(user_id, field_id);

-- FK整合性チェック時の参照負荷を軽減
CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id);
CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id);
