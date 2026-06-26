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
  - `.htaccess` による `database.sqlite` の直接ダウンロード拒否（Apache 配置時）
  - 公開環境でのHTTP→HTTPSリダイレクト、HSTS、Secure/HttpOnly/SameSiteセッションCookie

## 2. ディレクトリ構成

```text
/project-root
  /assets
    /css
      style.css
      lp.css
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
  index.php              # 未ログインでも閲覧できるランディングページ
  login.php
  register.php
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


## 4. 公開ランディングページ

`index.php` は未ログインユーザーでも閲覧できる公開LPです。`require_login()` は呼び出さず、セッション状態だけを確認して、ログイン中の場合は「ダッシュボードへ」の導線を表示します。

- 表示URL例: `https://honocca.com/agleef/`
- LP専用CSS: `assets/css/lp.css`
- 新規登録導線: `register.php`
- ログイン導線: `login.php`
- ログイン中ユーザー向け導線: `dashboard.php`


## HTTPS / 常時SSL

公開環境では `includes/config.php` の `FORCE_HTTPS` が有効になっているため、HTTPアクセスはHTTPSへ `308 Permanent Redirect` されます。Apache環境では `.htaccess` でもPHP実行前にHTTPSへリダイレクトします。

- `localhost` / `127.0.0.1` / `::1` / `*.localhost` はローカル開発用としてHTTPS強制の対象外です。
- TLS終端プロキシやロードバランサー配下では `X-Forwarded-Proto: https` または `X-Forwarded-SSL: on` を信頼し、リダイレクトループを防ぎます。
- HTTPS判定時は `Strict-Transport-Security` を送信し、セッションCookieに `Secure` / `HttpOnly` / `SameSite=Lax` を設定します。

## 5. セットアップ手順（ローカル）

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

## 6. 既存DBの migration 手順

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


## 7. バックアップ設計・復元手順

本番環境では `database.sqlite` と `assets/uploads/` を定期バックアップし、バックアップファイルは公開ディレクトリ外に保存します。

### 保存形式

標準のバックアップ先はプロジェクトルートの1階層上にある `agleef-backups/` です。Web公開ディレクトリがこのリポジトリの場合、公開ディレクトリ外になります。サーバー構成に合わせて `BACKUP_DIR` で明示指定してください。

```text
../agleef-backups/
  database_YYYYMMDD.sqlite
  uploads_YYYYMMDD.zip
```

### 手動バックアップ

DBとアップロードファイルをまとめてバックアップします。

```bash
./scripts/backup.sh
```

保存先を明示する場合は、必ずWebから直接アクセスできない場所を指定します。

```bash
BACKUP_DIR=/home/your-user/agleef-backups ./scripts/backup.sh
```

本番反映前は、最低限DBバックアップを必ず作成してからデプロイ・migrationを実行します。

```bash
./scripts/pre_deploy_backup.sh
```

### 定期バックアップ設定例

cronで毎日3:10にDBとアップロードファイルをバックアップする例です。`BACKUP_DIR` は公開ディレクトリ外の絶対パスに置き換えてください。

```cron
10 3 * * * cd /path/to/agleef && BACKUP_DIR=/home/your-user/agleef-backups ./scripts/backup.sh >> /home/your-user/agleef-backups/backup.log 2>&1
```

アップロードファイルが大きい場合は、DBは毎日、アップロードファイルは週1回などに分けて実行できます。

```cron
10 3 * * * cd /path/to/agleef && BACKUP_DIR=/home/your-user/agleef-backups ./scripts/backup.sh --db-only >> /home/your-user/agleef-backups/backup.log 2>&1
30 3 * * 0 cd /path/to/agleef && BACKUP_DIR=/home/your-user/agleef-backups ./scripts/backup.sh --uploads-only >> /home/your-user/agleef-backups/backup.log 2>&1
```

### 復元手順

復元作業前に、現在の状態を退避します。

```bash
cp database.sqlite database.sqlite.before-restore
if [ -d assets/uploads ]; then mv assets/uploads assets/uploads.before-restore; fi
```

DBを復元します。

```bash
cp /home/your-user/agleef-backups/database_YYYYMMDD.sqlite database.sqlite
```

アップロードファイルを復元します。

```bash
unzip /home/your-user/agleef-backups/uploads_YYYYMMDD.zip -d .
```

復元後、Webサーバー実行ユーザーがDBとアップロードディレクトリを読み書きできるよう権限を確認します。

```bash
chmod 664 database.sqlite
chmod -R u+rwX,g+rwX assets/uploads
sqlite3 database.sqlite "PRAGMA integrity_check;"
```

`PRAGMA integrity_check;` が `ok` を返したら、ログイン、日誌・経費・売上の一覧表示、写真表示を確認してください。

## 7. MVP機能
- 新規ユーザー登録（`ALLOW_REGISTRATION` による受付停止、CSRFトークン検証、パスワードハッシュ化）
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


## 8. アカウント管理

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


## 9. 新規ユーザー登録

`login.php` の「新規登録はこちら」から `register.php` に移動し、一般ユーザーが自分でアカウントを作成できます。

### 仕様

- 入力項目: ユーザー名、パスワード、パスワード確認
- ユーザー名は前後の空白を削除し、3文字以上50文字以内で検証します。
- 既存の `users.username` と重複するユーザー名は登録できません。
- パスワードは8文字以上、確認用パスワードとの一致を検証します。
- パスワードは必ず `password_hash()` でハッシュ化し、平文では保存しません。
- SQLはPDOの `prepare()` を使い、フォーム送信時はCSRFトークンを検証します。
- エラー時は入力済みユーザー名だけを保持し、パスワード欄は保持しません。
- 登録成功後は自動ログインせず、`login.php` に戻って「登録が完了しました。ログインしてください。」と表示します。
- 登録直後にユーザー別の初期経費カテゴリを作成します。

### 登録受付の停止

公開サーバーで登録受付を止めたい場合は、`includes/config.php` の設定を変更してください。

```php
define('ALLOW_REGISTRATION', false);
```

`false` の場合、ログイン画面の新規登録リンクは表示されず、`register.php` では「現在、新規登録は停止中です。」と表示されます。

### ローカルでの動作確認

1. PHPの組み込みサーバーを起動します。
   ```bash
   php -S 0.0.0.0:8000
   ```
2. `http://localhost:8000/login.php` を開き、「新規登録はこちら」が表示されることを確認します。
3. `register.php` でユーザー名、8文字以上のパスワード、確認用パスワードを入力して登録します。
4. 登録成功後に `login.php` へ戻り、登録したユーザー名とパスワードでログインできることを確認します。
5. 8文字未満のパスワード、確認不一致、既存ユーザー名、CSRFトークン不正で登録できないことを確認します。
6. `includes/config.php` の `ALLOW_REGISTRATION` を `false` に変更し、新規登録リンクとフォームが無効になることを確認します。

### 本番サーバーへの反映手順

1. 反映前に `database.sqlite` とアプリ一式をバックアップします。
2. `register.php`、`login.php`、`includes/config.php`、`includes/functions.php`、`assets/css/style.css`、`README.md` をアップロードします。
3. 本番で一般登録を受け付ける場合は `ALLOW_REGISTRATION` を `true`、一時停止する場合は `false` にします。
4. HTTPS環境でアクセスし、新規登録、ログイン、既存の日誌・経費・売上・CSV出力の主要操作を確認します。

### 想定されるエラーと対処法

- 「このユーザー名はすでに使われています。」: 別のユーザー名を入力してください。
- 「不正なリクエストです。」: フォームを再表示してから再送信してください。セッションやCookieが無効な場合も確認してください。
- 「現在、新規登録は停止中です。」: `ALLOW_REGISTRATION` が `false` です。登録受付が必要な場合は `true` に変更してください。
- 登録後にログインできない: 入力したパスワードを確認し、DBの `password_hash` に平文ではなくハッシュ値が保存されていることを確認してください。
- DB書き込みエラー: Webサーバー実行ユーザーが `database.sqlite` と配置ディレクトリへ書き込めるか確認してください。

## 9. 補足
- `includes/db.php` は起動時に不足テーブルや古い `diaries` 構造を補正しますが、本番データでは事前バックアップ後に migration SQL を明示実行する運用を推奨します。
- 本番運用ではHTTPS化、Cookie設定強化に加え、「バックアップ設計・復元手順」に沿って定期バックアップを運用してください。

## 10. 経費記録機能（第1段階MVP）

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
- JPG / PNG / WEBP をアップロードでき、画像以外・PHPなどの実行可能ファイル・PHPコードを含むファイル・3MB超過ファイルは拒否される
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

## 11. 売上記録機能（第2段階MVP）

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

売上明細・伝票写真は `assets/uploads/sales/` に保存され、DBには相対パスを保存します。対応形式は JPG / JPEG / PNG / WEBP、最大サイズは `includes/config.php` の `MAX_UPLOAD_SIZE`（3MB）です。PHPなどの実行可能ファイル名やPHPコードを含むファイルは拒否します。ファイル名は元ファイル名を使わず、ユーザーID・日時・ランダム文字列から生成します。

### 動作確認

1. ログイン後、ヘッダーまたはダッシュボードから売上一覧へ移動する
2. 写真なしで売上を登録する
3. JPG / PNG / WEBP の明細写真つきで売上を登録する
4. 画像以外のファイル、PHPなどの実行可能ファイル、PHPコードを含むファイル、3MB超の写真がエラーになることを確認する
5. 売上一覧・詳細・編集・削除ができることを確認する
6. 期間、販売経路、作物、圃場、入金状況、キーワードで絞り込みできることを確認する
7. 数量×単価、売上総額−手数料−送料の補助計算が動作することを確認する
8. 他ユーザーの売上詳細URLや編集URLに直接アクセスしても表示・編集・削除できないことを確認する
9. 既存の日誌・作物・圃場・経費・アカウント機能が従来通り使えることを確認する

## 12. 年間集計ページ（第3段階MVP）

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

## 13. CSV出力機能（第4段階MVP）

確定申告準備、Excelでの確認、税理士への共有、会計ソフトへの取り込み準備、バックアップ保存に使えるよう、ログイン中ユーザーのデータだけをCSVでダウンロードできる機能を追加しています。本格的な会計ソフト連携、複式簿記、仕訳、消費税・インボイス・減価償却などの厳密な税務処理はこの段階では対象外です。税務上の判断は税理士・税務署等へ確認してください。

### 追加ファイル

- `export.php`: CSV出力メニュー画面
- `export_csv.php`: CSVダウンロード処理

### 出力できるCSV

- 売上CSV（`sales` / `crops` / `fields`）
- 経費CSV（`expenses` / `expense_categories` / `crops` / `fields`）
- 日誌CSV（`diaries` / `crops` / `fields`）
- 年間集計CSV（年間サマリー、月別、経費カテゴリ別、販売経路別、作物別、圃場別）
- 売上・経費まとめCSV（売上と経費を日付順に並べた簡易出納帳形式）

### 使い方

1. ログインします。
2. ヘッダーまたはダッシュボードの「CSV出力」へ移動します。
3. 出力対象、対象年、任意の開始日・終了日、文字コードを選択します。
4. 「CSVをダウンロード」を押します。

開始日または終了日を指定した場合、売上・経費・日誌・売上経費まとめCSVでは期間指定を優先します。年間集計CSVは対象年のみで集計します。

### セキュリティと文字化け対策

- 未ログインユーザーは `export.php` / `export_csv.php` を利用できません。
- CSV出力SQLはセッションの `user_id` を条件にし、ログイン中ユーザーのデータだけを出力します。
- `type` / `year` / `date_from` / `date_to` / `encoding` はバリデーションします。
- SQLはPDOの `prepare()` を使います。
- Excelで開きやすいよう、文字コードは「UTF-8 BOM付き」と「Shift_JIS」を選べます。
- CSVインジェクション対策として、文字列セルが `=` / `+` / `-` / `@` / `*` で始まる場合は先頭にシングルクォートを付けます。

### ファイル名例

- `agleef_sales_2026.csv`
- `agleef_expenses_2026.csv`
- `agleef_diaries_2026.csv`
- `agleef_annual_summary_2026.csv`
- `agleef_finance_all_2026.csv`
- `agleef_sales_2026-06-01_2026-06-30.csv`

### ローカルでの動作確認手順

1. PHPの組み込みサーバーを起動します。
   ```bash
   php -S localhost:8000
   ```
2. ブラウザで `http://localhost:8000/login.php` を開き、ログインします。
3. ダッシュボードまたはヘッダーから「CSV出力」を開きます。
4. 売上、経費、日誌、年間集計、売上・経費まとめをそれぞれUTF-8 BOM付きでダウンロードします。
5. 文字コードをShift_JISに変更し、同じCSVがダウンロードできることを確認します。
6. 不正なURL例 `export_csv.php?type=invalid` や `export_csv.php?year=1999` にアクセスし、エラー表示で `export.php` に戻ることを確認します。
7. ExcelでCSVを開き、日本語が文字化けしにくいこと、金額列が数値として扱いやすいことを確認します。

### 本番サーバーへの反映手順

1. `database.sqlite` と `assets/uploads/` をバックアップします。
2. 追加・変更ファイル（`export.php`、`export_csv.php`、`dashboard.php`、`annual_summary.php`、`includes/header.php`、`includes/functions.php`、`assets/css/style.css`、`README.md`）を本番サーバーへ配置します。
3. 新しいテーブルは不要なため、CSV出力機能用のmigration実行は不要です。
4. 本番環境でログインし、CSV出力ページから各CSVをダウンロードできることを確認します。
5. HTTPS、セッションCookie設定、DBバックアップ運用を必要に応じて確認してください。

### 想定されるエラーと対処法

- CSVダウンロード時にログイン画面へ戻る: セッションが切れています。再ログインしてください。
- `文字コードが正しくありません。` と表示される: `encoding` は `utf8_bom` または `sjis` を指定してください。
- `出力対象が正しくありません。` と表示される: `type` は `sales` / `expenses` / `diaries` / `annual_summary` / `finance_all` のいずれかを指定してください。
- `対象年は2000年から...` と表示される: 対象年は2000年から現在年+1年までで指定してください。
- Excelで文字化けする: まず「UTF-8 BOM付き」を試し、環境によっては「Shift_JIS」を選んで再ダウンロードしてください。
- CSVにデータが出ない: 対象年または開始日・終了日の範囲に、ログイン中ユーザーのデータがあるか確認してください。
- `Call to undefined function mb_convert_encoding()`: PHPのmbstring拡張が無効です。本番サーバーでmbstringを有効化してください。

## 14. 管理者機能（ユーザー管理MVP）

運営者だけが登録者数と登録ユーザー概要を確認できる管理者専用画面を追加しています。一般ユーザーには管理画面リンクを表示せず、直接URLを入力しても `require_admin()` でブロックします。

### 追加・変更ファイル

- `admin_dashboard.php`: 管理者用ダッシュボード。総登録ユーザー数、今日・今月の登録数、管理者数、一般ユーザー数、最新登録ユーザー5件を表示します。
- `admin_users.php`: 登録ユーザー一覧。ユーザー名検索、管理者/一般ユーザー絞り込み、簡易ページネーション、利用件数概要を表示します。
- `admin_user_detail.php`: 特定ユーザーの基本情報、利用件数、経費合計、売上合計、最新日誌・経費・売上5件の概要を表示します。
- `migrations/add_is_admin_to_users.sql`: 既存DBへ `users.is_admin` を追加するmigrationです。
- `schema.sql`: 新規インストール時の `users` テーブルに `is_admin` を追加しています。
- `includes/auth.php`: `current_user_is_admin()` と `require_admin()` を追加しています。
- `login.php`: ログイン時に `is_admin` をセッションへ保存します。
- `includes/header.php` / `dashboard.php`: 管理者だけに「管理画面」導線を表示します。
- `assets/css/style.css`: 管理者画面用のカード、バッジ、テーブル、ページネーション表示を追加しています。

### DB設計の変更内容

`users` テーブルに以下のカラムを追加しました。

```sql
is_admin INTEGER NOT NULL DEFAULT 0
```

- `0`: 一般ユーザー
- `1`: 管理者

新規インストールでは `schema.sql` により最初から `is_admin` カラムが作成されます。既存DBでは下記migrationを実行してください。

```sql
ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0;
```

SQLiteでは同じカラムを二重追加すると `duplicate column name: is_admin` エラーになります。migration実行前に以下でカラム有無を確認してください。

```bash
sqlite3 database.sqlite "PRAGMA table_info(users);"
```

すでに `is_admin` が表示されている場合、`migrations/add_is_admin_to_users.sql` は再実行しないでください。アプリ起動時の `includes/db.php` も不足カラムを補正しますが、本番ではバックアップ後にmigrationを明示実行する運用を推奨します。

### 運営者アカウントを管理者化するSQL

まずユーザー名を確認します。

```bash
sqlite3 database.sqlite "SELECT id, username, is_admin, created_at FROM users ORDER BY id;"
```

運営者アカウントの `username` に置き換えて、以下を実行します。

```sql
UPDATE users SET is_admin = 1, updated_at = CURRENT_TIMESTAMP WHERE username = 'kouya';
```

`username` は環境によって異なります。必ず実DBの運営者アカウント名に置き換えてください。反映後は以下で確認します。

```bash
sqlite3 database.sqlite "SELECT id, username, is_admin FROM users WHERE is_admin = 1;"
```

### 管理者専用アクセス制御

- 管理者ページ（`admin_dashboard.php` / `admin_users.php` / `admin_user_detail.php`）は先頭で `require_admin()` を呼び出します。
- 未ログインの場合は `login.php` にリダイレクトします。
- ログイン済みでも `is_admin = 0` の場合は `dashboard.php` にリダイレクトし、「管理者権限が必要です。」と表示します。
- `current_user_is_admin()` はセッションだけを信用せず、DBの現在値を確認してから判定します。
- 管理画面では `password_hash` をSELECTせず、画面にも表示しません。生のパスワードも扱いません。

### ローカルDBへの反映手順

1. DBをバックアップします。
   ```bash
   cp database.sqlite database.sqlite.bak
   ```
2. `is_admin` カラムがないことを確認します。
   ```bash
   sqlite3 database.sqlite "PRAGMA table_info(users);"
   ```
3. カラムが未追加の場合だけmigrationを実行します。
   ```bash
   sqlite3 database.sqlite < migrations/add_is_admin_to_users.sql
   ```
4. 運営者アカウントを管理者にします（`kouya` は実際のユーザー名へ置換）。
   ```bash
   sqlite3 database.sqlite "UPDATE users SET is_admin = 1, updated_at = CURRENT_TIMESTAMP WHERE username = 'kouya';"
   ```
5. 反映を確認します。
   ```bash
   sqlite3 database.sqlite "SELECT id, username, is_admin FROM users ORDER BY id;"
   ```
6. PHPの組み込みサーバーを起動して動作確認します。
   ```bash
   php -S localhost:8000
   ```

### 本番サーバーへの反映手順

1. `database.sqlite`、アプリ一式、`assets/uploads/` をバックアップします。
2. 追加・変更ファイルを本番へ配置します。
   - `admin_dashboard.php`
   - `admin_users.php`
   - `admin_user_detail.php`
   - `migrations/add_is_admin_to_users.sql`
   - `schema.sql`
   - `includes/auth.php`
   - `includes/db.php`
   - `includes/header.php`
   - `login.php`
   - `dashboard.php`
   - `assets/css/style.css`
   - `README.md`
3. 本番DBで `PRAGMA table_info(users);` を実行し、`is_admin` が未追加の場合だけmigrationを実行します。
4. 運営者アカウントの `username` を確認し、`UPDATE users SET is_admin = 1 ...` を実行します。
5. HTTPS環境で管理者ログインし、管理画面リンクと各管理者ページを確認します。
6. 一般ユーザーでもログインし、管理画面リンクが表示されず、直接URLアクセスもブロックされることを確認します。

### 動作確認手順

1. `sqlite3 database.sqlite "PRAGMA table_info(users);"` で `is_admin` カラムがあることを確認します。
2. `sqlite3 database.sqlite "SELECT id, username, is_admin FROM users;"` で運営者アカウントの `is_admin` を `1` にできていることを確認します。
3. 管理者でログインし、ヘッダーとダッシュボードに「管理画面」リンクが表示されることを確認します。
4. 一般ユーザーでログインし、「管理画面」リンクが表示されないことを確認します。
5. 管理者で `admin_dashboard.php` を開けることを確認します。
6. 一般ユーザーで `admin_dashboard.php` に直接アクセスし、`dashboard.php` へ戻されることを確認します。
7. 未ログイン状態で `admin_dashboard.php` にアクセスし、`login.php` へ戻されることを確認します。
8. 管理者で登録ユーザー数、ユーザー一覧、ユーザー詳細概要を確認します。
9. 画面内に `password_hash` や平文パスワードが表示されないことを確認します。
10. `admin_users.php` でユーザー名検索と管理者/一般ユーザー絞り込みができることを確認します。
11. 既存のログイン、日誌、作物、圃場、経費、売上、年間集計、CSV出力の主要操作が壊れていないことを確認します。

### 想定されるエラーと対処法

- `duplicate column name: is_admin`: すでに `is_admin` が追加済みです。`PRAGMA table_info(users);` で確認し、migrationを再実行しないでください。
- 管理者でログインしても管理画面リンクが出ない: 運営者アカウントの `is_admin` が `1` になっているか確認し、再ログインしてください。
- 一般ユーザーが管理画面へ入れない代わりにダッシュボードへ戻される: 正常なアクセス制御です。
- `no such column: is_admin`: 本番DBにmigrationが未実行です。バックアップ後、`migrations/add_is_admin_to_users.sql` を1回だけ実行してください。
- 管理者ページでユーザーが見つからない: `admin_user_detail.php?user_id=...` のIDが存在しません。ユーザー一覧から詳細を開き直してください。
- DB書き込みエラー: Webサーバー実行ユーザーが `database.sqlite` と配置ディレクトリに読み書きできるか確認してください。
