<?php
/**
 * admin/parts/reservations.php — 予約一覧タブ
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';
requireAdminLogin();

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$rows = $pdo->query("
    SELECT r.id AS reservation_id, r.reserved_at,
           es.title AS slot_title, es.event_date, es.start_time,
           g.display_name, g.line_user_id, g.affiliation_name,
           gr.name AS grade_name, fa.name AS faculty_name
    FROM reservations r
    JOIN event_slots es ON es.id = r.slot_id
    JOIN registrants g ON g.id = r.registrant_id
    LEFT JOIN grades gr ON gr.id = g.grade_id
    LEFT JOIN faculties fa ON fa.id = g.faculty_id
    ORDER BY es.event_date ASC, es.start_time ASC, r.reserved_at ASC
")->fetchAll();

$grouped = [];
foreach ($rows as $r) {
    $key = $r['event_date'] . ' ' . $r['slot_title'];
    $grouped[$key]['title'] = $r['slot_title'];
    $grouped[$key]['date']  = $r['event_date'];
    $grouped[$key]['rows'][] = $r;
}
?>
<h5 class="fw-bold mb-3"><i class="bi bi-people text-success me-2"></i>予約一覧</h5>
<div id="resv-msg"></div>

<?php if (!$grouped): ?>
  <p class="text-muted">予約がまだありません。</p>
<?php else: foreach ($grouped as $g): ?>
  <div class="card mb-3">
    <div class="card-header bg-white fw-bold" style="font-size:13px;">
      <?= $h($g['title']) ?>
      <span class="text-muted fw-normal">（<?= $h($g['date']) ?>・<?= count($g['rows']) ?>名）</span>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead><tr><th>氏名</th><th>学年</th><th>学部</th><th>大学・サークル名</th><th>予約日時</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($g['rows'] as $r): ?>
          <tr>
            <td><?= $h($r['display_name']) ?></td>
            <td><?= $h($r['grade_name'] ?? '—') ?></td>
            <td><?= $h($r['faculty_name'] ?? '—') ?></td>
            <td><?= $h($r['affiliation_name'] ?? '—') ?></td>
            <td class="text-muted" style="font-size:12px;"><?= $h($r['reserved_at']) ?></td>
            <td>
              <button class="btn btn-outline-secondary btn-sm" onclick="sendReminder(<?= (int)$r['reservation_id'] ?>, this)">
                <i class="bi bi-bell me-1"></i>リマインダー送信
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; endif; ?>

<script>
async function sendReminder(reservationId, btn) {
  btn.disabled = true;
  const data = await apiFetch('../api/reservations/send_reminder.php', { reservation_id: reservationId });
  const msg = document.getElementById('resv-msg');
  msg.innerHTML = `<div class="alert ${data.success ? 'alert-success' : 'alert-danger'} py-2">${data.message}</div>`;
  btn.disabled = false;
}
</script>
