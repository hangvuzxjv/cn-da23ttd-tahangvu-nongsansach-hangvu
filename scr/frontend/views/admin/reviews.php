<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Quản lý đánh giá</h1>
        <p class="text-secondary mb-0"><?= (int) $total ?> đánh giá</p>
    </div>
</div>
<div data-review-admin-message role="status" aria-live="polite"></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<form class="card card-body mb-4" method="get" action="<?= e(url('/admin/reviews')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="reviewQuery">Tìm nội dung, khách hàng, email hoặc sản phẩm</label>
            <input class="form-control" id="reviewQuery" name="q" value="<?= e($filters['q']) ?>" maxlength="200">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="reviewProduct">Sản phẩm</label>
            <select class="form-select" id="reviewProduct" name="product_id">
                <option value="0">Tất cả sản phẩm</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int) $product['id'] ?>" <?= (int) $filters['product_id'] === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="reviewStatus">Trạng thái</label>
            <select class="form-select" id="reviewStatus" name="status">
                <option value="">Tất cả</option>
                <?php foreach (['pending' => 'Chờ duyệt', 'published' => 'Đã duyệt', 'hidden' => 'Đã ẩn'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-grid"><button class="btn btn-success" type="submit">Lọc</button></div>
    </div>
</form>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Sản phẩm / khách hàng</th><th>Đánh giá</th><th>Trạng thái</th><th>Ngày gửi</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($reviews as $review): ?>
            <?php $statusLabel = ['pending' => 'Chờ duyệt', 'published' => 'Đã duyệt', 'hidden' => 'Đã ẩn'][$review['status']] ?? $review['status']; ?>
            <tr>
                <td>
                    <a href="<?= e(url('/san-pham/' . $review['product_slug'])) ?>"><?= e($review['product_name']) ?></a>
                    <div class="small text-secondary"><?= e($review['customer_name']) ?> · <?= e($review['customer_email']) ?></div>
                </td>
                <td>
                    <span class="text-warning"><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></span>
                    <?php if ($review['title']): ?><strong class="d-block"><?= e($review['title']) ?></strong><?php endif; ?>
                    <div class="small"><?= nl2br(e($review['content'])) ?></div>
                </td>
                <td data-review-status><?= e($statusLabel) ?></td>
                <td class="small"><?= e($review['created_at']) ?></td>
                <td>
                    <?php if ($canManage): ?>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= e(url('/admin/reviews/' . (int) $review['id'] . '/status')) ?>" data-review-admin-action>
                            <?= csrf_field() ?>
                            <select class="form-select form-select-sm mb-1" name="status" aria-label="Trạng thái đánh giá">
                                <?php foreach (['pending' => 'Chờ duyệt', 'published' => 'Duyệt', 'hidden' => 'Ẩn'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $review['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-success" type="submit">Lưu</button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/reviews/' . (int) $review['id'] . '/delete')) ?>"
                              data-review-admin-action data-review-delete="1" data-confirm="Xóa đánh giá này?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                        </form>
                    </div>
                    <?php else: ?>
                        <span class="text-secondary">Chỉ xem</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><tr><td colspan="5" class="text-center text-secondary py-4">Không tìm thấy đánh giá phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pages > 1): ?>
    <nav aria-label="Phân trang đánh giá"><ul class="pagination flex-wrap">
        <?php for ($number = 1; $number <= $pages; $number++): $params = $_GET; $params['page'] = $number; ?>
            <li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/admin/reviews') . '?' . http_build_query($params)) ?>"><?= $number ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
