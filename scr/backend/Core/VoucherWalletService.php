<?php

declare(strict_types=1);

final class VoucherWalletService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function assertCanUseCode(int $userId, string $code, bool $lock = false): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }

        $sql = 'SELECT id, audience FROM vouchers WHERE code = :code';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $voucherQuery = $this->database->prepare($sql);
        $voucherQuery->execute(['code' => $code]);
        $voucher = $voucherQuery->fetch();
        if (!$voucher || $voucher['audience'] !== 'targeted') {
            return;
        }

        $walletSql = 'SELECT uv.used_order_id, uv.used_at, uv.expires_at,
                             COALESCE(uv.expires_at, v.ends_at) >= NOW() AS not_expired
                      FROM user_vouchers uv
                      JOIN vouchers v ON v.id = uv.voucher_id
                      WHERE uv.user_id = :user_id AND uv.voucher_id = :voucher_id';
        if ($lock) {
            $walletSql .= ' FOR UPDATE';
        }
        $walletQuery = $this->database->prepare($walletSql);
        $walletQuery->execute(['user_id' => $userId, 'voucher_id' => $voucher['id']]);
        $walletItem = $walletQuery->fetch();
        if (!$walletItem) {
            throw new PricingException('Mã này chỉ dành cho khách được tặng.');
        }
        if ($walletItem['used_order_id'] !== null || $walletItem['used_at'] !== null) {
            throw new PricingException('Voucher trong ví đã được sử dụng.');
        }
        if (!(int) $walletItem['not_expired']) {
            throw new PricingException('Voucher trong ví đã hết hạn.');
        }
    }

    public function walletForUser(int $userId): array
    {
        $query = $this->database->prepare(
            'SELECT uv.id AS user_voucher_id, uv.source, uv.granted_at, uv.expires_at,
                    uv.used_order_id, uv.used_at, v.id AS voucher_id, v.code, v.description,
                    v.discount_type, v.discount_value, v.maximum_discount, v.minimum_order,
                    v.usage_limit, v.used_count, v.per_user_limit, v.starts_at, v.ends_at, v.status,
                    COALESCE(uv.expires_at, v.ends_at) < NOW() AS wallet_expired,
                    v.starts_at > NOW() AS not_started,
                    v.ends_at < NOW() AS voucher_expired,
                    v.status <> \'active\' AS disabled,
                    (v.usage_limit IS NOT NULL AND v.used_count >= v.usage_limit) AS no_uses_left,
                    (SELECT COUNT(*) FROM voucher_usages vu
                     WHERE vu.voucher_id = v.id AND vu.user_id = uv.user_id AND vu.status = \'used\') AS user_used_count,
                    (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR \', \')
                     FROM voucher_categories vc JOIN categories c ON c.id = vc.category_id
                     WHERE vc.voucher_id = v.id) AS category_names,
                    (SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR \', \')
                     FROM voucher_products vp JOIN products p ON p.id = vp.product_id
                     WHERE vp.voucher_id = v.id) AS product_names,
                    (SELECT GROUP_CONCAT(DISTINCT vc.category_id)
                     FROM voucher_categories vc WHERE vc.voucher_id = v.id) AS category_ids,
                    (SELECT GROUP_CONCAT(DISTINCT vp.product_id)
                     FROM voucher_products vp WHERE vp.voucher_id = v.id) AS product_ids
             FROM user_vouchers uv
             JOIN vouchers v ON v.id = uv.voucher_id
             WHERE uv.user_id = :user_id
             ORDER BY uv.granted_at DESC, uv.id DESC'
        );
        $query->execute(['user_id' => $userId]);
        $vouchers = $query->fetchAll();
        foreach ($vouchers as &$voucher) {
            if ($voucher['used_order_id'] !== null || $voucher['used_at'] !== null) {
                $voucher['wallet_status'] = 'used';
                $voucher['status_reason'] = 'Đã sử dụng';
            } elseif ((int) $voucher['wallet_expired'] || (int) $voucher['voucher_expired']) {
                $voucher['wallet_status'] = 'expired';
                $voucher['status_reason'] = 'Đã hết hạn';
            } elseif ((int) $voucher['disabled']) {
                $voucher['wallet_status'] = 'expired';
                $voucher['status_reason'] = 'Voucher đang tạm tắt';
            } elseif ((int) $voucher['not_started']) {
                $voucher['wallet_status'] = 'expired';
                $voucher['status_reason'] = 'Voucher chưa bắt đầu';
            } elseif ((int) $voucher['no_uses_left']) {
                $voucher['wallet_status'] = 'expired';
                $voucher['status_reason'] = 'Voucher đã hết lượt';
            } elseif ((int) $voucher['user_used_count'] >= (int) $voucher['per_user_limit']) {
                $voucher['wallet_status'] = 'expired';
                $voucher['status_reason'] = 'Bạn đã dùng đủ lượt';
            } else {
                $voucher['wallet_status'] = 'available';
                $voucher['status_reason'] = '';
            }
        }
        unset($voucher);
        return $vouchers;
    }

    public function publicVouchers(?int $userId = null, ?int $limit = null): array
    {
        $sql = 'SELECT v.*,
                       EXISTS(SELECT 1 FROM user_vouchers uv
                              WHERE uv.user_id = :user_id AND uv.voucher_id = v.id) AS is_saved,
                       (SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR \', \')
                        FROM voucher_categories vc JOIN categories c ON c.id = vc.category_id
                        WHERE vc.voucher_id = v.id) AS category_names,
                       (SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR \', \')
                        FROM voucher_products vp JOIN products p ON p.id = vp.product_id
                        WHERE vp.voucher_id = v.id) AS product_names
                FROM vouchers v
                WHERE v.audience = \'public\' AND v.status = \'active\'
                  AND v.starts_at <= NOW() AND v.ends_at >= NOW()
                  AND (v.usage_limit IS NULL OR v.used_count < v.usage_limit)
                ORDER BY v.ends_at, v.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }
        $query = $this->database->prepare($sql);
        $query->bindValue(':user_id', $userId ?? 0, PDO::PARAM_INT);
        if ($limit !== null) {
            $query->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        }
        $query->execute();
        return $query->fetchAll();
    }

    public function claimPublicVoucher(int $userId, int $voucherId): bool
    {
        $this->database->beginTransaction();
        try {
            $userQuery = $this->database->prepare(
                "SELECT id FROM users WHERE id = :id AND role = 'customer' AND status = 'active' FOR UPDATE"
            );
            $userQuery->execute(['id' => $userId]);
            if ($userQuery->fetchColumn() === false) {
                throw new InvalidArgumentException('Chỉ khách hàng đang hoạt động mới có thể lưu voucher.');
            }
            $voucherQuery = $this->database->prepare(
                'SELECT *, starts_at <= NOW() AS is_started, ends_at >= NOW() AS not_expired
                 FROM vouchers WHERE id = :id FOR UPDATE'
            );
            $voucherQuery->execute(['id' => $voucherId]);
            $voucher = $voucherQuery->fetch();
            if (!$voucher) {
                throw new InvalidArgumentException('Không tìm thấy voucher.');
            }
            if ($voucher['audience'] !== 'public') {
                throw new InvalidArgumentException('Voucher này không được mở trong Kho voucher.');
            }

            $existingQuery = $this->database->prepare(
                'SELECT id FROM user_vouchers WHERE user_id = :user_id AND voucher_id = :voucher_id FOR UPDATE'
            );
            $existingQuery->execute(['user_id' => $userId, 'voucher_id' => $voucherId]);
            if ($existingQuery->fetchColumn() !== false) {
                $this->database->commit();
                return false;
            }
            if ($voucher['status'] !== 'active' || !(int) $voucher['is_started'] || !(int) $voucher['not_expired']) {
                throw new InvalidArgumentException('Voucher đã hết hạn hoặc chưa đến thời gian áp dụng.');
            }
            if ($voucher['usage_limit'] !== null && (int) $voucher['used_count'] >= (int) $voucher['usage_limit']) {
                throw new InvalidArgumentException('Voucher đã hết lượt sử dụng.');
            }

            $granted = $this->grantOne($userId, $voucher, 'claimed');
            if ($granted) {
                AuditLog::record(
                    $userId,
                    'voucher_claimed',
                    'voucher',
                    $voucherId,
                    'code=' . (string) $voucher['code'],
                    $this->database
                );
            }
            $this->database->commit();
            return $granted;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function checkoutOptions(int $userId, array $items, int $subtotal): array
    {
        $options = [];
        foreach ($this->walletForUser($userId) as $voucher) {
            if ($voucher['wallet_status'] !== 'available') {
                continue;
            }
            $reason = '';
            $minimum = PricingService::moneyToVnd((string) $voucher['minimum_order']);
            if ($subtotal < $minimum) {
                $reason = 'Cần mua thêm ' . number_format($minimum - $subtotal, 0, ',', '.') . ' đ';
            } else {
                $categoryIds = array_map('intval', array_filter(explode(',', (string) $voucher['category_ids'])));
                $productIds = array_map('intval', array_filter(explode(',', (string) $voucher['product_ids'])));
                if ($categoryIds || $productIds) {
                    $eligibleSubtotal = 0;
                    foreach ($items as $item) {
                        if (in_array((int) ($item['product_id'] ?? 0), $productIds, true)
                            || in_array((int) ($item['category_id'] ?? 0), $categoryIds, true)) {
                            $eligibleSubtotal += PricingService::lineTotal(
                                (string) ($item['unit_price'] ?? $item['current_price'] ?? '0'),
                                (int) ($item['quantity'] ?? 0)
                            );
                        }
                    }
                    if ($eligibleSubtotal < 1) {
                        $reason = 'Không áp dụng cho sản phẩm trong giỏ';
                    }
                }
            }
            $voucher['selection_reason'] = $reason;
            $options[] = $voucher;
        }
        return $options;
    }

    public static function formatDiscount(array $voucher): string
    {
        if ($voucher['discount_type'] === 'percent') {
            $percent = rtrim(rtrim(number_format((float) $voucher['discount_value'], 2, '.', ''), '0'), '.');
            $label = 'Giảm ' . $percent . '%';
            if ($voucher['maximum_discount'] !== null) {
                $label .= ' tối đa ' . number_format(
                    PricingService::moneyToVnd((string) $voucher['maximum_discount']),
                    0,
                    ',',
                    '.'
                ) . ' đ';
            }
            return $label;
        }
        return 'Giảm ' . number_format(
            PricingService::moneyToVnd((string) $voucher['discount_value']),
            0,
            ',',
            '.'
        ) . ' đ';
    }

    public function markUsedIfHeld(int $userId, int $voucherId, int $orderId): void
    {
        $this->database->prepare(
            'UPDATE user_vouchers
             SET used_order_id = :order_id, used_at = NOW()
             WHERE user_id = :user_id AND voucher_id = :voucher_id
               AND used_order_id IS NULL AND used_at IS NULL'
        )->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
            'voucher_id' => $voucherId,
        ]);
    }

    public function releaseUsageForOrder(int $orderId): void
    {
        $this->database->prepare(
            'UPDATE user_vouchers SET used_order_id = NULL, used_at = NULL
             WHERE used_order_id = :order_id'
        )->execute(['order_id' => $orderId]);
    }

    public function grantWelcomeSafely(int $userId): void
    {
        try {
            $this->grantWelcome($userId);
        } catch (Throwable $exception) {
            error_log('Welcome voucher grant failed (user=' . $userId . '): ' . $exception->getMessage());
        }
    }

    public function grantFirstDeliveredOrderSafely(int $userId, int $orderId): void
    {
        $this->grantAutomaticallySafely($userId, 'first_delivered_order', 'first_order', $orderId);
    }

    public function giftToUsers(int $voucherId, array $userIds, bool $allActiveCustomers, int $adminId): array
    {
        if (!$allActiveCustomers && count($userIds) > 100) {
            throw new InvalidArgumentException('Mỗi lần chỉ có thể tặng tối đa 100 khách.');
        }

        $userIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT) ?: 0, $userIds),
            static fn (int $id): bool => $id > 0
        )));
        if (!$allActiveCustomers && !$userIds) {
            throw new InvalidArgumentException('Hãy chọn ít nhất một khách hàng để tặng voucher.');
        }
        $this->database->beginTransaction();
        try {
            $voucherQuery = $this->database->prepare('SELECT * FROM vouchers WHERE id = :id FOR UPDATE');
            $voucherQuery->execute(['id' => $voucherId]);
            $voucher = $voucherQuery->fetch();
            if (!$voucher) {
                throw new InvalidArgumentException('Không tìm thấy voucher.');
            }
            if ($voucher['audience'] !== 'targeted') {
                throw new InvalidArgumentException('Chỉ voucher dành cho khách được tặng mới có thể phát hành thủ công.');
            }

            if ($allActiveCustomers) {
                $userIds = array_map(
                    'intval',
                    $this->database->query(
                        "SELECT id FROM users WHERE role = 'customer' AND status = 'active' ORDER BY id"
                    )->fetchAll(PDO::FETCH_COLUMN)
                );
            }

            $granted = 0;
            $skipped = 0;
            $activeCustomer = $this->database->prepare(
                "SELECT id FROM users WHERE id = :id AND role = 'customer' AND status = 'active'"
            );
            foreach ($userIds as $userId) {
                $activeCustomer->execute(['id' => $userId]);
                if ($activeCustomer->fetchColumn() === false) {
                    $skipped++;
                    continue;
                }
                if ($this->grantOne($userId, $voucher, 'admin_gift')) {
                    $granted++;
                    AuditLog::record(
                        $adminId,
                        'voucher_admin_gifted_user',
                        'voucher',
                        $voucherId,
                        'user_id=' . $userId,
                        $this->database
                    );
                } else {
                    $skipped++;
                }
            }
            AuditLog::record(
                $adminId,
                'voucher_admin_gifted',
                'voucher',
                $voucherId,
                'granted=' . $granted . '; skipped=' . $skipped,
                $this->database
            );
            $this->database->commit();
            return ['granted' => $granted, 'skipped' => $skipped];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function revoke(int $voucherId, int $userVoucherId, int $adminId): void
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare(
                'SELECT user_id, used_order_id, used_at FROM user_vouchers
                 WHERE id = :id AND voucher_id = :voucher_id FOR UPDATE'
            );
            $query->execute(['id' => $userVoucherId, 'voucher_id' => $voucherId]);
            $walletItem = $query->fetch();
            if (!$walletItem) {
                throw new InvalidArgumentException('Không tìm thấy voucher trong ví khách hàng.');
            }
            if ($walletItem['used_order_id'] !== null || $walletItem['used_at'] !== null) {
                AuditLog::record(
                    $adminId,
                    'voucher_holder_revoke_blocked',
                    'voucher',
                    $voucherId,
                    'user_id=' . (int) $walletItem['user_id'] . '; already used',
                    $this->database
                );
                $this->database->commit();
                throw new InvalidArgumentException('Không thể thu hồi voucher đã được sử dụng.');
            }

            AuditLog::record(
                $adminId,
                'voucher_holder_revoked',
                'voucher',
                $voucherId,
                'user_id=' . (int) $walletItem['user_id'],
                $this->database
            );
            $this->database->prepare('DELETE FROM user_vouchers WHERE id = :id')
                ->execute(['id' => $userVoucherId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function grantAutomaticallySafely(int $userId, string $rule, string $source, ?int $orderId = null): void
    {
        try {
            $this->grantAutomatically($userId, $rule, $source);
        } catch (Throwable $exception) {
            error_log(
                'Automatic voucher grant failed (user=' . $userId . ', rule=' . $rule
                . ($orderId === null ? '' : ', order=' . $orderId) . '): ' . $exception->getMessage()
            );
        }
    }

    private function grantAutomatically(int $userId, string $rule, string $source): void
    {
        $this->database->beginTransaction();
        try {
            $userQuery = $this->database->prepare(
                "SELECT id FROM users WHERE id = :id AND role = 'customer' AND status = 'active' FOR UPDATE"
            );
            $userQuery->execute(['id' => $userId]);
            if ($userQuery->fetchColumn() === false) {
                $this->database->commit();
                return;
            }

            $voucherQuery = $this->database->prepare(
                "SELECT * FROM vouchers
                 WHERE audience = 'targeted' AND auto_rule = :rule AND status = 'active'
                   AND starts_at <= NOW() AND ends_at >= NOW()
                   AND (usage_limit IS NULL OR used_count < usage_limit)
                 ORDER BY id FOR UPDATE"
            );
            $voucherQuery->execute(['rule' => $rule]);
            foreach ($voucherQuery->fetchAll() as $voucher) {
                if ($this->grantOne($userId, $voucher, $source)) {
                    AuditLog::record(
                        null,
                        'voucher_auto_granted',
                        'voucher',
                        (int) $voucher['id'],
                        'user_id=' . $userId . '; source=' . $source,
                        $this->database
                    );
                }
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function grantWelcome(int $userId): void
    {
        $this->database->beginTransaction();
        try {
            $userQuery = $this->database->prepare(
                "SELECT id FROM users
                 WHERE id = :id AND role = 'customer' AND status = 'active'
                   AND email_verified_at IS NOT NULL
                 FOR UPDATE"
            );
            $userQuery->execute(['id' => $userId]);
            if ($userQuery->fetchColumn() === false) {
                $this->database->commit();
                return;
            }

            $this->database->exec(
                "INSERT INTO vouchers
                    (code, description, discount_type, discount_value, minimum_order, maximum_discount,
                     usage_limit, per_user_limit, starts_at, ends_at, status, audience, auto_rule, valid_days_after_grant)
                 VALUES
                    ('CHAOMUNG', 'Giảm 10% tối đa 50.000 đồng cho đơn từ 100.000 đồng.',
                     'percent', 10, 100000, 50000, NULL, 1, CURRENT_TIMESTAMP,
                     DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY), 'active', 'targeted', 'welcome', 30)
                 ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    discount_type = VALUES(discount_type),
                    discount_value = VALUES(discount_value),
                    minimum_order = VALUES(minimum_order),
                    maximum_discount = VALUES(maximum_discount),
                    usage_limit = NULL,
                    per_user_limit = 1,
                    starts_at = CURRENT_TIMESTAMP,
                    ends_at = GREATEST(ends_at, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY)),
                    status = 'active',
                    audience = 'targeted',
                    auto_rule = 'welcome',
                    valid_days_after_grant = 30"
            );
            $voucherQuery = $this->database->prepare(
                'SELECT * FROM vouchers WHERE code = :code FOR UPDATE'
            );
            $voucherQuery->execute(['code' => 'CHAOMUNG']);
            $voucher = $voucherQuery->fetch();
            if (!$voucher) {
                throw new RuntimeException('Welcome voucher CHAOMUNG could not be created.');
            }

            $this->database->prepare(
                "UPDATE vouchers SET auto_rule = 'none'
                 WHERE code = 'WELCOME30' AND audience = 'targeted' AND auto_rule = 'welcome'"
            )->execute();
            $welcomeQuery = $this->database->query(
                "SELECT * FROM vouchers
                 WHERE audience = 'targeted' AND auto_rule = 'welcome' AND status = 'active'
                   AND starts_at <= CURRENT_TIMESTAMP AND ends_at >= CURRENT_TIMESTAMP
                   AND (usage_limit IS NULL OR used_count < usage_limit)
                 ORDER BY id FOR UPDATE"
            );
            foreach ($welcomeQuery->fetchAll() as $welcomeVoucher) {
                if ($this->grantOne($userId, $welcomeVoucher, 'welcome')) {
                    AuditLog::record(
                        null,
                        'voucher_auto_granted',
                        'voucher',
                        (int) $welcomeVoucher['id'],
                        'user_id=' . $userId . '; source=welcome',
                        $this->database
                    );
                }
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function grantOne(int $userId, array $voucher, string $source): bool
    {
        $insert = $this->database->prepare(
            'INSERT INTO user_vouchers (user_id, voucher_id, source, expires_at)
             SELECT :user_id, v.id, :source,
                    CASE WHEN v.valid_days_after_grant IS NULL THEN NULL
                         ELSE LEAST(v.ends_at, DATE_ADD(NOW(), INTERVAL v.valid_days_after_grant DAY))
                    END
             FROM vouchers v WHERE v.id = :voucher_id
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(user_vouchers.id)'
        );
        $insert->execute([
            'user_id' => $userId,
            'source' => $source,
            'voucher_id' => (int) $voucher['id'],
        ]);
        if ($insert->rowCount() !== 1) {
            return false;
        }

        $userVoucherId = (int) $this->database->lastInsertId();
        $details = $this->database->prepare(
            'SELECT uv.expires_at, v.ends_at, v.code, v.discount_type, v.discount_value, v.maximum_discount
             FROM user_vouchers uv JOIN vouchers v ON v.id = uv.voucher_id
             WHERE uv.id = :id'
        );
        $details->execute(['id' => $userVoucherId]);
        $voucherDetails = $details->fetch();
        if ($voucherDetails) {
            (new NotificationService($this->database))->notifyVoucherGranted($userId, $voucherDetails);
        }
        return true;
    }
}
