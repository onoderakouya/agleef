PRAGMA foreign_keys = ON;

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

CREATE INDEX IF NOT EXISTS idx_sales_user_date ON sales(user_id, sale_date DESC);
CREATE INDEX IF NOT EXISTS idx_sales_crop_id ON sales(crop_id);
CREATE INDEX IF NOT EXISTS idx_sales_field_id ON sales(field_id);
CREATE INDEX IF NOT EXISTS idx_sales_channel ON sales(user_id, sales_channel);
CREATE INDEX IF NOT EXISTS idx_sales_payment_status ON sales(user_id, payment_status);
