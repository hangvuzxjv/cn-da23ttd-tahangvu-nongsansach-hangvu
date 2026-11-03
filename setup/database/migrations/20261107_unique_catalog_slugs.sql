SET @database_name = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE regions ADD UNIQUE KEY uq_regions_slug (slug)', 'DO 0')
    FROM information_schema.statistics
    WHERE table_schema = @database_name AND table_name = 'regions'
      AND index_name = 'uq_regions_slug'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE categories ADD UNIQUE KEY uq_categories_slug (slug)', 'DO 0')
    FROM information_schema.statistics
    WHERE table_schema = @database_name AND table_name = 'categories'
      AND index_name = 'uq_categories_slug'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE farms ADD UNIQUE KEY uq_farms_slug (slug)', 'DO 0')
    FROM information_schema.statistics
    WHERE table_schema = @database_name AND table_name = 'farms'
      AND index_name = 'uq_farms_slug'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0, 'ALTER TABLE products ADD UNIQUE KEY uq_products_slug (slug)', 'DO 0')
    FROM information_schema.statistics
    WHERE table_schema = @database_name AND table_name = 'products'
      AND index_name = 'uq_products_slug'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
