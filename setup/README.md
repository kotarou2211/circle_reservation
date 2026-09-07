# セットアップ手順

## 前提
- PHP 8.x
- MySQL / MariaDB
- LINE公式アカウント（Messaging API）＋ LIFF

---

## STEP 1: DBを作成

任意のホスティング環境でMySQL/MariaDBデータベースを1つ作成し、文字コードは `UTF-8`（utf8mb4）にしてください。ホスト名・DB名・ユーザー名・パスワードをメモしておきます。

---

## STEP 2: 環境変数（.env）を作成・配置

1. `setup/.env.example` を `.env` にコピー
2. DB接続情報（ホスト・DB名・ユーザー・パス）を入力
3. **Webサーバーのドキュメントルートの1つ上**（Webから直接アクセスできない場所）に配置

```
/home/ユーザー名/          ← ここに .env を置く（Webから見えない）
/home/ユーザー名/www/      ← ドキュメントルート
/home/ユーザー名/www/reservation/  ← このアプリのディレクトリ
```

LINE関連の設定を `.env` に含めない場合は、`setup/config.secret.example.php` を `config.secret.php` にコピーして同じ場所に置くこともできます（任意）。

---

## STEP 3: ファイルをアップロード

このリポジトリ一式を、`.env`・`config.secret.php`・`setup/.env.example` 以外そのままアップロードしてください（`.gitignore` で除外されているファイルは、あなたのローカル環境にしか存在しません）。

---

## STEP 4: install.php を実行

1. `setup/install.php` 内の `INSTALL_SECRET` を必ず自分だけの値に変更する

   ```php
   define('INSTALL_SECRET', 'your_own_secret_here');  // ← 変更する
   ```

2. ブラウザでアクセス：
   ```
   https://ドメイン/reservation/setup/install.php?secret=your_own_secret_here
   ```

3. DB接続情報を入力して「▶ テーブル作成を実行」をクリック

4. 全テーブルが `OK` になれば成功

---

## STEP 5: セットアップ後の対処

### install.php を無効化（重要）
実行後は `setup/install.php` をサーバーから削除するか、`.htaccess` 等でアクセス不可にしてください。

### 最初の管理者アカウントを作成
`admin_users` テーブルにまだ行が無い状態です。`setup/create_admin.php` 内の `INSTALL_SECRET` を `install.php` と同じ値に変更してから、以下にアクセスして最初のSuper Adminを1件作成してください。

```
https://ドメイン/reservation/setup/create_admin.php?secret=your_own_secret_here
```

作成後は必ずこのファイルもサーバーから削除してください。以後の管理者追加は管理画面の「管理者アカウント」タブから行えます。

### LINE公式アカウントの設定
管理画面ログイン後、「LINE設定」タブから Channel ID・Channel Secret・Channel Access Token・LIFF IDを入力してください。Webhook URLは画面に表示されるものをLINE Developersコンソールに登録します。

---

## テーブル一覧

| テーブル | 説明 |
|---|---|
| `admin_users` | 管理者アカウント |
| `admin_login_attempts` | ログイン失敗記録（ブルートフォース対策） |
| `system_config` | システム設定（LINE連携情報など） |
| `grades` | 学年マスタ（管理画面から編集可） |
| `faculties` | 学部マスタ（管理画面から編集可） |
| `registrants` | 予約前に登録した参加者（名前・学年・学部・大学/サークル名） |
| `event_slots` | 予約枠（候補日・定員） |
| `reservations` | 予約 |
| `message_logs` | LINE確認・リマインダー送信ログ |
