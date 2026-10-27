# Hệ thống bán Nông Sản Sạch

Đồ án xây dựng website giới thiệu và bán nông sản sạch. Mã nguồn ứng dụng web nằm trong `scr/`; thư mục này là document root và `scr/index.php` là front controller duy nhất.

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
setup/                     # Tệp SQL dùng để khởi tạo CSDL (nếu có)
```

## Cài đặt với Laragon

1. Đặt hoặc clone repo vào thư mục `www` của Laragon.
2. Trong cấu hình Virtual Host của Laragon, đặt **DocumentRoot** trỏ tới thư mục `scr/` của dự án (ví dụ `C:/laragon/www/cn-da23ttd-tahangvu-nongsansach-hangvu/scr`). Không đổi tên hay xóa các thư mục sẵn có của repo.
3. Bật `mod_rewrite` và cho phép Apache đọc `.htaccess` (`AllowOverride All`), sau đó khởi động lại Apache.
4. Sao chép `scr/backend/config/config.sample.php` thành `scr/backend/config/config.php`. Cập nhật host, port, tên CSDL, tài khoản và mật khẩu MySQL cho môi trường Laragon. Tệp cấu hình thực tế đã được Git bỏ qua.
5. Tạo CSDL theo tên trong cấu hình, mở phpMyAdmin, chọn CSDL đó và dùng **Import** để nạp tệp `.sql` có trong `setup/`. Repo hiện chưa có tệp SQL khởi tạo; nếu thư mục chỉ có `.gitkeep`, cần bổ sung tệp dump CSDL trước khi import.
6. Truy cập tên miền Virtual Host. Nếu không tạo Virtual Host, có thể chạy với Apache alias/subfolder; URL và asset được tạo theo `BASE_URL`.

Trang `/` hiển thị trang chủ mẫu. `/admin` yêu cầu đăng nhập giả lập tại `/login`; nhập email bất kỳ để vào dashboard. Đây chỉ là luồng demo, chưa phải xác thực người dùng dùng trong production.

## Routes và middleware

Các route được khai báo tập trung trong `scr/backend/routes.php`. Router hỗ trợ GET/POST, tham số `{id}` (chữ số) và `{slug}`, cùng middleware `auth`, `role:<tên-vai-trò>` và `csrf`. Các form POST phải gửi token bằng helper `csrf_field()`.

Ứng dụng ghi log vào `scr/backend/storage/logs/app.log`. Để hiện chi tiết lỗi 500 trong môi trường phát triển, đặt `app.debug` thành `true` trong `scr/backend/config/config.php`; không bật debug trên môi trường triển khai thực tế.