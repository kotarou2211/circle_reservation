<?php
/**
 * api/registrants/register.php — 予約前のユーザー登録
 *
 * POST (JSON) { line_id, display_name, grade_id?, faculty_id?, affiliation_name? }
 * → { success, message }
 */
ini_set('display_errors', '0');
error_reporting(0);
ob_start();
require_once __DIR__ . '/../../common/db_connect.php';
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/json; charset=utf-8');

$data             = json_decode(file_get_contents('php://input'), true) ?: [];
$line_id          = trim($data['line_id'] ?? '');
$display_name     = trim($data['display_name'] ?? '');
$grade_id         = !empty($data['grade_id'])   ? (int)$data['grade_id']   : null;
$faculty_id       = !empty($data['faculty_id']) ? (int)$data['faculty_id'] : null;
$affiliation_name = trim($data['affiliation_name'] ?? '') ?: null;

if (!preg_match('/^U[0-9a-fA-F]{32}$/', $line_id)) {
    echo json_encode(['success' => false, 'message' => '不正なLINE IDです。']);
    exit;
}
if ($display_name === '') {
    echo json_encode(['success' => false, 'message' => '名前を入力してください。']);
    exit;
}

try {
    $pdo->prepare("
        INSERT INTO registrants (line_user_id, display_name, grade_id, faculty_id, affiliation_name, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            grade_id = VALUES(grade_id),
            faculty_id = VALUES(faculty_id),
            affiliation_name = VALUES(affiliation_name)
    ")->execute([$line_id, $display_name, $grade_id, $faculty_id, $affiliation_name]);

    echo json_encode(['success' => true, 'message' => '登録しました。']);
} catch (Throwable $e) {
    error_log('[registrants/register] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
