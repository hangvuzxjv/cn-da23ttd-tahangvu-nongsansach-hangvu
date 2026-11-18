<?php

declare(strict_types=1);

final class VoucherAdminService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public static function status(array $voucher, ?DateTimeImmutable $now = null): string
    {
        if (($voucher['status'] ?? '') !== 'active') {
            return 'inactive';
        }

        $now ??= new DateTimeImmutable();
        $timezone = $now->getTimezone();
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $voucher['starts_at'], $timezone);
        $endsAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $voucher['ends_at'], $timezone);
        if ($startsAt !== false && $startsAt > $now) {
            return 'upcoming';
        }
        if ($endsAt !== false && $endsAt < $now) {
            return 'expired';
        }
        if ($voucher['usage_limit'] !== null && (int) $voucher['used_count'] >= (int) $voucher['usage_limit']) {
            return 'exhausted';
        }
        return 'running';
    }

    public function validateInput(array $input, ?int $voucherId = null): array
    {
        $errors = [];
        $code = strtoupper(trim($this->inputString($input, 'code')));
        $type = $this->inputString($input, 'discount_type');
        $value = filter_var($this->inputString($input, 'discount_value'), FILTER_VALIDATE_INT);
        $minimum = filter_var($this->inputString($input, 'minimum_order'), FILTER_VALIDATE_INT);
        $maximumInput = trim($this->inputString($input, 'maximum_discount'));
        $maximum = $maximumInput === '' ? null : filter_var($maximumInput, FILTER_VALIDATE_INT);
        $usageInput = trim($this->inputString($input, 'usage_limit'));
        $usageLimit = $usageInput === '' ? null : filter_var($usageInput, FILTER_VALIDATE_INT);
        $perUser = filter_var($this->inputString($input, 'per_user_limit'), FILTER_VALIDATE_INT);
        $starts = trim($this->inputString($input, 'starts_at'));
        $ends = trim($this->inputString($input, 'ends_at'));
        $status = $this->inputString($input, 'status');
        $audience = $this->inputString($input, 'audience');
        $autoRule = $this->inputString($input, 'auto_rule');
        $validDaysInput = trim($this->inputString($input, 'valid_days_after_grant'));
        $validDays = $validDaysInput === '' ? null : filter_var($validDaysInput, FILTER_VALIDATE_INT);
        $description = trim($this->inputString($input, 'description'));
        [$categoryIds, $categoriesValid] = $this->selectedIds($input['category_ids'] ?? [], 'categories');
        [$productIds, $productsValid] = $this->selectedIds($input['product_ids'] ?? [], 'products');

        if (preg_match('/\A[A-Z0-9_-]{3,30}\z/', $code) !== 1) {
            $errors['code'] = 'Mã gồm 3–30 ký tự in hoa A–Z, số, dấu gạch ngang hoặc gạch dưới.';
        } else {
            $query = $this->database->prepare('SELECT id FROM vouchers WHERE code = :code AND id <> :id LIMIT 1');
            $query->execute(['code' => $code, 'id' => $voucherId ?? 0]);
            if ($query->fetchColumn() !== false) {
                $errors['code'] = 'Mã này đã được sử dụng. Hãy chọn mã khác.';
            }
        }
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $errors['discount_type'] = 'Hãy chọn số tiền hoặc phần trăm.';
        }
        if ($value === false || $value < 1 || ($type === 'percent' && $value > 100)) {
            $errors['discount_value'] = $type === 'percent'
                ? 'Phần trăm giảm phải từ 1 đến 100.'
                : 'Số tiền giảm phải lớn hơn 0.';
        }
        if ($minimum === false || $minimum < 0) {
            $errors['minimum_order'] = 'Giá trị đơn tối thiểu phải từ 0 trở lên.';
        }
        if ($maximumInput !== '' && ($maximum === false || $maximum < 0)) {
            $errors['maximum_discount'] = 'Mức giảm tối đa phải từ 0 trở lên.';
        }
        if ($usageInput !== '' && ($usageLimit === false || $usageLimit < 1)) {
            $errors['usage_limit'] = 'Tổng lượt phải lớn hơn 0 hoặc để trống nếu không giới hạn.';
        }
        if ($perUser === false || $perUser < 1) {
            $errors['per_user_limit'] = 'Lượt mỗi người phải từ 1 trở lên.';
        } elseif ($usageLimit !== null && $usageLimit !== false && $perUser > $usageLimit) {
            $errors['per_user_limit'] = 'Lượt mỗi người không được vượt tổng lượt.';
        }
        if (!$this->validDateTime($starts)) {
            $errors['starts_at'] = 'Hãy nhập ngày giờ bắt đầu hợp lệ.';
        }
        if (!$this->validDateTime($ends)) {
            $errors['ends_at'] = 'Hãy nhập ngày giờ kết thúc hợp lệ.';
        } elseif ($this->validDateTime($starts) && $ends <= $starts) {
            $errors['ends_at'] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Hãy chọn trạng thái bật hoặc tắt.';
        }
        if (!in_array($audience, ['code', 'public', 'targeted'], true)) {
            $errors['audience'] = 'Hãy chọn đối tượng áp dụng hợp lệ.';
        }
        if (!in_array($autoRule, ['none', 'welcome', 'first_delivered_order'], true)) {
            $errors['auto_rule'] = 'Hãy chọn cách tự tặng hợp lệ.';
        } elseif ($autoRule !== 'none' && $audience !== 'targeted') {
            $errors['auto_rule'] = 'Chỉ voucher dành cho khách được tặng mới có thể tự tặng.';
        }
        if ($validDaysInput !== '' && ($validDays === false || $validDays < 1)) {
            $errors['valid_days_after_grant'] = 'Hạn dùng sau khi nhận phải từ 1 ngày trở lên hoặc để trống.';
        } elseif ($audience !== 'targeted' && $validDaysInput !== '') {
            $errors['valid_days_after_grant'] = 'Chỉ voucher dành cho khách được tặng mới dùng hạn sau khi nhận.';
        }
        if (mb_strlen($description, 'UTF-8') > 255) {
            $errors['description'] = 'Mô tả không được dài quá 255 ký tự.';
        }
        if (!$categoriesValid) {
            $errors['category_ids'] = 'Danh mục đã chọn không còn hợp lệ. Hãy tải lại trang và chọn lại.';
        }
        if (!$productsValid) {
            $errors['product_ids'] = 'Sản phẩm đã chọn không còn hợp lệ. Hãy tải lại trang và chọn lại.';
        }

        return [
            'errors' => $errors,
            'values' => [
                'code' => $code,
                'description' => $description,
                'discount_type' => $type,
                'discount_value' => $value,
                'minimum_order' => $minimum,
                'maximum_discount' => $type === 'percent' ? $maximum : null,
                'usage_limit' => $usageLimit,
                'per_user_limit' => $perUser,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => $status,
                'audience' => $audience,
                'auto_rule' => $autoRule,
                'valid_days_after_grant' => $validDays,
                'category_ids' => $categoryIds,
                'product_ids' => $productIds,
            ],
        ];
    }

    public function save(array $values, array $categoryIds, array $productIds, ?int $voucherId, int $userId): int
    {
        $this->database->beginTransaction();
        try {
            if ($voucherId === null) {
                $this->database->prepare(
                    'INSERT INTO vouchers
                     (code, description, discount_type, discount_value, minimum_order, maximum_discount,
                      usage_limit, per_user_limit, starts_at, ends_at, status, audience, auto_rule, valid_days_after_grant)
                     VALUES (:code, :description, :type, :value, :minimum, :maximum, :usage_limit, :per_user, :starts, :ends, :status, :audience, :auto_rule, :valid_days)'
                )->execute($this->saveParameters($values));
                $voucherId = (int) $this->database->lastInsertId();
                $action = 'voucher_created';
            } else {
                $parameters = $this->saveParameters($values);
                $parameters['id'] = $voucherId;
                $this->database->prepare(
                    'UPDATE vouchers SET code = :code, description = :description, discount_type = :type,
                        discount_value = :value, minimum_order = :minimum, maximum_discount = :maximum,
                        usage_limit = :usage_limit, per_user_limit = :per_user, starts_at = :starts,
                        ends_at = :ends, status = :status, audience = :audience, auto_rule = :auto_rule,
                        valid_days_after_grant = :valid_days WHERE id = :id'
                )->execute($parameters);
                $exists = $this->database->prepare('SELECT id FROM vouchers WHERE id = :id');
                $exists->execute(['id' => $voucherId]);
                if ($exists->fetchColumn() === false) {
                    throw new InvalidArgumentException('Không tìm thấy voucher cần cập nhật.');
                }
                $action = 'voucher_updated';
            }

            $this->database->prepare('DELETE FROM voucher_categories WHERE voucher_id = :id')->execute(['id' => $voucherId]);
            $this->database->prepare('DELETE FROM voucher_products WHERE voucher_id = :id')->execute(['id' => $voucherId]);
            $categoryInsert = $this->database->prepare(
                'INSERT INTO voucher_categories (voucher_id, category_id) VALUES (:voucher_id, :target_id)'
            );
            foreach ($categoryIds as $categoryId) {
                $categoryInsert->execute(['voucher_id' => $voucherId, 'target_id' => $categoryId]);
            }
            $productInsert = $this->database->prepare(
                'INSERT INTO voucher_products (voucher_id, product_id) VALUES (:voucher_id, :target_id)'
            );
            foreach ($productIds as $productId) {
                $productInsert->execute(['voucher_id' => $voucherId, 'target_id' => $productId]);
            }

            AuditLog::record($userId, $action, 'voucher', $voucherId, $values['code'], $this->database);
            $this->database->commit();
            return $voucherId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function toggle(int $voucherId, int $userId): string
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT code, status FROM vouchers WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $voucherId]);
            $voucher = $query->fetch();
            if (!$voucher) {
                throw new InvalidArgumentException('Không tìm thấy voucher.');
            }
            $status = $voucher['status'] === 'active' ? 'inactive' : 'active';
            $this->database->prepare('UPDATE vouchers SET status = :status WHERE id = :id')
                ->execute(['status' => $status, 'id' => $voucherId]);
            AuditLog::record($userId, $status === 'active' ? 'voucher_enabled' : 'voucher_disabled', 'voucher', $voucherId, $voucher['code'], $this->database);
            $this->database->commit();
            return $status;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function duplicate(int $voucherId, int $userId): int
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT * FROM vouchers WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $voucherId]);
            $source = $query->fetch();
            if (!$source) {
                throw new InvalidArgumentException('Không tìm thấy voucher cần nhân bản.');
            }

            $suffixNumber = 1;
            do {
                $suffix = $suffixNumber === 1 ? '-COPY' : '-COPY-' . $suffixNumber;
                $code = substr((string) $source['code'], 0, 30 - strlen($suffix)) . $suffix;
                $exists = $this->database->prepare('SELECT id FROM vouchers WHERE code = :code LIMIT 1');
                $exists->execute(['code' => $code]);
                $taken = $exists->fetchColumn() !== false;
                $suffixNumber++;
            } while ($taken);

            $this->database->prepare(
                'INSERT INTO vouchers
                 (code, description, discount_type, discount_value, minimum_order, maximum_discount,
                  usage_limit, per_user_limit, used_count, starts_at, ends_at, status, audience, auto_rule, valid_days_after_grant)
                 VALUES (:code, :description, :type, :value, :minimum, :maximum, :usage_limit, :per_user, 0, :starts, :ends, \'inactive\', :audience, :auto_rule, :valid_days)'
            )->execute([
                'code' => $code,
                'description' => $source['description'],
                'type' => $source['discount_type'],
                'value' => $source['discount_value'],
                'minimum' => $source['minimum_order'],
                'maximum' => $source['maximum_discount'],
                'usage_limit' => $source['usage_limit'],
                'per_user' => $source['per_user_limit'],
                'starts' => $source['starts_at'],
                'ends' => $source['ends_at'],
                'audience' => $source['audience'],
                'auto_rule' => $source['auto_rule'],
                'valid_days' => $source['valid_days_after_grant'],
            ]);
            $copyId = (int) $this->database->lastInsertId();
            $this->database->prepare(
                'INSERT INTO voucher_categories (voucher_id, category_id)
                 SELECT :copy_id, category_id FROM voucher_categories WHERE voucher_id = :source_id'
            )->execute(['copy_id' => $copyId, 'source_id' => $voucherId]);
            $this->database->prepare(
                'INSERT INTO voucher_products (voucher_id, product_id)
                 SELECT :copy_id, product_id FROM voucher_products WHERE voucher_id = :source_id'
            )->execute(['copy_id' => $copyId, 'source_id' => $voucherId]);
            AuditLog::record($userId, 'voucher_duplicated', 'voucher', $copyId, $code . ' (từ ' . $source['code'] . ')', $this->database);
            $this->database->commit();
            return $copyId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $voucherId, int $userId): bool
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare('SELECT code, used_count FROM vouchers WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $voucherId]);
            $voucher = $query->fetch();
            if (!$voucher) {
                throw new InvalidArgumentException('Không tìm thấy voucher.');
            }
            $uses = $this->database->prepare('SELECT 1 FROM voucher_usages WHERE voucher_id = :id LIMIT 1');
            $uses->execute(['id' => $voucherId]);
            if ((int) $voucher['used_count'] > 0 || $uses->fetchColumn() !== false) {
                AuditLog::record($userId, 'voucher_delete_blocked', 'voucher', $voucherId, $voucher['code'] . ' đã có lượt sử dụng', $this->database);
                $this->database->commit();
                return false;
            }

            AuditLog::record($userId, 'voucher_deleted', 'voucher', $voucherId, $voucher['code'], $this->database);
            $this->database->prepare('DELETE FROM vouchers WHERE id = :id')->execute(['id' => $voucherId]);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function saveParameters(array $values): array
    {
        return [
            'code' => $values['code'],
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'type' => $values['discount_type'],
            'value' => $values['discount_value'],
            'minimum' => $values['minimum_order'],
            'maximum' => $values['maximum_discount'],
            'usage_limit' => $values['usage_limit'],
            'per_user' => $values['per_user_limit'],
            'starts' => str_replace('T', ' ', $values['starts_at']) . ':00',
            'ends' => str_replace('T', ' ', $values['ends_at']) . ':00',
            'status' => $values['status'],
            'audience' => $values['audience'],
            'auto_rule' => $values['auto_rule'],
            'valid_days' => $values['valid_days_after_grant'],
        ];
    }

    private function validDateTime(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && $date->format('Y-m-d\TH:i') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function inputString(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }

    private function selectedIds(mixed $input, string $table): array
    {
        if (!is_array($input)) {
            return [[], $input === null || $input === ''];
        }

        $ids = [];
        foreach ($input as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                return [[], false];
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            return [[], true];
        }

        $parameters = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = $id;
        }
        $query = $this->database->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        $query->execute($parameters);
        return [$ids, (int) $query->fetchColumn() === count($ids)];
    }
}
