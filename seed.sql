INSERT INTO users (username, password_hash) VALUES
('demo', '$2y$12$mcycXrK5D0vCAh58IpXUJ.z5OEc.K5BWpI6fe2OxRbB.v2F5OwUZW');

INSERT INTO crops (user_id, name) VALUES
(1, 'トマト'),
(1, 'ピーマン'),
(1, 'きゅうり');

INSERT INTO fields (user_id, name) VALUES
(1, '1号ハウス'),
(1, '2号ハウス'),
(1, '露地A');

INSERT INTO diaries (user_id, date, crop_id, field_id, work_content, memo) VALUES
(1, date('now', '-1 day'), 1, 1, '収穫', '朝に収穫。品質良好。'),
(1, date('now'), 2, 2, '潅水', '気温高めのため潅水量を増やした。');
