-- One-time migration for databases created before warehouse inventory management.
-- Import using phpMyAdmin after backup; do not re-run on a database already migrated.

ALTER TABLE products
    ADD COLUMN min_stock INT UNSIGNED NOT NULL DEFAULT 5 AFTER total_stock;

ALTER TABLE product_batches
    ADD COLUMN average_unit_cost DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER quantity_remaining;

ALTER TABLE inventory_movements
    ADD COLUMN unit_cost DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN balance_after INT UNSIGNED NOT NULL DEFAULT 0 AFTER unit_cost;

ALTER TABLE warehouse_receipts
    ADD COLUMN supplier_farm_id BIGINT UNSIGNED DEFAULT NULL AFTER supplier_name,
    ADD COLUMN confirmed_at DATETIME DEFAULT NULL AFTER status,
    ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER confirmed_at,
    ADD COLUMN cancellation_reason VARCHAR(500) DEFAULT NULL AFTER cancelled_at,
    ADD KEY idx_warehouse_receipts_supplier_farm (supplier_farm_id),
    ADD CONSTRAINT fk_warehouse_receipts_farm
        FOREIGN KEY (supplier_farm_id) REFERENCES farms (id) ON DELETE SET NULL;

UPDATE warehouse_receipts
SET confirmed_at = received_at
WHERE status = 'received' AND confirmed_at IS NULL;

UPDATE product_batches b
SET b.average_unit_cost = COALESCE((
    SELECT SUM(i.unit_cost * i.quantity) / NULLIF(SUM(i.quantity), 0)
    FROM warehouse_receipt_items i
    WHERE i.batch_id = b.id
), 0);

-- Append a balancing movement; historical ledger rows are never rewritten or deleted.
INSERT INTO inventory_movements
    (product_id, batch_id, movement_type, quantity, reference_type, reference_id, notes)
SELECT b.product_id, b.id, 'adjustment',
       CAST(b.quantity_remaining AS SIGNED) - CAST(COALESCE(m.movement_total, 0) AS SIGNED),
       'migration_stock_reconcile', NULL,
       'Cân bằng sổ cái ban đầu với tồn theo lô khi áp dụng migration kho.'
FROM product_batches b
LEFT JOIN (
    SELECT batch_id, SUM(quantity) AS movement_total
    FROM inventory_movements
    WHERE batch_id IS NOT NULL
    GROUP BY batch_id
) m ON m.batch_id = b.id
WHERE b.quantity_remaining <> COALESCE(m.movement_total, 0);

UPDATE products p
SET p.total_stock = (
    SELECT COALESCE(SUM(b.quantity_remaining), 0)
    FROM product_batches b
    WHERE b.product_id = p.id
);

CREATE TABLE stock_reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('reserved','consumed','released','expired') NOT NULL DEFAULT 'reserved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_reservations_batch_active (batch_id, status, expires_at),
    KEY idx_stock_reservations_order (order_id, status),
    CONSTRAINT fk_stock_reservations_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_reservations_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stocktakes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stocktake_no VARCHAR(80) NOT NULL,
    status ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    confirmed_by BIGINT UNSIGNED DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stocktakes_no (stocktake_no),
    KEY idx_stocktakes_status (status, created_at),
    CONSTRAINT fk_stocktakes_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_stocktakes_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stocktake_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stocktake_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NOT NULL,
    system_quantity INT UNSIGNED NOT NULL,
    counted_quantity INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stocktake_batch (stocktake_id, batch_id),
    KEY idx_stocktake_items_batch (batch_id),
    CONSTRAINT fk_stocktake_items_stocktake FOREIGN KEY (stocktake_id) REFERENCES stocktakes (id) ON DELETE CASCADE,
    CONSTRAINT fk_stocktake_items_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_sequences (
    sequence_key VARCHAR(40) NOT NULL,
    next_value INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (sequence_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, description)
VALUES ('expiry_warning_days', '7', 'Số ngày cảnh báo lô sắp hết hạn')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
