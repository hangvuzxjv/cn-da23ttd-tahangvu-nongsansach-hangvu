-- Back up the database before applying. This migration is safe to run repeatedly.
-- Do not run schema.sql or seed.sql against a database containing existing data.

CREATE TABLE IF NOT EXISTS contact_rate_limits (
    key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (key_hash),
    KEY idx_contact_rate_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @report_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'orders'
          AND index_name = 'idx_orders_report_date_status'
    ),
    'SET @report_noop = 1',
    'ALTER TABLE orders ADD KEY idx_orders_report_date_status (placed_at, status)'
);
PREPARE report_stmt FROM @report_sql;
EXECUTE report_stmt;
DEALLOCATE PREPARE report_stmt;

SET @report_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'order_items'
          AND index_name = 'idx_order_items_product_order'
    ),
    'SET @report_noop = 1',
    'ALTER TABLE order_items ADD KEY idx_order_items_product_order (product_id, order_id)'
);
PREPARE report_stmt FROM @report_sql;
EXECUTE report_stmt;
DEALLOCATE PREPARE report_stmt;

SET @report_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'inventory_movements'
          AND index_name = 'idx_inventory_report_date_type_batch'
    ),
    'SET @report_noop = 1',
    'ALTER TABLE inventory_movements ADD KEY idx_inventory_report_date_type_batch (created_at, movement_type, batch_id)'
);
PREPARE report_stmt FROM @report_sql;
EXECUTE report_stmt;
DEALLOCATE PREPARE report_stmt;

INSERT INTO pages (title, slug, content, status, published_at)
VALUES
    ('Giới thiệu', 'gioi-thieu',
     '<p>Nông Sản Sạch kết nối gia đình với nông sản theo mùa được tuyển chọn từ các vùng trồng Việt Nam.</p><h2>Chúng tôi cam kết</h2><ul><li>Thông tin sản phẩm, nguồn gốc và lô hàng được trình bày rõ ràng.</li><li>Ưu tiên nhà cung cấp có quy trình canh tác minh bạch và tôn trọng mùa vụ.</li><li>Đóng gói cẩn thận, hỗ trợ khách hàng trước và sau khi mua.</li></ul><p>Bạn có thể dùng mã QR trên sản phẩm để xem thêm thông tin truy xuất nguồn gốc.</p>',
     'published', NOW()),
    ('Chính sách đổi trả', 'chinh-sach-doi-tra',
     '<p>Nông Sản Sạch mong muốn mỗi đơn hàng đến tay bạn trong tình trạng tốt nhất. Vui lòng kiểm tra sản phẩm khi nhận hàng.</p><h2>Trường hợp được hỗ trợ</h2><ul><li>Sản phẩm bị dập hỏng, sai mặt hàng hoặc thiếu số lượng so với đơn.</li><li>Chất lượng không đúng mô tả do lỗi bảo quản hoặc đóng gói của cửa hàng.</li></ul><p>Hãy liên hệ trong vòng 24 giờ kể từ khi nhận hàng, kèm mã đơn và hình ảnh hiện trạng. Chúng tôi sẽ xác minh và thống nhất phương án đổi sản phẩm hoặc hoàn tiền phù hợp.</p><p>Sản phẩm tươi đã sử dụng hoặc bảo quản không theo hướng dẫn có thể không đủ điều kiện đổi trả, trừ trường hợp pháp luật quy định khác.</p>',
     'published', NOW()),
    ('Chính sách vận chuyển', 'chinh-sach-van-chuyen',
     '<p>Đơn hàng được xác nhận sau khi cửa hàng kiểm tra thông tin và tình trạng hàng hóa. Thời gian giao phụ thuộc địa chỉ nhận, thời tiết và lịch vận chuyển tại từng khu vực.</p><h2>Đóng gói và giao nhận</h2><ul><li>Nông sản được phân loại, đóng gói phù hợp với đặc tính từng mặt hàng.</li><li>Phí vận chuyển và điều kiện miễn phí (nếu có) được hiển thị trước khi đặt hàng.</li><li>Nhân viên giao hàng có thể liên hệ để xác nhận thời gian nhận hàng.</li></ul><p>Nếu địa chỉ hoặc lịch nhận thay đổi, vui lòng liên hệ cửa hàng sớm và cung cấp mã đơn để được hỗ trợ.</p>',
     'published', NOW()),
    ('Chính sách bảo mật', 'chinh-sach-bao-mat',
     '<p>Chúng tôi chỉ thu thập thông tin cần thiết để xử lý đơn hàng, giao hàng, hỗ trợ khách hàng và bảo vệ tài khoản.</p><h2>Sử dụng thông tin</h2><ul><li>Thông tin liên hệ được dùng để xác nhận đơn và trao đổi về dịch vụ.</li><li>Thông tin thanh toán được xử lý theo phương thức khách hàng lựa chọn; cửa hàng không yêu cầu khách gửi mật khẩu ngân hàng.</li><li>Chúng tôi không bán thông tin cá nhân cho bên thứ ba.</li></ul><p>Thông tin chỉ được chia sẻ với đơn vị vận chuyển hoặc nhà cung cấp dịch vụ cần thiết để hoàn thành yêu cầu của bạn, hoặc khi pháp luật yêu cầu. Bạn có thể liên hệ cửa hàng để yêu cầu cập nhật thông tin tài khoản.</p>',
     'published', NOW()),
    ('Điều khoản sử dụng', 'dieu-khoan-su-dung',
     '<p>Khi sử dụng website, bạn đồng ý cung cấp thông tin chính xác, bảo vệ thông tin đăng nhập và không sử dụng dịch vụ cho mục đích trái pháp luật.</p><h2>Thông tin sản phẩm và đơn hàng</h2><ul><li>Nông sản tự nhiên có thể khác nhẹ về kích thước, màu sắc hoặc hình thức theo mùa.</li><li>Giá, tồn kho, phí vận chuyển và ưu đãi được xác nhận tại thời điểm đặt hàng.</li><li>Đơn hàng chỉ được xem là tiếp nhận sau khi website hiển thị xác nhận đặt hàng.</li></ul><p>Nông Sản Sạch có thể cập nhật nội dung, giá và điều kiện dịch vụ để phản ánh tình trạng kinh doanh; các thay đổi không làm thay đổi điều kiện của đơn đã được xác nhận.</p>',
     'published', NOW()),
    ('Câu hỏi thường gặp', 'cau-hoi-thuong-gap',
     '<h2>Sản phẩm có nguồn gốc ở đâu?</h2><p>Thông tin vùng miền, nông trại và lô hàng được giới thiệu trên trang sản phẩm; một số mặt hàng có thể truy xuất thêm bằng mã QR.</p><h2>Làm sao kiểm tra đơn hàng?</h2><p>Đăng nhập và mở mục Đơn hàng để xem trạng thái, thông tin giao nhận và thanh toán.</p><h2>Có thể hủy đơn không?</h2><p>Khả năng hủy phụ thuộc trạng thái xử lý. Hãy mở chi tiết đơn hoặc liên hệ cửa hàng sớm nhất có thể.</p><h2>Tôi nhận hàng bị hư hỏng thì làm gì?</h2><p>Chụp ảnh sản phẩm và liên hệ chúng tôi trong vòng 24 giờ kể từ khi nhận hàng để được hỗ trợ.</p><h2>Cửa hàng có giao đến khu vực của tôi không?</h2><p>Địa chỉ phục vụ và phí vận chuyển sẽ được kiểm tra khi bạn nhập địa chỉ ở bước thanh toán.</p>',
     'published', NOW())
ON DUPLICATE KEY UPDATE
    status = IF(content = '<p>Trang giới thiệu mẫu.</p>', VALUES(status), status),
    published_at = IF(content = '<p>Trang giới thiệu mẫu.</p>', VALUES(published_at), published_at),
    title = IF(content = '<p>Trang giới thiệu mẫu.</p>', VALUES(title), title),
    content = IF(content = '<p>Trang giới thiệu mẫu.</p>', VALUES(content), content);
