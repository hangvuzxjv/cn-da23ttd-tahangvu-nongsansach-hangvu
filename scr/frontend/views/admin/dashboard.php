<?php
$roleNames = ['admin' => 'Quản trị viên', 'staff_sales' => 'Nhân viên bán hàng', 'staff_warehouse' => 'Nhân viên kho'];
?>
<header class="dashboard-page">
    <div>
        <h1 class="h2 mb-1">Bảng điều khiển</h1>
        <p class="text-secondary mb-0">Xin chào <?= e($user['name'] ?? '') ?> · <?= e($roleNames[$user['role'] ?? ''] ?? ($user['role'] ?? '')) ?></p>
    </div>
    <div class="dashboard-page__actions">
        <?php if (!empty($canViewReports)): ?>
            <a class="btn btn-success" href="<?= e(url('/admin/reports')) ?>">Xem báo cáo đầy đủ</a>
        <?php endif; ?>
        <a class="btn btn-outline-success" href="<?= e(url('/profile')) ?>">Hồ sơ</a>
    </div>
</header>

<?php if (!empty($actionCounts)): ?>
    <?php
    $actionTiles = [
        'pendingOrders' => ['label' => 'Đơn chờ xử lý', 'url' => '/admin/orders', 'tone' => 'danger'],
        'refundsRequired' => ['label' => 'Cần hoàn tiền', 'url' => '/admin/payments', 'tone' => 'danger'],
        'pendingReturns' => ['label' => 'Yêu cầu trả hàng chờ duyệt', 'url' => '/admin/returns', 'tone' => 'warning'],
        'unansweredTickets' => ['label' => 'Ticket hỗ trợ chưa trả lời', 'url' => '/admin/support', 'tone' => 'danger'],
        'lowStock' => ['label' => 'Sản phẩm dưới tồn tối thiểu', 'url' => '/admin/inventory/stock', 'tone' => 'warning'],
        'nearExpiry' => ['label' => 'Lô sắp hết hạn', 'url' => '/admin/batches', 'tone' => 'warning'],
    ];
    ?>
    <section class="dashboard-section" aria-labelledby="dashboard-actions-title">
        <h2 class="h4" id="dashboard-actions-title">Việc cần xử lý ngay</h2>
        <div class="dashboard-action-grid">
            <?php foreach ($actionTiles as $key => $tile): if (!array_key_exists($key, $actionCounts)) { continue; } ?>
                <?php $count = (int) $actionCounts[$key]; ?>
                <a class="dashboard-action-card<?= $count > 0 ? ' dashboard-action-card--' . e($tile['tone']) : ' dashboard-action-card--empty' ?>"
                   href="<?= e(url($tile['url'])) ?>">
                    <span class="dashboard-action-card__label"><?= e($tile['label']) ?></span>
                    <strong class="dashboard-action-card__count"><?= $count ?></strong>
                    <span class="dashboard-action-card__status"><?= $count > 0 ? 'Cần xử lý' : 'Không có việc' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($dashboard === null && !empty($quickLinks)): ?>
    <section class="card admin-quick-access dashboard-section">
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
    $statusGroups = [
        'flow' => [
            'pending' => ['label' => 'Chờ', 'tone' => 'pending'],
            'confirmed' => ['label' => 'Đã xác nhận', 'tone' => 'confirmed'],
            'processing' => ['label' => 'Chuẩn bị', 'tone' => 'processing'],
            'shipping' => ['label' => 'Đang giao', 'tone' => 'shipping'],
            'completed' => ['label' => 'Hoàn tất', 'tone' => 'completed'],
        ],
        'other' => [
            'cancelled' => ['label' => 'Đã hủy', 'tone' => 'muted'],
            'refunded' => ['label' => 'Đã hoàn tiền', 'tone' => 'danger'],
            'delivery_failed' => ['label' => 'Giao thất bại', 'tone' => 'danger'],
        ],
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
    <section class="dashboard-section" aria-labelledby="dashboard-revenue-title">
        <h2 class="h4" id="dashboard-revenue-title">Doanh thu</h2>
        <div class="dashboard-revenue-grid">
            <div class="dashboard-revenue-summary">
                <div class="dashboard-revenue-totals">
                    <article class="card dashboard-revenue-card">
                        <div class="card-body">
                            <span class="text-secondary">Hôm nay</span>
                            <strong><?= e($money($dashboard['revenue']['today'] ?? 0)) ?></strong>
                            <small>Tiền đã nhận</small>
                        </div>
                    </article>
                    <article class="card dashboard-revenue-card">
                        <div class="card-body">
                            <span class="text-secondary">7 ngày</span>
                            <strong><?= e($money($dashboard['revenue']['last_7_days'] ?? 0)) ?></strong>
                            <small>Tiền đã nhận</small>
                        </div>
                    </article>
                    <article class="card dashboard-revenue-card">
                        <div class="card-body">
                            <span class="text-secondary">30 ngày</span>
                            <strong><?= e($money($dashboard['revenue']['last_30_days'] ?? 0)) ?></strong>
                            <small>Tiền đã nhận</small>
                        </div>
                    </article>
                </div>
                <div class="dashboard-revenue-extras">
                    <article class="card dashboard-revenue-extra">
                        <span>Chờ thanh toán</span>
                        <strong><?= (int) $dashboard['pendingPaymentOrders'] ?></strong>
                    </article>
                    <article class="card dashboard-revenue-extra">
                        <span>Khách mới hôm nay</span>
                        <strong><?= (int) $dashboard['newCustomers'] ?></strong>
                    </article>
                </div>
            </div>
            <section class="card dashboard-chart-card">
                <div class="card-body">
                    <h3 class="h5">Tiền đã nhận 14 ngày gần nhất</h3>
                    <p class="small text-secondary">Chỉ tính giao dịch đã thanh toán, theo thời điểm thanh toán.</p>
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
    </section>

    <section class="card dashboard-section"><div class="card-body">
        <h2 class="h5">Đơn hàng theo trạng thái</h2>
        <div class="dashboard-status-grid">
            <?php foreach ($statusGroups['flow'] as $status => $item): ?>
                <a class="dashboard-status-card dashboard-status-card--<?= e($item['tone']) ?>" href="<?= e(url('/admin/orders?status=' . rawurlencode($status))) ?>">
                    <span><?= e($item['label']) ?></span>
                    <strong><?= (int) ($dashboard['statusCounts'][$status] ?? 0) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="dashboard-status-secondary">
            <?php foreach ($statusGroups['other'] as $status => $item): ?>
                <a class="dashboard-status-card dashboard-status-card--<?= e($item['tone']) ?>" href="<?= e(url('/admin/orders?status=' . rawurlencode($status))) ?>">
                    <span><?= e($item['label']) ?></span>
                    <strong><?= (int) ($dashboard['statusCounts'][$status] ?? 0) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </div></section>

    <div class="dashboard-bottom-grid dashboard-section">
        <section class="card"><div class="card-body">
            <h2 class="h5">Tiền đã nhận theo vùng miền (30 ngày)</h2>
            <?php if (!$dashboard['regionalRevenue']): ?><p class="text-secondary mb-0">Chưa có dữ liệu</p><?php else: ?>
                <?php foreach ($dashboard['regionalRevenue'] as $row): ?>
                    <?php $width = min(100, (int) round((float) $row['revenue'] / $maxRegion * 100)); ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between gap-2 small"><span><?= e($row['region_name']) ?></span><strong><?= e($money($row['revenue'])) ?></strong></div>
                        <div class="progress dashboard-region-progress" role="progressbar" aria-valuenow="<?= $width ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?= $width ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></section>
        <section class="card"><div class="card-body">
            <h2 class="h5">Sản phẩm bán chạy</h2>
            <table class="table table-sm align-middle dashboard-compact-table dashboard-product-table">
                <colgroup><col><col><col></colgroup>
                <thead><tr><th>Sản phẩm</th><th>Đã bán</th><th>Doanh thu</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($dashboard['topProducts'], 0, 5) as $row): ?>
                        <tr><td><?= e($row['product_name']) ?></td><td><?= (int) $row['quantity'] ?></td><td><?= e($money($row['revenue'])) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$dashboard['topProducts']): ?><tr><td colspan="3" class="text-secondary">Chưa có dữ liệu</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></section>
    </div>

    <div class="dashboard-bottom-grid dashboard-section">
        <section class="card"><div class="card-body">
            <h2 class="h5">Khách hàng doanh thu cao</h2>
            <table class="table table-sm align-middle dashboard-compact-table dashboard-customer-table">
                <thead><tr><th>Tên</th><th>Số đơn</th><th>Doanh thu</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($dashboard['topCustomers'], 0, 5) as $row): ?>
                        <tr><td><?= e($row['name']) ?></td><td><?= (int) $row['order_count'] ?></td><td><?= e($money($row['revenue'])) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$dashboard['topCustomers']): ?><tr><td colspan="3" class="text-secondary">Chưa có dữ liệu</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></section>
        <?php if (!empty($canViewBatches)): ?>
            <section class="card"><div class="card-body">
                <h2 class="h5">Top lô được quét QR</h2>
                <?php if (empty($topScanned)): ?><p class="text-secondary mb-0">Chưa có dữ liệu</p><?php else: ?>
                    <table class="table table-sm align-middle dashboard-compact-table dashboard-batch-table">
                        <thead><tr><th>Mã lô</th><th>Sản phẩm</th><th>Lượt quét</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($topScanned, 0, 5) as $batch): ?>
                                <tr><td><code><?= e($batch['batch_code']) ?></code></td><td><?= e($batch['product_name']) ?></td><td><?= (int) $batch['scan_count'] ?></td><td><a href="<?= e(url('/admin/batches/' . $batch['id'])) ?>">Chi tiết</a></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div></section>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($dashboard === null && !empty($canViewBatches)): ?>
    <section class="card dashboard-section"><div class="card-body">
        <h2 class="h5">Top lô được quét QR</h2>
        <?php if (empty($topScanned)): ?><p class="text-secondary mb-0">Chưa có dữ liệu</p><?php else: ?>
            <table class="table table-sm align-middle dashboard-compact-table dashboard-batch-table">
                <thead><tr><th>Mã lô</th><th>Sản phẩm</th><th>Lượt quét</th><th></th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($topScanned, 0, 5) as $batch): ?>
                        <tr><td><code><?= e($batch['batch_code']) ?></code></td><td><?= e($batch['product_name']) ?></td><td><?= (int) $batch['scan_count'] ?></td><td><a href="<?= e(url('/admin/batches/' . $batch['id'])) ?>">Chi tiết</a></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div></section>
<?php endif; ?>
