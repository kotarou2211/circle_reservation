<?php
/**
 * admin/parts/masters.php — 選択肢マスタ（学年・学部）管理タブ
 *
 * index.php から include されたとき（GET）は一覧を描画するだけ。
 * このファイルへ直接POSTされたときだけ保存処理を行い、index.phpへ戻す。
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';
requireAdminLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table = ($_POST['table'] ?? '') === 'faculties' ? 'faculties' : 'grades';
    $id         = (int)($_POST['id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $msg = 'ng:名前は必須です';
    } else {
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE {$table} SET name=?, sort_order=?, is_active=? WHERE id=?")
                    ->execute([$name, $sort_order, $is_active, $id]);
            } else {
                $pdo->prepare("INSERT INTO {$table} (name, sort_order, is_active, created_at) VALUES (?,?,?,NOW())")
                    ->execute([$name, $sort_order, $is_active]);
            }
        } catch (Throwable $e) {
            $msg = 'ng:' . $e->getMessage();
        }
    }
    header('Location: ../index.php?tab=masters' . ($msg ? '&msg=' . urlencode($msg) : ''));
    exit;
}

$msg = $_GET['msg'] ?? '';
$grades    = $pdo->query("SELECT * FROM grades ORDER BY sort_order, id")->fetchAll();
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY sort_order, id")->fetchAll();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$render_list = function(string $table, array $rows) use ($h) {
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<form method="post" action="masters.php" class="d-flex" style="display:table-row;">';
        echo '<input type="hidden" name="table" value="' . $h($table) . '">';
        echo '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
        echo '<td><input type="text" name="name" class="form-control form-control-sm" value="' . $h($r['name']) . '" required></td>';
        echo '<td style="width:100px;"><input type="number" name="sort_order" class="form-control form-control-sm" value="' . (int)$r['sort_order'] . '"></td>';
        echo '<td style="width:80px;text-align:center;"><input type="checkbox" name="is_active" ' . ($r['is_active'] ? 'checked' : '') . '></td>';
        echo '<td style="width:80px;"><button class="btn btn-outline-primary btn-sm">保存</button></td>';
        echo '</form>';
        echo '</tr>';
    }
    // 新規追加行
    echo '<tr>';
    echo '<form method="post" action="masters.php" style="display:table-row;">';
    echo '<input type="hidden" name="table" value="' . $h($table) . '">';
    echo '<td><input type="text" name="name" class="form-control form-control-sm" placeholder="新規追加" required></td>';
    echo '<td><input type="number" name="sort_order" class="form-control form-control-sm" value="' . (count($rows) + 1) . '"></td>';
    echo '<td style="text-align:center;"><input type="checkbox" name="is_active" checked></td>';
    echo '<td><button class="btn btn-success btn-sm">追加</button></td>';
    echo '</form>';
    echo '</tr>';
};
?>
<h5 class="fw-bold mb-3"><i class="bi bi-list-check text-success me-2"></i>選択肢マスタ</h5>
<?php if ($msg): $ok = !str_starts_with($msg, 'ng:'); ?>
  <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?> py-2"><?= $h($ok ? '保存しました' : substr($msg, 3)) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-white fw-bold" style="font-size:13px;">学年</div>
      <div class="card-body p-2">
        <table class="table table-sm mb-0"><tbody><?php $render_list('grades', $grades); ?></tbody></table>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-white fw-bold" style="font-size:13px;">学部</div>
      <div class="card-body p-2">
        <table class="table table-sm mb-0"><tbody><?php $render_list('faculties', $faculties); ?></tbody></table>
      </div>
    </div>
  </div>
</div>
