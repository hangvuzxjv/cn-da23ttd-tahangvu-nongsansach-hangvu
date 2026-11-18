<?php
$scopeText = static function (array $voucher): string {
    $parts = [];
    if (!empty($voucher['category_names'])) {
        $parts[] = 'danh mục: ' . $voucher['category_names'];
    }
    if (!empty($voucher['product_names'])) {
        $parts[] = 'sản phẩm: ' . $voucher['product_names'];
    }
    return $parts ? 'Áp dụng cho ' . implode(' hoặc ', $parts) : 'Áp dụng cho mọi sản phẩm';
};
?>
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li>
        <li class="breadcrumb-item active" aria-current="page">Kho voucher</li>
    </ol></nav>
    <h1 class="h2">Kho voucher</h1>
    <p class="text-secondary">Lưu voucher công khai vào ví để dùng khi thanh toán.</p>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (!$vouchers): ?>
    <div class="empty-state">
        <h2 class="h5">Chưa có voucher công khai đang chạy</h2>
        <p class="text-secondary mb-0">Ưu đãi mới sẽ được hiển thị tại đây khi bắt đầu áp dụng.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($vouchers as $voucher): ?>
            <div class="col">
                <article class="card h-100 voucher-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="voucher-code"><?= e($voucher['code']) ?></span>
                            <span class="badge text-bg-success">Đang chạy</span>
                        </div>
                        <p class="mt-2 mb-1"><?= e($voucher['description']) ?></p>
                        <strong class="voucher-discount"><?= e(VoucherWalletService::formatDiscount($voucher)) ?></strong>
                        <div class="small text-secondary mt-2">
                            <div>
                                <?= (int) $voucher['minimum_order'] > 0
                                    ? 'Đơn từ ' . number_format(PricingService::moneyToVnd((string) $voucher['minimum_order']), 0, ',', '.') . ' đ'
                                    : 'Không yêu cầu giá trị đơn tối thiểu' ?>
                            </div>
                            <div><?= e($scopeText($voucher)) ?></div>
                            <div>Hiệu lực đến <?= date('d/m/Y', strtotime((string) $voucher['ends_at'])) ?></div>
                            <?php if ($voucher['valid_days_after_grant'] !== null): ?>
                                <div>Hạn tối đa <?= (int) $voucher['valid_days_after_grant'] ?> ngày sau khi lưu</div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-auto pt-3">
                            <?php if (!empty($voucher['is_saved'])): ?>
                                <button class="btn btn-secondary w-100" type="button" disabled>Đã lưu</button>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('/kho-voucher/' . (int) $voucher['id'] . '/luu')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-success w-100" type="submit">Lưu vào ví</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
