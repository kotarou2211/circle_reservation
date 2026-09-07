<?php
/**
 * api/reservations/init.php — LIFF予約ページの初期表示データ
 *
 * GET ?line_id=Uxxxx
 * → { success, registered, registrant, grades, faculties, slots }
 *   slots[].my_reservation は true/false（この人が予約済みか）
 */
ini_set('display_errors', '0');
error_reporting(0);
ob_start();
require_once __DIR__ . '/../../common/db_connect.php';
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');

$line_id = trim($_GET['line_id'] ?? '');
if (!preg_match('/^U[0-9a-fA-F]{32}$/', $line_id)) {
    echo json_encode(['success' => false, 'message' => '不正なLINE IDです。']);
    exit;
}

try {
    $rstmt = $pdo->prepare("SELECT id, display_name, grade_id, faculty_id, affiliation_name FROM registrants WHERE line_user_id = ? LIMIT 1");
    $rstmt->execute([$line_id]);
    $registrant = $rstmt->fetch() ?: null;

    $grades    = $pdo->query("SELECT id, name FROM grades WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();
    $faculties = $pdo->query("SELECT id, name FROM faculties WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll();

    $slots = [];
    $sstmt = $pdo->prepare("
        SELECT es.id, es.title, es.event_date, es.start_time, es.end_time, es.location, es.description,
               es.capacity,
               (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = es.id) AS reserved_count,
               (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = es.id AND r.registrant_id = ?) AS my_reservation
        FROM event_slots es
        WHERE es.is_active = 1 AND es.event_date >= CURDATE()
        ORDER BY es.event_date ASC, es.start_time ASC
    ");
    $sstmt->execute([$registrant['id'] ?? 0]);
    foreach ($sstmt->fetchAll() as $s) {
        $cap  = (int)$s['capacity'];
        $full = $cap > 0 && (int)$s['reserved_count'] >= $cap;
        $slots[] = [
            'id'             => (int)$s['id'],
            'title'          => $s['title'],
            'event_date'     => $s['event_date'],
            'start_time'     => $s['start_time'],
            'end_time'       => $s['end_time'],
            'location'       => $s['location'],
            'description'    => $s['description'],
            'capacity'       => $cap,
            'reserved_count' => (int)$s['reserved_count'],
            'is_full'        => $full,
            'my_reservation' => (int)$s['my_reservation'] > 0,
        ];
    }

    echo json_encode([
        'success'    => true,
        'registered' => $registrant !== null,
        'registrant' => $registrant,
        'grades'     => $grades,
        'faculties'  => $faculties,
        'slots'      => $slots,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[reservations/init] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
