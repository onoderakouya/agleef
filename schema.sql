PRAGMA foreign_keys = ON;

-- =========================================================
-- users: ログインユーザーを管理するテーブル
-- 役割: 誰のデータかを判定するための親テーブル
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  email TEXT NOT NULL DEFAULT '',
  password_hash TEXT NOT NULL,
  is_admin INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (length(trim(username)) BETWEEN 3 AND 50),
  CHECK (email = '' OR (length(trim(email)) BETWEEN 3 AND 255 AND instr(email, '@') > 1)),
  CHECK (length(password_hash) >= 20),
  UNIQUE (username)
);

-- =========================================================
-- crops: 作物マスタを管理するテーブル
-- 役割: 「何を育てたか」を候補から選べるようにする
-- =========================================================
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email COLLATE NOCASE);

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
-- 役割: 1件の日誌に「作業日・天気・作業内容・作物・圃場」を紐づける
-- =========================================================
CREATE TABLE IF NOT EXISTS diaries (
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
);


-- =========================================================
-- expense_categories: 経費カテゴリを管理するテーブル
-- 役割: ユーザーごとに経費の分類候補を管理する
-- =========================================================
CREATE TABLE IF NOT EXISTS expense_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  sort_order INTEGER DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (length(trim(name)) BETWEEN 1 AND 100),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, name)
);

-- =========================================================
-- expenses: 農業経費の記録を保存するテーブル
-- 役割: 支払日・カテゴリ・金額・領収書写真などをユーザーごとに保存する
-- =========================================================
CREATE TABLE IF NOT EXISTS expenses (
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
);

-- =========================================================
-- sales: 農産物販売の売上記録を保存するテーブル
-- 役割: 売上日・販売経路・品目・金額・明細写真などをユーザーごとに保存する
-- =========================================================
CREATE TABLE IF NOT EXISTS sales (
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
  payment_status TEXT DEFAULT '未入金',
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
);

-- =========================================================
-- contact_requests: お問い合わせ・改善要望を保存するテーブル
-- 役割: ユーザーの声を改良材料として蓄積する
-- =========================================================
CREATE TABLE IF NOT EXISTS contact_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  name TEXT NOT NULL,
  email TEXT,
  category TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CHECK (length(trim(name)) BETWEEN 1 AND 100),
  CHECK (email IS NULL OR length(trim(email)) <= 255),
  CHECK (length(trim(category)) BETWEEN 1 AND 50),
  CHECK (length(trim(message)) BETWEEN 1 AND 3000)
);

-- =========================================================
-- インデックス設計
-- =========================================================
-- マスタ参照用（ユーザーごとの候補一覧を高速化）
CREATE INDEX IF NOT EXISTS idx_crops_user_id ON crops(user_id);
CREATE INDEX IF NOT EXISTS idx_fields_user_id ON fields(user_id);

-- 日誌の主要検索を高速化
CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC);
CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id);
CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id);

-- 経費の主要検索を高速化
CREATE INDEX IF NOT EXISTS idx_expense_categories_user_order ON expense_categories(user_id, sort_order, name);
CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses(user_id, expense_date DESC);
CREATE INDEX IF NOT EXISTS idx_expenses_category_id ON expenses(category_id);
CREATE INDEX IF NOT EXISTS idx_expenses_crop_id ON expenses(crop_id);
CREATE INDEX IF NOT EXISTS idx_expenses_field_id ON expenses(field_id);
-- 売上の主要検索を高速化
CREATE INDEX IF NOT EXISTS idx_sales_user_date ON sales(user_id, sale_date DESC);
CREATE INDEX IF NOT EXISTS idx_sales_crop_id ON sales(crop_id);
CREATE INDEX IF NOT EXISTS idx_sales_field_id ON sales(field_id);
CREATE INDEX IF NOT EXISTS idx_sales_channel ON sales(user_id, sales_channel);
CREATE INDEX IF NOT EXISTS idx_sales_payment_status ON sales(user_id, payment_status);

CREATE INDEX IF NOT EXISTS idx_contact_requests_created_at ON contact_requests(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_contact_requests_user_id ON contact_requests(user_id);
