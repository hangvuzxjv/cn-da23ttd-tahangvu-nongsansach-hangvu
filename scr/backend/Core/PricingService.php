<?php

declare(strict_types=1);

final class PricingService
{
    public function __construct(private ?PDO $database = null)
    {
        $this->database ??= Database::connection();
    }

    public function quote(
        array $items,
        int $regionId,
        ?string $voucherCode = null,
        ?int $userId = null,
        bool $lockVoucher = false,
        bool $enforceFlashSaleLimit = false
    ): array
    {
        if (!$items || $regionId < 1) {
            throw new InvalidArgumentException('Giỏ hàng hoặc địa chỉ nhận hàng không hợp lệ.');
        }

        $items = $this->applyFlashSalePricing($items, $userId, $lockVoucher, $enforceFlashSaleLimit);
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += self::lineTotal(
                (string) ($item['unit_price'] ?? $item['current_price'] ?? '0'),
                (int) ($item['quantity'] ?? 0)
            );
        }

        $discount = 0;
        $voucher = null;
        if ($voucherCode !== null && trim($voucherCode) !== '') {
            $voucher = $this->findVoucher(strtoupper(trim($voucherCode)), $lockVoucher);
            if (!$voucher || $voucher['status'] !== 'active'
                || !(int) $voucher['is_started'] || !(int) $voucher['not_expired']) {
                throw new PricingException('Mã giảm giá không tồn tại hoặc đã hết hạn.');
            }
            if ($voucher['usage_limit'] !== null && (int) $voucher['used_count'] >= (int) $voucher['usage_limit']) {
                throw new PricingException('Mã giảm giá đã hết lượt sử dụng.');
            }
            if ($userId !== null) {
                $usageQuery = $this->database->prepare(
                    'SELECT COUNT(*) FROM voucher_usages
                     WHERE voucher_id = :voucher_id AND user_id = :user_id AND status = \'used\''
                );
                $usageQuery->execute(['voucher_id' => $voucher['id'], 'user_id' => $userId]);
                if ((int) $usageQuery->fetchColumn() >= (int) $voucher['per_user_limit']) {
                    throw new PricingException('Bạn đã sử dụng mã giảm giá này đủ số lần cho phép.');
                }
            }
            if ($subtotal < self::moneyToVnd((string) $voucher['minimum_order'])) {
                throw new PricingException('Đơn hàng chưa đạt giá trị tối thiểu của mã giảm giá.');
            }

            $eligible = $this->eligibleSubtotal($items, (int) $voucher['id']);
            if ($eligible < 1) {
                throw new PricingException('Mã giảm giá không áp dụng cho sản phẩm trong giỏ.');
            }
            if ($voucher['discount_type'] === 'percent') {
                $percentHundredths = self::decimalToHundredths((string) $voucher['discount_value']);
                $discount = intdiv(($eligible * $percentHundredths) + 5000, 10000);
                if ($voucher['maximum_discount'] !== null) {
                    $discount = min($discount, self::moneyToVnd((string) $voucher['maximum_discount']));
                }
            } else {
                $discount = min($eligible, self::moneyToVnd((string) $voucher['discount_value']));
            }
            $discount = min($subtotal, max(0, $discount));
        }

        $shipping = $this->shippingQuote($regionId, $subtotal);
        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shipping['fee'],
            'total' => max(0, $subtotal - $discount) + $shipping['fee'],
            'voucher' => $voucher,
            'shipping' => $shipping,
            'items' => $items,
            'flash_sale_messages' => array_values(array_filter(array_map(
                static fn (array $item): ?string => $item['flash_sale_message'] ?? null,
                $items
            ))),
        ];
    }

    public function applyFlashSalePricing(
        array $items,
        ?int $userId = null,
        bool $lockItems = false,
        bool $enforceUserLimit = false
    ): array {
        $flashSales = new FlashSaleService($this->database);
        foreach ($items as &$item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $regularPrice = (string) ($item['unit_price'] ?? $item['current_price'] ?? '0');
            $item['flash_sale_item_id'] = null;
            $item['flash_sale_price'] = null;
            $item['flash_sale_message'] = null;
            $item['original_price'] = self::moneyToVnd($regularPrice);
            $item['current_price'] = self::moneyToVnd($regularPrice);
            if ($productId < 1 || $quantity < 1) {
                continue;
            }
            $deal = $flashSales->activeDeal($productId, $userId, $lockItems);
            if ($deal === null) {
                continue;
            }
            if ((int) $deal['remaining'] < 1) {
                $item['flash_sale_message'] = 'Flash Sale đã hết suất; đang áp dụng giá bán thường.';
                continue;
            }
            if ($quantity > (int) $deal['remaining']) {
                $item['flash_sale_message'] = 'Flash Sale không còn đủ suất cho số lượng này; đang áp dụng giá bán thường.';
                continue;
            }
            if ($userId === null) {
                $item['flash_sale_message'] = 'Đăng nhập để áp dụng giá Flash Sale.';
                continue;
            }
            $userPurchased = (int) ($deal['user_purchased'] ?? 0);
            if ($userId !== null && $userPurchased + $quantity > (int) $deal['per_user_limit']) {
                if ($enforceUserLimit) {
                    throw new PricingException(
                        'Bạn đã mua hoặc đang giữ ' . $userPurchased . ' sản phẩm; giới hạn Flash Sale là '
                        . (int) $deal['per_user_limit'] . ' sản phẩm.'
                    );
                }
                $item['flash_sale_message'] = 'Số lượng vượt giới hạn Flash Sale; đang áp dụng giá bán thường.';
                continue;
            }
            $salePrice = self::moneyToVnd((string) $deal['sale_price']);
            if ($salePrice >= $item['original_price']) {
                $item['flash_sale_message'] = 'Giá Flash Sale không còn thấp hơn giá bán hiện tại; đang áp dụng giá thường.';
                continue;
            }
            $item['flash_sale_item_id'] = (int) $deal['flash_sale_item_id'];
            $item['flash_sale_price'] = $salePrice;
            $item['current_price'] = $salePrice;
            $item['unit_price'] = (string) $deal['sale_price'];
            $item['flash_sale_name'] = $deal['campaign_name'];
            $item['flash_sale_remaining'] = (int) $deal['remaining'];
            $item['flash_sale_per_user_limit'] = (int) $deal['per_user_limit'];
            $item['flash_sale_user_purchased'] = $userPurchased;
        }
        unset($item);
        return $items;
    }

    public static function moneyToVnd(string $amount): int
    {
        if (preg_match('/\A([0-9]+)(?:\.([0-9]{1,}))?\z/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Số tiền trong dữ liệu không hợp lệ.');
        }
        $whole = (int) $matches[1];
        $fraction = $matches[2] ?? '';
        if ($fraction !== '' && (int) str_pad(substr($fraction, 0, 2), 2, '0') >= 50) {
            $whole++;
        }
        return $whole;
    }

    public static function lineTotal(string $unitPrice, int $quantity): int
    {
        $price = self::moneyToVnd($unitPrice);
        if ($quantity < 1 || $price < 0) {
            throw new InvalidArgumentException('Giá hoặc số lượng sản phẩm không hợp lệ.');
        }
        return $price * $quantity;
    }

    private static function decimalToHundredths(string $amount): int
    {
        if (preg_match('/\A([0-9]+)(?:\.([0-9]{1,}))?\z/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Phần trăm trong dữ liệu không hợp lệ.');
        }
        $fraction = str_pad(substr($matches[2] ?? '', 0, 2), 2, '0');
        if (isset(($matches[2] ?? '')[2]) && (int) ($matches[2][2]) >= 5) {
            $fraction = (string) ((int) $fraction + 1);
        }
        return ((int) $matches[1] * 100) + (int) $fraction;
    }

    private function findVoucher(string $code, bool $lock): array|false
    {
        $sql = 'SELECT v.*, v.starts_at <= NOW() AS is_started, v.ends_at >= NOW() AS not_expired
                FROM vouchers v WHERE v.code = :code';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $query = $this->database->prepare($sql);
        $query->execute(['code' => $code]);
        return $query->fetch();
    }

    private function eligibleSubtotal(array $items, int $voucherId): int
    {
        $categoryQuery = $this->database->prepare('SELECT category_id FROM voucher_categories WHERE voucher_id = :id');
        $categoryQuery->execute(['id' => $voucherId]);
        $categoryIds = array_map('intval', $categoryQuery->fetchAll(PDO::FETCH_COLUMN));
        $productQuery = $this->database->prepare('SELECT product_id FROM voucher_products WHERE voucher_id = :id');
        $productQuery->execute(['id' => $voucherId]);
        $productIds = array_map('intval', $productQuery->fetchAll(PDO::FETCH_COLUMN));
        if (!$categoryIds && !$productIds) {
            return array_sum(array_map(
                static fn (array $item): int => self::lineTotal(
                    (string) ($item['unit_price'] ?? $item['current_price'] ?? '0'),
                    (int) $item['quantity']
                ),
                $items
            ));
        }

        $eligible = 0;
        foreach ($items as $item) {
            if (in_array((int) ($item['product_id'] ?? 0), $productIds, true)
                || in_array((int) ($item['category_id'] ?? 0), $categoryIds, true)) {
                $eligible += self::lineTotal(
                    (string) ($item['unit_price'] ?? $item['current_price'] ?? '0'),
                    (int) $item['quantity']
                );
            }
        }
        return $eligible;
    }

    private function shippingQuote(int $regionId, int $subtotal): array
    {
        $regionQuery = $this->database->prepare('SELECT id, parent_id FROM regions WHERE id = :id AND level = 2 AND is_active = 1');
        $regionQuery->execute(['id' => $regionId]);
        $region = $regionQuery->fetch();
        if (!$region) {
            throw new PricingException('Không tìm thấy tỉnh/vùng nhận hàng.');
        }
        $feeQuery = $this->database->prepare(
            'SELECT fee, free_shipping_minimum FROM shipping_fees
             WHERE is_active = 1 AND region_id IN (:region_id, :parent_id)
             ORDER BY region_id = :exact_region DESC LIMIT 1'
        );
        $feeQuery->execute([
            'region_id' => $regionId,
            'parent_id' => (int) ($region['parent_id'] ?? 0),
            'exact_region' => $regionId,
        ]);
        $fee = $feeQuery->fetch();
        if (!$fee) {
            throw new PricingException('Chưa cấu hình phí vận chuyển cho tỉnh/thành nhận hàng.');
        }
        $settings = $this->database->query(
            "SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_minimum'"
        )->fetchColumn();
        $minimum = $fee && $fee['free_shipping_minimum'] !== null
            ? self::moneyToVnd((string) $fee['free_shipping_minimum'])
            : max(0, (int) $settings);
        $amount = $fee ? self::moneyToVnd((string) $fee['fee']) : 0;
        if ($minimum > 0 && $subtotal >= $minimum) {
            $amount = 0;
        }
        return ['fee' => $amount, 'minimum' => $minimum, 'region_id' => $regionId];
    }
}
