<?php
/**
 * api/reservations/send_reminder.php — 手動リマインダー送信（管理画面から）
 *
 * POST (JSON) { reservation_id }
 */
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/auth.php';
require_once __DIR__ . '/../../common/line_api.php';

header('Content-Type: application/json; charset=utf-8');

$admin = getAdminInfo();
if (!$admin) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'認証エラー']); exit; }

$data           = json_decode(file_get_contents('php://input'), true) ?: [];
$reservation_id = (int)($data['reservation_id'] ?? 0);
if ($reservation_id <= 0) { echo json_encode(['success'=>false,'message'=>'IDが不正です']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT r.slot_id, g.line_user_id, es.title, es.event_date, es.start_time, es.end_time, es.location
        FROM reservations r
        JOIN registrants g ON g.id = r.registrant_id
        JOIN event_slots es ON es.id = r.slot_id
        WHERE r.id = ? LIMIT 1
    ");
    $stmt->execute([$reservation_id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['success'=>false,'message'=>'予約が見つかりません']); exit; }

    $date_label = date('n月j日', strtotime($row['event_date']));
    $time_label = $row['start_time'] ? substr($row['start_time'], 0, 5) . ($row['end_time'] ? '〜' . substr($row['end_time'], 0, 5) : '') : '';
    $text = "⏰ リマインダー\n\n{$row['title']}\n📅 {$date_label}" . ($time_label ? " {$time_label}" : '')
          . ($row['location'] ? "\n📍 {$row['location']}" : '') . "\n\nご参加をお待ちしています！";

    $ok = linePushMessage($row['line_user_id'], [lineTextMsg($text)]);
    logMessageSent($pdo, $row['line_user_id'], 'reminder', (int)$row['slot_id'], $ok);

    echo json_encode(['success' => $ok, 'message' => $ok ? '送信しました' : '送信に失敗しました']);
} catch (Throwable $e) {
    error_log('[reservations/send_reminder] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
