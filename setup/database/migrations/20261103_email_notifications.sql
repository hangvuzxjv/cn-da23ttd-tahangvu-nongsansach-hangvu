-- Back up the database before applying. This migration is safe to run repeatedly.
-- Do not run schema.sql or seed.sql against a database containing existing data.

CREATE TABLE IF NOT EXISTS mail_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    text_body MEDIUMTEXT NOT NULL,
    html_body MEDIUMTEXT NOT NULL,
    driver VARCHAR(32) NOT NULL,
    status ENUM('pending','sent') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attempted_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_mail_outbox_status_date (status, created_at),
    KEY idx_mail_outbox_recipient_date (recipient, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_rate_limits (
    key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (key_hash),
    KEY idx_mail_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mail_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'notifications'
          AND column_name = 'source_type'
    ),
    'SET @mail_noop = 1',
    'ALTER TABLE notifications ADD COLUMN source_type VARCHAR(40) DEFAULT NULL'
);
PREPARE mail_stmt FROM @mail_sql;
EXECUTE mail_stmt;
DEALLOCATE PREPARE mail_stmt;

SET @mail_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'notifications'
          AND column_name = 'source_id'
    ),
    'SET @mail_noop = 1',
    'ALTER TABLE notifications ADD COLUMN source_id BIGINT UNSIGNED DEFAULT NULL'
);
PREPARE mail_stmt FROM @mail_sql;
EXECUTE mail_stmt;
DEALLOCATE PREPARE mail_stmt;

SET @mail_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'notifications'
          AND index_name = 'uq_notifications_user_source'
    ),
    'SET @mail_noop = 1',
    'ALTER TABLE notifications ADD UNIQUE KEY uq_notifications_user_source
     (user_id, source_type, source_id)'
);
PREPARE mail_stmt FROM @mail_sql;
EXECUTE mail_stmt;
DEALLOCATE PREPARE mail_stmt;
