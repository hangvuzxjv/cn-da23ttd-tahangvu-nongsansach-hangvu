# Dữ liệu mẫu nông sản

Bộ dữ liệu này chỉ dành cho phát triển và kiểm thử. Tên nông trại, chứng nhận,
ngày lô và số liệu kho đều là dữ liệu minh họa, không phải thông tin chứng nhận
thực tế.

## Thứ tự chạy

1. Sao lưu cơ sở dữ liệu trước khi thay đổi.
2. Chạy `setup/database/migrations/20261106_product_metadata.sql` trên đúng
   cơ sở dữ liệu ứng dụng. Với PHP CLI và cấu hình hiện tại:

   ```powershell
   & 'C:\xampp\php\php.exe' setup\database\apply-migration.php setup/database/migrations/20261106_product_metadata.sql
   ```

   Hoặc mở HeidiSQL/phpMyAdmin, chọn database rồi chạy file migration. Trên
   hosting miễn phí, mở phpMyAdmin của hosting và chạy file này sau khi sao lưu;
   nếu hosting cho SSH/PHP CLI, dùng cùng lệnh `php setup/database/apply-migration.php
   setup/database/migrations/20261106_product_metadata.sql`.
3. Với database đã từng nạp cả seed cũ và dữ liệu mẫu, chạy gộp trùng một lần:

   ```powershell
   & 'C:\xampp\php\php.exe' setup\database\sample-data\merge-duplicates.php
   ```

4. Chạy migration thêm khóa duy nhất cho slug sau khi gộp:

   ```powershell
   & 'C:\xampp\php\php.exe' setup\database\apply-migration.php setup\database\migrations\20261107_unique_catalog_slugs.sql
   ```

   Migration chỉ thêm các khóa `UNIQUE` cho slug vùng, danh mục, nông trại và
   sản phẩm; có thể chạy lặp lại.
5. Nạp dữ liệu bằng cấu hình database của ứng dụng:

   ```powershell
   & 'C:\xampp\php\php.exe' setup\database\sample-data\load.php
   ```

   Chạy lại lệnh trên an toàn; script nhận diện theo slug sản phẩm và mã lô.
   Script nhận diện theo slug và tên đã chuẩn hóa, nên cập nhật bản ghi cũ thay
   vì tạo bản sao.
6. Kiểm tra:

   ```sql
   SELECT COUNT(DISTINCT b.product_id) AS sample_products
   FROM product_batches b WHERE b.batch_code LIKE 'DEMO-BATCH-%';
   SELECT COUNT(*) AS sample_batches FROM product_batches WHERE batch_code LIKE 'DEMO-BATCH-%';
   SELECT p.slug, p.total_stock, COALESCE(SUM(b.quantity_remaining), 0) AS batch_stock
   FROM products p LEFT JOIN product_batches b ON b.product_id = p.id
   WHERE p.sku LIKE 'NS-DEMO-%' GROUP BY p.id;
   ```

## Gỡ dữ liệu mẫu

Chỉ xóa các sản phẩm, lô, ảnh, sự kiện và phiếu nhập do bộ này tạo:

```powershell
& 'C:\xampp\php\php.exe' setup\database\sample-data\load.php --remove
```

Không chạy lại `schema.sql` hoặc `seed.sql` trên cơ sở dữ liệu đang có dữ liệu.

## Phạm vi dữ liệu mở rộng

Script tạo thêm tài khoản nhân viên và 10 khách hàng `@example.test`, 36 đơn
trong 60 ngày, phương thức COD/VietQR/VNPay, lịch sử trạng thái, voucher, năm
chiến dịch Flash Sale, đánh giá, yêu thích, giỏ hàng, thông báo, liên hệ,
trang tĩnh và banner. Dữ liệu có tiền tố `DEMO-` hoặc `[DEMO]` và được dựng lại
trong mỗi lần chạy nên không nhân đôi.

Mật khẩu dùng chung cho tài khoản mẫu được ghi trong phần “Tài khoản dữ liệu
mẫu” của README gốc. Đây chỉ là mật khẩu phát triển; phải đổi toàn bộ trước
khi đưa lên hosting. Kiểm tra các bất biến sau khi nạp:

```powershell
& 'C:\xampp\php\php.exe' setup\tests\sample-data.php
& 'C:\xampp\php\php.exe' setup\tests\sample-data-commerce.php
```

`sample-data-commerce.php` kiểm tra tổng dòng hàng/tổng đơn, tổng tồn theo lô,
đã bán Flash Sale và số lượng đánh giá. Dữ liệu “đã xem gần đây” hiện được
lưu trong session trình duyệt theo thiết kế ứng dụng, không có bảng CSDL riêng.
