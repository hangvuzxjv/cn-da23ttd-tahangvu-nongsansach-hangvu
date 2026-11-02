<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Giỏ hàng</li></ol></nav>
    <h1 class="h2">Giỏ hàng</h1>
    <p class="text-secondary">Kiểm tra sản phẩm, số lượng và giá trước khi thanh toán.</p>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div id="cartPageMessage" class="d-none" role="alert"></div>
<?php if ($cart['items']): ?>
    <div class="cart-layout">
        <div class="cart-items table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Sản phẩm</th><th>Đơn giá hiện tại</th><th class="cart-quantity-column">Số lượng</th><th class="text-end">Thành tiền</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($cart['items'] as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= e(asset($item['image_url'] ?: '/img/product-placeholder.svg')) ?>" alt="" width="72" height="72" class="rounded object-fit-cover cart-product-image">
                                <div>
                                    <?php if ($item['product_status'] === 'active' && $item['deleted_at'] === null): ?>
                                        <a class="fw-semibold link-dark" href="<?= e(url('/san-pham/' . $item['slug'])) ?>"><?= e($item['name']) ?></a>
                                    <?php else: ?><strong><?= e($item['name']) ?></strong><?php endif; ?>
                                    <div class="small text-secondary"><?= e($item['sku']) ?> · <?= e($item['unit']) ?></div>
                                    <?php if ($item['message']): ?><div class="small <?= $item['valid'] ? 'text-warning' : 'text-danger' ?>"><?= e($item['message']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($item['is_flash_sale'])): ?>
                                <span class="badge text-bg-danger">Flash Sale</span><br>
                                <del class="small text-secondary"><?= number_format((int) $item['original_price'], 0, ',', '.') ?> ₫</del><br>
                            <?php endif; ?>
                            <?= number_format($item['current_price'], 0, ',', '.') ?> ₫ / <?= e($item['unit']) ?>
                        </td>
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
        <aside class="card cart-summary">
            <div class="card-body">
                <h2 class="h5">Tóm tắt giỏ hàng</h2>
                <div class="d-flex justify-content-between gap-3"><span>Số mặt hàng</span><strong><?= count($cart['items']) ?></strong></div>
                <div class="d-flex justify-content-between gap-3 mt-2"><span>Tạm tính hợp lệ</span><strong class="fs-5"><?= number_format($cart['subtotal'], 0, ',', '.') ?> ₫</strong></div>
                <p class="small text-secondary mt-2">Phí vận chuyển và mã giảm giá sẽ được tính tại bước thanh toán.</p>
                <?php if (array_filter($cart['items'], static fn (array $item): bool => !$item['valid'])): ?>
                    <button class="btn btn-secondary w-100" type="button" disabled>Xử lý dòng không hợp lệ trước khi thanh toán</button>
                <?php else: ?>
                    <a class="btn btn-success btn-lg w-100" href="<?= e(url('/thanh-toan')) ?>">Tiến hành thanh toán</a>
                <?php endif; ?>
                <p class="small text-secondary mt-3 mb-0">Tối đa <?= (int) $cart['max_quantity'] ?> sản phẩm mỗi mặt hàng. Giá và tồn kho được kiểm tra lại khi đặt hàng.</p>
            </div>
        </aside>
    </div>
<?php else: ?>
    <div class="empty-state"><h2 class="h4">Giỏ hàng đang trống</h2><p class="text-secondary">Hãy chọn nông sản theo mùa để bắt đầu.</p><a class="btn btn-success" href="<?= e(url('/san-pham')) ?>">Khám phá sản phẩm</a></div>
<?php endif; ?>
