<?php

declare(strict_types=1);

final class CodRiskService
{
    public const BLOCKED_MESSAGE = 'Phương thức thanh toán khi nhận hàng (COD) không khả dụng cho đơn này. Vui lòng thanh toán trước bằng VietQR hoặc VNPay.';

    private const DEFAULTS = [
        'cod_risk_failure_days' => 180,
        'cod_risk_failure_threshold' => 2,
        'cod_risk_new_customer_limit' => 300000,
        'cod_risk_open_orders_limit' => 2,
    ];

    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function settings(): array
    {
        $settings = self::DEFAULTS;
        $keys = array_keys($settings);
        $query = $this->database->prepare(
            'SELECT setting_key, setting_value FROM settings
             WHERE setting_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ')'
        );
        $query->execute($keys);
        foreach ($query->fetchAll() as $row) {
            $key = (string) $row['setting_key'];
            $value = filter_var($row['setting_value'], FILTER_VALIDATE_INT);
            if ($value !== false && $value > 0) {
                $settings[$key] = $value;
            }
        }
        return $settings;
    }

    public function saveSettings(array $input, int $actorId): void
    {
        $ranges = [
            'cod_risk_failure_days' => [1, 3650, 'Số ngày xét lịch sử phải từ 1 đến 3650.'],
            'cod_risk_failure_threshold' => [1, 100, 'Số lần giao thất bại phải từ 1 đến 100.'],
            'cod_risk_new_customer_limit' => [1, 100000000, 'Ngưỡng COD cho tài khoản mới phải từ 1 đến 100.000.000 đ.'],
            'cod_risk_open_orders_limit' => [1, 100, 'Số đơn COD đang mở phải từ 1 đến 100.'],
        ];
        $values = [];
        foreach ($ranges as $key => [$minimum, $maximum, $message]) {
            $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < $minimum || $value > $maximum) {
                throw new InvalidArgumentException($message);
            }
            $values[$key] = $value;
        }

        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO settings (setting_key, setting_value, description)
                 VALUES (:setting_key, :setting_value, :description)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($values as $key => $value) {
                $statement->execute([
                    'setting_key' => $key,
                    'setting_value' => (string) $value,
                    'description' => $this->settingDescription($key),
                ]);
            }
            AuditLog::record(
                $actorId,
                'cod_risk_settings_updated',
                'settings',
                null,
                implode('; ', array_map(
                    static fn (string $key, int $value): string => $key . '=' . $value,
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

    public function lockOrderIdentities(int $userId, string $phone, string $address): array
    {
        $keys = ['user:' . $userId];
        $normalizedPhone = DeliveryFailureNormalizer::normalizePhone($phone);
        if ($normalizedPhone !== '') {
            $keys[] = 'phone:' . $normalizedPhone;
        }
        if (trim($address) !== '') {
            $keys[] = 'address:' . DeliveryFailureNormalizer::addressKey($address);
        }
        return $this->acquireLocks($keys);
    }

    public function releaseLocks(array $locks): void
    {
        foreach (array_reverse($locks) as $lock) {
            try {
                $query = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $query->execute(['lock_name' => $lock]);
            } catch (Throwable $exception) {
                error_log('COD risk lock release failed: ' . $exception->getMessage());
            }
        }
    }

    public function assess(int $userId, string $phone, string $address, int $totalAmount, bool $lockOpenOrders = false): array
    {
        $settings = $this->settings();
        $phoneNorm = DeliveryFailureNormalizer::normalizePhone($phone);
        $addressKey = trim($address) === '' ? '' : DeliveryFailureNormalizer::addressKey($address);
        $userFailureCount = $this->failureCount('user', (string) $userId, $settings['cod_risk_failure_days']);
        $phoneFailureCount = $phoneNorm === ''
            ? 0
            : $this->failureCount('phone', $phoneNorm, $settings['cod_risk_failure_days']);
        $addressFailureCount = $addressKey === ''
            ? 0
            : $this->failureCount('address', $addressKey, $settings['cod_risk_failure_days']);
        $threshold = $settings['cod_risk_failure_threshold'];
        $ruleA = max($userFailureCount, $phoneFailureCount, $addressFailureCount) >= $threshold;
        $userRuleA = $userFailureCount >= $threshold;

        $deliveredQuery = $this->database->prepare(
            "SELECT id FROM orders WHERE user_id = :user_id AND status = 'completed' LIMIT 1 FOR UPDATE"
        );
        $deliveredQuery->execute(['user_id' => $userId]);
        $isNewCustomer = $deliveredQuery->fetchColumn() === false;
        $newCustomerLimit = $settings['cod_risk_new_customer_limit'];
        $ruleB = $isNewCustomer && $totalAmount > $newCustomerLimit;

        $openOrders = $this->openCodOrderCount($userId, $phoneNorm, $addressKey, $lockOpenOrders);
        $ruleC = $openOrders >= $settings['cod_risk_open_orders_limit'];
        $allowed = !$ruleA && !$ruleB && !$ruleC;
        $message = '';
        if ($ruleA || $ruleC) {
            $message = self::BLOCKED_MESSAGE;
        } elseif ($ruleB) {
            $message = 'Tài khoản mới chỉ dùng COD cho đơn đến '
                . number_format($newCustomerLimit, 0, ',', '.') . ' đ.';
        }

        return [
            'allowed' => $allowed,
            'message' => $message,
            'user_rule_a' => $userRuleA,
            'rule_a' => $ruleA,
            'rule_b' => $ruleB,
            'rule_c' => $ruleC,
        ];
    }

    public function assessCheckoutInput(int $userId, array $input, int $totalAmount): array
    {
        $addressId = filter_var($input['address_id'] ?? null, FILTER_VALIDATE_INT);
        if ($addressId !== false && $addressId > 0) {
            $query = $this->database->prepare(
                'SELECT a.phone, a.address_line, a.ward, r.name AS region_name
                 FROM addresses a JOIN regions r ON r.id = a.region_id
                 WHERE a.id = :id AND a.user_id = :user_id AND r.is_active = 1'
            );
            $query->execute(['id' => $addressId, 'user_id' => $userId]);
            $savedAddress = $query->fetch();
            if ($savedAddress) {
                $phone = (string) $savedAddress['phone'];
                $address = implode(', ', array_filter([
                    $savedAddress['address_line'],
                    $savedAddress['ward'],
                    $savedAddress['region_name'],
                ]));
                return $this->assess($userId, $phone, $address, $totalAmount);
            }
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $regionId = filter_var($input['region_id'] ?? null, FILTER_VALIDATE_INT);
        $regionName = '';
        if ($regionId !== false && $regionId > 0) {
            $regionQuery = $this->database->prepare(
                'SELECT name FROM regions WHERE id = :id AND level = 2 AND is_active = 1'
            );
            $regionQuery->execute(['id' => $regionId]);
            $regionName = (string) ($regionQuery->fetchColumn() ?: '');
        }
        $address = implode(', ', array_filter([
            trim((string) ($input['address_line'] ?? '')),
            trim((string) ($input['ward'] ?? '')),
            $regionName,
        ]));
        return $this->assess($userId, $phone, $address, $totalAmount);
    }

    public function notifyUserRuleAOnceSafely(int $userId): void
    {
        try {
            $settings = $this->settings();
            if ($this->failureCount('user', (string) $userId, $settings['cod_risk_failure_days'])
                < $settings['cod_risk_failure_threshold']) {
                return;
            }
            (new NotificationService($this->database))->notifyCodRiskOnce($userId);
        } catch (Throwable $exception) {
            error_log('COD risk customer notification failed (user=' . $userId . '): ' . $exception->getMessage());
        }
    }

    public function waive(string $type, string $value, string $reason, int $adminId): void
    {
        $reason = trim($reason);
        if (!in_array($type, ['user', 'phone', 'address'], true)) {
            throw new InvalidArgumentException('Loại định danh gỡ chặn không hợp lệ.');
        }
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 450) {
            throw new InvalidArgumentException('Lý do gỡ chặn là bắt buộc và không quá 450 ký tự.');
        }
        $value = $this->normalizeWaiverValue($type, $value);
        $locks = $this->acquireLocks([$type . ':' . $value]);
        try {
            $this->database->beginTransaction();
            $settings = $this->settings();
            if ($this->failureCount($type, $value, $settings['cod_risk_failure_days'])
                < $settings['cod_risk_failure_threshold']) {
                throw new InvalidArgumentException('Định danh này hiện không còn bị chặn theo Luật A.');
            }
            $insert = $this->database->prepare(
                'INSERT INTO cod_waivers (type, value, reason, created_by)
                 VALUES (:type, :value, :reason, :created_by)'
            );
            $insert->execute([
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'created_by' => $adminId,
            ]);
            $waiverId = (int) $this->database->lastInsertId();
            AuditLog::record(
                $adminId,
                'cod_risk_waived',
                'cod_waiver',
                $waiverId,
                'type=' . $type . '; identifier_hash=' . hash('sha256', $value) . '; reason=' . $reason,
                $this->database
            );
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->releaseLocks($locks);
            throw $exception;
        }
        $this->releaseLocks($locks);
    }

    public function activeBlocks(int $requestedPage = 1, int $perPage = 20): array
    {
        $perPage = min(max($perPage, 1), 100);
        $settings = $this->settings();
        $baseQuery = $this->blockedIdentitiesQuery(
            $settings['cod_risk_failure_days'],
            $settings['cod_risk_failure_threshold']
        );
        $total = (int) $this->database->query('SELECT COUNT(*) FROM (' . $baseQuery . ') blocked')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max($requestedPage, 1), $pages);
        $query = $this->database->prepare(
            'SELECT * FROM (' . $baseQuery . ') blocked
             ORDER BY last_failure_at DESC, type, value
             LIMIT :limit OFFSET :offset'
        );
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        $blocks = $query->fetchAll();
        foreach ($blocks as &$block) {
            $block['display_value'] = $this->displayIdentity(
                (string) $block['type'],
                (string) $block['value'],
                (int) $block['latest_id']
            );
        }
        unset($block);
        return ['items' => $blocks, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function recentWaivers(int $limit = 20): array
    {
        $query = $this->database->prepare(
            'SELECT w.*, u.name AS admin_name
             FROM cod_waivers w JOIN users u ON u.id = w.created_by
             ORDER BY w.created_at DESC, w.id DESC LIMIT :limit'
        );
        $query->bindValue(':limit', min(max($limit, 1), 100), PDO::PARAM_INT);
        $query->execute();
        $waivers = $query->fetchAll();
        foreach ($waivers as &$waiver) {
            $waiver['display_value'] = $this->displayIdentity(
                (string) $waiver['type'],
                (string) $waiver['value']
            );
        }
        unset($waiver);
        return $waivers;
    }

    private function acquireLocks(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);
        $acquired = [];
        try {
            foreach ($keys as $key) {
                $lockName = 'codrisk:' . substr(hash('sha256', $key), 0, 56);
                $query = $this->database->prepare('SELECT GET_LOCK(:lock_name, 10)');
                $query->execute(['lock_name' => $lockName]);
                if ((int) $query->fetchColumn() !== 1) {
                    throw new RuntimeException('Timed out while acquiring a COD risk identity lock.');
                }
                $acquired[] = $lockName;
            }
            return $acquired;
        } catch (Throwable $exception) {
            $this->releaseLocks($acquired);
            error_log('COD risk identity lock failed: ' . $exception->getMessage());
            throw new PricingException(self::BLOCKED_MESSAGE);
        }
    }

    private function failureCount(string $type, string $value, int $days): int
    {
        $where = match ($type) {
            'user' => 'df.user_id = :identity',
            'phone' => 'df.phone_norm = :identity',
            'address' => 'df.address_key = :identity',
            default => throw new InvalidArgumentException('Loại định danh không hợp lệ.'),
        };
        $waiver = $this->database->prepare(
            'SELECT created_at FROM cod_waivers
             WHERE type = :type AND value = :value
             ORDER BY created_at DESC, id DESC LIMIT 1 FOR UPDATE'
        );
        $waiver->execute(['type' => $type, 'value' => $value]);
        $waiverAt = $waiver->fetchColumn();
        $waiverCondition = $waiverAt === false ? '' : ' AND df.created_at > :waiver_at';
        $query = $this->database->prepare(
            'SELECT df.id FROM delivery_failures df
             WHERE df.counts_against_customer = 1
               AND df.created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
               AND ' . $where . '
               ' . $waiverCondition . '
             ORDER BY df.id FOR UPDATE'
        );
        $parameters = ['identity' => $type === 'user' ? (int) $value : $value];
        if ($waiverAt !== false) {
            $parameters['waiver_at'] = $waiverAt;
        }
        $query->execute($parameters);
        return count($query->fetchAll(PDO::FETCH_COLUMN));
    }

    private function openCodOrderCount(int $userId, string $phoneNorm, string $addressKey, bool $lock): int
    {
        $sql = "SELECT o.user_id, o.recipient_phone, o.shipping_address
                FROM orders o
                WHERE o.status IN ('pending', 'confirmed', 'processing', 'shipping')
                  AND EXISTS (
                      SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.method = 'cod'
                  )";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->database->query($sql);
        $count = 0;
        foreach ($query->fetchAll() as $order) {
            $matches = (int) $order['user_id'] === $userId;
            if (!$matches && $phoneNorm !== '') {
                $matches = DeliveryFailureNormalizer::normalizePhone((string) $order['recipient_phone']) === $phoneNorm;
            }
            if (!$matches && $addressKey !== '') {
                $matches = DeliveryFailureNormalizer::addressKey((string) $order['shipping_address']) === $addressKey;
            }
            if ($matches) {
                $count++;
            }
        }
        return $count;
    }

    private function blockedIdentitiesQuery(int $days, int $threshold): string
    {
        $cutoff = '\'1000-01-01 00:00:00\'';
        $user = "SELECT _utf8mb4'user' COLLATE utf8mb4_unicode_ci AS type,
                        CONVERT(CAST(df.user_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS value,
                        COUNT(*) AS failure_count, MAX(df.created_at) AS last_failure_at,
                        CAST(SUBSTRING_INDEX(GROUP_CONCAT(df.id ORDER BY df.created_at DESC, df.id DESC), ',', 1) AS UNSIGNED) AS latest_id
                 FROM delivery_failures df
                 WHERE df.counts_against_customer = 1
                   AND df.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                   AND df.created_at > COALESCE(
                       (SELECT MAX(w.created_at) FROM cod_waivers w
                        WHERE w.type COLLATE utf8mb4_unicode_ci = _utf8mb4'user' COLLATE utf8mb4_unicode_ci
                          AND CAST(w.value AS UNSIGNED) = df.user_id), {$cutoff})
                 GROUP BY df.user_id HAVING COUNT(*) >= {$threshold}";
        $phone = "SELECT _utf8mb4'phone' COLLATE utf8mb4_unicode_ci AS type,
                         CONVERT(df.phone_norm USING utf8mb4) COLLATE utf8mb4_unicode_ci AS value,
                         COUNT(*) AS failure_count, MAX(df.created_at) AS last_failure_at,
                         CAST(SUBSTRING_INDEX(GROUP_CONCAT(df.id ORDER BY df.created_at DESC, df.id DESC), ',', 1) AS UNSIGNED) AS latest_id
                  FROM delivery_failures df
                  WHERE df.counts_against_customer = 1
                    AND df.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    AND df.created_at > COALESCE(
                        (SELECT MAX(w.created_at) FROM cod_waivers w
                         WHERE w.type COLLATE utf8mb4_unicode_ci = _utf8mb4'phone' COLLATE utf8mb4_unicode_ci
                           AND w.value COLLATE utf8mb4_unicode_ci = df.phone_norm COLLATE utf8mb4_unicode_ci), {$cutoff})
                  GROUP BY df.phone_norm HAVING COUNT(*) >= {$threshold}";
        $address = "SELECT _utf8mb4'address' COLLATE utf8mb4_unicode_ci AS type,
                           CONVERT(df.address_key USING utf8mb4) COLLATE utf8mb4_unicode_ci AS value,
                           COUNT(*) AS failure_count, MAX(df.created_at) AS last_failure_at,
                           CAST(SUBSTRING_INDEX(GROUP_CONCAT(df.id ORDER BY df.created_at DESC, df.id DESC), ',', 1) AS UNSIGNED) AS latest_id
                    FROM delivery_failures df
                    WHERE df.counts_against_customer = 1
                      AND df.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                      AND df.created_at > COALESCE(
                          (SELECT MAX(w.created_at) FROM cod_waivers w
                           WHERE w.type COLLATE utf8mb4_unicode_ci = _utf8mb4'address' COLLATE utf8mb4_unicode_ci
                             AND w.value COLLATE utf8mb4_unicode_ci = df.address_key COLLATE utf8mb4_unicode_ci), {$cutoff})
                    GROUP BY df.address_key HAVING COUNT(*) >= {$threshold}";
        return $user . ' UNION ALL ' . $phone . ' UNION ALL ' . $address;
    }

    private function displayIdentity(string $type, string $value, ?int $failureId = null): string
    {
        if ($type === 'user') {
            $query = $this->database->prepare('SELECT name, email FROM users WHERE id = :id');
            $query->execute(['id' => (int) $value]);
            $user = $query->fetch();
            return $user ? (string) $user['name'] . ' · ' . (string) $user['email'] : 'Khách hàng #' . (int) $value;
        }
        if ($type === 'phone') {
            return $value;
        }
        $sql = 'SELECT o.shipping_address FROM delivery_failures df
                JOIN orders o ON o.id = df.order_id
                WHERE df.address_key = :value';
        if ($failureId !== null) {
            $sql .= ' AND df.id = :failure_id';
        }
        $sql .= ' ORDER BY df.created_at DESC, df.id DESC LIMIT 1';
        $query = $this->database->prepare($sql);
        $parameters = ['value' => $value];
        if ($failureId !== null) {
            $parameters['failure_id'] = $failureId;
        }
        $query->execute($parameters);
        $address = $query->fetchColumn();
        return $address === false ? 'Địa chỉ (' . substr($value, 0, 12) . '…)' : (string) $address;
    }

    private function normalizeWaiverValue(string $type, string $value): string
    {
        if ($type === 'user') {
            $userId = filter_var($value, FILTER_VALIDATE_INT);
            if ($userId === false || $userId < 1) {
                throw new InvalidArgumentException('Mã khách hàng không hợp lệ.');
            }
            $query = $this->database->prepare("SELECT id FROM users WHERE id = :id AND role = 'customer'");
            $query->execute(['id' => $userId]);
            if ($query->fetchColumn() === false) {
                throw new InvalidArgumentException('Không tìm thấy khách hàng.');
            }
            return (string) $userId;
        }
        if ($type === 'phone') {
            $phone = DeliveryFailureNormalizer::normalizePhone($value);
            if ($phone === '') {
                throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
            }
            return $phone;
        }
        $addressKey = strtolower(trim($value));
        if (preg_match('/\A[a-f0-9]{64}\z/', $addressKey) !== 1) {
            throw new InvalidArgumentException('Mã địa chỉ không hợp lệ.');
        }
        return $addressKey;
    }

    private function settingDescription(string $key): string
    {
        return [
            'cod_risk_failure_days' => 'Số ngày xét lịch sử giao thất bại COD',
            'cod_risk_failure_threshold' => 'Số lần giao thất bại để chặn COD',
            'cod_risk_new_customer_limit' => 'Giới hạn COD cho tài khoản chưa có đơn giao thành công',
            'cod_risk_open_orders_limit' => 'Số đơn COD đang mở tối đa theo định danh',
        ][$key] ?? $key;
    }
}
