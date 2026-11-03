-- Back up the database before applying. This migration is safe to run repeatedly.
-- Adds banner copy fields and seeds five local, non-destructive sample banners.
SET @banner_description_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'banners' AND column_name = 'description'),
    'SET @banner_noop = 1',
    'ALTER TABLE banners ADD COLUMN description VARCHAR(300) DEFAULT NULL AFTER title'
);
PREPARE banner_stmt FROM @banner_description_sql;
EXECUTE banner_stmt;
DEALLOCATE PREPARE banner_stmt;

SET @banner_button_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'banners' AND column_name = 'button_label'),
    'SET @banner_noop = 1',
    'ALTER TABLE banners ADD COLUMN button_label VARCHAR(80) DEFAULT NULL AFTER image_url'
);
PREPARE banner_stmt FROM @banner_button_sql;
EXECUTE banner_stmt;
DEALLOCATE PREPARE banner_stmt;

INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Rau củ tươi theo mùa', 'Rau xanh được chọn theo mùa và đóng gói cẩn thận.', '/img/banners/banner-1.svg', 'Xem sản phẩm', '/san-pham', 10, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE image_url = '/img/banners/banner-1.svg');
INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Trái cây đặc sản các vùng miền', 'Khám phá hương vị đặc trưng từ nhiều vùng trồng.', '/img/banners/banner-2.svg', 'Xem sản phẩm', '/san-pham', 20, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE image_url = '/img/banners/banner-2.svg');
INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Gạo và ngũ cốc chọn lọc', 'Những sản phẩm thiết yếu được tuyển chọn rõ ràng.', '/img/banners/banner-3.svg', 'Xem sản phẩm', '/san-pham', 30, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE image_url = '/img/banners/banner-3.svg');
INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Trà và cà phê vùng cao', 'Hương vị mộc mạc từ những vùng cao nhiều nắng gió.', '/img/banners/banner-4.svg', 'Xem sản phẩm', '/san-pham', 40, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE image_url = '/img/banners/banner-4.svg');
INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Truy xuất nguồn gốc rõ ràng', 'Theo dõi thông tin lô hàng bằng mã truy xuất.', '/img/banners/banner-5.svg', 'Tra cứu nguồn gốc', '/truy-xuat', 50, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE image_url = '/img/banners/banner-5.svg');
