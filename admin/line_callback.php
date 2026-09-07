<?php
/**
 * admin/line_callback.php — LINE OAuth コールバック
 * LINE Developers の「ログインチャネル」コールバックURL に設定:
 *   https://あなたのドメイン/reservation/admin/line_callback.php
 */
require_once __DIR__ . '/../common/db_connect.php';
require_once __DIR__ . '/../common/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$error = '';

do {
    $code  = trim($_GET['code']  ?? '');
    $state = trim($_GET['state'] ?? '');

    if (empty($code)) { $error = 'codeが取得できませんでした。'; break; }
    if (empty($state) || $state !== ($_SESSION['line_oauth_state'] ?? '')) {
        $error = 'stateが一致しません（CSRF対策）。再度お試しください。'; break;
    }
    unset($_SESSION['line_oauth_state']);

    $channel_id     = getConfig($pdo, 'line_login_channel_id')     ?: '';
    $channel_secret = getConfig($pdo, 'line_login_channel_secret') ?: '';

    if (!$channel_id || !$channel_secret) {
        $error = 'LINEログインチャネルが設定されていません。システム設定を確認してください。'; break;
    }

    $redirect_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . str_replace('/line_callback.php', '', $_SERVER['SCRIPT_NAME'])
                  . '/line_callback.php';

    $ch = curl_init('https://api.line.me/oauth2/v2.1/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri,
            'client_id'     => $channel_id,
            'client_secret' => $channel_secret,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 15,
    ]);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) { $error = 'トークン取得に失敗しました (HTTP ' . $http . ')。'; break; }
    $token_data   = json_decode($res, true);
    $access_token = $token_data['access_token'] ?? '';
    if (!$access_token) { $error = 'アクセストークンが取得できませんでした。'; break; }

    $ch2 = curl_init('https://api.line.me/v2/profile');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res2  = curl_exec($ch2);
    $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($http2 !== 200) { $error = 'プロフィール取得に失敗しました。'; break; }
    $profile      = json_decode($res2, true);
    $line_user_id = $profile['userId'] ?? '';

    if (!$line_user_id) { $error = 'LINE IDが取得できませんでした。'; break; }

    $result = loginAdminByLine($pdo, $line_user_id);
    if (!$result['success']) { $error = $result['message']; break; }

    $next = filter_var($_SESSION['line_oauth_next'] ?? '', FILTER_SANITIZE_URL);
    unset($_SESSION['line_oauth_next']);
    header('Location: ' . ($next ?: 'index.php'));
    exit;

} while (false);

$circle_name = getConfig($pdo, 'circle_name') ?: '説明会予約ツール';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログインエラー — <?= htmlspecialchars($circle_name, ENT_QUOTES) ?> 管理画面</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div style="max-width:420px;margin:80px auto;padding:20px;">
  <div class="card">
    <div class="card-body text-center p-4">
      <div style="font-size:3rem;color:#dc3545;margin-bottom:16px;">⚠️</div>
      <h5>LINEログインエラー</h5>
      <p class="text-muted"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
      <a href="login.php" class="btn btn-outline-secondary">ログイン画面に戻る</a>
    </div>
  </div>
</div>
</body>
</html>
