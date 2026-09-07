<?php
/**
 * admin/index.php — 管理画面ダッシュボード（最小構成）
 * 予約枠管理／予約一覧／選択肢マスタ／LINE設定／管理者アカウントの5タブ切替。
 */
require_once __DIR__ . '/../common/db_connect.php';
require_once __DIR__ . '/../common/auth.php';

$admin = requireAdminLogin();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf_token = adminCsrfToken();
$circle_name = getConfig($pdo, 'circle_name') ?: '説明会予約ツール';

$tabs = [
    'slots'        => ['icon' => 'bi-calendar-week',  'label' => '予約枠管理'],
    'reservations' => ['icon' => 'bi-people',          'label' => '予約一覧'],
    'masters'      => ['icon' => 'bi-list-check',      'label' => '選択肢マスタ'],
    'line'         => ['icon' => 'bi-line',            'label' => 'LINE設定'],
];
if (($admin['role'] ?? 'admin') === 'super_admin') {
    $tabs['admins'] = ['icon' => 'bi-person-badge', 'label' => '管理者アカウント'];
}

$active_tab = $_GET['tab'] ?? '';
if (!isset($tabs[$active_tab])) $active_tab = array_key_first($tabs);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($circle_name) ?> 管理画面</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
  body { background:#f1f5f9; }
  .topbar { background:#06c755; color:#fff; padding:12px 20px; display:flex; align-items:center; gap:12px; }
  .topbar .title { font-weight:700; flex:1; }
  .tabnav { background:#fff; border-bottom:1px solid #e2e8f0; padding:0 12px; display:flex; gap:4px; overflow-x:auto; }
  .tabnav button { border:none; background:none; padding:12px 16px; font-size:13px; color:#64748b; white-space:nowrap; cursor:pointer; border-bottom:3px solid transparent; }
  .tabnav button.active { color:#06c755; border-bottom-color:#06c755; font-weight:700; }
  .tabpane { display:none; padding:20px; max-width:1000px; margin:0 auto; }
  .tabpane.active { display:block; }
</style>
</head>
<body>
<div class="topbar">
  <i class="bi bi-calendar-event" style="font-size:20px;"></i>
  <span class="title"><?= $h($circle_name) ?> 管理画面</span>
  <span style="font-size:12px;opacity:.9;"><?= $h($admin['name']) ?></span>
  <a href="logout.php" style="color:#fff;font-size:13px;">ログアウト</a>
</div>

<div class="tabnav">
  <?php foreach ($tabs as $key => $t): ?>
  <button class="tab-btn<?= $key === $active_tab ? ' active' : '' ?>" data-tab="<?= $key ?>" onclick="switchTab('<?= $key ?>')">
    <i class="bi <?= $t['icon'] ?> me-1"></i><?= $h($t['label']) ?>
  </button>
  <?php endforeach; ?>
</div>

<?php foreach ($tabs as $key => $t): ?>
<div class="tabpane<?= $key === $active_tab ? ' active' : '' ?>" id="pane-<?= $key ?>">
  <?php include __DIR__ . '/parts/' . $key . '.php'; ?>
</div>
<?php endforeach; ?>

<script>
const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;
async function apiFetch(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify(body || {}),
  });
  return res.json();
}
function switchTab(key) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
  document.querySelectorAll('.tabpane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + key));
}
</script>
</body>
</html>
