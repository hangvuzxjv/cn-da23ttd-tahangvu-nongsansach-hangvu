<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h2 mb-1">Báo cáo thống kê</h1><p class="text-secondary mb-0">Doanh thu dựa trên số tiền và giá sản phẩm đã chụp tại thời điểm đặt hàng.</p></div>
    <a class="btn btn-outline-success" href="<?= e(url('/admin/reports.csv?' . http_build_query(['type' => $type, 'from' => $from, 'to' => $to]))) ?>">Xuất CSV · UTF-8</a>
</div>
<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
<form class="card card-body mb-4" method="get" action="<?= e(url('/admin/reports')) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label" for="reportType">Loại báo cáo</label><select class="form-select" name="type" id="reportType"><?php foreach ($definitions as $key => $definition): ?><option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($definition['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label" for="reportFrom">Từ ngày</label><input class="form-control" type="date" id="reportFrom" name="from" value="<?= e($from) ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label" for="reportTo">Đến ngày</label><input class="form-control" type="date" id="reportTo" name="to" value="<?= e($to) ?>" required></div>
        <div class="col-12 col-md-2"><button class="btn btn-success w-100" type="submit">Xem báo cáo</button></div>
    </div>
</form>
<section class="card"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5"><?= e($report['definition']['title']) ?></h2><span class="text-secondary small"><?= (int) $report['total'] ?> dòng</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><?php foreach ($report['definition']['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <?php foreach ($report['rows'] as $row): ?><tr>
                <?php foreach ($report['definition']['columns'] as $key => $label): ?>
                    <?php $value = $row[$key] ?? ''; $isMoney = in_array($key, ['revenue'], true); ?>
                    <td class="<?= in_array($key, ['revenue','quantity','quota','sold_quantity','order_count','incoming','outgoing','opening_stock','closing_stock'], true) ? 'text-end' : '' ?>">
                        <?= $isMoney ? e(number_format((float) $value, 0, ',', '.') . ' ₫') : e($value === null ? '—' : (string) $value) ?>
                    </td>
                <?php endforeach; ?>
            </tr><?php endforeach; ?>
            <?php if (!$report['rows']): ?><tr><td colspan="<?= count($report['definition']['columns']) ?>" class="text-center text-secondary py-4">Không có dữ liệu trong khoảng đã chọn.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($report['pages'] > 1): ?><nav aria-label="Phân trang báo cáo"><ul class="pagination flex-wrap mb-0"><?php for ($number = 1; $number <= $report['pages']; $number++): ?><li class="page-item <?= $number === $report['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/admin/reports?' . http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'page' => $number]))) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</div></section>
