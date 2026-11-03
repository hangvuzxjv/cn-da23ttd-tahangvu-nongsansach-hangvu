-- Back up the database before applying. This migration is safe to run repeatedly.
-- It only adds missing default banners and does not update or delete existing rows.
INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Nông sản sạch từ vườn đến bàn ăn', 'Sản phẩm theo mùa, được chọn lọc và đóng gói cẩn thận.', '/img/banners/restore-vegetables.svg', 'Xem sản phẩm', '/san-pham', 1, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE title = 'Nông sản sạch từ vườn đến bàn ăn');

INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Trái cây miền Tây theo mùa', 'Hương vị trái cây tươi từ những vùng trồng miền Tây.', '/img/banners/restore-mekong-fruit.svg', 'Xem sản phẩm', '/san-pham', 2, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE title = 'Trái cây miền Tây theo mùa');

INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Gạo và ngũ cốc chọn lọc', 'Lựa chọn quen thuộc cho bữa ăn đủ đầy mỗi ngày.', '/img/banners/restore-grains.svg', 'Xem sản phẩm', '/san-pham', 3, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE title = 'Gạo và ngũ cốc chọn lọc');

INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Trà và cà phê vùng cao', 'Hương vị mộc mạc từ những vùng cao nhiều nắng gió.', '/img/banners/restore-tea-coffee.svg', 'Xem sản phẩm', '/san-pham', 4, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE title = 'Trà và cà phê vùng cao');

INSERT INTO banners (title, description, image_url, button_label, link_url, sort_order, status)
SELECT 'Truy xuất nguồn gốc bằng mã QR', 'Theo dõi thông tin lô hàng rõ ràng từ sản phẩm.', '/img/banners/restore-market-basket.svg', 'Tra cứu nguồn gốc', '/truy-xuat', 5, 'active'
WHERE NOT EXISTS (SELECT 1 FROM banners WHERE title = 'Truy xuất nguồn gốc bằng mã QR');
