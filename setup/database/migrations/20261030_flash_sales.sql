-- Back up the database before applying. This migration is safe to run repeatedly.
-- Do not run schema.sql against a database containing existing data.

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'order_items'
              AND column_name = 'flash_sale_item_id'),
    'SET @flash_noop = 1',
    'ALTER TABLE order_items ADD COLUMN flash_sale_item_id BIGINT UNSIGNED DEFAULT NULL'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'order_items'
              AND index_name = 'idx_order_items_flash_sale_item'),
    'SET @flash_noop = 1',
    'ALTER TABLE order_items ADD KEY idx_order_items_flash_sale_item (flash_sale_item_id)'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE() AND table_name = 'order_items'
              AND constraint_name = 'fk_order_items_flash_sale_item'),
    'SET @flash_noop = 1',
    'ALTER TABLE order_items ADD CONSTRAINT fk_order_items_flash_sale_item
     FOREIGN KEY (flash_sale_item_id) REFERENCES flash_sale_items (id) ON DELETE SET NULL'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'flash_sales'
              AND column_name = 'description'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sales ADD COLUMN description TEXT DEFAULT NULL'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'flash_sales'
              AND column_name = 'banner_url'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sales ADD COLUMN banner_url VARCHAR(500) DEFAULT NULL'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_items'
              AND column_name = 'sort_order'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sale_items ADD COLUMN sort_order INT NOT NULL DEFAULT 0'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_purchases'
              AND column_name = 'status'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sale_purchases
     ADD COLUMN status ENUM(''reserved'',''confirmed'',''released'') NOT NULL DEFAULT ''confirmed'''
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_purchases'
              AND column_name = 'created_at'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sale_purchases ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_purchases'
              AND index_name = 'idx_flash_sale_purchases_user_status'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sale_purchases ADD KEY idx_flash_sale_purchases_user_status
     (user_id, flash_sale_item_id, status)'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_purchases'
              AND index_name = 'uq_flash_sale_purchase_user_item'),
    'ALTER TABLE flash_sale_purchases DROP INDEX uq_flash_sale_purchase_user_item',
    'SET @flash_noop = 1'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

SET @flash_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'flash_sale_purchases'
              AND index_name = 'uq_flash_sale_purchase_order_item'),
    'SET @flash_noop = 1',
    'ALTER TABLE flash_sale_purchases
     ADD UNIQUE KEY uq_flash_sale_purchase_order_item (order_id, flash_sale_item_id)'
);
PREPARE flash_stmt FROM @flash_sql;
EXECUTE flash_stmt;
DEALLOCATE PREPARE flash_stmt;

CREATE TABLE IF NOT EXISTS flash_sale_rate_limits (
    bucket_key VARCHAR(180) NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket_key),
    KEY idx_flash_sale_rate_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
