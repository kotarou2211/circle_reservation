<?php
/**
 * common/line_api.php — LINE Messaging API ラッパー（最小構成）
 */

/**
 * LINEチャネルアクセストークンを取得する。
 * 1. .env の LINE_CHANNEL_ACCESS_TOKEN
 * 2. 定数 LINE_CHANNEL_ACCESS_TOKEN（config.secret.php、あれば）
 * 3. system_config.line_channel_access_token（管理画面で保存した値）
 */
function lineAccessToken(): string {
    if (class_exists('Env')) {
        $t = Env::get('LINE_CHANNEL_ACCESS_TOKEN');
        if ($t) return $t;
    }
    if (defined('LINE_CHANNEL_ACCESS_TOKEN') && LINE_CHANNEL_ACCESS_TOKEN !== '') {
        return LINE_CHANNEL_ACCESS_TOKEN;
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && function_exists('getConfig')) {
        $t = getConfig($GLOBALS['pdo'], 'line_channel_access_token');
        if ($t) return (string)$t;
    }
    return '';
}

/**
 * LINE にメッセージをプッシュ送信する
 *
 * @param string $to       LINE ユーザーID
 * @param array  $messages メッセージオブジェクト配列（最大5件）
 * @return bool 成功/失敗
 */
function linePushMessage(string $to, array $messages): bool {
    $token = lineAccessToken();
    if (empty($token) || empty($to)) return false;

    $body = json_encode(['to' => $to, 'messages' => $messages], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 200;
}

/**
 * LINE プロフィールを取得する
 *
 * @return array|null ['displayName', 'pictureUrl', 'statusMessage']
 */
function lineGetProfile(string $line_user_id): ?array {
    $token = lineAccessToken();
    if (empty($token) || empty($line_user_id)) return null;

    $ch = curl_init("https://api.line.me/v2/bot/profile/" . rawurlencode($line_user_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response  = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) return null;
    return json_decode($response, true) ?: null;
}

/**
 * テキストメッセージオブジェクトを生成する（ヘルパー）
 */
function lineTextMsg(string $text): array {
    return ['type' => 'text', 'text' => $text];
}
