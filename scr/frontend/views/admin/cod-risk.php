<?php
$typeLabels = ['user' => 'Tài khoản', 'phone' => 'Số điện thoại', 'address' => 'Địa chỉ'];
$settingValues = [];
foreach ($settings as $key => $value) {
    $settingValues[$key] = $oldSettings[$key] ?? $value;
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1">Chặn COD theo rủi ro giao hàng</h1>
        <p class="text-secondary mb-0">COD được kiểm tra theo lịch sử giao thất bại, tài khoản mới và số đơn COD đang mở.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/delivery-failures')) ?>">Xem lịch sử giao thất bại</a>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<section class="card mb-4">
    <div class="card-header"><h2 class="h5 mb-0">Ngưỡng áp dụng</h2></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/cod-risk/settings')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="codRiskDays">Số ngày xét lịch sử</label>
                    <input class="form-control" id="codRiskDays" name="cod_risk_failure_days" type="number" min="1" max="3650" required value="<?= e($settingValues['cod_risk_failure_days']) ?>">
                    <div class="form-text">Chỉ tính giao thất bại trong N ngày gần nhất.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="codRiskFailures">Số lần giao thất bại</label>
                    <input class="form-control" id="codRiskFailures" name="cod_risk_failure_threshold" type="number" min="1" max="100" required value="<?= e($settingValues['cod_risk_failure_threshold']) ?>">
                    <div class="form-text">Luật A chặn khi một định danh có từ K lần được tính vào bom hàng.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="codRiskNewLimit">Giới hạn COD cho tài khoản mới (đ)</label>
                    <input class="form-control" id="codRiskNewLimit" name="cod_risk_new_customer_limit" type="number" min="1" max="100000000" required value="<?= e($settingValues['cod_risk_new_customer_limit']) ?>">
                    <div class="form-text">Tài khoản chưa có đơn Đã giao chỉ dùng COD đến ngưỡng này.</div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="codRiskOpenLimit">Số đơn COD đang mở tối đa</label>
                    <input class="form-control" id="codRiskOpenLimit" name="cod_risk_open_orders_limit" type="number" min="1" max="100" required value="<?= e($settingValues['cod_risk_open_orders_limit']) ?>">
                    <div class="form-text">Chặn đơn mới khi cùng tài khoản, SĐT hoặc địa chỉ đã có M đơn COD chưa kết thúc.</div>
                </div>
            </div>
            <button class="btn btn-success mt-3" type="submit">Lưu ngưỡng</button>
        </form>
    </div>
</section>

<section class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0">Định danh đang bị chặn theo Luật A</h2>
        <span class="text-secondary">Tổng <?= (int) $total ?> định danh</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Loại</th><th>Giá trị tham chiếu mới nhất</th><th>Số lần</th><th>Lần cuối</th><th>Gỡ chặn</th></tr></thead>
            <tbody>
            <?php foreach ($blocks as $block): ?>
                <tr>
                    <td><?= e($typeLabels[$block['type']] ?? $block['type']) ?></td>
                    <td class="text-break"><?= e($block['display_value']) ?></td>
                    <td><?= (int) $block['failure_count'] ?></td>
                    <td><?= e($block['last_failure_at']) ?></td>
                    <td>
                        <form class="d-flex flex-wrap gap-2" method="post" action="<?= e(url('/admin/cod-risk/waive')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($block['type']) ?>">
                            <input type="hidden" name="value" value="<?= e($block['value']) ?>">
                            <label class="visually-hidden" for="waiverReason<?= e($block['type']) ?><?= (int) $block['latest_id'] ?>">Lý do gỡ chặn</label>
                            <input class="form-control form-control-sm cod-risk-waiver-reason" id="waiverReason<?= e($block['type']) ?><?= (int) $block['latest_id'] ?>" name="reason" maxlength="450" required placeholder="Lý do gỡ chặn">
                            <button class="btn btn-sm btn-outline-success" type="submit">Gỡ chặn</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$blocks): ?><tr><td colspan="5" class="text-center text-secondary py-4">Hiện không có định danh nào bị chặn theo Luật A.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include FRONTEND_PATH . '/views/admin/_pagination.php'; ?>

<section class="card">
    <div class="card-header"><h2 class="h5 mb-0">Các lần gỡ chặn gần đây</h2></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Thời gian</th><th>Định danh</th><th>Giá trị</th><th>Lý do</th><th>Người thực hiện</th></tr></thead>
            <tbody>
            <?php foreach ($waivers as $waiver): ?>
                <tr>
                    <td><?= e($waiver['created_at']) ?></td>
                    <td><?= e($typeLabels[$waiver['type']] ?? $waiver['type']) ?></td>
                    <td class="text-break"><?= e($waiver['display_value']) ?></td>
                    <td><?= e($waiver['reason']) ?></td>
                    <td><?= e($waiver['admin_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$waivers): ?><tr><td colspan="5" class="text-center text-secondary py-4">Chưa có lần gỡ chặn nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
