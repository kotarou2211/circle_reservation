<?php
/**
 * api/line/webhook.php — LINE Messaging API Webhook エンドポイント
 *
 * このアプリは予約者への個別プッシュ送信のみを行うため、フォロー/グループ等の
 * イベント処理は行わない。LINE Developers Console の「検証」ボタンおよび
 * 署名検証だけを正しく通すための最小実装。
 */
require_once __DIR__ . '/../../common/db_connect.php';

$raw_body = file_get_contents('php://input');

$channel_secret = getConfig($pdo, 'line_channel_secret') ?: '';
if (!empty($channel_secret)) {
    $line_sig = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $expected = base64_encode(hash_hmac('sha256', $raw_body, $channel_secret, true));
    if (!hash_equals($expected, $line_sig)) {
        error_log('[line/webhook] 署名検証失敗 — 不正なリクエスト');
        http_response_code(400);
        exit;
    }
}

// LINEは常にHTTP 200を要求する。イベント内容は処理せず受信確認のみ行う。
http_response_code(200);
echo 'OK';
