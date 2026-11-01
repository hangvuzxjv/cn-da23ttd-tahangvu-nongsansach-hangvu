-- Back up the database before applying. This migration is safe to run repeatedly.
-- Do not run schema.sql or seed.sql against a database containing existing data.

SET @review_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'reviews'
          AND column_name = 'updated_at'
    ),
    'SET @review_noop = 1',
    'ALTER TABLE reviews ADD COLUMN updated_at TIMESTAMP NOT NULL
     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
);
PREPARE review_stmt FROM @review_sql;
EXECUTE review_stmt;
DEALLOCATE PREPARE review_stmt;

SET @review_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'reviews'
          AND index_name = 'uq_reviews_user_product'
    ) OR NOT EXISTS (
        SELECT 1 FROM reviews GROUP BY user_id, product_id HAVING COUNT(*) > 1
    ),
    IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'reviews'
              AND index_name = 'uq_reviews_user_product'
        ),
        'SET @review_noop = 1',
        'ALTER TABLE reviews ADD UNIQUE KEY uq_reviews_user_product (user_id, product_id)'
    ),
    'SET @review_noop = 1'
);
PREPARE review_stmt FROM @review_sql;
EXECUTE review_stmt;
DEALLOCATE PREPARE review_stmt;

CREATE TABLE IF NOT EXISTS review_rate_limits (
    user_id BIGINT UNSIGNED NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, window_started_at),
    KEY idx_review_rate_limits_window (window_started_at),
    CONSTRAINT fk_review_rate_limits_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
