<?php
// KND Store - Support Credits business logic

require_once __DIR__ . '/config.php';

/**
 * Add N business days (Mon-Fri) to a DateTime. Returns new DateTime.
 */
function add_business_days(DateTime $date, int $businessDays): DateTime {
    $result = clone $date;
    $added = 0;
    while ($added < $businessDays) {
        $result->modify('+1 day');
        $dow = (int) $result->format('N'); // 1=Mon .. 7=Sun
        if ($dow <= 5) {
            $added++;
        }
    }
    return $result;
}

/**
 * A user is "new" if their account is less than SUPPORT_NEW_ACCOUNT_DAYS old
 * OR they have zero confirmed support payments.
 */
function is_new_account(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return true;

    $createdAt = new DateTime($user['created_at']);
    $now = new DateTime();
    $daysSinceCreation = (int) $now->diff($createdAt)->days;
    if ($daysSinceCreation < SUPPORT_NEW_ACCOUNT_DAYS) {
        return true;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM support_payments WHERE user_id = ? AND status = 'confirmed'"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() === 0;
}

function compute_hold_business_days(PDO $pdo, int $userId): int {
    return is_new_account($pdo, $userId) ? SUPPORT_HOLD_DAYS_NEW : SUPPORT_HOLD_DAYS_NORMAL;
}

function points_rate_usd_to_points(float $amount): int {
    return (int) round($amount * SUPPORT_POINTS_PER_USD);
}

/**
 * Returns the user's point balance breakdown.
 */
function get_points_balance(PDO $pdo, int $userId): array {
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "SELECT status, SUM(points) AS total FROM points_ledger
         WHERE user_id = ? AND entry_type IN ('earn','reversal')
         GROUP BY status"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $balances = ['pending' => 0, 'available' => 0, 'locked' => 0, 'spent' => 0, 'expired' => 0];
    foreach ($rows as $r) {
        $balances[$r['status']] = (int) $r['total'];
    }

    $stmtSpent = $pdo->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM points_ledger
         WHERE user_id = ? AND entry_type = 'spend'"
    );
    $stmtSpent->execute([$userId]);
    $spentTotal = abs((int) $stmtSpent->fetchColumn());

    $stmtExpiring = $pdo->prepare(
        "SELECT id, points, expires_at FROM points_ledger
         WHERE user_id = ? AND status = 'available'
           AND expires_at IS NOT NULL AND expires_at <= DATE_ADD(?, INTERVAL 30 DAY)
         ORDER BY expires_at ASC LIMIT 10"
    );
    $stmtExpiring->execute([$userId, $now]);
    $expiringSoon = $stmtExpiring->fetchAll();

    return [
        'pending'        => $balances['pending'],
        'available'      => $balances['available'],
        'locked'         => $balances['locked'],
        'spent_total'    => $spentTotal,
        'expiring_soon'  => $expiringSoon,
    ];
}

/**
 * Move pending ledger entries to available if NOW >= available_at
 * and the source payment is confirmed.
 */
function release_available_points_if_due(PDO $pdo, int $userId): int {
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "SELECT pl.id, pl.source_id FROM points_ledger pl
         JOIN support_payments sp ON sp.id = pl.source_id
         WHERE pl.user_id = ?
           AND pl.status = 'pending'
           AND pl.source_type = 'support_payment'
           AND pl.available_at <= ?
           AND sp.status = 'confirmed'"
    );
    $stmt->execute([$userId, $now]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) return 0;

    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $update = $pdo->prepare(
        "UPDATE points_ledger SET status = 'available' WHERE id IN ($placeholders)"
    );
    $update->execute($ids);

    return count($ids);
}

/**
 * Expire available points whose expires_at has passed.
 */
function expire_points_if_due(PDO $pdo, int $userId): int {
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "UPDATE points_ledger SET status = 'expired'
         WHERE user_id = ? AND status = 'available'
           AND expires_at IS NOT NULL AND expires_at <= ?"
    );
    $stmt->execute([$userId, $now]);
    return $stmt->rowCount();
}

/**
 * Create a support payment + corresponding ledger entry (both pending).
 * Returns the payment row ID.
 */
function create_support_payment(PDO $pdo, int $userId, string $method, float $amount, string $currency = 'USD', ?string $notes = null): array {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $nowStr = $now->format('Y-m-d H:i:s');
    $points = points_rate_usd_to_points($amount);
    $holdDays = compute_hold_business_days($pdo, $userId);
    $availableAt = add_business_days(clone $now, $holdDays);
    $expiresAt = (clone $availableAt)->modify('+' . SUPPORT_EXPIRY_MONTHS . ' months');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO support_payments (user_id, method, amount_usd, currency, status, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)"
        );
        $stmt->execute([$userId, $method, $amount, $currency, $notes, $nowStr, $nowStr]);
        $paymentId = (int) $pdo->lastInsertId();

        $stmt2 = $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
             VALUES (?, 'support_payment', ?, 'earn', 'pending', ?, ?, ?, ?)"
        );
        $stmt2->execute([
            $userId,
            $paymentId,
            $points,
            $availableAt->format('Y-m-d H:i:s'),
            $expiresAt->format('Y-m-d H:i:s'),
            $nowStr,
        ]);

        $pdo->commit();

        return [
            'payment_id'     => $paymentId,
            'pending_points' => $points,
            'hold_days'      => $holdDays,
            'available_at'   => $availableAt->format('Y-m-d H:i:s'),
            'expires_at'     => $expiresAt->format('Y-m-d H:i:s'),
        ];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get the user's total available (spendable) points.
 */
function get_available_points(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM points_ledger
         WHERE user_id = ? AND status = 'available' AND entry_type = 'earn'"
    );
    $stmt->execute([$userId]);
    $earned = (int) $stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM points_ledger
         WHERE user_id = ? AND entry_type = 'spend'"
    );
    $stmt2->execute([$userId]);
    $spent = abs((int) $stmt2->fetchColumn());

    return max(0, $earned - $spent);
}

/**
 * Check user risk flag.
 */
function has_risk_flag(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT risk_flag FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int) $row['risk_flag'] === 1;
}

/**
 * Admin: confirm / reject / dispute / refund a payment.
 */
function admin_update_payment(PDO $pdo, int $paymentId, string $action, ?string $notes = null): array {
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM support_payments WHERE id = ? FOR UPDATE');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $pdo->rollBack();
            return ['error' => 'Payment not found'];
        }

        switch ($action) {
            case 'confirm':
                $pdo->prepare(
                    "UPDATE support_payments SET status = 'confirmed', confirmed_at = ?, notes = CONCAT(COALESCE(notes,''), ?), updated_at = ? WHERE id = ?"
                )->execute([$now, $notes ? " | Admin: $notes" : '', $now, $paymentId]);
                break;

            case 'reject':
                $pdo->prepare(
                    "UPDATE support_payments SET status = 'rejected', notes = CONCAT(COALESCE(notes,''), ?), updated_at = ? WHERE id = ?"
                )->execute([$notes ? " | Rejected: $notes" : ' | Rejected', $now, $paymentId]);

                $pdo->prepare(
                    "UPDATE points_ledger SET status = 'locked'
                     WHERE source_type = 'support_payment' AND source_id = ? AND status IN ('pending','available')"
                )->execute([$paymentId]);
                break;

            case 'dispute':
                $pdo->prepare(
                    "UPDATE support_payments SET status = 'disputed', notes = CONCAT(COALESCE(notes,''), ?), updated_at = ? WHERE id = ?"
                )->execute([$notes ? " | Disputed: $notes" : ' | Disputed', $now, $paymentId]);

                $pdo->prepare(
                    "UPDATE points_ledger SET status = 'locked'
                     WHERE source_type = 'support_payment' AND source_id = ? AND status IN ('pending','available')"
                )->execute([$paymentId]);

                $pdo->prepare('UPDATE users SET risk_flag = 1 WHERE id = ?')->execute([$payment['user_id']]);
                break;

            case 'refund':
                $pdo->prepare(
                    "UPDATE support_payments SET status = 'refunded', notes = CONCAT(COALESCE(notes,''), ?), updated_at = ? WHERE id = ?"
                )->execute([$notes ? " | Refunded: $notes" : ' | Refunded', $now, $paymentId]);

                $pdo->prepare(
                    "UPDATE points_ledger SET status = 'locked'
                     WHERE source_type = 'support_payment' AND source_id = ? AND status IN ('pending','available')"
                )->execute([$paymentId]);

                $alreadySpent = $pdo->prepare(
                    "SELECT COALESCE(SUM(points), 0) FROM points_ledger
                     WHERE source_type = 'support_payment' AND source_id = ? AND status = 'spent'"
                );
                $alreadySpent->execute([$paymentId]);
                $spentPts = abs((int) $alreadySpent->fetchColumn());
                if ($spentPts > 0) {
                    $pdo->prepare(
                        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
                         VALUES (?, 'adjustment', ?, 'reversal', 'locked', ?, ?)"
                    )->execute([$payment['user_id'], $paymentId, -$spentPts, $now]);
                    $pdo->prepare('UPDATE users SET risk_flag = 1 WHERE id = ?')->execute([$payment['user_id']]);
                }
                break;

            default:
                $pdo->rollBack();
                return ['error' => 'Invalid action'];
        }

        $pdo->commit();
        return ['ok' => true, 'action' => $action, 'payment_id' => $paymentId];
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
