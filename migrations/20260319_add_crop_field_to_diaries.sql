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
