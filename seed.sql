PRAGMA foreign_keys = ON;

-- 既存データを削除（依存順）
DELETE FROM diaries;
DELETE FROM crops;
DELETE FROM fields;
DELETE FROM users;

-- テストユーザー
-- password はどちらも "password" のハッシュ
INSERT INTO users (username, password_hash) VALUES
('demo', '$2y$12$mcycXrK5D0vCAh58IpXUJ.z5OEc.K5BWpI6fe2OxRbB.v2F5OwUZW'),
('hanako', '$2y$12$mcycXrK5D0vCAh58IpXUJ.z5OEc.K5BWpI6fe2OxRbB.v2F5OwUZW');

-- demo の作物
INSERT INTO crops (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'demo'), 'トマト'),
((SELECT id FROM users WHERE username = 'demo'), 'ピーマン'),
((SELECT id FROM users WHERE username = 'demo'), 'きゅうり');

-- hanako の作物
INSERT INTO crops (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'hanako'), 'いちご'),
((SELECT id FROM users WHERE username = 'hanako'), 'ほうれん草');

-- demo の圃場
INSERT INTO fields (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'demo'), '1号ハウス'),
((SELECT id FROM users WHERE username = 'demo'), '2号ハウス'),
((SELECT id FROM users WHERE username = 'demo'), '露地A');

-- hanako の圃場
INSERT INTO fields (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'hanako'), '南側ハウス'),
((SELECT id FROM users WHERE username = 'hanako'), '露地B');

-- demo の日誌
INSERT INTO diaries (user_id, date, crop_id, field_id, work_content, memo, photo_path) VALUES
(
  (SELECT id FROM users WHERE username = 'demo'),
  date('now', '-2 day'),
  (SELECT c.id FROM crops c JOIN users u ON u.id = c.user_id WHERE u.username = 'demo' AND c.name = 'トマト'),
  (SELECT f.id FROM fields f JOIN users u ON u.id = f.user_id WHERE u.username = 'demo' AND f.name = '1号ハウス'),
  '芽かきと誘引',
  '主枝を整理し、風通しを改善。',
  NULL
),
(
  (SELECT id FROM users WHERE username = 'demo'),
  date('now', '-1 day'),
  (SELECT c.id FROM crops c JOIN users u ON u.id = c.user_id WHERE u.username = 'demo' AND c.name = 'トマト'),
  (SELECT f.id FROM fields f JOIN users u ON u.id = f.user_id WHERE u.username = 'demo' AND f.name = '1号ハウス'),
  '収穫',
  '朝に収穫。品質良好。',
  'assets/uploads/demo_tomato_harvest.jpg'
),
(
  (SELECT id FROM users WHERE username = 'demo'),
  date('now'),
  (SELECT c.id FROM crops c JOIN users u ON u.id = c.user_id WHERE u.username = 'demo' AND c.name = 'ピーマン'),
  (SELECT f.id FROM fields f JOIN users u ON u.id = f.user_id WHERE u.username = 'demo' AND f.name = '2号ハウス'),
  '潅水',
  '気温高めのため潅水量を増やした。',
  NULL
);

-- hanako の日誌
INSERT INTO diaries (user_id, date, crop_id, field_id, work_content, memo, photo_path) VALUES
(
  (SELECT id FROM users WHERE username = 'hanako'),
  date('now', '-1 day'),
  (SELECT c.id FROM crops c JOIN users u ON u.id = c.user_id WHERE u.username = 'hanako' AND c.name = 'いちご'),
  (SELECT f.id FROM fields f JOIN users u ON u.id = f.user_id WHERE u.username = 'hanako' AND f.name = '南側ハウス'),
  '摘果',
  '小さい実を間引いて株の負担を軽減。',
  NULL
),
(
  (SELECT id FROM users WHERE username = 'hanako'),
  date('now'),
  (SELECT c.id FROM crops c JOIN users u ON u.id = c.user_id WHERE u.username = 'hanako' AND c.name = 'ほうれん草'),
  (SELECT f.id FROM fields f JOIN users u ON u.id = f.user_id WHERE u.username = 'hanako' AND f.name = '露地B'),
  '追肥',
  '生育ムラ対策で条間に追肥。',
  NULL
);
