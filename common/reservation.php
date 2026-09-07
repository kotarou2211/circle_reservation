<?php
/**
 * common/reservation.php — 予約処理（共通関数）
 */

/**
 * 登録者を予約枠に予約する。
 * 呼び出し元でトランザクションを張らないこと（この関数内で管理する）。
 *
 * @return array ['success' => bool, 'message' => string, 'already_reserved'?: bool]
 */
function reserveSlot(PDO $pdo, int $registrant_id, int $slot_id): array {
    if ($registrant_id <= 0 || $slot_id <= 0) {
        return ['success' => false, 'message' => 'パラメータが不正です。'];
    }

    try {
        $pdo->beginTransaction();

        // 枠の存在・有効・定員確認（行ロック）
        $sstmt = $pdo->prepare("
            SELECT es.id, es.capacity, es.is_active,
                   (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = es.id) AS reserved_count
            FROM event_slots es WHERE es.id = ? FOR UPDATE
        ");
        $sstmt->execute([$slot_id]);
        $slot = $sstmt->fetch();

        if (!$slot || (int)$slot['is_active'] !== 1) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'この枠は予約できません。'];
        }

        // 既に予約済みなら成功扱い（冪等）
        $dup = $pdo->prepare("SELECT id FROM reservations WHERE registrant_id = ? AND slot_id = ?");
        $dup->execute([$registrant_id, $slot_id]);
        if ($dup->fetch()) {
            $pdo->commit();
            return ['success' => true, 'message' => '予約しました。', 'already_reserved' => true];
        }

        $cap = (int)$slot['capacity'];
        if ($cap > 0 && (int)$slot['reserved_count'] >= $cap) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '定員に達しています。'];
        }

        $pdo->prepare("INSERT INTO reservations (slot_id, registrant_id, reserved_at) VALUES (?,?,NOW())")
            ->execute([$slot_id, $registrant_id]);

        $pdo->commit();
        return ['success' => true, 'message' => '予約しました。'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[reserveSlot] ' . $e->getMessage());
        return ['success' => false, 'message' => 'サーバーエラーが発生しました。'];
    }
}

/**
 * 予約をキャンセルする。
 */
function cancelReservation(PDO $pdo, int $registrant_id, int $slot_id): array {
    if ($registrant_id <= 0 || $slot_id <= 0) {
        return ['success' => false, 'message' => 'パラメータが不正です。'];
    }
    $pdo->prepare("DELETE FROM reservations WHERE registrant_id = ? AND slot_id = ?")
        ->execute([$registrant_id, $slot_id]);
    return ['success' => true, 'message' => '予約をキャンセルしました。'];
}
