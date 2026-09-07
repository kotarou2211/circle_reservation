<?php
/**
 * admin/parts/admins.php — 管理者アカウント管理タブ（Super Adminのみ表示）
 */
?>
<h5 class="fw-bold mb-3"><i class="bi bi-person-badge text-success me-2"></i>管理者アカウント</h5>
<div id="admins-msg"></div>

<div class="card mb-3">
  <div class="card-header bg-white fw-bold" style="font-size:13px;">新しい管理者を追加</div>
  <div class="card-body">
    <form id="admin-create-form" class="row g-2 align-items-end" onsubmit="return submitAdminCreate(event)">
      <div class="col-md-3">
        <label class="form-label small text-muted mb-1">ユーザー名</label>
        <input type="text" name="username" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted mb-1">表示名</label>
        <input type="text" name="display_name" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">パスワード（8文字以上）</label>
        <input type="password" name="password" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">権限</label>
        <select name="role" class="form-select form-select-sm">
          <option value="admin">admin</option>
          <option value="super_admin">super_admin</option>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>追加</button>
      </div>
    </form>
  </div>
</div>

<table class="table table-sm bg-white">
  <thead><tr><th>ユーザー名</th><th>表示名</th><th>権限</th><th>状態</th><th>最終ログイン</th><th></th></tr></thead>
  <tbody id="admins-tbody"><tr><td colspan="6" class="text-center text-muted py-3">読み込み中...</td></tr></tbody>
</table>

<script>
async function loadAdmins() {
  const res = await fetch('../api/system/admin_accounts.php?action=list');
  const data = await res.json();
  const tb = document.getElementById('admins-tbody');
  if (!data.success) { tb.innerHTML = '<tr><td colspan="6" class="text-danger">読み込み失敗</td></tr>'; return; }
  if (!data.admins.length) { tb.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">まだ登録がありません</td></tr>'; return; }
  tb.innerHTML = data.admins.map(a => `
    <tr>
      <td>${escAdm(a.username)}</td>
      <td>${escAdm(a.display_name)}</td>
      <td><span class="badge ${a.role === 'super_admin' ? 'bg-danger' : 'bg-secondary'}">${a.role}</span></td>
      <td>${a.is_active ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>'}</td>
      <td class="text-muted" style="font-size:12px;">${a.last_login_at || '—'}</td>
      <td>
        <button class="btn btn-outline-secondary btn-sm" onclick="toggleAdminActive(${a.id}, ${a.is_active ? 0 : 1})">
          ${a.is_active ? '無効化' : '有効化'}
        </button>
      </td>
    </tr>`).join('');
}

function escAdm(s) {
  const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML;
}

async function submitAdminCreate(ev) {
  ev.preventDefault();
  const f = ev.target;
  const body = {
    action: 'create',
    username: f.username.value.trim(),
    display_name: f.display_name.value.trim(),
    password: f.password.value,
    role: f.role.value,
  };
  const data = await apiFetch('../api/system/admin_accounts.php', body);
  const msg = document.getElementById('admins-msg');
  msg.innerHTML = `<div class="alert ${data.success ? 'alert-success' : 'alert-danger'} py-2">${escAdm(data.message)}</div>`;
  if (data.success) { f.reset(); loadAdmins(); }
  return false;
}

async function toggleAdminActive(id, active) {
  const data = await apiFetch('../api/system/admin_accounts.php', { action: 'toggle_active', id, active });
  if (data.success) loadAdmins();
  else alert(data.message || '失敗しました');
}

loadAdmins();
</script>
