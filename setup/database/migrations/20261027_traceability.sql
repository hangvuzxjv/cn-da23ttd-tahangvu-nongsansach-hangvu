ALTER TABLE farms
    ADD COLUMN certification_number VARCHAR(120) DEFAULT NULL AFTER certifications,
    ADD COLUMN certification_expires_at DATE DEFAULT NULL AFTER certification_number,
    ADD COLUMN certificate_image_url VARCHAR(500) DEFAULT NULL AFTER certification_expires_at,
    ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER certificate_image_url,
    ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL AFTER image_url,
    ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;

ALTER TABLE product_batches
    ADD COLUMN farm_id BIGINT UNSIGNED DEFAULT NULL AFTER product_id,
    ADD COLUMN status ENUM('selling','suspended','recalled','ended') NOT NULL DEFAULT 'selling' AFTER inspection_notes,
    ADD COLUMN recall_reason TEXT DEFAULT NULL AFTER status;

UPDATE product_batches b
JOIN products p ON p.id = b.product_id
SET b.farm_id = p.farm_id,
    b.status = CASE
        WHEN b.expires_at IS NOT NULL AND b.expires_at < CURDATE() THEN 'ended'
        WHEN b.quantity_remaining = 0 THEN 'ended'
        ELSE 'selling'
    END;

ALTER TABLE product_batches
    ADD KEY idx_product_batches_farm (farm_id),
    ADD KEY idx_product_batches_public (status, expires_at, quantity_remaining),
    ADD CONSTRAINT fk_product_batches_farm FOREIGN KEY (farm_id) REFERENCES farms (id) ON DELETE RESTRICT;

ALTER TABLE trace_events
    ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER description,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER image_url,
    ADD KEY idx_trace_events_batch_order (batch_id, sort_order, event_at);

CREATE TABLE trace_scans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_agent VARCHAR(200) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_trace_scans_batch_date (batch_id, scanned_at),
    KEY idx_trace_scans_batch_ip_date (batch_id, ip_hash, scanned_at),
    CONSTRAINT fk_trace_scans_batch FOREIGN KEY (batch_id) REFERENCES product_batches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
