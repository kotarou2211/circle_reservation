<?php
require_once __DIR__ . '/../common/db_connect.php';
require_once __DIR__ . '/../common/auth.php';

// 既にログイン済みならダッシュボードへ
$admin = getAdminInfo();
if ($admin) {
    header('Location: index.php');
    exit;
}

$error   = '';
$timeout = !empty($_GET['timeout']);

// LINEでログインURL生成
if (session_status() === PHP_SESSION_NONE) session_start();
$line_login_channel_id = getConfig($pdo, 'line_login_channel_id') ?: '';
$line_login_url = '';
if ($line_login_channel_id) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['line_oauth_state'] = $state;
    $_SESSION['line_oauth_next']  = filter_var($_GET['next'] ?? '', FILTER_SANITIZE_URL);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirect_uri = urlencode($scheme . '://' . $_SERVER['HTTP_HOST']
        . str_replace('/login.php', '', $_SERVER['SCRIPT_NAME'])
        . '/line_callback.php');
    $line_login_url = 'https://access.line.me/oauth2/v2.1/authorize?response_type=code'
        . '&client_id=' . urlencode($line_login_channel_id)
        . '&redirect_uri=' . $redirect_uri
        . '&state=' . $state
        . '&scope=profile';
}

// POST: ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginAdmin($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        $next = filter_var($_GET['next'] ?? '', FILTER_SANITIZE_URL);
        header('Location: ' . ($next ?: 'index.php'));
        exit;
    }
    $error = $result['message'];
}

$circle_name = getConfig($pdo, 'circle_name') ?: '説明会予約ツール';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログイン — <?= h($circle_name) ?>管理画面</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
body { background: #f0f2f5; }
.login-card { max-width: 400px; margin: 80px auto; }
.login-card .card-header { background: #06c755; color: #fff; text-align: center; padding: 24px; }
.login-card .card-header .logo { font-size: 2rem; }
</style>
</head>
<body>
<div class="login-card">
  <div class="card shadow-sm">
    <div class="card-header">
      <div class="logo"><i class="bi bi-calendar-event"></i></div>
      <h5 class="mb-0 mt-2"><?= h($circle_name) ?> 管理画面</h5>
    </div>
    <div class="card-body p-4">

      <?php if ($timeout): ?>
        <div class="alert alert-warning small">セッションの有効期限が切れました。再度ログインしてください。</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger small"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($line_login_url): ?>
      <div class="d-grid mb-3">
        <a href="<?= h($line_login_url) ?>" class="btn btn-lg"
           style="background:#06c755;color:#fff;font-weight:700;border:none;">
          <i class="bi bi-line me-2" style="font-size:1.2em;"></i>LINEでログイン
        </a>
      </div>
      <div class="d-flex align-items-center mb-3 text-muted" style="font-size:12px;">
        <hr style="flex:1;"><span class="mx-2">または</span><hr style="flex:1;">
      </div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-bold">ユーザー名</label>
          <input type="text" name="username" class="form-control"
                 value="<?= h($_POST['username'] ?? '') ?>"
                 autocomplete="username" <?= $line_login_url ? '' : 'autofocus' ?> required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold">パスワード</label>
          <input type="password" name="password" class="form-control"
                 autocomplete="current-password" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i>ID・パスワードでログイン
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
</body>
</html>
<?php
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
