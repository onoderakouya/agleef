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
  last_login_at TEXT,
  is_suspended INTEGER NOT NULL DEFAULT 0,
  suspended_at TEXT,
  suspended_reason TEXT,
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
CREATE INDEX IF NOT EXISTS idx_users_is_suspended ON users(is_suspended);
CREATE INDEX IF NOT EXISTS idx_users_last_login_at ON users(last_login_at);

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
-- app_settings: アプリ全体設定
-- =========================================================
CREATE TABLE IF NOT EXISTS app_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO app_settings (key, value, updated_at)
VALUES ('registration_enabled', '1', CURRENT_TIMESTAMP);

-- =========================================================
-- contacts: お問い合わせ管理
-- =========================================================
CREATE TABLE IF NOT EXISTS contacts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  name TEXT,
  email TEXT,
  subject TEXT NOT NULL,
  message TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT '未対応',
  admin_memo TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================
-- admin_logs: 管理者操作ログ
-- =========================================================
CREATE TABLE IF NOT EXISTS admin_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_user_id INTEGER NOT NULL,
  action TEXT NOT NULL,
  target_type TEXT,
  target_id INTEGER,
  detail TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
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

CREATE INDEX IF NOT EXISTS idx_contacts_status_created ON contacts(status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_contacts_user_id ON contacts(user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created_at ON admin_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_user_id ON admin_logs(admin_user_id);

-- =========================================================
-- Opt-in email delivery (existing users remain not_subscribed)
-- =========================================================
CREATE TABLE IF NOT EXISTS email_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER NOT NULL UNIQUE,email TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'not_subscribed' CHECK(status IN ('subscribed','unsubscribed','not_subscribed')),consent_source TEXT,subscribed_at TEXT,unsubscribed_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_email_subscriptions_email ON email_subscriptions(email COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_email_subscriptions_status ON email_subscriptions(status);
CREATE TABLE IF NOT EXISTS email_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT,subject TEXT NOT NULL,body_text TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','queued','sending','completed','cancelled')),created_by INTEGER NOT NULL,target_filter TEXT NOT NULL DEFAULT 'subscribed_active',from_name TEXT,from_address TEXT,reply_to TEXT,total_count INTEGER NOT NULL DEFAULT 0,queued_count INTEGER NOT NULL DEFAULT 0,sent_count INTEGER NOT NULL DEFAULT 0,failed_count INTEGER NOT NULL DEFAULT 0,skipped_count INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,queued_at TEXT,started_at TEXT,completed_at TEXT,cancelled_at TEXT,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT);
CREATE INDEX IF NOT EXISTS idx_email_campaigns_status ON email_campaigns(status,created_at);
CREATE TABLE IF NOT EXISTS email_campaign_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT,campaign_id INTEGER NOT NULL,user_id INTEGER NOT NULL,email TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','processing','sent','failed','skipped')),attempt_count INTEGER NOT NULL DEFAULT 0,last_error TEXT,next_attempt_at TEXT,queued_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,sent_at TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,UNIQUE(campaign_id,user_id));
CREATE INDEX IF NOT EXISTS idx_email_recipients_queue ON email_campaign_recipients(status,next_attempt_at,id);
CREATE INDEX IF NOT EXISTS idx_email_recipients_campaign ON email_campaign_recipients(campaign_id,status);
INSERT OR IGNORE INTO email_subscriptions(user_id,email,status,consent_source) SELECT id,email,'not_subscribed','schema' FROM users;
