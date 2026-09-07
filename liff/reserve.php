<?php
/**
 * liff/reserve.php — 説明会予約ページ（LIFF）
 * 初回アクセス時は登録フォーム（名前・学年・学部）→ 予約枠一覧の順に表示する。
 */
require_once __DIR__ . '/../common/db_connect.php';
$liff_id         = getConfig($pdo, 'liff_id') ?: '';
$circle_name_raw = getConfig($pdo, 'circle_name') ?: '';
$circle_name     = $circle_name_raw ?: '説明会予約';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($circle_name) ?> 予約</title>
<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<style>
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Hiragino Sans",sans-serif; background:#f7f8fa; color:#111827; }
  .header { background:#06c755; color:#fff; padding:16px; font-weight:700; text-align:center; }
  .container { padding:16px; max-width:480px; margin:0 auto; }
  .card { background:#fff; border-radius:12px; padding:16px; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
  label { font-size:12px; color:#6b7280; font-weight:700; display:block; margin-bottom:4px; }
  input[type=text], select { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; margin-bottom:12px; }
  .btn { display:block; width:100%; padding:13px; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; }
  .btn-primary { background:#06c755; color:#fff; }
  .btn-primary:disabled { opacity:.5; }
  .btn-outline { background:#fff; color:#dc2626; border:1px solid #dc2626; }
  .slot-title { font-weight:700; font-size:15px; margin-bottom:4px; }
  .slot-meta { font-size:12px; color:#6b7280; margin-bottom:10px; }
  .badge { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:8px; }
  .badge-full { background:#fee2e2; color:#dc2626; }
  .badge-reserved { background:#dcfce7; color:#15803d; }
  .error { color:#dc2626; font-size:12px; margin-top:-6px; margin-bottom:10px; }
  #loading { text-align:center; padding:60px 20px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header"><?= $h($circle_name) ?></div>
<div class="container">
  <div id="loading">読み込み中...</div>
  <div id="app" style="display:none;"></div>
</div>

<script>
const LIFF_ID = <?= json_encode($liff_id) ?>;
const CIRCLE_NAME = <?= json_encode($circle_name_raw) ?>;
const API_ROOT = '../api/';
let _lineId = '';
let _initData = null;

(async () => {
  try {
    if (!LIFF_ID) throw new Error('LIFF IDが設定されていません。管理画面から設定してください。');
    await liff.init({ liffId: LIFF_ID });
    if (!liff.isLoggedIn()) { liff.login({ redirectUri: location.href }); return; }
    const profile = await liff.getProfile();
    _lineId = profile.userId;
    await loadInit();
  } catch (e) {
    document.getElementById('loading').textContent = 'エラー: ' + e.message;
  }
})();

async function loadInit() {
  const res = await fetch(API_ROOT + 'reservations/init.php?line_id=' + encodeURIComponent(_lineId));
  const data = await res.json();
  if (!data.success) throw new Error(data.message || '読み込みに失敗しました');
  _initData = data;
  document.getElementById('loading').style.display = 'none';
  document.getElementById('app').style.display = 'block';
  render();
}

function render() {
  if (!_initData.registered) {
    renderRegisterForm();
  } else {
    renderSlots();
  }
}

function renderRegisterForm() {
  const gradeOpts = _initData.grades.map(g => `<option value="${g.id}">${escH(g.name)}</option>`).join('');
  const facOpts = _initData.faculties.map(f => `<option value="${f.id}">${escH(f.name)}</option>`).join('');
  const greeting = CIRCLE_NAME
    ? `<div class="slot-title" style="color:#06c755;">${escH(CIRCLE_NAME)}の説明会予約画面です！</div>`
    : '';
  document.getElementById('app').innerHTML = `
    <div class="card">
      ${greeting}
      <div class="slot-title">はじめに登録してください</div>
      <div class="slot-meta">予約の前に、簡単な情報の登録をお願いします。</div>
      <label>お名前</label>
      <input type="text" id="reg-name" placeholder="山田 太郎">
      <label>学年</label>
      <select id="reg-grade"><option value="">選択してください</option>${gradeOpts}</select>
      <label>学部</label>
      <select id="reg-faculty"><option value="">選択してください</option>${facOpts}</select>
      <div class="error" id="reg-error" style="display:none;"></div>
      <button class="btn btn-primary" id="reg-submit" onclick="submitRegister()">登録して予約に進む</button>
    </div>`;
}

async function submitRegister() {
  const name = document.getElementById('reg-name').value.trim();
  const errEl = document.getElementById('reg-error');
  if (!name) {
    errEl.textContent = 'お名前を入力してください。';
    errEl.style.display = 'block';
    return;
  }
  errEl.style.display = 'none';
  const btn = document.getElementById('reg-submit');
  btn.disabled = true; btn.textContent = '登録中...';

  const body = {
    line_id: _lineId,
    display_name: name,
    grade_id: document.getElementById('reg-grade').value || null,
    faculty_id: document.getElementById('reg-faculty').value || null,
  };
  try {
    const res = await fetch(API_ROOT + 'registrants/register.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || '登録に失敗しました');
    await loadInit();
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
    btn.disabled = false; btn.textContent = '登録して予約に進む';
  }
}

function renderSlots() {
  const slots = _initData.slots;
  if (!slots.length) {
    document.getElementById('app').innerHTML = `<div class="card"><div class="slot-meta">現在予約できる枠はありません。</div></div>`;
    return;
  }
  document.getElementById('app').innerHTML = slots.map(s => {
    const wd = ['日','月','火','水','木','金','土'][new Date(s.event_date + 'T00:00:00').getDay()];
    const dateLabel = s.event_date.replace(/-/g, '/') + `(${wd})`;
    let timeLabel = '';
    if (s.start_time) timeLabel = s.start_time.slice(0,5) + (s.end_time ? '〜' + s.end_time.slice(0,5) : '');
    const capLabel = s.capacity > 0 ? `${s.reserved_count}/${s.capacity}名` : `${s.reserved_count}名`;

    let action;
    if (s.my_reservation) {
      action = `<span class="badge badge-reserved">予約済み</span>
        <button class="btn btn-outline" style="margin-top:10px;" onclick="doReserve(${s.id}, 'cancel', this)">キャンセルする</button>`;
    } else if (s.is_full) {
      action = `<span class="badge badge-full">満席</span>`;
    } else {
      action = `<button class="btn btn-primary" onclick="doReserve(${s.id}, 'reserve', this)">予約する</button>`;
    }

    return `<div class="card">
      <div class="slot-title">${escH(s.title)}</div>
      <div class="slot-meta">${dateLabel}${timeLabel ? ' ' + timeLabel : ''}${s.location ? ' ・ ' + escH(s.location) : ''} ・ ${capLabel}</div>
      ${s.description ? `<div class="slot-meta">${escH(s.description)}</div>` : ''}
      ${action}
    </div>`;
  }).join('');
}

async function doReserve(slotId, action, btn) {
  if (action === 'cancel' && !confirm('この予約をキャンセルしますか？')) return;
  btn.disabled = true;
  try {
    const res = await fetch(API_ROOT + 'reservations/reserve.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ line_id: _lineId, slot_id: slotId, action }),
    });
    const data = await res.json();
    if (!data.success) { alert(data.message || '失敗しました'); btn.disabled = false; return; }
    await loadInit();
  } catch (e) {
    alert('通信エラーが発生しました');
    btn.disabled = false;
  }
}

function escH(s) {
  const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML;
}
</script>
</body>
</html>
