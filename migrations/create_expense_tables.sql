PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS expense_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  sort_order INTEGER DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE (user_id, name)
);

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

CREATE INDEX IF NOT EXISTS idx_expense_categories_user_order ON expense_categories(user_id, sort_order, name);
CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses(user_id, expense_date DESC);
CREATE INDEX IF NOT EXISTS idx_expenses_category_id ON expenses(category_id);
CREATE INDEX IF NOT EXISTS idx_expenses_crop_id ON expenses(crop_id);
CREATE INDEX IF NOT EXISTS idx_expenses_field_id ON expenses(field_id);

-- demoユーザー向け初期カテゴリ。通常利用ではログイン後に ensure_default_expense_categories() がユーザーごとに作成します。
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '種苗費', 10 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '肥料費', 20 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '農薬費', 30 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '諸材料費', 40 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '農具費', 50 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '修繕費', 60 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '動力光熱費', 70 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '車両費', 80 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '荷造運賃', 90 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '通信費', 100 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '研修費', 110 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, '雑費', 120 FROM users WHERE username = 'demo';
INSERT OR IGNORE INTO expense_categories (user_id, name, sort_order)
SELECT id, 'その他', 130 FROM users WHERE username = 'demo';
