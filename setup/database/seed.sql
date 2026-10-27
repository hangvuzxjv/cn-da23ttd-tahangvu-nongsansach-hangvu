SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO users (id, name, email, phone, password, role, status, email_verified_at) VALUES
(1, 'Nguyễn Minh An', 'customer@example.com', '0901000001', '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.', 'customer', 'active', NOW()),
(2, 'Quản trị viên', 'admin@example.com', '0901000002', '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.', 'admin', 'active', NOW()),
(3, 'Nhân viên bán hàng', 'sales@example.com', '0901000003', '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.', 'staff_sales', 'active', NOW()),
(4, 'Nhân viên kho', 'warehouse@example.com', '0901000004', '$2y$10$Om2rOB1t6FsKFKd48WLdUekO6m46UCvW67Lts2JY6VQjEFO/.mPQ.', 'staff_warehouse', 'active', NOW());

INSERT INTO regions (id, parent_id, name, level, slug) VALUES
(1, NULL, 'Miền Bắc', 1, 'mien-bac'),
(2, NULL, 'Miền Trung - Tây Nguyên', 1, 'mien-trung-tay-nguyen'),
(3, NULL, 'Miền Nam', 1, 'mien-nam'),
(4, 1, 'Thái Nguyên', 2, 'thai-nguyen'),
(5, 1, 'Hà Giang', 2, 'ha-giang'),
(6, 1, 'Sơn La', 2, 'son-la'),
(7, 2, 'Đắk Lắk', 2, 'dak-lak'),
(8, 2, 'Lâm Đồng', 2, 'lam-dong'),
(9, 2, 'Quảng Nam', 2, 'quang-nam'),
(10, 3, 'Bến Tre', 2, 'ben-tre'),
(11, 3, 'Tiền Giang', 2, 'tien-giang'),
(12, 3, 'Đồng Tháp', 2, 'dong-thap'),
(13, 3, 'Cần Thơ', 2, 'can-tho');
UPDATE regions SET description = CASE id
    WHEN 1 THEN 'Đặc sản và nông sản theo mùa từ các tỉnh miền Bắc.'
    WHEN 2 THEN 'Nông sản vùng duyên hải miền Trung và cao nguyên.'
    WHEN 3 THEN 'Trái cây, rau củ và đặc sản từ miền Nam.'
    WHEN 4 THEN 'Chè xanh nổi tiếng từ vùng Tân Cương, Thái Nguyên.'
    WHEN 7 THEN 'Cà phê và nông sản từ vùng đất đỏ Tây Nguyên.'
    WHEN 10 THEN 'Bưởi da xanh và dừa xiêm từ vùng đất Bến Tre.'
    WHEN 11 THEN 'Xoài cát Hòa Lộc và trái cây đặc sản Tiền Giang.'
    ELSE CONCAT('Nông sản đặc trưng vùng ', name, '.')
END;

INSERT INTO categories (id, parent_id, name, slug, description, sort_order) VALUES
(1, NULL, 'Rau củ', 'rau-cu', 'Rau củ tươi theo mùa.', 1),
(2, NULL, 'Trái cây', 'trai-cay', 'Trái cây đặc sản vùng miền.', 2),
(3, NULL, 'Gạo và ngũ cốc', 'gao-ngu-coc', 'Gạo, đậu và ngũ cốc.', 3),
(4, NULL, 'Đặc sản khô', 'dac-san-kho', 'Nông sản sơ chế, bảo quản khô.', 4),
(5, NULL, 'Trà và cà phê', 'tra-ca-phe', 'Trà, cà phê từ vùng nguyên liệu.', 5),
(6, NULL, 'Gia vị', 'gia-vi', 'Gia vị nông sản tự nhiên.', 6);

INSERT INTO farms (id, name, slug, region_id, address, contact_name, phone, certifications, description) VALUES
(1, 'HTX chè Tân Cương', 'htx-che-tan-cuong', 4, 'Tân Cương, Thái Nguyên', 'Nguyễn Văn Trà', '02083800001', 'VietGAP (dữ liệu mẫu)', 'Vùng chè xanh Tân Cương.'),
(2, 'HTX cam Hà Giang', 'htx-cam-ha-giang', 5, 'Bắc Quang, Hà Giang', 'Trần Thị Cam', '02193800002', 'OCOP (dữ liệu mẫu)', 'Vườn cam vùng cao.'),
(3, 'HTX mận Sơn La', 'htx-man-son-la', 6, 'Mộc Châu, Sơn La', 'Lò Văn Mận', '02233800003', 'VietGAP (dữ liệu mẫu)', 'Vùng trồng mận và nhãn.'),
(4, 'Nông trại cà phê Buôn Ma Thuột', 'nong-trai-ca-phe-buon-ma-thuot', 7, 'Buôn Ma Thuột, Đắk Lắk', 'Y Khoa', '02623800004', '4C (dữ liệu mẫu)', 'Nông trại cà phê Tây Nguyên.'),
(5, 'HTX rau Đà Lạt', 'htx-rau-da-lat', 8, 'Đà Lạt, Lâm Đồng', 'Lê Thị Xanh', '02633800005', 'VietGAP (dữ liệu mẫu)', 'Canh tác rau củ theo mùa.'),
(6, 'HTX quế Trà My', 'htx-que-tra-my', 9, 'Trà My, Quảng Nam', 'Hồ Văn Quế', '02353800006', 'OCOP (dữ liệu mẫu)', 'Vùng nguyên liệu quế.'),
(7, 'HTX bưởi Bến Tre', 'htx-buoi-ben-tre', 10, 'Chợ Lách, Bến Tre', 'Nguyễn Văn Bưởi', '02753800007', 'VietGAP (dữ liệu mẫu)', 'Vườn bưởi da xanh.'),
(8, 'HTX xoài Hòa Lộc', 'htx-xoai-hoa-loc', 11, 'Cái Bè, Tiền Giang', 'Phạm Thị Xoài', '02733800008', 'VietGAP (dữ liệu mẫu)', 'Vùng xoài cát Hòa Lộc.'),
(9, 'HTX sen Đồng Tháp', 'htx-sen-dong-thap', 12, 'Tháp Mười, Đồng Tháp', 'Võ Văn Sen', '02773800009', 'OCOP (dữ liệu mẫu)', 'Sản phẩm từ vùng sen Đồng Tháp.'),
(10, 'Vườn trái cây Cần Thơ', 'vuon-trai-cay-can-tho', 13, 'Phong Điền, Cần Thơ', 'Đặng Minh Vườn', '02923800010', 'VietGAP (dữ liệu mẫu)', 'Vườn cây ăn trái miền Tây.');

INSERT INTO products
(id, category_id, region_id, farm_id, name, slug, sku, unit, price, suggested_shelf_life_days, storage_instructions, certifications, status, total_stock, description)
VALUES
(1, 5, 4, 1, 'Chè Tân Cương loại đặc biệt', 'che-tan-cuong-dac-biet', 'NS-TRA-001', 'gói 500g', 185000, 365, 'Để nơi khô ráo, kín khí, tránh ánh nắng.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Chè xanh đặc sản Thái Nguyên.'),
(2, 2, 5, 2, 'Cam sành Hà Giang', 'cam-sanh-ha-giang', 'NS-TRAICAY-002', 'kg', 45000, 14, 'Bảo quản mát, tránh dập.', 'OCOP (dữ liệu mẫu)', 'active', 50, 'Cam sành theo mùa vùng Hà Giang.'),
(3, 2, 6, 3, 'Mận hậu Sơn La', 'man-hau-son-la', 'NS-TRAICAY-003', 'kg', 65000, 10, 'Bảo quản lạnh 4-8°C.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Mận hậu chín cây, thu hoạch theo mùa.'),
(4, 5, 7, 4, 'Cà phê Robusta Buôn Ma Thuột', 'ca-phe-robusta-buon-ma-thuot', 'NS-CAFE-004', 'gói 500g', 135000, 365, 'Đậy kín sau khi mở gói.', '4C (dữ liệu mẫu)', 'active', 50, 'Cà phê rang xay từ Đắk Lắk.'),
(5, 1, 8, 5, 'Khoai tây Đà Lạt', 'khoai-tay-da-lat', 'NS-RAUCU-005', 'kg', 32000, 30, 'Để nơi thoáng mát, tránh ánh sáng.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Khoai tây củ tươi Lâm Đồng.'),
(6, 6, 9, 6, 'Quế thanh Trà My', 'que-thanh-tra-my', 'NS-GIAVI-006', 'gói 250g', 95000, 365, 'Bảo quản khô, kín.', 'OCOP (dữ liệu mẫu)', 'active', 50, 'Quế thanh thơm từ Quảng Nam.'),
(7, 2, 10, 7, 'Bưởi da xanh Bến Tre', 'buoi-da-xanh-ben-tre', 'NS-TRAICAY-007', 'trái', 78000, 30, 'Để nơi thoáng mát.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Bưởi da xanh đặc sản Bến Tre.'),
(8, 2, 11, 8, 'Xoài cát Hòa Lộc', 'xoai-cat-hoa-loc', 'NS-TRAICAY-008', 'kg', 92000, 10, 'Để nhiệt độ phòng khi xanh, làm mát khi chín.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Xoài cát Hòa Lộc từ Tiền Giang.'),
(9, 4, 12, 9, 'Hạt sen sấy Đồng Tháp', 'hat-sen-say-dong-thap', 'NS-DACSAN-009', 'gói 200g', 110000, 180, 'Đậy kín, để nơi khô ráo.', 'OCOP (dữ liệu mẫu)', 'active', 50, 'Hạt sen sấy tiện lợi.'),
(10, 2, 13, 10, 'Sầu riêng Ri6 Cần Thơ', 'sau-rieng-ri6-can-tho', 'NS-TRAICAY-010', 'kg', 125000, 5, 'Bảo quản mát sau khi tách múi.', 'VietGAP (dữ liệu mẫu)', 'active', 50, 'Sầu riêng Ri6 theo mùa.'),
(11, 1, 8, 5, 'Cà rốt Đà Lạt', 'ca-rot-da-lat', 'NS-RAUCU-011', 'kg', 28000, 21, 'Bảo quản ngăn mát.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Cà rốt tươi từ Lâm Đồng.'),
(12, 2, 10, 7, 'Dừa xiêm xanh Bến Tre', 'dua-xiem-xanh-ben-tre', 'NS-TRAICAY-012', 'trái', 25000, 14, 'Để nơi mát, tránh nắng.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Dừa xiêm uống nước.'),
(13, 2, 11, 8, 'Thanh long Tiền Giang', 'thanh-long-tien-giang', 'NS-TRAICAY-013', 'kg', 38000, 14, 'Bảo quản nơi thoáng mát.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Thanh long ruột đỏ theo mùa.'),
(14, 3, 12, 9, 'Gạo tím than Đồng Tháp', 'gao-tim-than-dong-thap', 'NS-GAO-014', 'kg', 55000, 365, 'Để khô ráo, tránh côn trùng.', 'OCOP (dữ liệu mẫu)', 'active', 30, 'Gạo đặc sản Đồng Tháp.'),
(15, 2, 13, 10, 'Mít Thái Cần Thơ', 'mit-thai-can-tho', 'NS-TRAICAY-015', 'kg', 42000, 7, 'Bảo quản mát khi đã bổ.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Mít Thái trồng tại Cần Thơ.'),
(16, 5, 4, 1, 'Trà xanh Thái Nguyên túi lọc', 'tra-xanh-thai-nguyen-tui-loc', 'NS-TRA-016', 'hộp 20 túi', 68000, 365, 'Giữ kín, nơi khô mát.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Trà xanh tiện dụng, sản phẩm mẫu.'),
(17, 2, 5, 2, 'Lê Hà Giang', 'le-ha-giang', 'NS-TRAICAY-017', 'kg', 58000, 14, 'Bảo quản mát.', 'OCOP (dữ liệu mẫu)', 'active', 30, 'Lê trồng tại Hà Giang.'),
(18, 2, 6, 3, 'Nhãn Sơn La', 'nhan-son-la', 'NS-TRAICAY-018', 'kg', 52000, 7, 'Bảo quản lạnh sau thu hoạch.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Nhãn Sơn La theo mùa.'),
(19, 5, 7, 4, 'Cà phê Arabica Tây Nguyên', 'ca-phe-arabica-tay-nguyen', 'NS-CAFE-019', 'gói 500g', 165000, 365, 'Đậy kín sau khi mở gói.', '4C (dữ liệu mẫu)', 'active', 30, 'Cà phê Arabica rang xay từ vùng Tây Nguyên.'),
(20, 1, 8, 5, 'Bắp cải Đà Lạt', 'bap-cai-da-lat', 'NS-RAUCU-020', 'kg', 24000, 14, 'Bảo quản ngăn mát.', 'VietGAP (dữ liệu mẫu)', 'active', 30, 'Bắp cải tươi theo mùa.');

UPDATE products SET
    short_description = CONCAT(name, ' - nông sản sạch theo mùa từ vùng nguyên liệu.'),
    is_featured = IF(id IN (1, 4, 7, 8, 10, 14), 1, 0),
    sale_price = IF(id IN (1, 8), ROUND(price * 0.9, 0), NULL);

INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary) VALUES
(1, '/img/placeholder-tea-coffee.svg', 'Chè Tân Cương - hình minh họa', 1, 1),
(2, '/img/placeholder-fruit.svg', 'Cam sành Hà Giang - hình minh họa', 1, 1),
(3, '/img/placeholder-fruit.svg', 'Mận hậu Sơn La - hình minh họa', 1, 1),
(4, '/img/placeholder-tea-coffee.svg', 'Cà phê Buôn Ma Thuột - hình minh họa', 1, 1),
(5, '/img/placeholder-vegetables.svg', 'Khoai tây Đà Lạt - hình minh họa', 1, 1),
(6, '/img/placeholder-spices.svg', 'Quế thanh Trà My - hình minh họa', 1, 1),
(7, '/img/placeholder-fruit.svg', 'Bưởi da xanh Bến Tre - hình minh họa', 1, 1),
(8, '/img/placeholder-fruit.svg', 'Xoài cát Hòa Lộc - hình minh họa', 1, 1),
(9, '/img/placeholder-dry-goods.svg', 'Hạt sen sấy Đồng Tháp - hình minh họa', 1, 1),
(10, '/img/placeholder-fruit.svg', 'Sầu riêng Ri6 Cần Thơ - hình minh họa', 1, 1),
(11, '/img/placeholder-vegetables.svg', 'Cà rốt Đà Lạt - hình minh họa', 1, 1),
(12, '/img/placeholder-fruit.svg', 'Dừa xiêm xanh Bến Tre - hình minh họa', 1, 1),
(13, '/img/placeholder-fruit.svg', 'Thanh long Tiền Giang - hình minh họa', 1, 1),
(14, '/img/placeholder-grains.svg', 'Gạo tím than Đồng Tháp - hình minh họa', 1, 1),
(15, '/img/placeholder-fruit.svg', 'Mít Thái Cần Thơ - hình minh họa', 1, 1),
(16, '/img/placeholder-tea-coffee.svg', 'Trà xanh Thái Nguyên - hình minh họa', 1, 1),
(17, '/img/placeholder-fruit.svg', 'Lê Hà Giang - hình minh họa', 1, 1),
(18, '/img/placeholder-fruit.svg', 'Nhãn Sơn La - hình minh họa', 1, 1),
(19, '/img/placeholder-tea-coffee.svg', 'Cà phê Arabica Tây Nguyên - hình minh họa', 1, 1),
(20, '/img/placeholder-vegetables.svg', 'Bắp cải Đà Lạt - hình minh họa', 1, 1);

INSERT INTO product_batches
(id, product_id, batch_code, qr_token, harvested_at, packed_at, expires_at, quantity_received, quantity_remaining, inspection_result, inspection_notes)
VALUES
(1,1,'LO-20261001',SHA2('qr-LO-20261001',256),'2026-10-10','2026-10-11','2027-10-11',40,30,'passed','Kiểm định mẫu đạt.'),
(2,2,'LO-20261002',SHA2('qr-LO-20261002',256),'2026-10-12','2026-10-12','2026-10-26',40,30,'passed','Kiểm định mẫu đạt.'),
(3,3,'LO-20261003',SHA2('qr-LO-20261003',256),'2026-10-14','2026-10-14','2026-10-24',40,30,'passed','Kiểm định mẫu đạt.'),
(4,4,'LO-20261004',SHA2('qr-LO-20261004',256),'2026-10-05','2026-10-06','2027-10-06',40,30,'passed','Kiểm định mẫu đạt.'),
(5,5,'LO-20261005',SHA2('qr-LO-20261005',256),'2026-10-15','2026-10-15','2026-11-14',40,30,'passed','Kiểm định mẫu đạt.'),
(6,6,'LO-20261006',SHA2('qr-LO-20261006',256),'2026-10-01','2026-10-02','2027-10-02',40,30,'passed','Kiểm định mẫu đạt.'),
(7,7,'LO-20261007',SHA2('qr-LO-20261007',256),'2026-10-13','2026-10-13','2026-11-12',40,30,'passed','Kiểm định mẫu đạt.'),
(8,8,'LO-20261008',SHA2('qr-LO-20261008',256),'2026-10-16','2026-10-16','2026-10-26',40,30,'passed','Kiểm định mẫu đạt.'),
(9,9,'LO-20261009',SHA2('qr-LO-20261009',256),'2026-10-09','2026-10-10','2027-04-08',40,30,'passed','Kiểm định mẫu đạt.'),
(10,10,'LO-20261010',SHA2('qr-LO-20261010',256),'2026-10-20','2026-10-20','2026-10-25',40,30,'passed','Kiểm định mẫu đạt.'),
(11,11,'LO-20261011',SHA2('qr-LO-20261011',256),'2026-10-15','2026-10-15','2026-11-05',30,30,'passed','Kiểm định mẫu đạt.'),
(12,12,'LO-20261012',SHA2('qr-LO-20261012',256),'2026-10-17','2026-10-17','2026-10-31',30,30,'passed','Kiểm định mẫu đạt.'),
(13,13,'LO-20261013',SHA2('qr-LO-20261013',256),'2026-10-18','2026-10-18','2026-11-01',30,30,'passed','Kiểm định mẫu đạt.'),
(14,14,'LO-20261014',SHA2('qr-LO-20261014',256),'2026-10-08','2026-10-09','2027-10-09',30,30,'passed','Kiểm định mẫu đạt.'),
(15,15,'LO-20261015',SHA2('qr-LO-20261015',256),'2026-10-21','2026-10-21','2026-10-28',30,30,'passed','Kiểm định mẫu đạt.'),
(16,16,'LO-20261016',SHA2('qr-LO-20261016',256),'2026-10-01','2026-10-02','2027-10-02',30,30,'passed','Kiểm định mẫu đạt.'),
(17,17,'LO-20261017',SHA2('qr-LO-20261017',256),'2026-10-14','2026-10-14','2026-10-28',30,30,'passed','Kiểm định mẫu đạt.'),
(18,18,'LO-20261018',SHA2('qr-LO-20261018',256),'2026-10-19','2026-10-19','2026-10-26',30,30,'passed','Kiểm định mẫu đạt.'),
(19,19,'LO-20261019',SHA2('qr-LO-20261019',256),'2026-10-03','2026-10-04','2027-10-04',30,30,'passed','Kiểm định mẫu đạt.'),
(20,20,'LO-20261020',SHA2('qr-LO-20261020',256),'2026-10-20','2026-10-20','2026-11-03',30,30,'passed','Kiểm định mẫu đạt.'),
(21,1,'LO-20261021',SHA2('qr-LO-20261021',256),'2026-10-18','2026-10-19','2027-10-19',30,20,'passed','Kiểm định mẫu đạt.'),
(22,2,'LO-20261022',SHA2('qr-LO-20261022',256),'2026-10-19','2026-10-19','2026-11-02',30,20,'passed','Kiểm định mẫu đạt.'),
(23,3,'LO-20261023',SHA2('qr-LO-20261023',256),'2026-10-20','2026-10-20','2026-10-30',30,20,'passed','Kiểm định mẫu đạt.'),
(24,4,'LO-20261024',SHA2('qr-LO-20261024',256),'2026-10-06','2026-10-07','2027-10-07',30,20,'passed','Kiểm định mẫu đạt.'),
(25,5,'LO-20261025',SHA2('qr-LO-20261025',256),'2026-10-18','2026-10-18','2026-11-17',30,20,'passed','Kiểm định mẫu đạt.'),
(26,6,'LO-20261026',SHA2('qr-LO-20261026',256),'2026-10-02','2026-10-03','2027-10-03',30,20,'passed','Kiểm định mẫu đạt.'),
(27,7,'LO-20261027',SHA2('qr-LO-20261027',256),'2026-10-19','2026-10-19','2026-11-18',30,20,'passed','Kiểm định mẫu đạt.'),
(28,8,'LO-20261028',SHA2('qr-LO-20261028',256),'2026-10-20','2026-10-20','2026-10-30',30,20,'passed','Kiểm định mẫu đạt.'),
(29,9,'LO-20261029',SHA2('qr-LO-20261029',256),'2026-10-10','2026-10-11','2027-04-09',30,20,'passed','Kiểm định mẫu đạt.'),
(30,10,'LO-20261030',SHA2('qr-LO-20261030',256),'2026-10-22','2026-10-22','2026-10-27',30,20,'passed','Kiểm định mẫu đạt.');

INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'planting', DATE_SUB(CONCAT(harvested_at, ' 08:00:00'), INTERVAL 90 DAY), 'Nông trại', CONCAT('Ghi nhận vùng trồng cho lô ', batch_code, '.')
FROM product_batches;
INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'care', DATE_SUB(CONCAT(harvested_at, ' 08:00:00'), INTERVAL 30 DAY), 'Nông trại', CONCAT('Theo dõi chăm sóc và sinh trưởng lô ', batch_code, '.')
FROM product_batches;
INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'harvest', CONCAT(harvested_at, ' 08:00:00'), 'Nông trại', CONCAT('Thu hoạch lô ', batch_code, '.')
FROM product_batches;
INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'processing', CONCAT(DATE_ADD(harvested_at, INTERVAL 1 DAY), ' 09:00:00'), 'Khu sơ chế', CONCAT('Sơ chế và kiểm tra chất lượng lô ', batch_code, '.')
FROM product_batches;
INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'packaging', CONCAT(DATE_ADD(harvested_at, INTERVAL 1 DAY), ' 14:00:00'), 'Khu đóng gói', CONCAT('Đóng gói, dán mã truy xuất lô ', batch_code, '.')
FROM product_batches;
INSERT INTO trace_events (batch_id, event_type, event_at, location, description)
SELECT id, 'transport', CONCAT(DATE_ADD(harvested_at, INTERVAL 2 DAY), ' 08:00:00'), 'Kho Nông Sản Sạch', CONCAT('Vận chuyển về kho lô ', batch_code, '.')
FROM product_batches;

INSERT INTO warehouse_receipts (id, receipt_no, supplier_name, received_by, received_at, status, notes)
VALUES (1, 'PN-2026-001', 'Các nông trại trong danh sách mẫu', 4, NOW(), 'received', 'Phiếu nhập mẫu cho 30 lô hàng.');
INSERT INTO warehouse_receipt_items (receipt_id, batch_id, quantity, unit_cost)
SELECT 1, id, quantity_received, 0 FROM product_batches;
INSERT INTO inventory_movements (product_id, batch_id, movement_type, quantity, reference_type, reference_id, performed_by, notes)
SELECT product_id, id, 'in', quantity_received, 'warehouse_receipt', 1, 4, 'Nhập kho dữ liệu mẫu'
FROM product_batches;

INSERT INTO addresses (id, user_id, recipient_name, phone, region_id, address_line, ward, is_default)
VALUES (1, 1, 'Nguyễn Minh An', '0901000001', 10, '12 Đường Đồng Khởi', 'Phường An Hội', 1);

INSERT INTO carts (id, user_id, status) VALUES (1, 1, 'active');
INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (1, 7, 1);

INSERT INTO orders
(id, user_id, order_no, status, payment_status, recipient_name, recipient_phone, shipping_address, region_name, subtotal, shipping_fee, discount_amount, total_amount, placed_at)
VALUES
(1, 1, 'DH-2026-0001', 'confirmed', 'unpaid', 'Nguyễn Minh An', '0901000001', '12 Đường Đồng Khởi, Bến Tre', 'Bến Tre', 78000, 25000, 0, 103000, NOW());
INSERT INTO order_items (order_id, product_id, batch_id, product_name, sku, unit, unit_price, quantity, line_total)
VALUES (1, 7, 7, 'Bưởi da xanh Bến Tre', 'NS-TRAICAY-007', 'trái', 78000, 1, 78000);
INSERT INTO order_status_history (order_id, status, note, changed_by)
VALUES (1, 'confirmed', 'Đơn hàng mẫu đã được xác nhận.', 3);
INSERT INTO payments (order_id, method, amount, status)
VALUES (1, 'cod', 103000, 'pending');

INSERT INTO shipping_fees (region_id, fee, free_shipping_minimum) VALUES
(4, 30000, 500000), (5, 35000, 500000), (6, 30000, 500000),
(7, 35000, 500000), (8, 30000, 500000), (9, 35000, 500000),
(10, 25000, 400000), (11, 25000, 400000), (12, 28000, 400000), (13, 28000, 400000);

INSERT INTO vouchers (id, code, description, discount_type, discount_value, minimum_order, maximum_discount, usage_limit, starts_at, ends_at, status) VALUES
(1, 'CHAOMUNG', 'Giảm giá đơn hàng đầu tiên (mẫu).', 'percent', 10, 100000, 50000, 100, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
(2, 'NONGSAN20', 'Giảm 20.000 đồng (mẫu).', 'fixed', 20000, 200000, NULL, 200, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
(3, 'FREESHIP', 'Ưu đãi phí vận chuyển theo điều kiện.', 'fixed', 25000, 150000, 25000, 100, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active');

INSERT INTO flash_sales (id, name, starts_at, ends_at, status)
VALUES (1, 'Flash Sale nông sản mùa thu', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active');
INSERT INTO flash_sale_items (id, flash_sale_id, product_id, sale_price, quota, sold_quantity, per_user_limit)
VALUES (1, 1, 1, 149000, 25, 0, 2);

INSERT INTO reviews (user_id, product_id, order_id, rating, title, content, status)
VALUES (1, 7, 1, 5, 'Sản phẩm mẫu', 'Đánh giá mẫu cho dữ liệu phát triển.', 'published');
INSERT INTO wishlists (user_id, product_id) VALUES (1, 8);
INSERT INTO banners (title, image_url, link_url, sort_order, status)
VALUES ('Nông sản sạch theo mùa', '/img/banner-fresh.svg', '/san-pham/xoai-cat-hoa-loc', 1, 'active');
INSERT INTO pages (title, slug, content, status, published_at)
VALUES ('Giới thiệu', 'gioi-thieu', '<p>Trang giới thiệu mẫu.</p>', 'published', NOW());
INSERT INTO contacts (name, email, subject, message, status)
VALUES ('Khách hàng mẫu', 'contact@example.com', 'Tư vấn sản phẩm', 'Nội dung liên hệ mẫu.', 'new');
INSERT INTO notifications (user_id, title, message, link_url)
VALUES (1, 'Chào mừng', 'Tài khoản dữ liệu mẫu đã sẵn sàng.', '/');
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
VALUES (2, 'seed_imported', 'database', 1, 'Tạo dữ liệu mẫu ban đầu.');
INSERT INTO settings (setting_key, setting_value, description) VALUES
('store_name', 'Nông Sản Sạch', 'Tên hiển thị cửa hàng'),
('currency', 'VND', 'Đơn vị tiền tệ');

COMMIT;
