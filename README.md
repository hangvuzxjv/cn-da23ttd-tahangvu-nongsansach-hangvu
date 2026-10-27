# Hệ thống bán Nông Sản Sạch

Đồ án xây dựng website giới thiệu và bán nông sản sạch. Mã nguồn ứng dụng nằm trong `scr/`; đây là document root và `scr/index.php` là front controller duy nhất.

## Cấu trúc chính

```text
scr/
├── backend/
│   ├── Controllers/       # Bộ điều khiển
│   ├── Core/              # Router, Request/Response, View, Database, Auth...
│   ├── Helpers/           # Hàm dùng chung
│   ├── Models/            # Tầng dữ liệu
│   ├── config/            # Cấu hình riêng và config.sample.php
│   ├── routes.php         # Khai báo tập trung các route và middleware
│   └── storage/logs/      # Nhật ký ứng dụng
├── frontend/views/        # Layout, partial, trang client/admin và lỗi
├── css/, js/, img/        # Tài nguyên giao diện
├── uploads/               # Ảnh tải lên; không cho phép thực thi mã
├── .htaccess              # Rewrite URL về front controller
└── index.php
setup/database/
├── schema.sql             # Tạo bảng, khóa ngoại và chỉ mục
├── seed.sql               # Tài khoản và dữ liệu mẫu
└── ERD.md                 # ERD Mermaid và mô tả bảng
```

## Cài đặt CSDL bằng phpMyAdmin (XAMPP/Laragon)

1. Khởi động Apache và MySQL/MariaDB; yêu cầu InnoDB, MySQL 5.7+ hoặc MariaDB 10.x.
2. Mở phpMyAdmin, tạo CSDL tên `nongsan`, chọn collation `utf8mb4_unicode_ci`.
3. Chọn CSDL `nongsan` → **Import** → chọn `setup/database/schema.sql` → **Go/Thực hiện**.
4. Sau khi schema import thành công, import tiếp `setup/database/seed.sql` theo cách tương tự.
5. Tạo cấu hình cục bộ từ `scr/backend/config/config.sample.php` (hoặc dùng `config.php` đã có). Mặc định phát triển là host `localhost`, port `3306`, database `nongsan`, user `root`, mật khẩu rỗng. Tệp `config.php` được Git bỏ qua; không dùng thông tin mặc định này trên hosting.
6. Đặt DocumentRoot của Virtual Host trỏ vào thư mục `scr/`, bật Apache `mod_rewrite` và cho phép `.htaccess` (`AllowOverride All`), rồi truy cập tên miền.

Hai file SQL có thể import trực tiếp sau khi đã chọn database trong phpMyAdmin. `schema.sql` xóa và tạo lại các bảng theo thứ tự phụ thuộc khóa ngoại; **không chạy lại trên CSDL có dữ liệu cần giữ**.

## Tài khoản dữ liệu mẫu

Tất cả tài khoản sau dùng mật khẩu thử `password` (bcrypt). Chỉ dùng để phát triển:

| Vai trò | Email |
|---|---|
| customer | `customer@example.com` |
| admin | `admin@example.com` |
| staff_sales | `sales@example.com` |
| staff_warehouse | `warehouse@example.com` |

Seed có 3 miền, 10 tỉnh/vùng, 6 danh mục, 10 nông trại, 20 sản phẩm, 30 lô hàng (mỗi lô có 6 sự kiện truy xuất), một phiếu nhập, một đơn hàng mẫu, 3 voucher, phí ship, và một Flash Sale có thời gian bắt đầu/kết thúc tương đối với lúc import. Các chứng nhận và hồ sơ nông trại trong seed là dữ liệu minh họa, không phải xác nhận chứng nhận thực tế.

## Kiểm tra dữ liệu sau import

Đã import schema và seed, sau đó chạy 5 truy vấn dưới đây trên MySQL 8.4. Kết quả ghi lại tương ứng với seed chưa bị chỉnh sửa; đơn vị giá là VND.

**1. Sản phẩm theo tỉnh/vùng (Bến Tre):**

```sql
SELECT p.name, p.sku, p.price, p.total_stock
FROM products p
JOIN regions r ON r.id = p.region_id
WHERE r.slug = 'ben-tre'
ORDER BY p.id;
```

Kết quả: `Bưởi da xanh Bến Tre` (`NS-TRAICAY-007`, 78.000, tồn 50) và `Dừa xiêm xanh Bến Tre` (`NS-TRAICAY-012`, 25.000, tồn 30).

**2. Tồn kho theo lô hàng:**

```sql
SELECT b.batch_code, p.name, b.quantity_received, b.quantity_remaining
FROM product_batches b
JOIN products p ON p.id = b.product_id
WHERE b.batch_code = 'LO-20261001';
```

Kết quả: `LO-20261001`, `Chè Tân Cương loại đặc biệt`, nhập 40, còn 30.

**3. Truy xuất một mã lô:**

```sql
SELECT b.batch_code, p.name, t.event_type, t.event_at, t.location, t.description
FROM product_batches b
JOIN products p ON p.id = b.product_id
JOIN trace_events t ON t.batch_id = b.id
WHERE b.batch_code = 'LO-20261001'
ORDER BY t.event_at, t.id;
```

Kết quả: 6 sự kiện của lô `LO-20261001`, lần lượt gồm gieo trồng, chăm sóc, thu hoạch, sơ chế, đóng gói và vận chuyển.

**4. Flash Sale đang diễn ra:**

```sql
SELECT fs.name, p.name AS product_name, fsi.sale_price, fsi.quota, fsi.sold_quantity
FROM flash_sales fs
JOIN flash_sale_items fsi ON fsi.flash_sale_id = fs.id
JOIN products p ON p.id = fsi.product_id
WHERE fs.status = 'active' AND NOW() BETWEEN fs.starts_at AND fs.ends_at;
```

Kết quả: chiến dịch `Flash Sale nông sản mùa thu`, sản phẩm `Chè Tân Cương loại đặc biệt`, giá 149.000, hạn mức 25, đã bán 0.

**5. Đơn hàng của người dùng mẫu:**

```sql
SELECT o.order_no, o.status, o.subtotal, o.shipping_fee, o.total_amount
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE u.email = 'customer@example.com'
ORDER BY o.placed_at DESC;
```

Kết quả: đơn `DH-2026-0001`, trạng thái `confirmed`, tạm tính 78.000, phí ship 25.000, tổng 103.000.

## Route kiểm tra kết nối

Đặt `app.debug` thành `true` trong `scr/backend/config/config.php`, sau đó mở `/_dev/db-check`. Route chỉ được đăng ký khi debug bật; trang hiển thị kết nối, tổng số bảng và số bản ghi từng bảng. Tắt debug trên hosting/production vì trang có thể tiết lộ cấu trúc và dữ liệu CSDL. Route kết nối thật tới database nên cũng là phép thử nhanh cho cấu hình và seed.

## Routes và middleware

Các route ứng dụng được khai báo tập trung trong `scr/backend/routes.php`. Router hỗ trợ GET/POST, tham số `{id}` (chữ số) và `{slug}`, cùng middleware `auth`, `role:<tên-vai-trò>` và `csrf`. Các form POST phải gửi token bằng helper `csrf_field()`.

Ứng dụng ghi log vào `scr/backend/storage/logs/app.log`. Trang `/admin` yêu cầu đăng nhập giả lập tại `/login`; đây là luồng demo hiện tại, chưa phải xác thực người dùng production.
