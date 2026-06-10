-- 既存DBの日誌テーブルに写真パス保存用カラムを追加します。
-- 注意: SQLiteでは同じカラムを二重追加するとエラーになります。
-- 実行前に `PRAGMA table_info(diaries);` で photo_path が未追加であることを確認してください。
ALTER TABLE diaries ADD COLUMN photo_path TEXT;
