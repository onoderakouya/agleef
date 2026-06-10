-- diaries に crop_id / field_id を追加し、SQLite でも外部キー制約を持つ形へ作り直す migration です。
-- 対象: 既存の diaries が user_id / work_date / weather / work_content / created_at を持つDB。
-- 注意: SQLite は ALTER TABLE ADD COLUMN で外部キー制約つきカラムを安全に追加しづらいため、
--       テーブルを作り直して既存データをコピーします。
-- 実行前に必ず database.sqlite をバックアップしてください。

PRAGMA foreign_keys = OFF;
BEGIN TRANSACTION;

CREATE TABLE diaries_new (
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
);

-- 既存日誌は過去データのため crop_id / field_id は NULL で引き継ぎます。
-- 画面側では新規作成・編集時に作物と圃場を必須選択にしています。
INSERT INTO diaries_new (id, user_id, crop_id, field_id, work_date, weather, work_content, created_at)
SELECT
  id,
  user_id,
  NULL AS crop_id,
  NULL AS field_id,
  work_date,
  weather,
  work_content,
  COALESCE(created_at, CURRENT_TIMESTAMP)
FROM diaries;

DROP TABLE diaries;
ALTER TABLE diaries_new RENAME TO diaries;

CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC);
CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id);
CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id);

COMMIT;
PRAGMA foreign_keys = ON;

-- 確認用:
-- PRAGMA foreign_key_check;
-- PRAGMA table_info(diaries);
