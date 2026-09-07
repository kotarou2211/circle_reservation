<?php
/**
 * admin/parts/slots.php — 予約枠管理タブ
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';
requireAdminLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $event_date  = trim($_POST['event_date'] ?? '');
        $start_time  = trim($_POST['start_time'] ?? '') ?: null;
        $end_time    = trim($_POST['end_time'] ?? '') ?: null;
        $location    = trim($_POST['location'] ?? '') ?: null;
        $capacity    = (int)($_POST['capacity'] ?? 0);
        $description = trim($_POST['description'] ?? '') ?: null;
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $event_date === '') {
            $msg = 'ng:タイトルと候補日は必須です';
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE event_slots SET title=?, event_date=?, start_time=?, end_time=?,
                               location=?, capacity=?, description=?, is_active=?
                        WHERE id=?
                    ")->execute([$title, $event_date, $start_time, $end_time, $location, $capacity, $description, $is_active, $id]);
                } else {
                    $pdo->prepare("
                        INSERT INTO event_slots (title, event_date, start_time, end_time, location, capacity, description, is_active, created_at)
                        VALUES (?,?,?,?,?,?,?,?,NOW())
                    ")->execute([$title, $event_date, $start_time, $end_time, $location, $capacity, $description, $is_active]);
                }
            } catch (Throwable $e) {
                $msg = 'ng:' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM reservations WHERE slot_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM event_slots WHERE id=?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'ng:' . $e->getMessage();
        }
    }

    header('Location: ../index.php?tab=slots' . ($msg ? '&msg=' . urlencode($msg) : ''));
    exit;
}

$msg = $_GET['msg'] ?? '';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$slots = $pdo->query("
    SELECT es.*, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = es.id) AS reserved_count
    FROM event_slots es ORDER BY es.event_date DESC, es.id DESC
")->fetchAll();
?>
<h5 class="fw-bold mb-3"><i class="bi bi-calendar-week text-success me-2"></i>予約枠管理</h5>
<?php if ($msg): $ok = !str_starts_with($msg, 'ng:'); ?>
  <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?> py-2"><?= $h($ok ? '保存しました' : substr($msg, 3)) ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header bg-white fw-bold" style="font-size:13px;">新しい予約枠を追加</div>
  <div class="card-body">
    <form method="post" action="slots.php" class="row g-2">
      <input type="hidden" name="action" value="save">
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">タイトル</label>
        <input type="text" name="title" class="form-control form-control-sm" placeholder="例: 説明会 第1回" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">候補日</label>
        <input type="date" name="event_date" class="form-control form-control-sm" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">開始時刻</label>
        <input type="time" name="start_time" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">終了時刻</label>
        <input type="time" name="end_time" class="form-control form-control-sm">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">定員（0=無制限）</label>
        <input type="number" name="capacity" class="form-control form-control-sm" value="0" min="0">
      </div>
      <div class="col-md-4">
        <label class="form-label small text-muted mb-1">場所</label>
        <input type="text" name="location" class="form-control form-control-sm">
      </div>
      <div class="col-md-6">
        <label class="form-label small text-muted mb-1">説明</label>
        <input type="text" name="description" class="form-control form-control-sm">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
          <input type="checkbox" name="is_active" class="form-check-input" checked>
          <label class="form-check-label small">公開する</label>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>追加</button>
      </div>
    </form>
  </div>
</div>

<table class="table table-sm bg-white align-middle">
  <thead><tr><th>タイトル</th><th>候補日</th><th>開始</th><th>終了</th><th>場所</th><th>定員</th><th>予約数</th><th>公開</th><th></th></tr></thead>
  <tbody>
    <?php if (!$slots): ?>
      <tr><td colspan="9" class="text-center text-muted py-3">予約枠がありません</td></tr>
    <?php else: foreach ($slots as $s): ?>
      <tr>
        <form method="post" action="slots.php" style="display:table-row;">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <td><input type="text" name="title" class="form-control form-control-sm" value="<?= $h($s['title']) ?>" required></td>
          <td><input type="date" name="event_date" class="form-control form-control-sm" value="<?= $h($s['event_date']) ?>" required></td>
          <td><input type="time" name="start_time" class="form-control form-control-sm" value="<?= $h($s['start_time'] ?? '') ?>"></td>
          <td><input type="time" name="end_time" class="form-control form-control-sm" value="<?= $h($s['end_time'] ?? '') ?>"></td>
          <td><input type="text" name="location" class="form-control form-control-sm" value="<?= $h($s['location'] ?? '') ?>"></td>
          <td style="width:80px;"><input type="number" name="capacity" class="form-control form-control-sm" value="<?= (int)$s['capacity'] ?>" min="0"></td>
          <td class="text-center"><?= (int)$s['reserved_count'] ?></td>
          <td class="text-center"><input type="checkbox" name="is_active" <?= $s['is_active'] ? 'checked' : '' ?>></td>
          <td class="text-nowrap">
            <button class="btn btn-outline-primary btn-sm">保存</button>
        </form>
            <form method="post" action="slots.php" style="display:inline;" onsubmit="return confirm('この予約枠を削除します。予約データも削除されます。よろしいですか？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
            </form>
          </td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
