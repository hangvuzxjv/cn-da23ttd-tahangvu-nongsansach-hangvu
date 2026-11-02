<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 dashboard-page">
    <div>
        <h1 class="h2">Bảng điều khiển</h1>
        <?php $roleNames = ['admin' => 'Quản trị viên', 'staff_sales' => 'Nhân viên bán hàng', 'staff_warehouse' => 'Nhân viên kho']; ?>
        <p class="text-secondary mb-0">Xin chào <?= e($user['name'] ?? '') ?> · Vai trò: <?= e($roleNames[$user['role'] ?? ''] ?? ($user['role'] ?? '')) ?></p>
    </div>
    <a class="btn btn-outline-success" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
</div>

<?php if ($dashboard === null && !empty($quickLinks)): ?>
    <section class="card admin-quick-access mt-4">
        <div class="card-body">
            <h2 class="h5">Truy cập nhanh</h2>
            <div class="admin-quick-access__links">
                <?php foreach ($quickLinks as $link): ?>
                    <a class="admin-quick-access__link" href="<?= e(url($link['url'])) ?>">
                        <span><?= e($link['label']) ?></span>
                        <small><?= e($link['group']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($dashboard !== null): ?>
    <?php
    $money = static fn (mixed $value): string => number_format((float) $value, 0, ',', '.') . ' ₫';
    $statusNames = [
        'pending' => 'Chờ xử lý', 'confirmed' => 'Đã xác nhận', 'processing' => 'Đang chuẩn bị',
        'shipping' => 'Đang giao', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền',
    ];
    $maxDaily = max(1, ...array_map(static fn (array $row): float => (float) $row['revenue'], $dashboard['dailyRevenue']));
    $points = [];
    foreach ($dashboard['dailyRevenue'] as $index => $row) {
        $x = 20 + $index * 50;
        $y = 155 - (int) round((float) $row['revenue'] / $maxDaily * 130);
        $points[] = $x . ',' . $y;
    }
    $maxRegion = max(1, ...array_map(static fn (array $row): float => (float) $row['revenue'], $dashboard['regionalRevenue']));
    ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mt-2">
        <div class="col"><div class="card h-100 border-success dashboard-stat"><div class="card-body"><div class="text-secondary">Doanh thu hôm nay</div><div class="fs-4 fw-bold text-success"><?= e($money($dashboard['revenue']['today'] ?? 0)) ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Doanh thu 7 ngày</div><div class="fs-4 fw-bold"><?= e($money($dashboard['revenue']['last_7_days'] ?? 0)) ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Doanh thu 30 ngày</div><div class="fs-4 fw-bold"><?= e($money($dashboard['revenue']['last_30_days'] ?? 0)) ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Đơn chờ xử lý</div><div class="fs-4 fw-bold"><?= (int) $dashboard['pendingOrders'] ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Khách mới hôm nay</div><div class="fs-4 fw-bold"><?= (int) $dashboard['newCustomers'] ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Sản phẩm dưới tồn tối thiểu</div><div class="fs-4 fw-bold <?= (int) $dashboard['lowStock'] > 0 ? 'text-danger' : '' ?>"><?= (int) $dashboard['lowStock'] ?></div></div></div></div>
        <div class="col"><div class="card h-100 dashboard-stat"><div class="card-body"><div class="text-secondary">Lô sắp hết hạn (30 ngày)</div><div class="fs-4 fw-bold <?= (int) $dashboard['nearExpiry'] > 0 ? 'text-warning' : '' ?>"><?= (int) $dashboard['nearExpiry'] ?></div></div></div></div>
    </div>

    <section class="card mt-4">
        <div class="card-body">
            <h2 class="h5">Đơn hàng theo trạng thái</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($statusNames as $status => $label): ?>
                    <span class="badge rounded-pill text-bg-light border"><?= e($label) ?>: <?= (int) ($dashboard['statusCounts'][$status] ?? 0) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="row g-4 mt-0">
        <div class="col-12 col-xl-7">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Doanh thu 14 ngày gần nhất</h2>
                    <p class="small text-secondary">Theo tổng tiền đã lưu trên đơn; loại đơn hủy và hoàn tiền.</p>
                    <div class="table-responsive">
                        <svg viewBox="0 0 700 190" role="img" aria-label="Biểu đồ doanh thu 14 ngày" class="w-100 dashboard-chart">
                            <line x1="20" y1="155" x2="680" y2="155" class="dashboard-chart__axis" />
                            <polyline points="<?= e(implode(' ', $points)) ?>" fill="none" class="dashboard-chart__line" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            <?php foreach ($dashboard['dailyRevenue'] as $index => $row): if ($index % 2 !== 0 && $index !== 13) { continue; } ?>
                                <?php $x = 20 + $index * 50; $y = 155 - (int) round((float) $row['revenue'] / $maxDaily * 130); ?>
                                <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" class="dashboard-chart__point"><title><?= e($row['date']) ?>: <?= e($money($row['revenue'])) ?></title></circle>
                                <text x="<?= $x ?>" y="178" text-anchor="middle" font-size="10" class="dashboard-chart__label"><?= e(substr((string) $row['date'], 5)) ?></text>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Doanh thu theo vùng miền (30 ngày)</h2>
                    <?php if (!$dashboard['regionalRevenue']): ?><p class="text-secondary mb-0">Chưa có dữ liệu.</p><?php else: ?>
                        <?php foreach ($dashboard['regionalRevenue'] as $row): ?>
                            <?php $width = min(100, (int) round((float) $row['revenue'] / $maxRegion * 100)); ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between gap-2 small"><span><?= e($row['region_name']) ?></span><strong><?= e($money($row['revenue'])) ?></strong></div>
                                <div class="progress dashboard-region-progress" role="progressbar" aria-valuenow="<?= $width ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?= $width ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-0">
        <div class="col-12 col-xl-6">
            <section class="card h-100"><div class="card-body">
                <h2 class="h5">Sản phẩm bán chạy</h2>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Sản phẩm</th><th class="text-end">Đã bán</th><th class="text-end">Doanh thu</th></tr></thead><tbody>
                    <?php foreach ($dashboard['topProducts'] as $row): ?><tr><td><?= e($row['product_name']) ?></td><td class="text-end"><?= (int) $row['quantity'] ?></td><td class="text-end"><?= e($money($row['revenue'])) ?></td></tr><?php endforeach; ?>
                    <?php if (!$dashboard['topProducts']): ?><tr><td colspan="3" class="text-secondary">Chưa có dữ liệu đơn hàng.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div></section>
        </div>
        <div class="col-12 col-xl-6">
            <section class="card h-100"><div class="card-body">
                <h2 class="h5">Khách hàng có doanh thu cao</h2>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Khách hàng</th><th class="text-end">Đơn</th><th class="text-end">Doanh thu</th></tr></thead><tbody>
                    <?php foreach ($dashboard['topCustomers'] as $row): ?><tr><td><?= e($row['name']) ?><div class="small text-secondary"><?= e($row['email']) ?></div></td><td class="text-end"><?= (int) $row['order_count'] ?></td><td class="text-end"><?= e($money($row['revenue'])) ?></td></tr><?php endforeach; ?>
                    <?php if (!$dashboard['topCustomers']): ?><tr><td colspan="3" class="text-secondary">Chưa có dữ liệu đơn hàng.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div></section>
        </div>
    </div>
    <div class="mt-3"><a class="btn btn-outline-success" href="<?= e(url('/admin/reports')) ?>">Mở trang báo cáo</a></div>
<?php endif; ?>

<?php if (!empty($canViewBatches)): ?>
    <section class="card mt-4"><div class="card-body">
        <h2 class="h5">Top lô được quét nhiều</h2>
        <?php if (empty($topScanned)): ?><div class="empty-state">Chưa có lượt quét nguồn gốc.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Mã lô</th><th>Sản phẩm</th><th>Lượt quét</th><th></th></tr></thead><tbody>
                <?php foreach ($topScanned as $batch): ?><tr><td><code><?= e($batch['batch_code']) ?></code></td><td><?= e($batch['product_name']) ?></td><td><?= (int) $batch['scan_count'] ?></td><td><a href="<?= e(url('/admin/batches/' . $batch['id'])) ?>">Chi tiết</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></section>
<?php endif; ?>
