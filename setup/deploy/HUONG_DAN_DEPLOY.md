# Hướng dẫn triển khai InfinityFree

Ứng dụng PHP MVC này được cấu hình để chạy trên shared hosting không có SSH/cron. PHP chạy từ `htdocs`, vì vậy **upload nội dung bên trong `scr/` trực tiếp vào `htdocs/`**, không upload thư mục `scr` làm một cấp URL bổ sung.

## 1. Tạo tài khoản và tên miền

1. Tạo tài khoản InfinityFree và một hosting account trong bảng điều khiển của nhà cung cấp.
2. Tạo/chọn subdomain miễn phí hoặc thêm domain riêng, rồi xác nhận domain đã trỏ tới hosting account.
3. Bật SSL/HTTPS trong bảng điều khiển nếu có. Chờ trạng thái chứng chỉ hoạt động trước khi tiếp tục.
4. Mở File Manager hoặc kết nối FTP theo hostname, username, password và port mà InfinityFree hiển thị. Không dùng thông tin FTP/MySQL mẫu trong tài liệu này.

## 2. Tạo database MySQL

1. Trong bảng điều khiển hosting, mở phần **MySQL Databases** và tạo database.
2. Ghi chính xác các giá trị do hosting cấp: **MySQL Hostname**, **Database Name**, **Username** và password. Trên shared hosting, host thường không phải `localhost`; tên database và user thường có prefix tài khoản. Chỉ dùng giá trị đang hiển thị trong bảng điều khiển.
3. Mở phpMyAdmin từ chính bảng điều khiển của hosting và chọn database vừa tạo.
4. Với một database trống hoàn toàn, import theo thứ tự:
   1. `setup/database/schema.sql`
   2. `setup/database/seed.sql`
5. Nếu đã có dữ liệu cần giữ, **không import lại `schema.sql` hoặc `seed.sql`**. Áp dụng lần lượt các migration phù hợp với phiên bản database hiện có, mỗi migration đúng một lần:
   - Bước 3: `setup/database/migrations/20261027_auth_login_attempts.sql`
   - Bước 4: `setup/database/migrations/20261027_catalog_fields.sql`
   - Bước 5: `setup/database/migrations/20261027_traceability.sql`
   Nếu trước đó chưa áp dụng migration của bước cũ, hãy áp dụng chúng theo thứ tự trên sau khi sao lưu database. Không import migration nếu các cột/bảng tương ứng đã tồn tại.

Import SQL bằng phpMyAdmin; không cần SSH, command line MySQL hay cron. Chạy một migration trên database staging trước nếu hosting đang giữ dữ liệu quan trọng.

## 3. Tạo cấu hình production

1. Mở `setup/deploy/config.production.sample.php` và điền:
   - `app.url`: URL HTTPS chính xác của site, ví dụ `https://your-site.infinityfreeapp.com`, không thêm `/scr`.
   - `database.host`, `database.name`, `database.username`, `database.password`: lấy từ trang **MySQL Databases** của hosting.
2. Giữ `app.debug` là `false` và `mailer.driver` là `log`. InfinityFree chặn SMTP nên không chọn driver SMTP.
3. Lưu tệp cấu hình đã điền thành `scr/backend/config/config.php` trong bộ file tải lên. Đường dẫn cuối trên server sẽ là `htdocs/backend/config/config.php`. Không để tệp production chứa dấu ngoặc placeholder hoặc thông tin mẫu.
4. Không công khai hoặc gửi password database cho người khác. Không đưa config production vào repository.

## 4. Chuẩn bị file và upload

1. Trên máy phát triển, chạy Composer trong thư mục gốc repository để chuẩn bị thư viện production:

   ```text
   composer install --no-dev --optimize-autoloader
   ```

2. Đưa **nội dung** của `scr/` vào thư mục `htdocs/`. Upload cây ứng dụng, bao gồm `index.php`, `.htaccess`, `backend/`, `frontend/`, `css/`, `js/`, `img/` và `uploads/`. Nếu FTP/File Manager ẩn dotfiles, bật chế độ hiển thị file ẩn để `.htaccess` được tải lên.
   Không upload `scr/backend/config/config.php` hoặc log local; tạo config production riêng theo mục trên.
3. Đưa thư mục Composer `vendor/` vào `htdocs/vendor/`. Front controller tìm autoloader tại vị trí này khi chạy dưới `htdocs`; cấu trúc phát triển hiện tại với `vendor/` cạnh `scr/` vẫn được hỗ trợ.
4. Nếu File Manager cho phép giải nén archive, nén nội dung của `scr/` thành ZIP và upload ZIP, sau đó **giải nén trực tiếp trong `htdocs/`**; giữ tên bắt đầu `index.php`, không tạo thêm `htdocs/scr/`. Nén thư mục `vendor/` thành ZIP riêng và giải nén thành `htdocs/vendor/`. Sau giải nén, kiểm tra có `htdocs/vendor/autoload.php` và các thư mục package bên trong.
5. Nếu File Manager không hỗ trợ giải nén, dùng FTP để tải các thư mục/file lên. Có thể upload `vendor/` từ ZIP cục bộ đã giải nén; không cần Composer trên hosting.
6. Sau khi xác nhận site chạy, không giữ archive ZIP/backup chứa mã nguồn hoặc cấu hình trong `htdocs`, vì chúng có thể bị tải trực tiếp nếu server không chặn phần mở rộng đó.

### Những gì không upload vào `htdocs`

- `.git/`, `.gitignore`
- `setup/` (chứa SQL, migration và tài liệu; import các SQL cần thiết qua phpMyAdmin trước)
- `thesis/`, `progress-report/`, `docker/`, `soft/`
- `README.md`, `composer.json`, `composer.lock` (runtime không cần khi đã upload đầy đủ `vendor/`)
- `scr/backend/config/config.php` của máy local và các log `scr/backend/storage/logs/*.log`; thay bằng config production đã điền, giữ các thư mục log rỗng
- ZIP, bản sao database hoặc file tạm

Chỉ upload `scr/` và `vendor/` đã cài dependencies production. `config.php` được tạo riêng theo hướng dẫn, không dùng cấu hình local mặc định.

## 5. Quyền ghi và phiên bản PHP

- Chọn PHP 8.2 hoặc phiên bản cao hơn còn được hosting hỗ trợ. Bật PDO MySQL (`pdo_mysql`), `fileinfo` và `mbstring` nếu bảng điều khiển cho phép. GD chỉ dùng để thu nhỏ ảnh khi extension có; ứng dụng không yêu cầu GD/Imagick để tạo QR.
- Các thư mục cần được PHP ghi:
  - `htdocs/uploads/` (bao gồm thư mục con ảnh sản phẩm và truy xuất)
  - `htdocs/backend/storage/logs/` để ghi `app.log` và `mail.log`
- Thư mục/thư mục con trên các hosting Linux thường dùng quyền `755`, file `644`; nếu PHP không thể ghi, dùng File Manager để điều chỉnh **chỉ các thư mục cần thiết** theo hướng dẫn host (thường `755` hoặc `775`). Không đặt toàn site thành `777`.
- Bật `app.debug=false`. Log email của driver `log` ghi trong `backend/storage/logs/mail.log`. SMTP không được dùng trên hosting này; chức năng gửi thực tế cần một driver/API phù hợp với chính sách hosting nếu muốn thêm sau.
- Không cần cron: hết hạn token, khóa đăng nhập và chống đếm scan trùng được kiểm tra khi có request. Không cần SSH: import database bằng phpMyAdmin, upload bằng FTP/File Manager, cài Composer trên máy phát triển.

## 6. Kiểm tra sau deploy

1. Mở `https://your-site.infinityfreeapp.com/_health`.
2. Khi ứng dụng hoạt động bình thường, endpoint trả JSON có dạng:

   ```json
   {"status":"ok","php_version":"8.x.x","database_connected":true}
   ```

   Khi PHP hoạt động nhưng không kết nối được database, `database_connected` là `false` và HTTP status là `503`. Phản hồi không chứa hostname, tên database, username, password, stack trace hoặc đường dẫn máy chủ.
3. Thử trang chủ `/`, sản phẩm `/san-pham`, đăng nhập `/login`, trang truy xuất `/truy-xuat`, và QR một mã lô `/qr/MA-LO`.
4. Khi `app.debug=false`, `/_dev/db-check` không được đăng ký và trả 404. Giữ debug tắt sau khi deploy.
5. Nếu URL đẹp báo 404, kiểm tra `.htaccess` đã upload nguyên trạng và hosting đã bật `mod_rewrite`/cho phép override. Có thể kiểm tra `/_health` độc lập trước để xác nhận PHP và database.

## .htaccess và phương án dự phòng

`scr/.htaccess` dùng `mod_rewrite` trong khối `<IfModule mod_rewrite.c>` và dùng `Require all denied` chỉ khi Apache 2.4 `mod_authz_core` tồn tại; nhánh `<IfModule !mod_authz_core.c>` giữ cú pháp tương thích hosting Apache cũ. Quy tắc rewrite chuyển URL không phải file/thư mục thật về `index.php` và chặn truy cập HTTP trực tiếp tới `vendor/` (PHP vẫn đọc thư viện nội bộ).

Nếu `mod_rewrite` không hoạt động, cấu hình `ErrorDocument 404 /index.php` là phương án dự phòng cơ bản cho URL động không có file vật lý. Hãy thử `/san-pham` và `/_health`; một số shared host vô hiệu hóa hoặc ghi đè ErrorDocument. Nếu fallback cũng bị chặn, bật `mod_rewrite`/AllowOverride trong hosting hoặc nhờ hỗ trợ hosting kích hoạt chúng; nếu nhà cung cấp không cho phép cả hai, URL đẹp của MVC không thể chạy ổn định trên cấu hình đó.

## File vendor để đối chiếu

Trong bộ mã hiện tại, `vendor/` ở thư mục gốc repository, cùng cấp với `scr/`; front controller hỗ trợ cả vị trí phát triển này và `htdocs/vendor/` trên hosting. `vendor/` có **162 file** (khoảng 771 KB theo kích thước file cộng dồn tại thời điểm ghi hướng dẫn), gồm thư viện QR SVG, PHPMailer và dependencies. Upload đủ thư mục, đặc biệt `vendor/autoload.php`; đếm file có thể thay đổi nếu phiên bản Composer/package được cập nhật.
