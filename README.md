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

Ứng dụng ghi log vào `scr/backend/storage/logs/app.log`. Tài khoản và quyền truy cập được kiểm tra từ bảng `users`; xem các luồng xác thực bên dưới.

## Xác thực và phân quyền

- Đăng ký tại `/register`; server kiểm tra họ tên, email duy nhất, điện thoại, mật khẩu tối thiểu 8 ký tự có chữ/số và xác nhận mật khẩu. Mật khẩu lưu bằng bcrypt.
- Đăng nhập tại `/login`, đăng xuất chỉ qua POST có CSRF. Email/mật khẩu sai dùng chung thông báo; năm lần sai liên tiếp theo cặp email/IP khóa cặp đó trong 15 phút. Bảng mới `login_attempts` chỉ lưu SHA-256 email/IP, số lần và thời hạn khóa.
- `/forgot-password` tạo token ngẫu nhiên; DB chỉ lưu SHA-256 token, hết hạn sau 60 phút và chỉ dùng một lần. `/reset-password/{token}` nhận mật khẩu mới. Email xác thực tùy chọn tại `/verify-email/{token}`; thay đổi email trong hồ sơ sẽ yêu cầu xác thực lại.
- `/profile` cho phép cập nhật họ tên/email/điện thoại và đổi mật khẩu với mật khẩu hiện tại. `/addresses` cung cấp CRUD sổ địa chỉ và địa chỉ mặc định.
- Session ID được đổi khi đăng nhập; cookie có `HttpOnly`, `SameSite=Lax`, bật `Secure` khi HTTPS. Session hết hạn sau hai giờ không hoạt động.
- Các thay đổi đăng nhập/đăng xuất, đổi mật khẩu, đổi vai trò và khóa/mở tài khoản được ghi vào `audit_logs`.

### Cấu hình email

`scr/backend/config/config.php` có cấu hình `mailer.driver` là `log` (mặc định) hoặc `smtp`. Đặt `app.url` thành URL chuẩn của ứng dụng để tạo liên kết email chính xác (không lấy domain từ HTTP Host của request). Driver `log` ghi email và liên kết vào `scr/backend/storage/logs/mail.log`; nếu `app.debug=true`, link demo cũng hiện trên trang sau thao tác tương ứng. Thư mục log bị Apache chặn truy cập trực tiếp.

Driver `smtp` dùng PHPMailer. Chạy `composer install`, sau đó điền `mailer.smtp.host`, `port`, `username`, `password`, `encryption` và `mailer.from_address` trong cấu hình riêng. Tệp cấu hình thực tế không được commit. `Mailer` là interface và `MailerFactory` chọn driver; có thể thêm driver HTTP API sau này mà không đổi các luồng xác thực.

### Quyền quản trị

Ma trận quyền có tại `scr/backend/config/permissions.php`. `admin` có toàn quyền; `staff_sales` có dashboard, đơn hàng, voucher, đánh giá và danh sách khách hàng; `staff_warehouse` có dashboard, kho, lô hàng và chỉ xem sản phẩm; `customer` không có quyền quản trị. Các route `/admin/*` được bảo vệ bằng middleware `permission:<tên-quyền>`. Menu ẩn mục không được phép, nhưng route vẫn được kiểm tra server-side và trả trang 403. Người chưa đăng nhập được chuyển tới `/login`, sau đó trở lại URL nội bộ ban đầu.

`/admin/users` cho phép admin tìm kiếm/lọc tài khoản, đổi vai trò và khóa/mở tài khoản; không thể tự đổi vai trò hay trạng thái của chính tài khoản đang đăng nhập. `/admin/customers` chỉ hiển thị danh sách khách hàng cho nhân viên bán hàng.

### Cập nhật schema đã có

`setup/database/schema.sql` có thêm bảng `login_attempts` cho cài đặt mới. Nếu đã tạo CSDL ở Bước 2 và cần giữ dữ liệu, import riêng `setup/database/migrations/20261027_auth_login_attempts.sql` vào database đã chọn trong phpMyAdmin. Migration dùng `CREATE TABLE IF NOT EXISTS`, không xóa dữ liệu hiện có.

### Kiểm tra xác thực đã chạy

Đã kiểm tra qua PHP development server và MySQL cục bộ:

- Đăng ký khách hàng, bcrypt, mail xác thực ghi vào `mail.log`, sau đó vào hồ sơ: **đạt**.
- Năm mật khẩu sai liên tiếp rồi thử đúng trong thời gian khóa: bị từ chối với thông báo chung; bảng `login_attempts` lưu khóa theo email/IP: **đạt**.
- Quên mật khẩu: link từ `mail.log`/debug đặt lại được mật khẩu; mở lại token trả lỗi hết hạn/đã dùng: **đạt**.
- Đăng nhập customer, admin, staff_sales, staff_warehouse và kiểm tra route quản trị cho phép/từ chối theo quyền: **đạt**; customer vào `/admin` nhận HTTP 403.
- POST `/logout` thiếu CSRF nhận HTTP 419; thao tác quản trị không thể tự đổi vai trò/trạng thái và các thay đổi tài khoản được ghi audit: **đạt**.
- Xác thực email cập nhật `email_verified_at`; CRUD địa chỉ mặc định: **đạt**.
- Bốn tài khoản seed dùng hash bcrypt `$2y$` dài 60 ký tự. PHP lint toàn bộ 42 file PHP và `git diff --check`: **đạt**.

Route `/_dev/db-check` và link demo chỉ nên dùng trong môi trường phát triển. Đặt `app.debug=false` và cấu hình thông tin DB/email riêng trước khi triển khai.
