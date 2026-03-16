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
  - 画像アップロードのMIME/サイズ制限

## 2. ディレクトリ構成

```text
/project-root
  /assets
    /css
      style.css
    /js
      app.js
    /uploads
  /includes
    config.php
    db.php
    functions.php
    header.php
    footer.php
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
- date
- crop_id (FK -> crops)
- field_id (FK -> fields)
- work_content
- memo
- photo_path
- created_at
- updated_at

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

## 5. MVP機能
- ログイン / ログアウト
- 作物マスタ管理（登録・編集・削除）
- 圃場管理（登録・編集・削除）
- 日誌作成（写真1枚アップロード可）
- 日誌一覧（新しい順）
- 日誌検索（日期・作物・圃場）
- 日誌詳細表示
- 日誌編集・削除

## 6. 補足
- `assets/uploads/` はWebサーバーから書き込み可能にしてください。
- 本番運用ではHTTPS化、Cookie設定強化、バックアップ設計を追加してください。
