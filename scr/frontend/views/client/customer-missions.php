<?php
$formatMissionMoney = static fn (int $amount): string => number_format($amount, 0, ',', '.') . ' đ';
?>
<?php if (!empty($error)): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php elseif (is_array($missions)): ?>
    <?php $settings = $missions['settings']; ?>
    <?php $activeAccountTab = 'missions'; ?>
    <div class="client-account-layout">
            <aside class="client-account-layout__sidebar">
                <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
            </aside>
            <div class="client-account-layout__content">
                <header class="page-heading">
                    <h1 class="h2">Nhiệm vụ nhận voucher</h1>
                    <p class="text-secondary">Hoàn thành các hoạt động mua sắm để nhận voucher tự động vào tài khoản.</p>
                </header>
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <section class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h2 class="h5">Đánh giá sản phẩm</h2>
                                <p class="text-secondary">Đánh giá sản phẩm trong đơn đã giao. Khi đánh giá được duyệt, bạn nhận voucher giảm <?= e($formatMissionMoney($settings['review_reward_vnd'])) ?> cho đơn từ <?= e($formatMissionMoney($settings['review_minimum_vnd'])) ?>.</p>
                                <p class="mb-0">Đánh giá đang chờ duyệt: <strong><?= number_format($missions['reviews']['pending']) ?></strong>
                                    <span class="text-secondary"> · Đã nhận thưởng: <?= number_format($missions['reviews']['rewarded']) ?> lần</span>
                                </p>
                            </div>
                        </section>
                    </div>
                    <div class="col-12 col-lg-6">
                        <section class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h2 class="h5">Chi tiêu mỗi tháng</h2>
                                <p class="text-secondary">Tổng giá trị đơn đã giao trong tháng <?= e($missions['monthly']['period']) ?> đạt <?= e($formatMissionMoney($missions['monthly']['threshold'])) ?> để nhận voucher <?= e($formatMissionMoney($settings['monthly_reward_vnd'])) ?>. Mỗi tháng nhận tối đa một lần.</p>
                                <div class="progress mb-2" role="progressbar" aria-label="Tiến độ chi tiêu tháng" aria-valuenow="<?= (int) $missions['monthly']['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-success" data-progress="<?= (int) $missions['monthly']['progress_percent'] ?>"></div>
                                </div>
                                <p class="mb-0">Đã tính: <strong><?= e($formatMissionMoney($missions['monthly']['spend'])) ?></strong>
                                    <?php if ($missions['monthly']['rewarded']): ?>
                                        <span class="text-success"> · Đã nhận voucher tháng này</span>
                                    <?php else: ?>
                                        <span class="text-secondary"> · Còn <?= e($formatMissionMoney($missions['monthly']['remaining'])) ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </section>
                    </div>
                    <div class="col-12">
                        <section class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h2 class="h5">Mời bạn bè</h2>
                                <p class="text-secondary">Chia sẻ mã bên dưới. Khi người bạn mời xác minh email và hoàn tất đơn đầu tiên từ <?= e($formatMissionMoney($settings['referral_order_minimum_vnd'])) ?>, cả hai cùng nhận voucher <?= e($formatMissionMoney($settings['referral_reward_vnd'])) ?>.</p>
                                <label class="form-label" for="customer-referral-code">Mã giới thiệu của bạn</label>
                                <div class="input-group mb-3">
                                    <input class="form-control" id="customer-referral-code" value="<?= e($missions['referral']['code']) ?>" readonly>
                                    <a class="btn btn-outline-success" href="<?= e(url('/register?ref=' . rawurlencode($missions['referral']['code']))) ?>">Mời bạn đăng ký</a>
                                </div>
                                <p class="mb-0">Đang chờ đơn đủ điều kiện: <strong><?= number_format($missions['referral']['pending']) ?></strong>
                                    <span class="text-secondary"> · Đã thưởng: <?= number_format($missions['referral']['rewarded']) ?> lượt</span>
                                </p>
                            </div>
                        </section>
                    </div>
                </div>
                <p class="small text-secondary mt-3 mb-0">Voucher được cấp vào <a href="<?= e(url('/voucher-cua-toi')) ?>">Voucher của tôi</a> và có hạn sử dụng 30 ngày kể từ ngày nhận.</p>
            </div>
    </div>
<?php endif; ?>
