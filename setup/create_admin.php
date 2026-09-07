<?php
/**
 * setup/create_admin.php — 最初の管理者ユーザー作成
 * 初回セットアップ後に1度だけ使用してください。使用後はサーバーから削除してください。
 *
 * URL: https://ドメイン/reservation/setup/create_admin.php?secret=<INSTALL_SECRET>
 */
define('INSTALL_SECRET', 'REPLACE_ME_BEFORE_USE');  // ← install.php と同じ値に変更する

$secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($secret !== INSTALL_SECRET || INSTALL_SECRET === 'REPLACE_ME_BEFORE_USE') {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>');
}

require_once __DIR__ . '/../common/db_connect.php';

$result = '';
$step   = $_POST['step'] ?? 'form';

if ($step === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $name     = trim($_POST['display_name'] ?? '');
    $role     = $_POST['role'] === 'admin' ? 'admin' : 'super_admin';

    if (empty($username) || empty($password)) {
        $result = '<div class="alert alert-danger">ユーザー名とパスワードを入力してください。</div>';
    } elseif (strlen($password) < 8) {
        $result = '<div class="alert alert-danger">パスワードは8文字以上にしてください。</div>';
    } elseif ($password !== $confirm) {
        $result = '<div class="alert alert-danger">パスワードが一致しません。</div>';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("
                INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ")->execute([$username, $hash, $name ?: $username, $role]);
            $result = '<div class="alert alert-success">
                <strong>✅ 管理者を作成しました。</strong><br>
                ユーザー名: <code>' . htmlspecialchars($username) . '</code><br>
                <strong>⚠️ このファイル (create_admin.php) をサーバーから削除してください。</strong>
            </div>';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $result = '<div class="alert alert-danger">このユーザー名は既に使用されています。</div>';
            } else {
                $result = '<div class="alert alert-danger">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理者作成</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body style="background:#f0f2f5;">
<div style="max-width:440px;margin:60px auto;">
  <div class="card shadow-sm">
    <div class="card-header" style="background:#06c755;color:#fff;">
      <h5 class="mb-0">管理者ユーザー作成</h5>
    </div>
    <div class="card-body p-4">
      <?= $result ?>
      <form method="post">
        <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
        <input type="hidden" name="step" value="create">
        <div class="mb-3">
          <label class="form-label fw-bold">ユーザー名 <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username']??'') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">表示名</label>
          <input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($_POST['display_name']??'') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">パスワード <span class="text-danger">*</span>（8文字以上）</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold">パスワード確認 <span class="text-danger">*</span></label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold">権限</label>
          <select name="role" class="form-select">
            <option value="super_admin">super_admin（全権限）</option>
            <option value="admin">admin（一般管理）</option>
          </select>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-success">作成する</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
