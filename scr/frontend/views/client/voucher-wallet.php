<?php
$sourceLabels = [
    'welcome' => 'Chào mừng',
    'first_order' => 'Cảm ơn đơn đầu',
    'claimed' => 'Đã lưu từ Kho voucher',
    'admin_gift' => 'Được tặng',
];
$tabLabels = [
    'available' => 'Có thể dùng',
    'used' => 'Đã dùng',
    'expired' => 'Hết hạn',
];
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
<?php $activeAccountTab = 'vouchers'; ?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li>
        <li class="breadcrumb-item active" aria-current="page">Voucher của tôi</li>
    </ol></nav>
    <h1 class="h2">Voucher của tôi</h1>
    <p class="text-secondary">Theo dõi voucher bạn đã nhận và dùng mã phù hợp cho đơn hàng.</p>
</header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<nav class="nav nav-pills voucher-tabs mb-4" aria-label="Trạng thái voucher">
    <?php foreach ($tabLabels as $tabKey => $label): ?>
        <a class="nav-link<?= $activeTab === $tabKey ? ' active' : '' ?>"
           href="<?= e(url('/voucher-cua-toi?tab=' . rawurlencode($tabKey))) ?>"
           <?= $activeTab === $tabKey ? 'aria-current="page"' : '' ?>>
            <?= e($label) ?> <span class="badge rounded-pill text-bg-light"><?= (int) $tabCounts[$tabKey] ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php if (!$vouchers): ?>
    <div class="empty-state">
        <h2 class="h5">Chưa có voucher trong mục này</h2>
        <p class="text-secondary">Khám phá Kho voucher để lưu ưu đãi công khai vào ví của bạn.</p>
        <a class="btn btn-success" href="<?= e(url('/kho-voucher')) ?>">Xem Kho voucher</a>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-3">
        <?php foreach ($vouchers as $voucher): ?>
            <?php
            $effectiveExpiry = $voucher['expires_at'] ?: $voucher['ends_at'];
            $expiryTimestamp = strtotime((string) $effectiveExpiry);
            $remainingDays = max(0, (int) ceil(($expiryTimestamp - time()) / 86400));
            ?>
            <div class="col">
                <article class="card h-100 voucher-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <span class="voucher-code"><?= e($voucher['code']) ?></span>
                                <p class="mb-1 mt-2"><?= e($voucher['description']) ?></p>
                            </div>
                            <?php if ($voucher['wallet_status'] === 'available'): ?>
                                <span class="badge text-bg-success">Có thể dùng</span>
                            <?php elseif ($voucher['wallet_status'] === 'used'): ?>
                                <span class="badge text-bg-secondary">Đã dùng</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning"><?= e($voucher['status_reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <strong class="voucher-discount"><?= e(VoucherWalletService::formatDiscount($voucher)) ?></strong>
                        <div class="small text-secondary mt-2">
                            <div>
                                <?= (int) $voucher['minimum_order'] > 0
                                    ? 'Đơn từ ' . number_format(PricingService::moneyToVnd((string) $voucher['minimum_order']), 0, ',', '.') . ' đ'
                                    : 'Không yêu cầu giá trị đơn tối thiểu' ?>
                            </div>
                            <div><?= e($scopeText($voucher)) ?></div>
                            <div>
                                <?php if ($voucher['wallet_status'] === 'available'): ?>
                                    Còn <?= $remainingDays ?> ngày (đến <?= date('d/m/Y', $expiryTimestamp) ?>)
                                <?php elseif ($voucher['wallet_status'] === 'used' && $voucher['used_at']): ?>
                                    Đã dùng <?= date('d/m/Y H:i', strtotime((string) $voucher['used_at'])) ?>
                                <?php else: ?>
                                    <?= e($voucher['status_reason']) ?><?php if ($voucher['status_reason'] === 'Đã hết hạn'): ?> <?= date('d/m/Y', $expiryTimestamp) ?><?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div>Nguồn nhận: <?= e($sourceLabels[$voucher['source']] ?? 'Được tặng') ?></div>
                        </div>
                        <div class="mt-auto pt-3 d-flex flex-wrap gap-2">
                            <button class="btn btn-outline-success btn-sm" type="button" data-copy-voucher="<?= e($voucher['code']) ?>">Sao chép mã</button>
                            <?php if ($voucher['wallet_status'] === 'available'): ?>
                                <a class="btn btn-success btn-sm" href="<?= e(url('/gio-hang')) ?>">Dùng ngay</a>
                            <?php endif; ?>
                            <span class="small text-success align-self-center" data-copy-feedback aria-live="polite"></span>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
    </div>
</div>
