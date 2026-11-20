<?php
$missionFields = [
    'review_reward_vnd' => ['Số tiền voucher đánh giá', 'Khoản giảm cho mỗi đánh giá đã duyệt.'],
    'review_minimum_vnd' => ['Đơn tối thiểu dùng voucher đánh giá', 'Giá trị đơn hàng tối thiểu để áp dụng.'],
    'monthly_spend_threshold_vnd' => ['Mốc chi tiêu tháng', 'Tổng giá trị đơn đã giao cần đạt trong tháng.'],
    'monthly_reward_vnd' => ['Số tiền voucher mốc tháng', 'Mỗi khách nhận tối đa một voucher trong tháng.'],
    'referral_reward_vnd' => ['Số tiền voucher giới thiệu', 'Mỗi bên nhận một voucher sau đơn hợp lệ đầu tiên.'],
    'referral_order_minimum_vnd' => ['Đơn tối thiểu để tính giới thiệu', 'Áp dụng cho đơn đầu tiên đã giao của người được mời.'],
];
?>
<div class="mb-3">
    <h1 class="h2 mb-1">Thưởng nhiệm vụ voucher</h1>
    <p class="text-muted mb-0">Điều chỉnh các mức thưởng tự động cho khách hàng. Voucher được cấp vào ví và có hạn 30 ngày.</p>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form class="card border-0 shadow-sm" method="post" action="<?= e(url('/admin/vouchers/missions')) ?>">
    <div class="card-body p-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?php foreach ($missionFields as $field => [$label, $help]): ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                    <div class="input-group">
                        <input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" type="number" min="1" max="1000000000" step="1" value="<?= e((string) $settings[$field]) ?>" required>
                        <span class="input-group-text">đ</span>
                    </div>
                    <div class="form-text"><?= e($help) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between">
        <a class="btn btn-outline-secondary" href="<?= e(url('/admin/vouchers')) ?>">Quay lại voucher</a>
        <button class="btn btn-success" type="submit">Lưu cài đặt</button>
    </div>
</form>
