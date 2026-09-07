<?php
/**
 * api/reservations/reserve.php — 予約 / キャンセル
 *
 * POST (JSON) { line_id, slot_id, action: 'reserve'|'cancel' }
 * → { success, action, reserved, message }
 *
 * 予約成功時は本人へLINEで確認メッセージを送信する。
 */
ini_set('display_errors', '0');
error_reporting(0);
ob_start();
require_once __DIR__ . '/../../common/db_connect.php';
require_once __DIR__ . '/../../common/reservation.php';
require_once __DIR__ . '/../../common/line_api.php';
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');

$data    = json_decode(file_get_contents('php://input'), true) ?: [];
$line_id = trim($data['line_id'] ?? '');
$slot_id = (int)($data['slot_id'] ?? 0);
$action  = ($data['action'] ?? '') === 'cancel' ? 'cancel' : 'reserve';

if (!preg_match('/^U[0-9a-fA-F]{32}$/', $line_id)) {
    echo json_encode(['success' => false, 'message' => '不正なLINE IDです。']);
    exit;
}
if ($slot_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'イベント枠が指定されていません。']);
    exit;
}

try {
    // 本人（登録済みの参加者）を特定
    $rstmt = $pdo->prepare("SELECT id FROM registrants WHERE line_user_id = ? LIMIT 1");
    $rstmt->execute([$line_id]);
    $registrant_id = (int)($rstmt->fetchColumn() ?: 0);
    if ($registrant_id <= 0) {
        echo json_encode(['success' => false, 'message' => '先に登録を行ってください。']);
        exit;
    }

    if ($action === 'cancel') {
        $result = cancelReservation($pdo, $registrant_id, $slot_id);
        echo json_encode(['success' => $result['success'], 'action' => 'cancel', 'reserved' => false, 'message' => $result['message']]);
        exit;
    }

    $result = reserveSlot($pdo, $registrant_id, $slot_id);

    if ($result['success'] && empty($result['already_reserved'])) {
        $slot = $pdo->prepare("SELECT title, event_date, start_time, end_time, location FROM event_slots WHERE id = ?");
        $slot->execute([$slot_id]);
        $s = $slot->fetch();
        if ($s) {
            $date_label = date('n月j日', strtotime($s['event_date']));
            $time_label = $s['start_time'] ? substr($s['start_time'], 0, 5) . ($s['end_time'] ? '〜' . substr($s['end_time'], 0, 5) : '') : '';
            $text = "✅ 予約を受け付けました\n\n{$s['title']}\n📅 {$date_label}" . ($time_label ? " {$time_label}" : '')
                  . ($s['location'] ? "\n📍 {$s['location']}" : '') . "\n\n前日にリマインダーをお送りします。";
            $ok = linePushMessage($line_id, [lineTextMsg($text)]);
            logMessageSent($pdo, $line_id, 'confirm', $slot_id, $ok);
        }
    }

    echo json_encode([
        'success'  => $result['success'],
        'action'   => 'reserve',
        'reserved' => $result['success'],
        'message'  => $result['message'],
    ]);
} catch (Throwable $e) {
    error_log('[reservations/reserve] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
