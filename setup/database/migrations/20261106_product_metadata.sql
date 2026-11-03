SET @database_name = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN season VARCHAR(255) NULL AFTER short_description', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'season'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN nutrition TEXT NULL AFTER season', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'nutrition'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN preparation TEXT NULL AFTER nutrition', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'preparation'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL AFTER preparation', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'tags'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN seo_title VARCHAR(255) NULL AFTER tags', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'seo_title'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD COLUMN seo_description VARCHAR(500) NULL AFTER seo_title', 'DO 0')
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'products' AND column_name = 'seo_description'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
