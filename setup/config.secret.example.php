<?php
/**
 * config.secret.php — 機密設定ファイルのサンプル
 *
 * 使い方:
 *   1. このファイルを config.secret.php にコピー
 *   2. Webサーバーのドキュメントルート「1つ上」のディレクトリに配置
 *      （public_html/ の外なのでWebから直接アクセス不可）
 *   3. 各定数に実際の値を入力
 *
 * 補足: DB接続情報は .env（.env.example参照）で管理するのが推奨。
 * こちらはLINE関連の値のみ .env 未移行の場合の後方互換用。
 */

// ── データベース（.envを使わない場合のみ） ────────────────
define('DB_HOST',    '');
define('DB_NAME',    '');
define('DB_USER',    '');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── LINE Messaging API ──────────────────────────────────
define('LINE_CHANNEL_ID',           '');   // LINE Developersコンソール → チャネル基本設定
define('LINE_CHANNEL_SECRET',       '');   // チャネルシークレット
define('LINE_CHANNEL_ACCESS_TOKEN', '');   // チャネルアクセストークン（長期）

// ── LIFF ────────────────────────────────────────────────
define('LIFF_ID', '');   // LINE Developers → LIFFタブ → LIFF ID

// ── 管理画面 ─────────────────────────────────────────────
define('ADMIN_SESSION_NAME',   'circle_reservation_admin');
define('ADMIN_SESSION_SECRET', 'replace_with_random_string_32chars');  // ← ランダム文字列に変更
