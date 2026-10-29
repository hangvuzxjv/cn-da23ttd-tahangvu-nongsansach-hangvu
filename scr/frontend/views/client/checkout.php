<h1 class="h2 mb-4">Thanh toán và đặt hàng</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('/thanh-toan/dat-hang')) ?>" id="placeOrderForm">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_token" value="<?= e($checkoutToken) ?>">
            <div class="card mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Địa chỉ nhận hàng</h2></div>
                <div class="card-body">
                    <label class="form-label" for="checkoutAddress">Chọn từ sổ địa chỉ hoặc nhập địa chỉ mới</label>
                    <select class="form-select" name="address_id" id="checkoutAddress">
                        <option value="">Nhập địa chỉ mới</option>
                        <?php foreach ($addresses as $address): ?>
                            <?php $selectedAddress = (string) ($oldInput['address_id'] ?? $defaultAddress ?? '') === (string) $address['id']; ?>
                            <option value="<?= (int) $address['id'] ?>" data-region="<?= (int) $address['region_id'] ?>" <?= $selectedAddress ? 'selected' : '' ?>>
                                <?= e($address['recipient_name'] . ' · ' . $address['phone'] . ' · ' . $address['address_line'] . ', ' . $address['ward'] . ', ' . $address['region_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php $useNewAddress = array_key_exists('address_id', $oldInput) ? empty($oldInput['address_id']) : $defaultAddress === null; ?>
                    <div id="newCheckoutAddress" class="row g-3 mt-1" <?= $useNewAddress ? '' : 'hidden' ?>>
                        <div class="col-md-6"><label class="form-label">Người nhận</label><input class="form-control" name="recipient_name" value="<?= e($oldInput['recipient_name'] ?? Auth::user()['name']) ?>" maxlength="150" required <?= $useNewAddress ? '' : 'disabled' ?>></div>
                        <div class="col-md-6"><label class="form-label">Điện thoại</label><input class="form-control" name="phone" value="<?= e($oldInput['phone'] ?? Auth::user()['phone']) ?>" required <?= $useNewAddress ? '' : 'disabled' ?>></div>
                        <div class="col-md-6"><label class="form-label">Tỉnh/thành</label><select class="form-select" name="region_id" id="checkoutRegion" required <?= $useNewAddress ? '' : 'disabled' ?>><option value="">Chọn tỉnh/thành</option><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (string) ($oldInput['region_id'] ?? '') === (string) $region['id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Phường/xã</label><input class="form-control" name="ward" value="<?= e($oldInput['ward'] ?? '') ?>" <?= $useNewAddress ? '' : 'disabled' ?>></div>
                        <div class="col-12"><label class="form-label">Địa chỉ cụ thể</label><input class="form-control" name="address_line" value="<?= e($oldInput['address_line'] ?? '') ?>" maxlength="255" required <?= $useNewAddress ? '' : 'disabled' ?>></div>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header"><h2 class="h5 mb-0">Thanh toán</h2></div>
                <div class="card-body">
                    <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="cod" id="payCod" checked><label class="form-check-label" for="payCod">Thanh toán khi nhận hàng (COD)</label></div>
                    <div class="form-check text-secondary"><input class="form-check-input" type="radio" disabled><label class="form-check-label">Chuyển khoản / ví điện tử — sắp có</label></div>
                    <label class="form-label mt-3" for="customerNote">Ghi chú đơn hàng</label><textarea class="form-control" id="customerNote" name="customer_note" rows="2"><?= e($oldInput['customer_note'] ?? '') ?></textarea>
                </div>
            </div>
            <button class="btn btn-success btn-lg w-100" type="submit" id="placeOrderButton" <?= array_filter($cart['items'], static fn (array $item): bool => !$item['valid']) ? 'disabled' : '' ?>>Đặt hàng COD</button>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2 class="h5 mb-0">Đơn hàng của bạn</h2></div>
            <div class="card-body">
                <?php foreach ($cart['items'] as $item): ?>
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span><?= e($item['name']) ?> × <?= (int) $item['quantity'] ?> <?= e($item['unit']) ?></span>
                        <strong><?= number_format($item['current_price'] * (int) $item['quantity'], 0, ',', '.') ?> ₫</strong>
                    </div>
                <?php endforeach; ?>
                <hr>
                <form id="voucherPreviewForm" method="post" action="<?= e(url('/thanh-toan/xem-voucher')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="voucherCode">Mã giảm giá</label>
                    <div class="input-group">
                        <input class="form-control" id="voucherCode" name="voucher_code" value="<?= e($oldInput['voucher_code'] ?? '') ?>" autocomplete="off">
                        <button class="btn btn-outline-success" type="submit">Áp dụng / tính lại</button>
                    </div>
                    <div id="voucherPreviewMessage" class="small mt-2" aria-live="polite"></div>
                </form>
                <div id="checkoutQuote" class="mt-3">
                    <div class="d-flex justify-content-between"><span>Tạm tính</span><strong data-subtotal><?= number_format($initialQuote['subtotal'] ?? $cart['subtotal'], 0, ',', '.') ?> ₫</strong></div>
                    <div class="d-flex justify-content-between"><span>Giảm giá</span><strong data-discount><?= number_format($initialQuote['discount'] ?? 0, 0, ',', '.') ?> ₫</strong></div>
                    <div class="d-flex justify-content-between"><span>Phí vận chuyển</span><strong data-shipping><?= $initialQuote !== null ? number_format($initialQuote['shipping_fee'], 0, ',', '.') . ' ₫' : 'Chọn địa chỉ' ?></strong></div>
                    <hr><div class="d-flex justify-content-between fs-5"><span>Tổng cộng</span><strong data-total><?= number_format($initialQuote['total'] ?? $cart['subtotal'], 0, ',', '.') ?> ₫</strong></div>
                </div>
                <p class="small text-secondary mt-3 mb-0">Giá, tồn kho, voucher và phí vận chuyển sẽ được tính lại trên máy chủ ngay khi đặt hàng.</p>
            </div>
        </div>
    </div>
</div>
