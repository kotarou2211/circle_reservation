<?php
/**
 * admin/parts/line_settings.php — LINE設定タブ
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';
requireAdminLogin();

$KEYS = [
    'circle_name', 'liff_id',
    'line_channel_id', 'line_channel_secret', 'line_channel_access_token',
    'line_login_channel_id', 'line_login_channel_secret',
    'auto_notify_reminder',
];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        foreach ($KEYS as $key) {
            if ($key === 'auto_notify_reminder') {
                $stmt->execute([$key, isset($_POST[$key]) ? '1' : '0']);
                continue;
            }
            $stmt->execute([$key, trim($_POST[$key] ?? '')]);
        }
    } catch (Throwable $e) {
        $msg = 'ng:' . $e->getMessage();
    }
    header('Location: ../index.php?tab=line' . ($msg ? '&msg=' . urlencode($msg) : '&msg=ok'));
    exit;
}

$msg = $_GET['msg'] ?? '';
$h   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$cfg = getConfig($pdo);
$v   = fn($k) => $h($cfg[$k] ?? '');

$webhook_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/api/line/webhook.php';
$callback_url = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/admin/line_callback.php';
?>
<h5 class="fw-bold mb-3"><i class="bi bi-line text-success me-2"></i>LINE設定</h5>
<?php if ($msg === 'ok'): ?>
  <div class="alert alert-success py-2">保存しました</div>
<?php elseif (str_starts_with($msg, 'ng:')): ?>
  <div class="alert alert-danger py-2"><?= $h(substr($msg, 3)) ?></div>
<?php endif; ?>

<form method="post" action="line_settings.php">
  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">基本設定</div>
    <div class="card-body">
      <label class="form-label small text-muted mb-1">サークル・団体名</label>
      <input type="text" name="circle_name" class="form-control form-control-sm" value="<?= $v('circle_name') ?>">
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">LINE Messaging API</div>
    <div class="card-body">
      <p class="text-muted small">LINE Developers Console のチャネル設定ページで確認できます。</p>
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">チャネルID</label>
        <input type="text" name="line_channel_id" class="form-control form-control-sm" value="<?= $v('line_channel_id') ?>">
      </div>
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">チャネルシークレット</label>
        <input type="text" name="line_channel_secret" class="form-control form-control-sm" value="<?= $v('line_channel_secret') ?>">
      </div>
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">チャネルアクセストークン（長期）</label>
        <input type="text" name="line_channel_access_token" class="form-control form-control-sm" value="<?= $v('line_channel_access_token') ?>">
      </div>
      <div class="mb-0">
        <label class="form-label small text-muted mb-1">Webhook URL（読み取り専用・LINE Developersに登録）</label>
        <input type="text" class="form-control form-control-sm" value="<?= $h($webhook_url) ?>" readonly>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">LIFF設定</div>
    <div class="card-body">
      <label class="form-label small text-muted mb-1">LIFF ID</label>
      <input type="text" name="liff_id" class="form-control form-control-sm" value="<?= $v('liff_id') ?>">
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">LINEログイン（管理者認証用・任意）</div>
    <div class="card-body">
      <p class="text-muted small">
        設定すると管理画面ログイン画面に「LINEでログイン」ボタンが出ます。コールバックURL:
        <code><?= $h($callback_url) ?></code>
      </p>
      <div class="mb-2">
        <label class="form-label small text-muted mb-1">LINEログイン チャネルID</label>
        <input type="text" name="line_login_channel_id" class="form-control form-control-sm" value="<?= $v('line_login_channel_id') ?>">
      </div>
      <div class="mb-0">
        <label class="form-label small text-muted mb-1">LINEログイン チャネルシークレット</label>
        <input type="text" name="line_login_channel_secret" class="form-control form-control-sm" value="<?= $v('line_login_channel_secret') ?>">
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">メッセージ配信</div>
    <div class="card-body">
      <div class="form-check">
        <input type="checkbox" name="auto_notify_reminder" class="form-check-input" <?= ($cfg['auto_notify_reminder'] ?? '1') === '1' ? 'checked' : '' ?>>
        <label class="form-check-label small">前日リマインダーを自動送信する</label>
      </div>
    </div>
  </div>

  <button class="btn btn-success"><i class="bi bi-floppy me-1"></i>保存</button>
</form>
