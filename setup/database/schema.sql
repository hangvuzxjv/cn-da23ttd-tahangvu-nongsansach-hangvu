SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS flash_sale_purchases;
DROP TABLE IF EXISTS flash_sale_items;
DROP TABLE IF EXISTS flash_sales;
DROP TABLE IF EXISTS voucher_usages;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS warehouse_receipt_items;
DROP TABLE IF EXISTS warehouse_receipts;
DROP TABLE IF EXISTS trace_events;
DROP TABLE IF EXISTS product_batches;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS farms;
DROP TABLE IF EXISTS shipping_fees;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS regions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','admin','staff_sales','staff_warehouse') NOT NULL DEFAULT 'customer',
    status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    recipient_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    region_id BIGINT UNSIGNED DEFAULT NULL,
    address_line VARCHAR(255) NOT NULL,
    ward VARCHAR(120) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_addresses_user (user_id),
    KEY idx_addresses_region (region_id),
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_verifications_token (token_hash),
    KEY idx_email_verifications_user (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_failed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_login_attempts_email_ip (email_hash, ip_hash),
    KEY idx_login_attempts_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    level TINYINT UNSIGNED NOT NULL COMMENT '1 = miền, 2 = vùng/tỉnh',
    slug VARCHAR(140) NOT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regions_slug (slug),
    KEY idx_regions_parent (parent_id),
    CONSTRAINT fk_regions_parent FOREIGN KEY (parent_id) REFERENCES regions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE addresses
    ADD CONSTRAINT fk_addresses_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL;

CREATE TABLE categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent_sort (parent_id, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE farms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    region_id BIGINT UNSIGNED NOT NULL,
    address VARCHAR(255) NOT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    certifications TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_farms_slug (slug),
    KEY idx_farms_region (region_id),
    CONSTRAINT fk_farms_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    region_id BIGINT UNSIGNED NOT NULL,
    farm_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    sku VARCHAR(80) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    price DECIMAL(12,2) UNSIGNED NOT NULL,
    sale_price DECIMAL(12,2) UNSIGNED DEFAULT NULL,
    suggested_shelf_life_days SMALLINT UNSIGNED DEFAULT NULL,
    storage_instructions TEXT DEFAULT NULL,
    certifications TEXT DEFAULT NULL,
    short_description VARCHAR(500) DEFAULT NULL,
    status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    total_stock INT UNSIGNED NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category_status (category_id, status),
    KEY idx_products_region_status (region_id, status),
    KEY idx_products_farm (farm_id),
    KEY idx_products_price (price),
    KEY idx_products_catalog_filter (status, deleted_at, category_id, region_id, price),
    KEY idx_products_featured (is_featured, status, deleted_at, created_at),
    KEY idx_products_created (created_at),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_products_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_products_farm FOREIGN KEY (farm_id) REFERENCES farms (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    alt_text VARCHAR(200) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_product_images_product_sort (product_id, sort_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    batch_code VARCHAR(100) NOT NULL,
    qr_token CHAR(64) NOT NULL,
    harvested_at DATE NOT NULL,
    packed_at DATE DEFAULT NULL,
    expires_at DATE DEFAULT NULL,
    quantity_received INT UNSIGNED NOT NULL,
    quantity_remaining INT UNSIGNED NOT NULL,
    inspection_result ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending',
    inspection_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_batches_code (batch_code),
    UNIQUE KEY uq_product_batches_qr (qr_token),
    KEY idx_product_batches_product_expiry (product_id, expires_at),
    KEY idx_product_batches_inspection (inspection_result),
    CONSTRAINT fk_product_batches_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT chk_product_batches_remaining CHECK (quantity_remaining <= quantity_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trace_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('planting','care','harvest','processing','packaging','transport') NOT NULL,
    event_at DATETIME NOT NULL,
    location VARCHAR(200) DEFAULT NULL,
    description TEXT NOT NULL,
    recorded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_trace_events_batch_time (batch_id, event_at),
    KEY idx_trace_events_recorded_by (recorded_by),
    CONSTRAINT fk_trace_events_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE CASCADE,
    CONSTRAINT fk_trace_events_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warehouse_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_no VARCHAR(80) NOT NULL,
    supplier_name VARCHAR(180) NOT NULL,
    received_by BIGINT UNSIGNED DEFAULT NULL,
    received_at DATETIME NOT NULL,
    status ENUM('draft','received','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warehouse_receipts_no (receipt_no),
    KEY idx_warehouse_receipts_received_at (received_at),
    CONSTRAINT fk_warehouse_receipts_user FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warehouse_receipt_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_cost DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warehouse_receipt_batch (receipt_id, batch_id),
    KEY idx_warehouse_receipt_items_batch (batch_id),
    CONSTRAINT fk_warehouse_receipt_items_receipt FOREIGN KEY (receipt_id) REFERENCES warehouse_receipts (id) ON DELETE CASCADE,
    CONSTRAINT fk_warehouse_receipt_items_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED DEFAULT NULL,
    movement_type ENUM('in','out','adjustment','return') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(40) DEFAULT NULL,
    reference_id BIGINT UNSIGNED DEFAULT NULL,
    performed_by BIGINT UNSIGNED DEFAULT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_product_date (product_id, created_at),
    KEY idx_inventory_batch_date (batch_id, created_at),
    KEY idx_inventory_reference (reference_type, reference_id),
    KEY idx_inventory_performed_by (performed_by),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_user FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    session_key VARCHAR(128) DEFAULT NULL,
    status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_session_key (session_key),
    KEY idx_carts_user_status (user_id, status),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    KEY idx_cart_items_product (product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    order_no VARCHAR(80) NOT NULL,
    status ENUM('pending','confirmed','processing','shipping','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payment_status ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
    recipient_name VARCHAR(150) NOT NULL,
    recipient_phone VARCHAR(30) NOT NULL,
    shipping_address VARCHAR(500) NOT NULL,
    region_name VARCHAR(120) DEFAULT NULL,
    subtotal DECIMAL(12,2) UNSIGNED NOT NULL,
    shipping_fee DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    voucher_code VARCHAR(50) DEFAULT NULL,
    customer_note TEXT DEFAULT NULL,
    placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_order_no (order_no),
    KEY idx_orders_user_date (user_id, placed_at),
    KEY idx_orders_status_date (status, placed_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED DEFAULT NULL,
    batch_id BIGINT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    sku VARCHAR(80) DEFAULT NULL,
    unit VARCHAR(40) NOT NULL,
    unit_price DECIMAL(12,2) UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(12,2) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    KEY idx_order_items_batch (batch_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_order_items_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','processing','shipping','completed','cancelled','refunded') NOT NULL,
    note VARCHAR(500) DEFAULT NULL,
    changed_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_status_history_order_time (order_id, created_at),
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    method ENUM('cod','bank_transfer','online') NOT NULL,
    amount DECIMAL(12,2) UNSIGNED NOT NULL,
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_ref VARCHAR(150) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_transaction_ref (transaction_ref),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_fees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    region_id BIGINT UNSIGNED NOT NULL,
    fee DECIMAL(10,2) UNSIGNED NOT NULL,
    free_shipping_minimum DECIMAL(12,2) UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shipping_fees_region (region_id),
    CONSTRAINT fk_shipping_fees_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vouchers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    discount_type ENUM('fixed','percent') NOT NULL,
    discount_value DECIMAL(12,2) UNSIGNED NOT NULL,
    minimum_order DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    maximum_discount DECIMAL(12,2) UNSIGNED DEFAULT NULL,
    usage_limit INT UNSIGNED DEFAULT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uq_vouchers_code (code),
    KEY idx_vouchers_status_dates (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voucher_usages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_voucher_usage_user_order (voucher_id, user_id, order_id),
    KEY idx_voucher_usages_user (user_id),
    KEY idx_voucher_usages_order (order_id),
    CONSTRAINT fk_voucher_usages_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_voucher_usages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_voucher_usages_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flash_sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('draft','scheduled','active','ended','cancelled') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_flash_sales_status_dates (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flash_sale_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    flash_sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    sale_price DECIMAL(12,2) UNSIGNED NOT NULL,
    quota INT UNSIGNED NOT NULL,
    sold_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flash_sale_product (flash_sale_id, product_id),
    KEY idx_flash_sale_items_product (product_id),
    CONSTRAINT fk_flash_sale_items_sale FOREIGN KEY (flash_sale_id) REFERENCES flash_sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_sale_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT chk_flash_sale_sold_quota CHECK (sold_quantity <= quota)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE flash_sale_purchases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    flash_sale_item_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flash_sale_purchase_user_item (user_id, flash_sale_item_id),
    KEY idx_flash_sale_purchases_item (flash_sale_item_id),
    KEY idx_flash_sale_purchases_order (order_id),
    CONSTRAINT fk_flash_sale_purchases_item FOREIGN KEY (flash_sale_item_id) REFERENCES flash_sale_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_sale_purchases_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_flash_sale_purchases_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(180) DEFAULT NULL,
    content TEXT DEFAULT NULL,
    status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reviews_product_status_date (product_id, status, created_at),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    KEY idx_wishlists_product (product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY idx_banners_status_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at DATETIME DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug),
    KEY idx_pages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new','processing','replied','closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contacts_status_date (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user_read_date (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_user_date (user_id, created_at),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
