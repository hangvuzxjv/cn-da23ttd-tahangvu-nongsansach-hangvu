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

Hướng dẫn triển khai shared hosting InfinityFree (không SSH/cron) và cấu hình production mẫu: [setup/deploy/HUONG_DAN_DEPLOY.md](./setup/deploy/HUONG_DAN_DEPLOY.md).

## Tài khoản dữ liệu mẫu

Tất cả tài khoản sau dùng mật khẩu thử `password` (bcrypt). Chỉ dùng để phát triển:

| Vai trò | Email |
|---|---|
| customer | `customer@example.com` |
| admin | `admin@example.com` |
| staff_sales | `sales@example.com` |
| staff_warehouse | `warehouse@example.com` |

Seed có 3 miền, 10 tỉnh/vùng, 6 danh mục, 10 nông trại, 20 sản phẩm, 130 lô hàng (mỗi lô có 6 sự kiện truy xuất), một phiếu nhập, một đơn hàng mẫu, 3 voucher, phí ship, và một Flash Sale có thời gian bắt đầu/kết thúc tương đối với lúc import. Các chứng nhận và hồ sơ nông trại trong seed là dữ liệu minh họa, không phải xác nhận chứng nhận thực tế.

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

Kết quả trên seed Step 6: `Bưởi da xanh Bến Tre` (`NS-TRAICAY-007`, 78.000, tồn 69) và `Dừa xiêm xanh Bến Tre` (`NS-TRAICAY-012`, 25.000, tồn 49).

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

Các route ứng dụng được khai báo tập trung trong `scr/backend/routes.php`. Router hỗ trợ GET/POST, tham số `{id}` (chữ số), `{slug}` và `{code}` (mã lô có dấu chấm, gạch nối), cùng middleware `auth`, `role:<tên-vai-trò>` và `csrf`. Các form POST phải gửi token bằng helper `csrf_field()`.

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

Ma trận quyền có tại `scr/backend/config/permissions.php`. `admin` có toàn quyền; `staff_sales` có dashboard, đơn hàng, voucher, đánh giá, khách hàng và quyền xem vùng miền/danh mục/sản phẩm; `staff_warehouse` có dashboard, kho, lô hàng và quyền quản lý vùng miền/danh mục/sản phẩm; `customer` không có quyền quản trị. Các route `/admin/*` được bảo vệ bằng middleware `permission:<tên-quyền>`. Menu ẩn mục không được phép, nhưng route vẫn được kiểm tra server-side và trả trang 403. Người chưa đăng nhập được chuyển tới `/login`, sau đó trở lại URL nội bộ ban đầu.

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

## Bước 4: Danh mục, vùng miền và sản phẩm

### Cập nhật cơ sở dữ liệu

- Cài mới: import `setup/database/schema.sql`, sau đó `setup/database/seed.sql` như hướng dẫn ở trên. Seed hiện có mô tả vùng, giá khuyến mãi/mô tả ngắn, cờ nổi bật và 20 ảnh SVG minh họa cục bộ.
- CSDL đã có từ Bước 2/3: chọn đúng CSDL trong phpMyAdmin và import `setup/database/migrations/20261027_catalog_fields.sql` **một lần**. Migration thêm `regions.description`, các trường catalog/index cho `products`, và chuyển trạng thái cũ `out_of_stock` sang `inactive` trước khi điều chỉnh ENUM. Không import lại `schema.sql` vì thao tác đó xóa các bảng/dữ liệu.
- Nếu đã import seed cũ, không chạy lại toàn bộ `seed.sql` trên database đang dùng vì seed chứa các ID mẫu cố định. Ảnh SVG cục bộ sẽ có trong database seed mới; ảnh sản phẩm chưa có ảnh vẫn dùng `/img/product-placeholder.svg`.

### Chức năng đã nối

- Admin: `/admin/regions` CRUD cây hai cấp, tự sinh slug không dấu, bật/tắt hiển thị và chặn xóa khi còn tham chiếu; `/admin/categories` CRUD danh mục cha/con, thứ tự và slug, chặn xóa khi còn sản phẩm/con; `/admin/products` tìm kiếm tên/SKU, lọc và phân trang 15 dòng. Tạo/sửa sản phẩm có kiểm tra slug/SKU duy nhất, danh mục/vùng/nông trại, giá và trạng thái. Xóa sản phẩm là xóa mềm. Các thao tác ghi có CSRF và audit log.
- Nhân viên kho có thể quản lý catalog; nhân viên bán hàng chỉ xem; admin có toàn quyền. Product form tải nhiều JPG/PNG/WebP (MIME được kiểm tra bằng `finfo`, tối đa 2 MB/tệp), tên file ngẫu nhiên, GD thu nhỏ cạnh dài tối đa 1000 px khi hỗ trợ. Có thể xóa ảnh và đổi thứ tự; ảnh đầu tiên làm ảnh đại diện.
- Cửa hàng tại `/san-pham` có lọc/tìm/sắp xếp/phân trang 12 sản phẩm; `/vung-mien/{slug}`, `/danh-muc/{slug}` và `/san-pham/{slug}` là trang vùng, danh mục và chi tiết. Trang chủ lấy banner/sản phẩm/Flash Sale từ DB. Tồn kho hiển thị được tính từ lô còn hạn, chưa kiểm định lỗi; giỏ hàng, truy xuất chi tiết và đánh giá hiện là placeholder cho bước tiếp theo. `/sitemap.xml` được dựng từ sản phẩm, danh mục và vùng đang hiển thị.
- Tệp hình minh họa cục bộ nằm trong `scr/img/`; không phụ thuộc hotlink ảnh sản phẩm bên ngoài. Query catalog dùng PDO prepared statements và tổng hợp ảnh/tồn/bán chạy trong subquery để không phát sinh N+1.

### Kiểm tra Bước 4

- Đã áp dụng migration catalog lên database `nongsan` hiện có; xác nhận các cột `products.sale_price`, `regions.description` và giữ nguyên 20 sản phẩm.
- PHP lint toàn bộ backend và view: **đạt**; `git diff --check`: **đạt**.
- Import mới `schema.sql` và `seed.sql` vào database kiểm thử riêng trên MySQL 8.4: **đạt**; xác nhận 20 sản phẩm/20 ảnh cục bộ, 30 lô/180 sự kiện truy xuất, 3 miền, 6 danh mục, 10 nông trại và Flash Sale còn hiệu lực. Database kiểm thử đã được xóa sau khi xác nhận.
- Qua PHP development server, trang chủ, cửa hàng, lọc query, trang miền, tỉnh/vùng, danh mục, chi tiết và sitemap trả HTTP 200. Cửa hàng trả đúng 12 sản phẩm trang 1, 8 sản phẩm trang 2; lọc Bến Tre/danh mục trái cây cho đúng tập sản phẩm seed: **đạt**.
- Đăng nhập bằng bốn tài khoản seed: admin và staff kho vào được trang xem/quản lý catalog; staff bán hàng xem được catalog nhưng `/admin/products/new` trả 403; customer vào các trang catalog admin trả 403: **đạt**.
- CRUD vùng miền/danh mục và sản phẩm, tìm kiếm SKU, xóa mềm sản phẩm, chặn xóa danh mục còn sản phẩm, và từ chối POST thiếu CSRF: **đạt**. Các bản ghi QA tạo trong lúc kiểm tra đã được dọn khỏi CSDL.
- Tải PNG hợp lệ qua form admin, xác nhận bản ghi/tệp ảnh, rồi xóa ảnh để dọn cả DB lẫn tệp: **đạt**. Môi trường XAMPP hiện không bật GD; nhánh thu nhỏ ảnh khi có GD chưa chạy trong môi trường này. Đã kiểm tra trang cửa hàng hiển thị bằng browser và ảnh SVG placeholder cục bộ.

## Bước 5: Truy xuất nguồn gốc và QR

### Cập nhật cơ sở dữ liệu

- Cài mới: import `schema.sql` rồi `seed.sql`; seed tạo 130 lô/780 bước truy xuất. Mỗi sản phẩm có thêm một lô còn hạn, hai lô sắp hết hạn, một lô đã hết hạn và một lô thu hồi. Ngày các lô kiểm thử này tính tương đối theo ngày import.
- Đã có dữ liệu từ các bước trước: **không chạy lại schema.sql hoặc seed.sql**. Chọn CSDL trong phpMyAdmin → **Import** → chạy một lần `setup/database/migrations/20261027_traceability.sql`. Migration bổ sung hồ sơ/chứng nhận/tọa độ nông trại, nông trại và trạng thái cho lô, ảnh/thứ tự sự kiện, index mới và bảng `trace_scans`; dữ liệu lô hiện tại được gắn với nông trại của sản phẩm khi có thể. `farm_id` có thể để NULL cho dữ liệu cũ không có liên kết; lô mới qua giao diện luôn phải chọn nông trại.
- Sao lưu database trước khi áp dụng migration trên hosting. Migration không xóa bảng/dữ liệu, nhưng cần chạy đúng một lần; chạy lại sẽ báo lỗi do các cột/index đã tồn tại.

### Tính năng và URL kiểm thử

- Tra cứu công khai: `/truy-xuat` (nhập mã lô), `/truy-xuat/{mã_lô}`. Có cảnh báo đỏ cho lô thu hồi, trạng thái hạn sử dụng, kết quả kiểm định, thông tin nông trại/chứng nhận/vùng, liên kết bản đồ, timeline và ảnh bước truy xuất. Trang chủ và menu có ô tra cứu.
- Trang sản phẩm `/san-pham/{slug}` liệt kê lô còn hàng, còn hạn và chưa kiểm định lỗi theo hạn dùng gần nhất trước (FEFO).
- Admin và nhân viên kho: `/admin/farms` quản lý nông trại/ảnh/chứng nhận; `/admin/batches` tạo lô, quản lý trạng thái/thu hồi, in tem; `/admin/batches/{id}` quản lý sự kiện, tạo nhanh sáu bước, xem lượt quét; `/admin/labels?ids[]=1&ids[]=2` in tem đã chọn. Nhân viên bán hàng và khách hàng không được quyền quản lý lô/nông trại.
- Trong chi tiết lô có thể cập nhật nông trại cùng vùng, các mốc ngày, số lượng, kiểm định và trạng thái; SKU/sản phẩm và mã lô giữ cố định để không làm sai mã truy xuất đã phát hành.
- Ảnh tải lên được xác minh bằng MIME `finfo`, chỉ JPEG/PNG/WebP, tối đa 2 MB; tên ngẫu nhiên, GD thu nhỏ cạnh dài còn tối đa 1000 px nếu extension có sẵn.
- `/qr/{mã_lô}` xuất QR SVG động, không dùng dịch vụ QR bên ngoài và không cần GD/Imagick. URL đích lấy từ `app.url` đã cấu hình, không lấy host từ request.
- `trace_scans` chỉ ghi hash SHA-256 của IP, user-agent tối đa 200 ký tự và thời điểm. Cùng lô/IP chỉ ghi một lượt trong mỗi cửa sổ 10 phút. Không lưu IP thô.

### Đưa thư viện QR lên hosting không có SSH

Trong môi trường phát triển, chạy `composer install --no-dev --optimize-autoloader` tại thư mục gốc dự án, sau đó tải toàn bộ thư mục `vendor/` cùng mã nguồn lên hosting bằng FTP/cPanel. Giữ `composer.json` và `composer.lock` đồng bộ. QR dùng `bacon/bacon-qr-code` với backend SVG thuần; không cần chạy Composer trên hosting, GD hoặc Imagick.

### Truy vấn kiểm tra truy xuất

```sql
-- Mỗi sản phẩm có đủ các loại lô seed
SELECT p.id, p.name,
       SUM(b.status = 'selling' AND b.expires_at > CURDATE() + INTERVAL 7 DAY) AS valid_lots,
       SUM(b.status = 'selling' AND b.expires_at BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY) AS near_expiry_lots,
       SUM(b.expires_at < CURDATE()) AS expired_lots,
       SUM(b.status = 'recalled') AS recalled_lots
FROM products p
JOIN product_batches b ON b.product_id = p.id
GROUP BY p.id, p.name
ORDER BY p.id;

-- Timeline một mã lô
SELECT b.batch_code, p.name, t.event_type, t.event_at, t.description
FROM product_batches b
JOIN products p ON p.id = b.product_id
JOIN trace_events t ON t.batch_id = b.id
WHERE b.batch_code = 'LO-20261001'
ORDER BY t.sort_order, t.event_at, t.id;

-- Lô được quét nhiều nhất
SELECT b.batch_code, COUNT(s.id) AS scans
FROM product_batches b
LEFT JOIN trace_scans s ON s.batch_id = b.id
GROUP BY b.id, b.batch_code
ORDER BY scans DESC;
```

### Kiểm tra Bước 5

- Fresh import `schema.sql` + `seed.sql` trên MySQL 8.4: **đạt**; 20 sản phẩm, 130 lô, 780 sự kiện, 0 sản phẩm thiếu các loại lô cần kiểm thử và 0 lô thiếu đủ sáu sự kiện.
- Áp dụng migration một lần lên database `nongsan` đang có: **đạt**; giữ nguyên 30 lô/180 sự kiện cũ, 0 lô thiếu liên kết nông trại và tạo bảng `trace_scans`.
- PHP development server: trang sản phẩm, truy xuất lô, nông trại/lô admin, chi tiết lô và in tem trả HTTP 200; mã lô không tồn tại trả 404. Endpoint QR trả `image/svg+xml` với SVG hợp lệ.
- Đăng nhập thử với admin và staff kho vào được `/admin/batches`; staff bán hàng và customer nhận 403. POST thiếu CSRF nhận 419.
- Qua form admin đã tạo/sửa/xóa nông trại; tạo lô với mã tự sinh, tạo nhanh đủ sáu sự kiện, thu hồi lô và xác nhận trang công khai hiển thị cảnh báo; ba lượt mở từ cùng IP chỉ ghi một scan trong 10 phút. In tem QR SVG hoạt động; không cho xóa lô có lịch sử. Bản ghi QA và các sự kiện phụ thuộc đã được xóa sau kiểm tra.
- Upload PNG hợp lệ cho hồ sơ nông trại và dọn tệp khi xóa hồ sơ: **đạt**. Môi trường XAMPP không bật GD nên nhánh thu nhỏ ảnh khi extension có sẵn chưa được kiểm chứng.
- PHP lint cho controller/view/router và `git diff --check`: **đạt**.

## Bước 6: Quản lý kho theo lô

### Cập nhật cơ sở dữ liệu

- Cài mới: import `setup/database/schema.sql`, sau đó `setup/database/seed.sql`. Seed có 130 lô/780 sự kiện, movement khớp tồn lô, sản phẩm #19 dưới ngưỡng và sản phẩm #20 hết hàng; có lô còn hạn, sắp hết hạn, hết hạn và thu hồi.
- Database đã có dữ liệu: sao lưu trước, rồi import duy nhất `setup/database/migrations/20261028_inventory_management.sql` trong phpMyAdmin. Migration thêm `min_stock`, giá nhập bình quân, thông tin phiếu nhập, giữ chỗ, kiểm kê và bộ cấp số chứng từ; cuối cùng **chỉ thêm** movement điều chỉnh để cân bằng sổ cái với tồn lô hiện tại và đồng bộ tổng tồn sản phẩm. Không chạy lại `schema.sql` hoặc `seed.sql`.
- Migration không dùng `CHECK` mới của MySQL 8; mọi thay đổi kho chạy trên InnoDB qua `InventoryService`. Không sửa/xóa `inventory_movements`; hủy phiếu nhập tạo movement đảo. Giữ chỗ hết hạn được giải phóng lazy khi tính tồn, không cần cron.

### URL quản trị

- `/admin/inventory` — dashboard kho, cảnh báo, cấu hình ngưỡng sắp hết hạn và chuyển lô quá hạn sang đã hết.
- `/admin/inventory/stock` — tồn theo lô, lọc, CSV, xuất thủ công và điều chỉnh sau kiểm kê.
- `/admin/inventory/receipts` — phiếu nhập nháp nhiều dòng, tạo lô mới ngay trong phiếu, xác nhận hoặc hủy bằng movement đảo.
- `/admin/inventory/stocktakes` — tạo đợt kiểm kê, nhập số đếm và xác nhận chênh lệch.
- `/admin/inventory/movements` — sổ cái có lọc, phân trang và số dư sau movement.
- Staff kho và admin được quản lý; staff bán hàng chỉ có quyền xem các trang tồn/sổ cái; customer không có quyền truy cập.

### Kiểm thử kho

Chạy trên CSDL MySQL/MariaDB **riêng có tên kết thúc `_test`**, không chạy trên database sản xuất. Đặt các biến môi trường `INVENTORY_TEST_DB_HOST=127.0.0.1`, `INVENTORY_TEST_DB_PORT=3306`, `INVENTORY_TEST_DB_NAME=nongsan_step6_test`, `INVENTORY_TEST_DB_USERNAME` và `INVENTORY_TEST_DB_PASSWORD` theo local MySQL (`__EMPTY__` biểu thị mật khẩu rỗng); import schema và seed vào CSDL thử đó.

1. FEFO: `php setup/tests/fefo.php <product_id> <so_luong>`.
2. Sổ cái/tổng tồn: `php setup/tests/reconcile.php`.
3. Đồng thời 200 lần xuất một đơn vị từ cùng lô:
   - Chạy ứng dụng qua Apache local với `app.debug=true` trong `scr/backend/config/config.php`; endpoint thử nghiệm chỉ được đăng ký khi debug bật, giới hạn loopback, cần token ngẫu nhiên tạm thời và chỉ chấp nhận lô `NS-TEST-*`.
   - Đặt `INVENTORY_TEST_BASE_URL` thành URL local của ứng dụng (ví dụ `http://localhost` hoặc `http://localhost/nongsan/scr` theo cấu hình Apache), rồi chạy `php setup/tests/concurrency.php`. Chế độ `php setup/tests/concurrency.php --cli` gọi service trực tiếp từ 200 worker CLI theo nhóm song song, không cần bật HTTP route.
   - Kỳ vọng: `issued=20`, `unavailable=180`, tồn cuối `0`, tổng movement `0`, không có tồn âm. Script tự xóa token tạm; lô test và movement được giữ lại để bảo toàn sổ cái nên hãy dùng CSDL thử riêng.

### Kết quả kiểm tra Bước 6

- Import mới `schema.sql` + `seed.sql` vào MySQL 8.4.3: **đạt**; 20 sản phẩm, 130 lô, 780 sự kiện; sản phẩm #19 tồn 3 dưới ngưỡng 5, sản phẩm #20 tồn 0.
- Import schema/seed phiên bản trước Bước 6 rồi áp dụng migration `20261028_inventory_management.sql`: **đạt**; không mất dữ liệu lô/sự kiện, tổng `inventory_movements` theo lô và `products.total_stock` khớp số tồn.
- `setup/tests/reconcile.php`: **đạt** trên cả CSDL seed mới và CSDL sau migration; 0 lô lệch ledger, 0 sản phẩm lệch tổng tồn, 0 tồn âm.
- `setup/tests/fefo.php 1 4`: **đạt**; lô gần hết hạn nhất được phân bổ trước.
- Kiểm tra đồng thời qua 200 worker gọi `InventoryService` (40 worker đồng thời theo từng nhóm): **đạt**; 20 xuất thành công, 180 thiếu hàng, tồn cuối 0, tổng movement 0. Script `concurrency.php` cũng có chế độ `curl_multi` 200 HTTP request; chế độ HTTP chưa chạy trong môi trường này vì app config hiện tắt debug.
- Kiểm tra nghiệp vụ trực tiếp trên CSDL thử: giữ chỗ giảm tồn khả dụng; `deductForOrder` và `restoreForOrder` idempotent; giữ chỗ được tách đúng phần đã dùng và phần giải phóng khi đơn chỉ trừ một phần; xác nhận/hủy phiếu nhập và chốt kiểm kê ghi movement; sau các bước vẫn reconcile.
- Sinh hai mã lô trong cùng SKU/tháng cho thấy bộ cấp số dùng chung không tạo mã trùng giữa luồng tạo lô và phiếu nhập.
- PHP syntax lint cho toàn bộ 222 tệp PHP và `git diff --check`: **đạt**.
