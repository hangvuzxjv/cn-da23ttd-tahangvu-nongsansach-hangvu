-- One-time checkout and order management migration. Back up the database first.
-- Do not run this migration against a fresh schema that already includes these fields.

ALTER TABLE cart_items
    ADD COLUMN price_snapshot DECIMAL(12,2) UNSIGNED DEFAULT NULL AFTER quantity;

ALTER TABLE orders
    ADD COLUMN region_id BIGINT UNSIGNED DEFAULT NULL AFTER customer_note,
    ADD COLUMN idempotency_token CHAR(64) DEFAULT NULL AFTER region_id,
    ADD UNIQUE KEY uq_orders_idempotency_token (idempotency_token),
    ADD KEY idx_orders_payment_date (payment_status, placed_at),
    ADD KEY idx_orders_region (region_id),
    ADD CONSTRAINT fk_orders_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL;

ALTER TABLE vouchers
    ADD COLUMN per_user_limit INT UNSIGNED NOT NULL DEFAULT 1 AFTER usage_limit;

ALTER TABLE voucher_usages
    ADD COLUMN status ENUM('used','reversed') NOT NULL DEFAULT 'used' AFTER discount_amount,
    ADD COLUMN reversed_at DATETIME DEFAULT NULL AFTER status,
    ADD KEY idx_voucher_usages_active_user (voucher_id, user_id, status);

CREATE TABLE voucher_categories (
    voucher_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (voucher_id, category_id),
    CONSTRAINT fk_voucher_categories_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_categories_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voucher_products (
    voucher_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (voucher_id, product_id),
    CONSTRAINT fk_voucher_products_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_products_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('cart_max_quantity', '20', 'Số lượng tối đa mỗi sản phẩm trong giỏ'),
    ('free_shipping_minimum', '500000', 'Giá trị đơn tối thiểu để miễn phí vận chuyển')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
