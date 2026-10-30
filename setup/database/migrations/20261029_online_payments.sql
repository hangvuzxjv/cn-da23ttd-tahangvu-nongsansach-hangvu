-- Bước 7B: online payments, VietQR receipts, and lazy expiration settings.
-- Back up the database, then run once. Do not re-import schema.sql on existing data.

ALTER TABLE payments
    MODIFY method ENUM('cod','bank_transfer','online','vnpay') NOT NULL,
    ADD COLUMN raw_response JSON DEFAULT NULL AFTER paid_at,
    ADD COLUMN proof_path VARCHAR(255) DEFAULT NULL AFTER raw_response,
    ADD KEY idx_payments_method_status_created (method, status, created_at);

INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('payment_cod_enabled', '1', 'Bật/tắt thanh toán khi nhận hàng'),
    ('payment_vietqr_enabled', '1', 'Bật/tắt chuyển khoản VietQR'),
    ('payment_vnpay_enabled', '0', 'Bật/tắt VNPay sandbox'),
    ('online_payment_hold_minutes', '30', 'Thời gian giữ tồn cho đơn thanh toán trực tuyến (phút)'),
    ('vietqr_bank_bin', '', 'Mã BIN ngân hàng nhận chuyển khoản VietQR'),
    ('vietqr_account_number', '', 'Số tài khoản nhận chuyển khoản VietQR'),
    ('vietqr_account_name', '', 'Tên chủ tài khoản nhận chuyển khoản VietQR')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
