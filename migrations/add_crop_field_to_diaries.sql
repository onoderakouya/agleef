-- diaries テーブルに作物ID・圃場ID・更新日時を追加する migration です。
--
-- 実行前の注意:
-- 1. 必ず database.sqlite をバックアップしてください。
-- 2. SQLite の ALTER TABLE ADD COLUMN には IF NOT EXISTS がないため、
--    同じカラムを二重追加すると「duplicate column name」エラーになります。
-- 3. 実行前に以下で現在のカラムを確認し、存在しないカラムの ALTER TABLE だけを実行してください。
--      PRAGMA table_info(diaries);
--
-- 例: sqlite3 database.sqlite ".schema diaries"
--     sqlite3 database.sqlite < migrations/add_crop_field_to_diaries.sql
--
-- すでにアプリを一度開いている場合、includes/db.php の自動更新処理により
-- crop_id / field_id / updated_at が追加済みの可能性があります。

ALTER TABLE diaries ADD COLUMN crop_id INTEGER;
ALTER TABLE diaries ADD COLUMN field_id INTEGER;
ALTER TABLE diaries ADD COLUMN updated_at TEXT;

-- 既存データの updated_at は created_at と同じ値で初期化します。
UPDATE diaries
SET updated_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)
WHERE updated_at IS NULL;

-- よく使う検索・JOIN用のインデックスを作成します。
CREATE INDEX IF NOT EXISTS idx_diaries_user_work_date ON diaries(user_id, work_date DESC);
CREATE INDEX IF NOT EXISTS idx_diaries_crop_id ON diaries(crop_id);
CREATE INDEX IF NOT EXISTS idx_diaries_field_id ON diaries(field_id);

-- 確認用:
-- PRAGMA table_info(diaries);
