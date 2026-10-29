<h1 class="h2 mb-4">Giỏ hàng</h1>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div id="cartPageMessage" class="d-none" role="alert"></div>
<?php $hasInvalid = false; ?>
<?php if ($cart['items']): ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Sản phẩm</th><th>Đơn giá hiện tại</th><th style="width: 12rem">Số lượng</th><th class="text-end">Thành tiền</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cart['items'] as $item): ?>
                <?php if (!$item['valid']) $hasInvalid = true; ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= e(asset($item['image_url'])) ?>" alt="" width="72" height="72" class="rounded object-fit-cover">
                            <div>
                                <?php if ($item['product_status'] === 'active' && $item['deleted_at'] === null): ?>
                                    <a class="fw-semibold link-dark" href="<?= e(url('/san-pham/' . $item['slug'])) ?>"><?= e($item['name']) ?></a>
                                <?php else: ?><strong><?= e($item['name']) ?></strong><?php endif; ?>
                                <div class="small text-secondary"><?= e($item['sku']) ?> · <?= e($item['unit']) ?></div>
                                <?php if ($item['message']): ?><div class="small <?= $item['valid'] ? 'text-warning' : 'text-danger' ?>"><?= e($item['message']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= number_format($item['current_price'], 0, ',', '.') ?> ₫ / <?= e($item['unit']) ?></td>
                    <td>
                        <?php if ($item['valid']): ?>
                            <form method="post" data-cart-update="<?= e(url('/gio-hang/cap-nhat')) ?>" class="d-flex gap-1">
                                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                <input class="form-control form-control-sm" type="number" name="quantity" min="1" max="<?= min((int) $item['available_stock'], (int) $cart['max_quantity']) ?>" value="<?= (int) $item['quantity'] ?>" aria-label="Số lượng">
                                <button class="btn btn-sm btn-outline-success" type="submit">Cập nhật</button>
                            </form>
                        <?php else: ?>
                            <span class="text-danger">Không đủ tồn</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $item['valid'] ? number_format($item['current_price'] * (int) $item['quantity'], 0, ',', '.') . ' ₫' : '—' ?></td>
                    <td>
                        <form method="post" data-cart-update="<?= e(url('/gio-hang/cap-nhat')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                            <input type="hidden" name="action" value="remove">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-top pt-3">
        <p class="mb-0">Tạm tính hợp lệ: <strong class="fs-5"><?= number_format($cart['subtotal'], 0, ',', '.') ?> ₫</strong></p>
        <?php if ($hasInvalid): ?>
            <button class="btn btn-secondary" type="button" disabled>Xử lý dòng không hợp lệ trước khi thanh toán</button>
        <?php else: ?>
            <a class="btn btn-success btn-lg" href="<?= e(url('/thanh-toan')) ?>">Tiến hành thanh toán</a>
        <?php endif; ?>
    </div>
    <p class="small text-secondary mt-2">Số lượng tối đa <?= (int) $cart['max_quantity'] ?> mỗi sản phẩm. Giá và tồn kho luôn được kiểm tra lại trước khi đặt.</p>
<?php else: ?>
    <div class="alert alert-light border">Giỏ hàng đang trống. <a href="<?= e(url('/san-pham')) ?>">Khám phá sản phẩm</a>.</div>
<?php endif; ?>
