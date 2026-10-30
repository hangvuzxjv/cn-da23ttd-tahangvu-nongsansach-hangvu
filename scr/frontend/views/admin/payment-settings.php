<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2 mb-0">Cấu hình thanh toán</h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/payments')) ?>">Danh sách thanh toán</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="card" method="post" action="<?= e(url('/admin/payments/settings')) ?>">
    <?= csrf_field() ?><div class="card-body">
        <h2 class="h5">Phương thức và giữ tồn</h2>
        <div class="form-check mb-2"><input class="form-check-input" id="paymentCod" type="checkbox" name="payment_cod_enabled" value="1" <?= ($settings['payment_cod_enabled'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="paymentCod">Bật COD</label></div>
        <div class="form-check mb-2"><input class="form-check-input" id="paymentVietqr" type="checkbox" name="payment_vietqr_enabled" value="1" <?= ($settings['payment_vietqr_enabled'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="paymentVietqr">Bật VietQR</label></div>
        <div class="form-check mb-2"><input class="form-check-input" id="paymentVnpay" type="checkbox" name="payment_vnpay_enabled" value="1" <?= ($settings['payment_vnpay_enabled'] ?? '0') === '1' ? 'checked' : '' ?> <?= $vnpayConfigured ? '' : 'disabled' ?>><label class="form-check-label" for="paymentVnpay">Bật VNPay sandbox<?= $vnpayConfigured ? '' : ' (chưa cấu hình trong config.php)' ?></label></div>
        <div class="mb-3"><label class="form-label" for="holdMinutes">Số phút giữ tồn (1–1440)</label><input class="form-control" id="holdMinutes" name="online_payment_hold_minutes" type="number" min="1" max="1440" value="<?= e($settings['online_payment_hold_minutes'] ?? '30') ?>" required></div>
        <h2 class="h5 mt-4">Tài khoản nhận VietQR</h2>
        <div class="mb-3"><label class="form-label" for="bankBin">BIN ngân hàng (6 chữ số)</label><input class="form-control" id="bankBin" name="vietqr_bank_bin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" value="<?= e($settings['vietqr_bank_bin'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="accountNumber">Số tài khoản</label><input class="form-control" id="accountNumber" name="vietqr_account_number" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" value="<?= e($settings['vietqr_account_number'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="accountName">Tên chủ tài khoản</label><input class="form-control" id="accountName" name="vietqr_account_name" maxlength="99" value="<?= e($settings['vietqr_account_name'] ?? '') ?>"></div>
        <p class="small text-secondary">Mã VNPay và hash secret chỉ cấu hình trong file config.php, không lưu trong database.</p>
        <button class="btn btn-success">Lưu cấu hình</button>
    </div>
</form>
