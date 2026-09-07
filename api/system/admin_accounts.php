<?php
/**
 * api/system/admin_accounts.php — 管理者アカウント管理（Super Admin専用）
 *
 * GET  ?action=list                         → 管理者一覧
 * POST { action:'create', username, display_name, password }
 * POST { action:'update', id, username, display_name, password? }   password空なら変更しない
 * POST { action:'toggle_active', id, active }
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';

header('Content-Type: application/json; charset=utf-8');

$admin = getAdminInfo();
if (!$admin) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'認証エラー']); exit; }
if (($admin['role'] ?? 'admin') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Super Admin権限が必要です']); exit;
}

// ── 一覧 ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rows = $pdo->query("
            SELECT id, username, display_name, role, is_active,
                   (line_user_id IS NOT NULL AND TRIM(line_user_id) <> '') AS has_line,
                   last_login_at
            FROM admin_users
            ORDER BY (role='super_admin') DESC, id ASC
        ")->fetchAll();
        foreach ($rows as &$r) {
            $r['id']        = (int)$r['id'];
            $r['is_active'] = (int)$r['is_active'];
            $r['has_line']  = (int)$r['has_line'];
        }
        unset($r);
        echo json_encode(['success'=>true, 'admins'=>$rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }
    exit;
}

// ── 更新系 ────────────────────────────────────────────────
$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = trim($data['action'] ?? '');

try {
    if ($action === 'create') {
        $username = trim($data['username'] ?? '');
        $display  = trim($data['display_name'] ?? '');
        $password = (string)($data['password'] ?? '');
        $role     = ($data['role'] ?? 'admin') === 'super_admin' ? 'super_admin' : 'admin';
        if ($username === '' || !preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
            echo json_encode(['success'=>false,'message'=>'ユーザー名は半角英数字3〜50文字で入力してください']); exit;
        }
        if (strlen($password) < 8) {
            echo json_encode(['success'=>false,'message'=>'パスワードは8文字以上にしてください']); exit;
        }
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE username=? LIMIT 1");
        $chk->execute([$username]);
        if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'そのユーザー名は既に使われています']); exit; }

        $pdo->prepare("INSERT INTO admin_users (username, password_hash, display_name, role, is_active, created_at)
                       VALUES (?, ?, ?, ?, 1, NOW())")
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $display ?: $username, $role]);
        echo json_encode(['success'=>true, 'message'=>'管理者を追加しました']);
        exit;
    }

    if ($action === 'update') {
        $id       = (int)($data['id'] ?? 0);
        $username = trim($data['username'] ?? '');
        $display  = trim($data['display_name'] ?? '');
        $password = (string)($data['password'] ?? '');
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'IDが不正です']); exit; }
        if ($username === '' || !preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
            echo json_encode(['success'=>false,'message'=>'ユーザー名は半角英数字3〜50文字で入力してください']); exit;
        }
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE username=? AND id<>? LIMIT 1");
        $chk->execute([$username, $id]);
        if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'そのユーザー名は既に使われています']); exit; }

        if ($password !== '') {
            if (strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'パスワードは8文字以上にしてください']); exit; }
            $pdo->prepare("UPDATE admin_users SET username=?, display_name=?, password_hash=? WHERE id=?")
                ->execute([$username, $display ?: $username, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $pdo->prepare("UPDATE admin_users SET username=?, display_name=? WHERE id=?")
                ->execute([$username, $display ?: $username, $id]);
        }
        echo json_encode(['success'=>true, 'message'=>'管理者情報を更新しました']);
        exit;
    }

    if ($action === 'toggle_active') {
        $id     = (int)($data['id'] ?? 0);
        $active = empty($data['active']) ? 0 : 1;
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'IDが不正です']); exit; }
        if ($id === (int)$admin['id'] && !$active) {
            echo json_encode(['success'=>false,'message'=>'自分自身は無効化できません']); exit;
        }
        $pdo->prepare("UPDATE admin_users SET is_active=? WHERE id=?")->execute([$active, $id]);
        echo json_encode(['success'=>true, 'message'=>$active ? '有効にしました' : '無効にしました']);
        exit;
    }

    echo json_encode(['success'=>false, 'message'=>'不明なアクション']);
} catch (Throwable $e) {
    error_log('[admin_accounts] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
