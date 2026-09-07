<?php
/**
 * common/db_connect.php — DB接続
 *
 * DB接続情報は .env (Webルート外) で管理する。
 * config.secret.php は後方互換のため存在すれば読み込むが必須ではない。
 * このファイルを include すると $pdo が使用可能になる。
 */

date_default_timezone_set('Asia/Tokyo');

// ── config.secret.php の読み込み（存在すれば。必須ではない） ──
$_secret_paths = [
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config.secret.php',
    __DIR__ . '/../../config.secret.php',
    __DIR__ . '/../config.secret.php',
];
foreach ($_secret_paths as $_p) {
    if (file_exists($_p) && is_readable($_p)) {
        require_once $_p;
        break;
    }
}
unset($_secret_paths, $_p);

// ── .env の読み込み（DB接続情報はこちらが正） ────────────────
require_once __DIR__ . '/Env.php';
$_envPaths = [
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '') . '/.env',
    __DIR__ . '/../../.env',
    __DIR__ . '/../.env',
];
foreach ($_envPaths as $_envPath) {
    if (file_exists($_envPath)) {
        Env::load($_envPath);
        break;
    }
}
unset($_envPaths, $_envPath);

$_dbHost    = Env::get('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : null);
$_dbPort    = Env::get('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : null);
$_dbName    = Env::get('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : null);
$_dbCharset = Env::get('DB_CHARSET') ?: (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
$_dbUser    = Env::get('DB_USER') ?: (defined('DB_USER') ? DB_USER : null);
$_dbPass    = Env::get('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : null);

if ($_dbHost === null || $_dbName === null || $_dbUser === null) {
    error_log('[CRITICAL] DB接続情報が見つかりません（.env / config.secret.php とも不足）。');
    http_response_code(503);
    die(json_encode(['error' => '設定ファイルが見つかりません。']));
}

// ── PDO 接続 ──────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . $_dbHost . ($_dbPort ? ';port=' . $_dbPort : '') . ';dbname=' . $_dbName . ';charset=' . $_dbCharset,
        $_dbUser,
        $_dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[CRITICAL] DB接続失敗: ' . $e->getMessage());
    http_response_code(503);
    die(json_encode(['error' => 'データベース接続に失敗しました。']));
}

/**
 * システム設定を取得する
 *
 * @param string|null $key 指定するとその値のみ返す。nullなら全設定を配列で返す。
 */
function getConfig(PDO $pdo, ?string $key = null): mixed {
    if ($key !== null) {
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: null;
    }
    return $pdo->query("SELECT config_key, config_value FROM system_config")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * メッセージ送信ログを記録する（非致命的）
 */
function logMessageSent(PDO $pdo, string $line_user_id, string $kind, ?int $slot_id, bool $success = true): void {
    try {
        $pdo->prepare("
            INSERT INTO message_logs (line_user_id, kind, slot_id, sent_at, success)
            VALUES (?, ?, ?, NOW(), ?)
        ")->execute([$line_user_id, $kind, $slot_id, $success ? 1 : 0]);
    } catch (Throwable $e) {
        error_log('[logMessageSent] ' . $e->getMessage());
    }
}

// ── LIFF IDトークン検証ガード ─────────────────────────────
// system_config.liff_token_verify = '1' のとき、line_id / line_user_id を含む
// リクエストに対して IDトークン検証を強制する（詳細は common/liff_auth.php）
require_once __DIR__ . '/liff_auth.php';
liffGuardEnforce($pdo);
