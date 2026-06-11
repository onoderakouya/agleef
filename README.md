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
  account.php
  account_edit.php
  password_change.php
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
- アカウント情報表示
- ユーザー名変更（現在のパスワード確認・重複チェック・CSRFトークン検証）
- パスワード変更（現在のパスワード確認・8文字以上・確認入力・CSRFトークン検証）


## 7. アカウント管理

ログイン後、ヘッダーの「アカウント」またはダッシュボードの「アカウント情報を確認する」から、自分のアカウント情報を確認できます。

- `account.php`: ユーザーID、ユーザー名、登録日時、更新日時を表示
- `account_edit.php`: ユーザー名を変更
  - ユーザー名は前後の空白を削除し、3文字以上50文字以内で検証
  - 他ユーザーが使用中のユーザー名は使用不可
  - 変更時は現在のパスワードを `password_verify()` で確認
- `password_change.php`: パスワードを変更
  - 新しいパスワードは8文字以上
  - 確認用パスワードとの一致を検証
  - 現在のパスワードと同じ値は不可
  - `password_hash()` でハッシュ化して保存

ユーザー情報の取得・更新はGETパラメータではなく、必ずセッションの `user_id` を使用します。更新成功時は `users.updated_at` を更新し、ユーザー名変更時はセッション内の `username` も更新します。

## 8. 補足
- `includes/db.php` は起動時に不足テーブルや古い `diaries` 構造を補正しますが、本番データでは事前バックアップ後に migration SQL を明示実行する運用を推奨します。
- 本番運用ではHTTPS化、Cookie設定強化、バックアップ設計を追加してください。

## 9. 経費記録機能（第1段階MVP）

確定申告準備や経営の振り返りに使えるよう、日々の農業経費を記録する機能を追加しています。複式簿記・仕訳・消費税・インボイス・減価償却の厳密な処理はこの段階では対象外です。税務判断が必要な内容は、将来的に税理士など専門家へ確認してください。

### 追加機能
- `expense_list.php`: 経費一覧、検索・絞り込み、表示中合計、今月合計、今年合計、カテゴリ別合計
- `expense_create.php`: 経費登録（支払日、カテゴリ、作物、圃場、支払先、内容、金額、支払方法、領収書写真、メモ）
- `expense_detail.php`: 経費詳細と領収書写真表示
- `expense_edit.php`: 経費編集、領収書写真の差し替え・削除
- `expense_delete.php`: POST + CSRFトークンによる経費削除
- `expense_category.php`: ユーザー別の経費カテゴリ追加・編集・削除

### 経費カテゴリの初期作成
ログイン後、対象ユーザーの `expense_categories` が0件の場合、`ensure_default_expense_categories($user_id)` により以下の初期カテゴリが自動作成されます。

- 種苗費
- 肥料費
- 農薬費
- 諸材料費
- 農具費
- 修繕費
- 動力光熱費
- 車両費
- 荷造運賃
- 通信費
- 研修費
- 雑費
- その他

### 領収書写真アップロード
- 保存先: `assets/uploads/expenses/`
- 対応形式: JPG / JPEG / PNG / WEBP
- 最大サイズ: `includes/config.php` の `MAX_UPLOAD_SIZE`（標準3MB）
- ファイル名は元ファイル名を使わず、ユーザーID・日時・ランダム文字列を含む安全な名前で保存します。
- 削除時は `assets/uploads/` 配下にあるファイルだけを安全確認して削除します。

### 経費テーブル

#### expense_categories
- id (PK)
- user_id (FK -> users)
- name
- sort_order
- created_at
- updated_at
- UNIQUE (user_id, name)

#### expenses
- id (PK)
- user_id (FK -> users)
- expense_date
- category_id (FK -> expense_categories, nullable / ON DELETE SET NULL)
- crop_id (FK -> crops, nullable / ON DELETE SET NULL)
- field_id (FK -> fields, nullable / ON DELETE SET NULL)
- payee
- description
- amount
- payment_method
- receipt_path
- memo
- created_at
- updated_at

## 10. 経費機能の既存DB migration 手順

既存の `database.sqlite` に経費テーブルを追加する場合は、バックアップ後に migration SQL を実行してください。

```bash
cp database.sqlite database.sqlite.bak
sqlite3 database.sqlite < migrations/create_expense_tables.sql
sqlite3 database.sqlite "PRAGMA foreign_key_check;"
sqlite3 database.sqlite "PRAGMA table_info(expense_categories);"
sqlite3 database.sqlite "PRAGMA table_info(expenses);"
```

migration SQL には demo ユーザー向けの初期カテゴリ投入SQLも含まれます。通常ユーザーはログイン後にカテゴリが0件なら自動作成されます。

## 11. 経費機能の本番反映手順

1. アプリケーションファイルを本番サーバーへ配置します。
2. `database.sqlite` と `assets/uploads/` をバックアップします。
3. `sqlite3 database.sqlite < migrations/create_expense_tables.sql` を実行します。
4. `assets/uploads/expenses/` がWebサーバーから書き込み可能であることを確認します。
5. ログインして「経費管理」へ移動し、カテゴリ初期作成・経費登録・写真アップロードを確認します。

## 12. 経費機能の動作確認

- ログイン後、ヘッダーまたはダッシュボードから経費一覧へ移動できる
- 経費カテゴリが初期作成される
- 経費カテゴリを追加・編集できる
- 経費を写真なし / 写真ありで登録できる
- JPG / PNG / WEBP をアップロードでき、画像以外や3MB超過ファイルは拒否される
- 経費一覧・詳細・編集・削除ができる
- 領収書写真の差し替え・削除ができる
- 表示中合計、今月合計、今年合計、カテゴリ別合計が表示される
- 期間、カテゴリ、作物、圃場、キーワードで絞り込みできる
- 他ユーザーの経費・カテゴリ・作物・圃場へアクセスできない
- 既存の日誌、作物、圃場、アカウント機能が動作する

### 想定されるエラーと対処法
- `no such table: expenses`: migration が未実行です。`sqlite3 database.sqlite < migrations/create_expense_tables.sql` を実行してください。
- 写真を保存できない: `assets/uploads/expenses/` の書き込み権限を確認してください。
- `写真のサイズは3MB以下にしてください。`: `MAX_UPLOAD_SIZE` を超えています。画像を圧縮するか、設定値を見直してください。
- `同じ名前のカテゴリはすでに登録されています。`: 同一ユーザー内ではカテゴリ名が重複できません。別名にしてください。

## 10. 売上記録機能（第2段階MVP）

確定申告準備や農業経営の振り返りに使えるよう、農産物販売の売上を日々記録する機能を追加しています。複式簿記・仕訳・消費税・インボイス・補助金処理・確定申告の完全自動化はこの段階では対象外です。税務判断が必要な内容は、将来的に税理士など専門家へ確認してください。

### 追加機能
- `sale_list.php`: 売上一覧、検索・絞り込み、表示中の売上総額合計、表示中の差引入金額合計、今月・今年の売上総額合計、販売経路別合計
- `sale_create.php`: 売上登録（売上日、販売経路、作物、圃場、販売先、品目、数量、単位、単価、売上総額、手数料、送料、差引入金額、入金状況、入金日、明細写真、メモ）
- `sale_detail.php`: 売上詳細と明細・伝票写真表示
- `sale_edit.php`: 売上編集、明細写真の差し替え・削除
- `sale_delete.php`: POST + CSRFトークンによる売上削除

### sales テーブル
- `id`: 主キー
- `user_id`: ユーザーID（`users.id`）
- `sale_date`: 売上日・販売日・出荷日
- `crop_id`: 関連作物（任意）
- `field_id`: 関連圃場（任意）
- `buyer`: 販売先・取引先
- `sales_channel`: 販売経路
- `product_name`: 販売品目名
- `quantity`: 数量
- `unit`: 単位
- `unit_price`: 単価
- `gross_amount`: 売上総額
- `fee_amount`: 販売手数料・JA手数料など
- `shipping_amount`: 送料・運賃など
- `net_amount`: 差引入金額
- `payment_status`: 入金状況
- `payment_date`: 入金日
- `document_path`: 明細書・納品書・売上伝票などの画像パス
- `memo`: メモ
- `created_at`: 作成日時
- `updated_at`: 更新日時

### 既存DBへの反映手順

既存の `database.sqlite` に売上テーブルを追加する場合は、バックアップ後に migration を実行してください。

```bash
cp database.sqlite database.sqlite.bak
sqlite3 database.sqlite < migrations/create_sales_table.sql
sqlite3 database.sqlite "PRAGMA table_info(sales);"
sqlite3 database.sqlite "PRAGMA foreign_key_check;"
```

### 画像アップロード

売上明細・伝票写真は `assets/uploads/sales/` に保存され、DBには相対パスを保存します。対応形式は JPG / JPEG / PNG / WEBP、最大サイズは `includes/config.php` の `MAX_UPLOAD_SIZE`（3MB）です。ファイル名は元ファイル名を使わず、ユーザーID・日時・ランダム文字列から生成します。

### 動作確認

1. ログイン後、ヘッダーまたはダッシュボードから売上一覧へ移動する
2. 写真なしで売上を登録する
3. JPG / PNG / WEBP の明細写真つきで売上を登録する
4. 画像以外のファイルや3MB超の写真がエラーになることを確認する
5. 売上一覧・詳細・編集・削除ができることを確認する
6. 期間、販売経路、作物、圃場、入金状況、キーワードで絞り込みできることを確認する
7. 数量×単価、売上総額−手数料−送料の補助計算が動作することを確認する
8. 他ユーザーの売上詳細URLや編集URLに直接アクセスしても表示・編集・削除できないことを確認する
9. 既存の日誌・作物・圃場・経費・アカウント機能が従来通り使えることを確認する

## 11. 年間集計ページ（第3段階MVP）

確定申告準備や農業経営の振り返りに役立つよう、`annual_summary.php` に年間集計ページを追加しています。既存の `sales` / `expenses` / `expense_categories` / `crops` / `fields` テーブルを利用し、新しい集計専用テーブルは作成しません。

### できること
- 対象年の選択（`annual_summary.php?year=2026` のようにGETパラメータで指定）
- 年間の売上総額、差引入金額、経費合計、簡易差引の表示
- 1月〜12月の月別集計
- 経費カテゴリ別の合計と割合
- 販売経路別の売上合計と割合
- 作物別の売上・経費・差引
- 圃場別の売上・経費・差引
- 未入金・一部入金の売上一覧
- 領収書写真が未登録の経費一覧

### 集計ルール
- 売上総額: `sales.gross_amount` の合計
- 差引入金額: `sales.net_amount` の合計
  - `net_amount` が `NULL` の場合は `gross_amount - fee_amount - shipping_amount` で補完します。
  - `fee_amount` / `shipping_amount` が `NULL` の場合は0円として扱います。
- 経費合計: `expenses.amount` の合計
- 簡易差引:
  - 売上総額ベース: 売上総額 − 経費合計
  - 入金額ベース: 差引入金額 − 経費合計

### 注意事項
この年間集計は、入力済みデータをもとにした申告準備・経営把握のための簡易集計です。複式簿記、仕訳、消費税・インボイス、減価償却、家事按分などの厳密な会計・税務処理は行いません。税務上の判断は税理士・税務署等へ確認してください。
