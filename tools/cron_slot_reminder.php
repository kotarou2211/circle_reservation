<?php
/**
 * tools/cron_slot_reminder.php — 翌日開催の予約枠の前日リマインダー送信（cron用）
 *
 * 毎日12:00に実行し、「翌日が開催日の予約枠」の予約者全員に個別でリマインダーを送信する。
 *
 * ── 実行方法 ────────────────────────────────────────────────
 *  Webクーロン:
 *    https://<host>/reservation/tools/cron_slot_reminder.php?token=<sync_secret_token>
 *  CLIクーロン:
 *    php /path/to/tools/cron_slot_reminder.php
 *
 * ── 推奨cron設定 ────────────────────────────────────────────
 *  毎日 12:00 に実行:
 *    0 12 * * *  curl -s "https://<host>/reservation/tools/cron_slot_reminder.php?token=XXXX" >/dev/null
 *
 * ── 自動配信トグル ──────────────────────────────────────────
 *  管理画面「LINE設定」タブの「前日リマインダーを自動送信する」で停止できる
 *  （system_config.auto_notify_reminder = '0' で停止）。
 */

require_once __DIR__ . '/../common/db_connect.php';
require_once __DIR__ . '/../common/line_api.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    $cron_token  = getConfig($pdo, 'sync_secret_token') ?? '';
    $given_token = trim($_GET['token'] ?? '');
    if ($cron_token === '' || $given_token === '' || !hash_equals($cron_token, $given_token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '認証エラー（token不一致）']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

if (getConfig($pdo, 'auto_notify_reminder') === '0') {
    $msg = '前日リマインダーは無効化されています（LINE設定で再開できます）';
    if ($is_cli) { echo $msg . PHP_EOL; } else { echo json_encode(['success'=>true,'skipped'=>true,'message'=>$msg]); }
    exit;
}

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT r.id AS reservation_id, g.line_user_id,
               es.title, es.event_date, es.start_time, es.end_time, es.location
        FROM reservations r
        JOIN event_slots es ON es.id = r.slot_id
        JOIN registrants g ON g.id = r.registrant_id
        WHERE es.event_date = ? AND es.is_active = 1
    ");
    $stmt->execute([$tomorrow]);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $err = '予約取得エラー: ' . $e->getMessage();
    if ($is_cli) { echo $err . PHP_EOL; exit(1); }
    echo json_encode(['success'=>false,'message'=>$err]); exit;
}

if (empty($rows)) {
    $msg = "翌日（{$tomorrow}）開催の予約枠はありません";
    if ($is_cli) { echo $msg . PHP_EOL; } else { echo json_encode(['success'=>true,'sent'=>0,'message'=>$msg]); }
    exit;
}

$sent_total = 0;
$results    = [];

foreach ($rows as $row) {
    $date_label = date('n月j日', strtotime($row['event_date']));
    $time_label = $row['start_time'] ? substr($row['start_time'], 0, 5) . ($row['end_time'] ? '〜' . substr($row['end_time'], 0, 5) : '') : '';
    $text = "⏰ 明日の予約リマインダー\n\n{$row['title']}\n📅 {$date_label}" . ($time_label ? " {$time_label}" : '')
          . ($row['location'] ? "\n📍 {$row['location']}" : '') . "\n\nご参加をお待ちしています！";

    $ok = linePushMessage($row['line_user_id'], [lineTextMsg($text)]);
    logMessageSent($pdo, $row['line_user_id'], 'reminder', null, $ok);
    if ($ok) $sent_total++;
    $results[] = ($ok ? '[OK]   ' : '[FAIL] ') . $row['title'] . ' → ' . $row['line_user_id'];
}

if ($is_cli) {
    echo "=== 前日リマインダー ({$tomorrow}) ===" . PHP_EOL;
    echo "対象予約: " . count($rows) . "件 / 送信成功: {$sent_total}件" . PHP_EOL;
    foreach ($results as $r) { echo $r . PHP_EOL; }
} else {
    echo json_encode([
        'success'     => true,
        'target_date' => $tomorrow,
        'total'       => count($rows),
        'sent'        => $sent_total,
        'results'     => $results,
    ], JSON_UNESCAPED_UNICODE);
}
