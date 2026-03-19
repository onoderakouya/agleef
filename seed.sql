PRAGMA foreign_keys = ON;

DELETE FROM diaries;
DELETE FROM crops;
DELETE FROM fields;
DELETE FROM users;

-- テストユーザー（password: password）
INSERT INTO users (username, password_hash) VALUES
('demo', '$2y$12$mcycXrK5D0vCAh58IpXUJ.z5OEc.K5BWpI6fe2OxRbB.v2F5OwUZW'),
('hanako', '$2y$12$mcycXrK5D0vCAh58IpXUJ.z5OEc.K5BWpI6fe2OxRbB.v2F5OwUZW');

-- 既存ページ互換のための作物/圃場マスタ
INSERT INTO crops (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'demo'), 'トマト'),
((SELECT id FROM users WHERE username = 'demo'), 'ピーマン'),
((SELECT id FROM users WHERE username = 'hanako'), 'いちご');

INSERT INTO fields (user_id, name) VALUES
((SELECT id FROM users WHERE username = 'demo'), '1号ハウス'),
((SELECT id FROM users WHERE username = 'demo'), '2号ハウス'),
((SELECT id FROM users WHERE username = 'hanako'), '南側ハウス');

-- 日誌サンプル
INSERT INTO diaries (user_id, work_date, weather, work_content) VALUES
((SELECT id FROM users WHERE username = 'demo'), date('now', '-2 day'), '晴れ', 'トマトの誘引作業を実施'),
((SELECT id FROM users WHERE username = 'demo'), date('now', '-1 day'), '曇り', '圃場の除草と潅水を実施'),
((SELECT id FROM users WHERE username = 'hanako'), date('now'), '雨', 'ハウス内の点検と追肥を実施');
