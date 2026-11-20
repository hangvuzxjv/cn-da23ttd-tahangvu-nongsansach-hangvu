<?php

declare(strict_types=1);

final class CustomerMissionService
{
    private const MAX_SETTING_VALUE = 1000000000;

    private const SETTING_DEFINITIONS = [
        'review_reward_vnd' => [
            'key' => 'mission.review_reward_vnd',
            'default' => 10000,
            'description' => 'Voucher thưởng cho đánh giá sản phẩm đã được duyệt',
        ],
        'review_minimum_vnd' => [
            'key' => 'mission.review_minimum_vnd',
            'default' => 100000,
            'description' => 'Giá trị đơn tối thiểu của voucher thưởng đánh giá',
        ],
        'monthly_spend_threshold_vnd' => [
            'key' => 'mission.monthly_spend_threshold_vnd',
            'default' => 500000,
            'description' => 'Mốc chi tiêu đơn đã giao trong tháng để nhận thưởng',
        ],
        'monthly_reward_vnd' => [
            'key' => 'mission.monthly_reward_vnd',
            'default' => 20000,
            'description' => 'Voucher thưởng khi đạt mốc chi tiêu trong tháng',
        ],
        'referral_reward_vnd' => [
            'key' => 'mission.referral_reward_vnd',
            'default' => 15000,
            'description' => 'Voucher giới thiệu cho mỗi bên',
        ],
        'referral_order_minimum_vnd' => [
            'key' => 'mission.referral_order_minimum_vnd',
            'default' => 200000,
            'description' => 'Mức đơn đầu tiên tối thiểu để ghi nhận giới thiệu',
        ],
    ];

    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function settings(): array
    {
        $definitions = self::SETTING_DEFINITIONS;
        $keys = array_column($definitions, 'key');
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $query = $this->database->prepare(
            'SELECT setting_key, setting_value FROM settings WHERE setting_key IN (' . $placeholders . ')'
        );
        $query->execute($keys);
        $stored = [];
        foreach ($query->fetchAll() as $row) {
            $stored[$row['setting_key']] = $row['setting_value'];
        }

        $values = [];
        foreach ($definitions as $field => $definition) {
            $value = $stored[$definition['key']] ?? null;
            $values[$field] = is_string($value) && ctype_digit($value) && (int) $value > 0
                ? (int) $value
                : $definition['default'];
        }
        return $values;
    }

    public function saveSettings(array $input, int $adminId): void
    {
        $values = [];
        foreach (self::SETTING_DEFINITIONS as $field => $definition) {
            $raw = $input[$field] ?? null;
            $value = is_scalar($raw) ? filter_var((string) $raw, FILTER_VALIDATE_INT) : false;
            if ($value === false || $value < 1 || $value > self::MAX_SETTING_VALUE) {
                throw new InvalidArgumentException('Các mức thưởng và điều kiện phải là số nguyên dương hợp lệ.');
            }
            $values[$field] = $value;
        }

        $this->database->beginTransaction();
        try {
            $save = $this->database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, :setting_value, :description)
                 ON DUPLICATE KEY UPDATE setting_value = :updated_value'
            );
            foreach (self::SETTING_DEFINITIONS as $field => $definition) {
                $save->execute([
                    'setting_key' => $definition['key'],
                    'setting_value' => (string) $values[$field],
                    'description' => $definition['description'],
                    'updated_value' => (string) $values[$field],
                ]);
            }
            AuditLog::record(
                $adminId,
                'customer_mission_settings_saved',
                'customer_missions',
                null,
                implode(';', array_map(
                    static fn (string $field, int $value): string => $field . '=' . $value,
                    array_keys($values),
                    array_values($values)
                )),
                $this->database
            );
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function validateReferralCode(string $code, string $phone): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }
        $this->referrerForCode($code, $phone);
    }

    public function attachReferrer(int $referredUserId, string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }
        $this->withinTransaction(function () use ($referredUserId, $code): void {
            $referredQuery = $this->database->prepare(
                "SELECT id, phone FROM users
                 WHERE id = :id AND role = 'customer' AND status = 'active'
                 FOR UPDATE"
            );
            $referredQuery->execute(['id' => $referredUserId]);
            $referred = $referredQuery->fetch();
            if (!$referred) {
                throw new InvalidArgumentException('Không thể gắn mã giới thiệu cho tài khoản này.');
            }

            $referrer = $this->referrerForCode($code, (string) ($referred['phone'] ?? ''), $referredUserId);
            $existingQuery = $this->database->prepare(
                'SELECT referrer_user_id FROM customer_referrals WHERE referred_user_id = :user_id FOR UPDATE'
            );
            $existingQuery->execute(['user_id' => $referredUserId]);
            $existingReferrerId = $existingQuery->fetchColumn();
            if ($existingReferrerId !== false) {
                if ((int) $existingReferrerId !== (int) $referrer['user_id']) {
                    throw new InvalidArgumentException('Tài khoản này đã được đăng ký bằng một mã giới thiệu khác.');
                }
                return;
            }

            $this->database->prepare(
                'INSERT INTO customer_referrals
                    (referrer_user_id, referred_user_id, referral_code, status)
                 VALUES (:referrer_id, :referred_id, :code, \'pending\')'
            )->execute([
                'referrer_id' => (int) $referrer['user_id'],
                'referred_id' => $referredUserId,
                'code' => $code,
            ]);
        });
    }

    public function dashboard(int $userId): array
    {
        $settings = $this->settings();
        $referralCode = $this->referralCodeForUser($userId);
        $monthlyQuery = $this->database->prepare(
            "SELECT DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m') AS period_key,
                    COALESCE(SUM(GREATEST(subtotal - discount_amount, 0)), 0) AS spend
             FROM orders
             WHERE user_id = :user_id AND status = 'completed'
               AND updated_at >= DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-01')
               AND updated_at < DATE_ADD(DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-01'), INTERVAL 1 MONTH)"
        );
        $monthlyQuery->execute(['user_id' => $userId]);
        $monthly = $monthlyQuery->fetch();
        $periodKey = (string) $monthly['period_key'];
        $monthlyRewardQuery = $this->database->prepare(
            "SELECT COUNT(*) FROM customer_mission_rewards
             WHERE user_id = :user_id AND mission_code = 'monthly_spend' AND reference_key = :period"
        );
        $monthlyRewardQuery->execute(['user_id' => $userId, 'period' => $periodKey]);

        $reviewQuery = $this->database->prepare(
            "SELECT
                SUM(status = 'pending') AS pending_count,
                SUM(status = 'published') AS published_count
             FROM reviews WHERE user_id = :user_id AND order_id IS NOT NULL"
        );
        $reviewQuery->execute(['user_id' => $userId]);
        $reviews = $reviewQuery->fetch() ?: ['pending_count' => 0, 'published_count' => 0];
        $reviewRewardQuery = $this->database->prepare(
            "SELECT COUNT(*) FROM customer_mission_rewards
             WHERE user_id = :user_id AND mission_code = 'review'"
        );
        $reviewRewardQuery->execute(['user_id' => $userId]);

        $referralQuery = $this->database->prepare(
            "SELECT SUM(status = 'pending') AS pending_count, SUM(status = 'rewarded') AS rewarded_count
             FROM customer_referrals WHERE referrer_user_id = :user_id"
        );
        $referralQuery->execute(['user_id' => $userId]);
        $referrals = $referralQuery->fetch() ?: ['pending_count' => 0, 'rewarded_count' => 0];

        $spend = (int) $monthly['spend'];
        $threshold = $settings['monthly_spend_threshold_vnd'];
        return [
            'settings' => $settings,
            'reviews' => [
                'pending' => (int) ($reviews['pending_count'] ?? 0),
                'published' => (int) ($reviews['published_count'] ?? 0),
                'rewarded' => (int) $reviewRewardQuery->fetchColumn(),
            ],
            'monthly' => [
                'period' => $periodKey,
                'spend' => $spend,
                'threshold' => $threshold,
                'remaining' => max(0, $threshold - $spend),
                'progress_percent' => min(100, (int) floor($spend * 100 / $threshold)),
                'rewarded' => (int) $monthlyRewardQuery->fetchColumn() > 0,
            ],
            'referral' => [
                'code' => $referralCode,
                'pending' => (int) ($referrals['pending_count'] ?? 0),
                'rewarded' => (int) ($referrals['rewarded_count'] ?? 0),
            ],
        ];
    }

    public function rewardApprovedReview(int $reviewId): void
    {
        $this->withinTransaction(function () use ($reviewId): void {
            $query = $this->database->prepare(
                "SELECT r.id, r.user_id, r.order_id, r.status, o.status AS order_status
                 FROM reviews r
                 LEFT JOIN orders o ON o.id = r.order_id
                 WHERE r.id = :id
                 FOR UPDATE"
            );
            $query->execute(['id' => $reviewId]);
            $review = $query->fetch();
            if (!$review || $review['status'] !== 'published' || $review['order_id'] === null
                || $review['order_status'] !== 'completed') {
                return;
            }
            if (!$this->isVerifiedActiveCustomer((int) $review['user_id'])) {
                return;
            }

            $settings = $this->settings();
            $this->issueReward(
                (int) $review['user_id'],
                'review',
                'review:' . $reviewId,
                'review_reward',
                $settings['review_reward_vnd'],
                $settings['review_minimum_vnd'],
                'Cảm ơn bạn đã gửi đánh giá được duyệt.'
            );
        });
    }

    public function rewardDeliveredOrderSafely(int $orderId): void
    {
        try {
            $this->rewardDeliveredOrder($orderId);
        } catch (Throwable $exception) {
            app_log_exception('Customer mission reward failed (order=' . $orderId . ')', $exception);
        }
    }

    private function rewardDeliveredOrder(int $orderId): void
    {
        $this->withinTransaction(function () use ($orderId): void {
            $orderQuery = $this->database->prepare(
                "SELECT o.id, o.user_id, o.subtotal, o.discount_amount, o.status
                 FROM orders o WHERE o.id = :id FOR UPDATE"
            );
            $orderQuery->execute(['id' => $orderId]);
            $order = $orderQuery->fetch();
            if (!$order || $order['status'] !== 'completed'
                || !$this->isVerifiedActiveCustomer((int) $order['user_id'], true)) {
                return;
            }

            $userId = (int) $order['user_id'];
            $settings = $this->settings();
            $monthlyQuery = $this->database->prepare(
                "SELECT DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m') AS period_key,
                        COALESCE(SUM(GREATEST(subtotal - discount_amount, 0)), 0) AS spend
                 FROM orders
                 WHERE user_id = :user_id AND status = 'completed'
                   AND updated_at >= DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-01')
                   AND updated_at < DATE_ADD(DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-01'), INTERVAL 1 MONTH)"
            );
            $monthlyQuery->execute(['user_id' => $userId]);
            $monthly = $monthlyQuery->fetch();
            if ((int) $monthly['spend'] >= $settings['monthly_spend_threshold_vnd']) {
                $this->issueReward(
                    $userId,
                    'monthly_spend',
                    (string) $monthly['period_key'],
                    'monthly_milestone',
                    $settings['monthly_reward_vnd'],
                    0,
                    'Cảm ơn bạn đã đạt mốc chi tiêu tháng.'
                );
            }

            $referralQuery = $this->database->prepare(
                "SELECT referral.id, referral.referrer_user_id, referrer.phone AS referrer_phone,
                        referred.phone AS referred_phone, referred.email_verified_at AS referred_verified_at,
                        referrer.email_verified_at AS referrer_verified_at,
                        referrer.status AS referrer_status
                 FROM customer_referrals referral
                 JOIN users referrer ON referrer.id = referral.referrer_user_id
                 JOIN users referred ON referred.id = referral.referred_user_id
                 WHERE referral.referred_user_id = :user_id AND referral.status = 'pending'
                 FOR UPDATE"
            );
            $referralQuery->execute(['user_id' => $userId]);
            $referral = $referralQuery->fetch();
            $qualifyingOrderValue = max(0, (int) $order['subtotal'] - (int) $order['discount_amount']);
            if (!$referral || $qualifyingOrderValue < $settings['referral_order_minimum_vnd']
                || $referral['referrer_status'] !== 'active'
                || $referral['referrer_verified_at'] === null
                || $referral['referred_verified_at'] === null
                || $this->phoneDigits((string) $referral['referrer_phone']) === ''
                || $this->phoneDigits((string) $referral['referrer_phone'])
                    === $this->phoneDigits((string) $referral['referred_phone'])) {
                return;
            }

            $referralId = (int) $referral['id'];
            $rewardAmount = $settings['referral_reward_vnd'];
            $this->issueReward(
                (int) $referral['referrer_user_id'],
                'referral',
                'referral:' . $referralId . ':referrer',
                'referral',
                $rewardAmount,
                0,
                'Cảm ơn bạn đã giới thiệu khách hàng mới.'
            );
            $this->issueReward(
                $userId,
                'referral',
                'referral:' . $referralId . ':referred',
                'referral',
                $rewardAmount,
                0,
                'Cảm ơn bạn đã tham gia qua lời mời của bạn bè.'
            );
            $this->database->prepare(
                "UPDATE customer_referrals
                 SET status = 'rewarded', qualifying_order_id = :order_id, rewarded_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = 'pending'"
            )->execute(['order_id' => $orderId, 'id' => $referralId]);
        });
    }

    private function issueReward(
        int $userId,
        string $mission,
        string $referenceKey,
        string $source,
        int $amount,
        int $minimumOrder,
        string $description
    ): bool {
        $existing = $this->database->prepare(
            'SELECT id FROM customer_mission_rewards
             WHERE user_id = :user_id AND mission_code = :mission AND reference_key = :reference_key
             FOR UPDATE'
        );
        $existing->execute([
            'user_id' => $userId,
            'mission' => $mission,
            'reference_key' => $referenceKey,
        ]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }

        $prefix = match ($mission) {
            'review' => 'RV',
            'monthly_spend' => 'MS',
            'referral' => 'RF',
            default => throw new InvalidArgumentException('Loại nhiệm vụ voucher không hợp lệ.'),
        };
        $code = $prefix . strtoupper(bin2hex(random_bytes(8)));
        $voucherInsert = $this->database->prepare(
            "INSERT INTO vouchers
                (code, description, discount_type, discount_value, minimum_order, maximum_discount,
                 usage_limit, per_user_limit, starts_at, ends_at, status, audience, auto_rule, valid_days_after_grant)
             VALUES
                (:code, :description, 'fixed', :amount, :minimum_order, NULL,
                 1, 1, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY),
                 'active', 'targeted', 'none', 30)"
        );
        $voucherInsert->execute([
            'code' => $code,
            'description' => $description,
            'amount' => $amount,
            'minimum_order' => $minimumOrder,
        ]);
        $voucherId = (int) $this->database->lastInsertId();
        $this->database->prepare(
            'INSERT INTO user_vouchers (user_id, voucher_id, source, expires_at)
             VALUES (:user_id, :voucher_id, :source, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))'
        )->execute([
            'user_id' => $userId,
            'voucher_id' => $voucherId,
            'source' => $source,
        ]);
        $this->database->prepare(
            'INSERT INTO customer_mission_rewards
                (user_id, mission_code, reference_key, voucher_id)
             VALUES (:user_id, :mission, :reference_key, :voucher_id)'
        )->execute([
            'user_id' => $userId,
            'mission' => $mission,
            'reference_key' => $referenceKey,
            'voucher_id' => $voucherId,
        ]);

        $details = $this->database->prepare(
            'SELECT uv.expires_at, v.ends_at, v.code, v.discount_type, v.discount_value, v.maximum_discount
             FROM user_vouchers uv JOIN vouchers v ON v.id = uv.voucher_id
             WHERE uv.user_id = :user_id AND uv.voucher_id = :voucher_id'
        );
        $details->execute(['user_id' => $userId, 'voucher_id' => $voucherId]);
        $voucher = $details->fetch();
        if (!$voucher) {
            throw new RuntimeException('Không thể tải voucher nhiệm vụ vừa tạo.');
        }
        (new NotificationService($this->database))->notifyVoucherGranted($userId, $voucher);
        AuditLog::record(
            null,
            'customer_mission_rewarded',
            'customer_mission',
            $voucherId,
            'user_id=' . $userId . '; mission=' . $mission . '; reference=' . $referenceKey,
            $this->database
        );
        return true;
    }

    private function isVerifiedActiveCustomer(int $userId, bool $lock = false): bool
    {
        $sql = "SELECT id FROM users
                WHERE id = :id AND role = 'customer' AND status = 'active'
                  AND email_verified_at IS NOT NULL";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->database->prepare($sql);
        $query->execute(['id' => $userId]);
        return $query->fetchColumn() !== false;
    }

    private function referralCodeForUser(int $userId): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->database->beginTransaction();
            try {
                if (!$this->isVerifiedActiveCustomer($userId, true)) {
                    throw new InvalidArgumentException('Chỉ khách hàng đã xác minh email mới có mã giới thiệu.');
                }
                $existing = $this->database->prepare(
                    'SELECT code FROM customer_referral_codes WHERE user_id = :user_id FOR UPDATE'
                );
                $existing->execute(['user_id' => $userId]);
                $code = $existing->fetchColumn();
                if ($code !== false) {
                    $this->database->commit();
                    return (string) $code;
                }

                $code = 'NS' . strtoupper(bin2hex(random_bytes(5)));
                $this->database->prepare(
                    'INSERT INTO customer_referral_codes (user_id, code) VALUES (:user_id, :code)'
                )->execute(['user_id' => $userId, 'code' => $code]);
                $this->database->commit();
                return $code;
            } catch (PDOException $exception) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }
                if ((string) $exception->getCode() !== '23000' || $attempt === 4) {
                    throw $exception;
                }
            } catch (Throwable $exception) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }
                throw $exception;
            }
        }
        throw new RuntimeException('Không thể tạo mã giới thiệu.');
    }

    private function referrerForCode(string $code, string $phone, ?int $excludedUserId = null): array
    {
        if (preg_match('/\ANS[A-F0-9]{10}\z/', $code) !== 1) {
            throw new InvalidArgumentException('Mã giới thiệu không đúng định dạng.');
        }
        $query = $this->database->prepare(
            "SELECT u.id AS user_id, u.phone
             FROM customer_referral_codes c
             JOIN users u ON u.id = c.user_id
             WHERE c.code = :code AND u.role = 'customer' AND u.status = 'active'
               AND u.email_verified_at IS NOT NULL"
        );
        $query->execute(['code' => $code]);
        $referrer = $query->fetch();
        if (!$referrer || ($excludedUserId !== null && (int) $referrer['user_id'] === $excludedUserId)) {
            throw new InvalidArgumentException('Mã giới thiệu không tồn tại hoặc không còn hoạt động.');
        }
        $referrerPhone = $this->phoneDigits((string) ($referrer['phone'] ?? ''));
        $customerPhone = $this->phoneDigits($phone);
        if ($referrerPhone !== '' && $customerPhone !== '' && $referrerPhone === $customerPhone) {
            throw new InvalidArgumentException('Không thể dùng mã giới thiệu của chính mình.');
        }
        return $referrer;
    }

    private function phoneDigits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function withinTransaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }
}
