# 農業日誌アプリ（MVP）

日本の個人農家・小規模農園向けに、スマホから素早く入力できるシンプルな農業日誌Webアプリです。

## 1. 全体設計
- **技術スタック**: PHP + SQLite + HTML/CSS + JavaScript（最小構成）
- **方針**: 画面遷移が分かりやすいサーバーサイドレンダリング中心
- **認証**: セッションログイン（ユーザーごとにデータ分離）
- **セキュリティ（最低限）**
  - Prepared Statement による SQLインジェクション対策
  - `htmlspecialchars` による XSS対策
  - CSRFトークン検証
  - ログインユーザーの `user_id` によるデータ分離

## 2. ディレクトリ構成

```text
/project-root
  /assets
    /css
      style.css
    /js
      app.js
  /includes
    config.php
    db.php
    auth.php
    header.php
    footer.php
  /migrations
    20260319_add_crop_field_to_diaries.sql
    add_photo_path_to_diaries.sql
  index.php
  login.php
  logout.php
  dashboard.php
  crops.php
  fields.php
  diary_list.php
  diary_create.php
  diary_edit.php
  diary_detail.php
  schema.sql
  seed.sql
  README.md
```

## 3. DB設計

### users
- id (PK)
- username (UNIQUE)
- password_hash
- created_at
- updated_at

### crops
- id (PK)
- user_id (FK -> users)
- name
- created_at
- updated_at

### fields
- id (PK)
- user_id (FK -> users)
- name
- created_at
- updated_at

### diaries
- id (PK)
- user_id (FK -> users)
- crop_id (FK -> crops, nullable / ON DELETE SET NULL)
- field_id (FK -> fields, nullable / ON DELETE SET NULL)
- work_date
- weather
- work_content
- photo_path（写真の相対パス、nullable）
- created_at
- updated_at

`crop_id` と `field_id` は、過去データを安全に残すためDB上は NULL 許容です。画面では新規作成・編集時に「作物」「圃場」を必須選択にしています。

## 4. セットアップ手順（ローカル）

1. DB初期化
```bash
sqlite3 database.sqlite < schema.sql
sqlite3 database.sqlite < seed.sql
```

2. 開発サーバー起動
```bash
php -S 0.0.0.0:8000
```

3. ブラウザでアクセス
- `http://localhost:8000`

4. ログイン
- ユーザー名: `demo`
- パスワード: `password`

## 5. 既存DBの migration 手順

既存の `database.sqlite` に `diaries.crop_id` / `diaries.field_id` を追加する場合は、SQLite の外部キー制約追加の制限を避けるため、テーブルを作り直す migration を使います。

1. 必ずバックアップを作成
```bash
cp database.sqlite database.sqlite.bak
```

2. migration を実行
```bash
sqlite3 database.sqlite < migrations/20260319_add_crop_field_to_diaries.sql
```

3. 外部キーとカラムを確認
```bash
sqlite3 database.sqlite "PRAGMA foreign_key_check;"
sqlite3 database.sqlite "PRAGMA table_info(diaries);"
```

4. 既存日誌への作物・圃場設定
- migration 直後の既存日誌は `crop_id` / `field_id` が `NULL` です。
- ログイン後、日誌編集画面から作物・圃場を選択して更新してください。

### 日誌写真カラムを追加する migration

既存の `database.sqlite` に `diaries.photo_path` を追加する場合は、先にバックアップを作成し、未追加であることを確認してから実行してください。SQLite は同じカラムを二重追加するとエラーになります。

```bash
cp database.sqlite database.sqlite.bak
sqlite3 database.sqlite "PRAGMA table_info(diaries);"
sqlite3 database.sqlite < migrations/add_photo_path_to_diaries.sql
sqlite3 database.sqlite "PRAGMA table_info(diaries);"
```

`photo_path` がすでに表示されている場合、`migrations/add_photo_path_to_diaries.sql` は実行しないでください。

## 6. MVP機能
- ログイン / ログアウト
- 作物マスタ管理（登録・編集・削除）
- 圃場管理（登録・編集・削除）
- 日誌作成（作業日・作物・圃場・天気・作業内容・写真1枚）
- 日誌一覧（新しい順、作物名・圃場名・写真サムネイルを表示）
- 日誌詳細表示
- 日誌編集・削除

## 7. 補足
- `includes/db.php` は起動時に不足テーブルや古い `diaries` 構造を補正しますが、本番データでは事前バックアップ後に migration SQL を明示実行する運用を推奨します。
- 本番運用ではHTTPS化、Cookie設定強化、バックアップ設計を追加してください。
