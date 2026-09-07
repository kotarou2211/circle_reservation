<?php
/**
 * common/liff_auth.php — LIFF IDトークン検証ガード
 *
 * 背景:
 *   LIFF系APIは line_id / line_user_id をパラメータとして受け取るが、
 *   そのままでは「他人のLINE IDを指定してデータを取得・改ざんできる」リスクがある。
 *   このガードは、リクエストに含まれる IDトークン（X-Liff-Id-Token ヘッダー）を
 *   LINEの検証API（oauth2/v2.1/verify）で検証し、トークンの sub（=本人のLINE ID）と
 *   パラメータの line_id が一致することを確認する。
 *
 * 有効化:
 *   system_config.liff_token_verify = '1' のときのみ強制される（段階導入用）。
 *
 * 除外:
 *   - 管理画面セッションでのリクエスト
 *   - line_id / line_user_id をトップレベルに含まないリクエスト
 */

/**
 * IDトークンをLINEの検証APIで検証し、sub（LINE user ID）を返す。失敗時 null。
 */
function liffVerifyIdToken(PDO $pdo, string $id_token): ?string {
    static $cache = [];
    $key = sha1($id_token);
    if (array_key_exists($key, $cache)) return $cache[$key];

    $channel_id = (string)(getConfig($pdo, 'line_login_channel_id') ?? '');
    if ($channel_id === '' && defined('LINE_LOGIN_CHANNEL_ID')) {
        $channel_id = (string)LINE_LOGIN_CHANNEL_ID;
    }
    if ($channel_id === '') {
        // チャネルID未設定では検証不可能。フェイルオープン（ログのみ）にして
        // 設定ミスで全LIFFが停止する事故を防ぐ。
        error_log('[liff_auth] line_login_channel_id 未設定のため IDトークン検証をスキップ');
        return $cache[$key] = '__UNVERIFIED__';
    }

    $ch = curl_init('https://api.line.me/oauth2/v2.1/verify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['id_token' => $id_token, 'client_id' => $channel_id]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        error_log("[liff_auth] IDトークン検証失敗 HTTP {$code}");
        return $cache[$key] = null;
    }
    $j = json_decode($body, true);
    $sub = is_array($j) ? (string)($j['sub'] ?? '') : '';
    return $cache[$key] = ($sub !== '' ? $sub : null);
}

/**
 * リクエスト全体のガード。db_connect.php から自動で呼ばれる。
 * 検証NG時は 401/403 を返して exit する。
 */
function liffGuardEnforce(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    if (PHP_SAPI === 'cli') return;

    try {
        if (getConfig($pdo, 'liff_token_verify') !== '1') return;
    } catch (Throwable $e) {
        return; // system_config 未作成等は対象外
    }

    // リクエストから line_id / line_user_id を収集（トップレベルのみ）
    $ids = [];
    $json = null;
    foreach (['line_id', 'line_user_id'] as $k) {
        if (!empty($_GET[$k])  && is_string($_GET[$k]))  $ids[] = trim($_GET[$k]);
        if (!empty($_POST[$k]) && is_string($_POST[$k])) $ids[] = trim($_POST[$k]);
    }
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            foreach (['line_id', 'line_user_id'] as $k) {
                if (!empty($json[$k]) && is_string($json[$k])) $ids[] = trim($json[$k]);
            }
        }
    }
    $ids = array_values(array_unique(array_filter($ids)));
    if (!$ids) return; // LINE IDを扱わないリクエストは対象外

    // 管理画面セッションがあればスキップ
    require_once __DIR__ . '/auth.php';
    try {
        if (getAdminInfo(true)) return; // true = CSRFチェックを行わない参照のみ
    } catch (Throwable $e) {}

    $token = trim($_SERVER['HTTP_X_LIFF_ID_TOKEN'] ?? '');
    if ($token === '' && is_array($json) && !empty($json['id_token'])) $token = trim((string)$json['id_token']);
    if ($token === '' && !empty($_GET['id_token'])) $token = trim((string)$_GET['id_token']);

    header('Content-Type: application/json; charset=utf-8');
    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '認証トークンがありません。LINEアプリから開き直してください。']);
        exit;
    }

    $sub = liffVerifyIdToken($pdo, $token);
    if ($sub === '__UNVERIFIED__') return; // チャネルID未設定（フェイルオープン）
    if ($sub === null) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '認証トークンが無効です。LINEアプリから開き直してください。']);
        exit;
    }

    foreach ($ids as $id) {
        if ($id !== $sub) {
            error_log("[liff_auth] line_id不一致: param={$id} sub={$sub}");
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '不正なアクセスです。']);
            exit;
        }
    }
    // OK — 続行
}
