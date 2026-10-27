ALTER TABLE regions
    ADD COLUMN description TEXT DEFAULT NULL AFTER slug;

UPDATE products SET status = 'inactive' WHERE status = 'out_of_stock';

ALTER TABLE products
    MODIFY COLUMN status ENUM('draft','active','inactive') NOT NULL DEFAULT 'draft',
    ADD COLUMN sale_price DECIMAL(12,2) UNSIGNED DEFAULT NULL AFTER price,
    ADD COLUMN short_description VARCHAR(500) DEFAULT NULL AFTER certifications,
    ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER description,
    ADD KEY idx_products_catalog_filter (status, deleted_at, category_id, region_id, price),
    ADD KEY idx_products_featured (is_featured, status, deleted_at, created_at),
    ADD KEY idx_products_created (created_at);
