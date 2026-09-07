<?php
/**
 * common/auth.php — 管理画面セッション管理
 */

define('ADMIN_SESSION_LIFETIME', 7200); // 2時間

function adminSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $name = defined('ADMIN_SESSION_NAME') ? ADMIN_SESSION_NAME : 'circle_reservation_admin';
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * ログイン必須チェック。未ログインならリダイレクト。
 * @return array ['id', 'name', 'role']
 */
function requireAdminLogin(string $redirect = ''): array {
    adminSessionStart();

    if (empty($redirect)) {
        $redirect = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/login.php';
        if (str_contains($redirect, '/admin') === false) {
            $redirect = '/admin/login.php';
        }
    }

    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . $redirect . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
    if ((time() - ($_SESSION['admin_last_active'] ?? 0)) > ADMIN_SESSION_LIFETIME) {
        session_unset(); session_destroy();
        header('Location: ' . $redirect . '?timeout=1');
        exit;
    }
    $_SESSION['admin_last_active'] = time();
    return [
        'id'   => (int)$_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

/**
 * Super Admin専用ページのガード。
 * まずログインを確認し、role が super_admin でなければ 403 で終了する。
 *
 * @return array ログイン中のSuper Admin情報
 */
function requireSuperAdmin(string $redirect = ''): array {
    $admin = requireAdminLogin($redirect);
    if (($admin['role'] ?? 'admin') !== 'super_admin') {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8">'
            . '<div style="font-family:-apple-system,sans-serif;max-width:420px;margin:80px auto;text-align:center;color:#374151;">'
            . '<div style="font-size:42px;">🔒</div>'
            . '<h2 style="font-size:18px;">アクセス権限がありません</h2>'
            . '<p style="color:#6b7280;font-size:14px;">このページは <strong>Super Admin</strong> 専用です。</p>'
            . '<a href="index.php" style="color:#2563eb;">管理画面に戻る</a></div>');
    }
    return $admin;
}

/**
 * CSRFトークンを取得（セッションに無ければ生成）
 */
function adminCsrfToken(): string {
    adminSessionStart();
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

/**
 * 現在のログイン情報を取得（未ログインは null）
 *
 * GET以外のリクエストでは X-CSRF-Token ヘッダーを検証する。
 * @param bool $skip_csrf trueでCSRF検証を行わない（ログイン有無の参照のみの用途）
 */
function getAdminInfo(bool $skip_csrf = false): ?array {
    adminSessionStart();
    if (empty($_SESSION['admin_id'])) return null;

    if (!$skip_csrf && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $real = $_SESSION['admin_csrf_token'] ?? '';
        if ($real === '' || $sent === '' || !hash_equals($real, $sent)) {
            error_log('[auth] CSRFトークン不一致: ' . ($_SERVER['REQUEST_URI'] ?? ''));
            return null;
        }
    }

    return [
        'id'   => (int)$_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

// ブルートフォース対策の閾値
define('ADMIN_LOGIN_MAX_FAILS', 5);     // 失敗回数の上限
define('ADMIN_LOGIN_LOCK_MIN', 10);     // 監視・ロック時間（分）

/**
 * ログイン処理（ブルートフォース対策つき）
 */
function loginAdmin(PDO $pdo, string $username, string $password): array {
    if (trim($username) === '' || trim($password) === '') {
        return ['success' => false, 'message' => 'ユーザー名とパスワードを入力してください。'];
    }
    $username = trim($username);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // ── 失敗回数チェック ──────────────────────────────
    try {
        $chk = $pdo->prepare("
            SELECT COUNT(*) FROM admin_login_attempts
            WHERE (username = ? OR ip_address = ?)
              AND attempted_at > DATE_SUB(NOW(), INTERVAL " . ADMIN_LOGIN_LOCK_MIN . " MINUTE)
        ");
        $chk->execute([$username, $ip]);
        if ((int)$chk->fetchColumn() >= ADMIN_LOGIN_MAX_FAILS) {
            error_log("[auth] ログインロック中: user={$username} ip={$ip}");
            return ['success' => false, 'message' => 'ログイン失敗が続いたため、一時的にロックされています。' . ADMIN_LOGIN_LOCK_MIN . '分後に再度お試しください。'];
        }
    } catch (Throwable $e) {
        error_log('[auth] login_attempts チェック失敗: ' . $e->getMessage());
    }

    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, display_name, role
         FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        try {
            $pdo->prepare("INSERT INTO admin_login_attempts (username, ip_address) VALUES (?, ?)")
                ->execute([$username, $ip]);
            $pdo->exec("DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        } catch (Throwable $e) {}
        return ['success' => false, 'message' => 'ユーザー名またはパスワードが正しくありません。'];
    }

    try {
        $pdo->prepare("DELETE FROM admin_login_attempts WHERE username = ? OR ip_address = ?")
            ->execute([$username, $ip]);
    } catch (Throwable $e) {}

    adminSessionStart();
    session_regenerate_id(true);
    $_SESSION['admin_id']          = $user['id'];
    $_SESSION['admin_name']        = $user['display_name'] ?: $user['username'];
    $_SESSION['admin_role']        = $user['role'];
    $_SESSION['admin_last_active'] = time();

    $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
    return ['success' => true];
}

/**
 * LINE IDでログイン（LINE OAuth コールバック後）
 */
function loginAdminByLine(PDO $pdo, string $line_user_id): array {
    if (!preg_match('/^U[0-9a-fA-F]{32}$/', $line_user_id)) {
        return ['success' => false, 'message' => '不正なLINE IDです。'];
    }

    $stmt = $pdo->prepare(
        "SELECT id, username, display_name, role
         FROM admin_users WHERE line_user_id = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$line_user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'このLINEアカウントは管理者として登録されていません。'];
    }

    adminSessionStart();
    session_regenerate_id(true);
    $_SESSION['admin_id']          = $user['id'];
    $_SESSION['admin_name']        = $user['display_name'] ?: $user['username'];
    $_SESSION['admin_role']        = $user['role'];
    $_SESSION['admin_last_active'] = time();

    $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
    return ['success' => true];
}

/**
 * ログアウト処理
 */
function logoutAdmin(): void {
    adminSessionStart();
    session_unset();
    session_destroy();
}
