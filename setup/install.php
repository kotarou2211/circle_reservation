<?php
/**
 * install.php
 * 説明会予約ツール — セットアップスクリプト
 *
 * 使い方:
 *   1. このファイルをサーバーにアップロード（setup/install.php）
 *   2. 下の INSTALL_SECRET を必ず自分だけの値に変更する
 *   3. ブラウザで https://ドメイン/setup/install.php?secret=<INSTALL_SECRET> にアクセス
 *   4. 画面の指示に従ってDBを選択し「実行」
 *   5. 完了したらこのファイルを削除（またはリネーム）
 *
 * セキュリティ:
 *   INSTALL_SECRET を変更せずに公開サーバーへ置かないこと。
 *   実行後は必ずこのファイルをサーバーから削除してください。
 */

// ── 設定 ──────────────────────────────────────────────────
define('INSTALL_SECRET', 'REPLACE_ME_BEFORE_USE');   // ← 必ず変更する
define('SCHEMA_FILE',    __DIR__ . '/schema.sql');

// ── 認証 ──────────────────────────────────────────────────
$secret = $_GET['secret'] ?? $_POST['secret'] ?? '';
if ($secret !== INSTALL_SECRET || INSTALL_SECRET === 'REPLACE_ME_BEFORE_USE') {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>INSTALL_SECRET を変更してから、URLに ?secret=your_secret を付けてアクセスしてください。</p>');
}

// ── DB接続情報（フォーム送信時 or POST） ─────────────────
$db_host    = trim($_POST['db_host']    ?? '');
$db_name    = trim($_POST['db_name']    ?? '');
$db_user    = trim($_POST['db_user']    ?? '');
$db_pass    = trim($_POST['db_pass']    ?? '');
$db_charset = 'utf8mb4';

$result_html = '';
$step        = $_POST['step'] ?? 'form';

// ── 実行処理 ──────────────────────────────────────────────
if ($step === 'execute' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($db_name) || empty($db_user)) {
        $result_html = error_box('DBホスト・DB名・ユーザー名は必須です。');
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            $sql_raw    = file_get_contents(SCHEMA_FILE);
            $statements = split_sql($sql_raw);

            $ok_count   = 0;
            $skip_count = 0;
            $details    = [];

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                try {
                    $pdo->exec($stmt);
                    $details[] = ['ok', get_stmt_label($stmt)];
                    $ok_count++;
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (
                        strpos($msg, 'already exists') !== false ||
                        strpos($msg, 'Duplicate') !== false ||
                        strpos($msg, 'errno: 1007') !== false
                    ) {
                        $details[] = ['skip', get_stmt_label($stmt) . ' (既存のためスキップ)'];
                        $skip_count++;
                    } else {
                        $details[] = ['error', htmlspecialchars(substr($stmt, 0, 80)) . '… → ' . htmlspecialchars($msg)];
                    }
                }
            }

            $error_lines = array_filter($details, fn($d) => $d[0] === 'error');
            $result_html = build_result_table($details, $ok_count, $skip_count, count($error_lines));

        } catch (PDOException $e) {
            $result_html = error_box('DB接続に失敗しました: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

// ── ヘルパー関数 ──────────────────────────────────────────

function split_sql(string $sql): array {
    $lines   = explode("\n", $sql);
    $stmts   = [];
    $current = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) continue;
        $current .= ' ' . $line;
        if (str_ends_with($trimmed, ';')) {
            $stmts[] = trim($current);
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $stmts[] = trim($current);
    }
    return $stmts;
}

function get_stmt_label(string $stmt): string {
    if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $stmt, $m))
        return 'CREATE TABLE ' . $m[1];
    if (preg_match('/INSERT IGNORE INTO\s+`?(\w+)`?/i', $stmt, $m))
        return 'INSERT IGNORE INTO ' . $m[1];
    if (preg_match('/^(SET|ALTER|UPDATE|DROP)\b/i', $stmt, $m))
        return strtoupper($m[1]) . ' …';
    return substr(preg_replace('/\s+/', ' ', $stmt), 0, 60);
}

function error_box(string $msg): string {
    return '<div class="alert alert-danger"><strong>エラー:</strong> ' . $msg . '</div>';
}

function build_result_table(array $details, int $ok, int $skip, int $err): string {
    $summary_class = ($err > 0) ? 'alert-warning' : 'alert-success';
    $summary_icon  = ($err > 0) ? '⚠️' : '✅';
    $html  = "<div class='alert {$summary_class}'>";
    $html .= "{$summary_icon} 完了: <strong>{$ok}</strong> 件成功 / {$skip} 件スキップ";
    if ($err > 0) $html .= " / <strong class='text-danger'>{$err} 件エラー</strong>";
    $html .= "</div>";

    $html .= "<table class='table table-sm table-bordered mt-3'><thead class='table-dark'><tr><th>結果</th><th>内容</th></tr></thead><tbody>";
    foreach ($details as [$status, $label]) {
        $badge = match($status) {
            'ok'    => "<span class='badge bg-success'>OK</span>",
            'skip'  => "<span class='badge bg-secondary'>SKIP</span>",
            'error' => "<span class='badge bg-danger'>ERROR</span>",
            default => $status,
        };
        $html .= "<tr><td style='white-space:nowrap'>{$badge}</td><td><code>" . htmlspecialchars($label) . "</code></td></tr>";
    }
    $html .= "</tbody></table>";

    if ($err === 0) {
        $html .= "<div class='alert alert-warning mt-3'>
            <strong>⚠️ 重要:</strong> セットアップ完了後、このファイル (<code>setup/install.php</code>) を
            サーバーから必ず削除してください。
        </div>";
    }
    return $html;
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>説明会予約ツール — セットアップ</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
body { background:#f0f2f5; }
.setup-card { max-width:640px; margin:60px auto; }
.setup-card .card-header { background:#06c755; color:#fff; }
pre { background:#1e1e1e; color:#d4d4d4; padding:12px; border-radius:6px; font-size:12px; overflow-x:auto; }
</style>
</head>
<body>
<div class="setup-card">
  <div class="card shadow">
    <div class="card-header py-3">
      <h5 class="mb-0">📅 説明会予約ツール — DB セットアップ</h5>
    </div>
    <div class="card-body">

      <?php if ($result_html): ?>
        <h6 class="fw-bold mb-3">実行結果</h6>
        <?= $result_html ?>
        <hr>
        <a href="?secret=<?= htmlspecialchars($secret) ?>" class="btn btn-outline-secondary btn-sm">← 戻る</a>
      <?php else: ?>

        <p class="text-muted small mb-4">
          以下にDB接続情報を入力してください。<br>
          スキーマSQL (<code>setup/schema.sql</code>) を読み込んで全テーブルを作成します。
        </p>

        <form method="post">
          <input type="hidden" name="secret" value="<?= htmlspecialchars($secret) ?>">
          <input type="hidden" name="step"   value="execute">

          <div class="mb-3">
            <label class="form-label fw-bold">DBホスト <span class="text-danger">*</span></label>
            <input type="text" name="db_host" class="form-control"
                   value="<?= htmlspecialchars($db_host) ?>"
                   placeholder="例: localhost" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">DB名 <span class="text-danger">*</span></label>
            <input type="text" name="db_name" class="form-control"
                   value="<?= htmlspecialchars($db_name) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">DBユーザー名 <span class="text-danger">*</span></label>
            <input type="text" name="db_user" class="form-control"
                   value="<?= htmlspecialchars($db_user) ?>" required>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">DBパスワード</label>
            <input type="password" name="db_pass" class="form-control">
          </div>

          <div class="alert alert-warning small">
            <strong>⚠️ 注意:</strong>
            既存のテーブルは <code>CREATE TABLE IF NOT EXISTS</code> で保護されているため上書きされません。
            ただし <strong>完全に新規のDB</strong> に対して実行することを推奨します。
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-success btn-lg">
              ▶ テーブル作成を実行
            </button>
          </div>
        </form>

      <?php endif; ?>

    </div>
  </div>
</div>
</body>
</html>
