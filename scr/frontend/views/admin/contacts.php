<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h2 mb-1">Tin nhắn liên hệ</h1><p class="text-secondary mb-0"><?= (int) $total ?> tin nhắn</p></div>
    <form class="d-flex gap-2" method="get" action="<?= e(url('/admin/contacts')) ?>"><label class="visually-hidden" for="contactFilter">Lọc trạng thái</label><select class="form-select" id="contactFilter" name="status"><option value="">Tất cả trạng thái</option><?php foreach (['new' => 'Mới', 'processing' => 'Đang xử lý', 'replied' => 'Đã phản hồi', 'closed' => 'Đã đóng'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-outline-success">Lọc</button></form>
</div>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<section class="card mb-4"><div class="card-body"><h2 class="h5">Thông báo liên hệ mới</h2><form class="row g-2 align-items-end" method="post" action="<?= e(url('/admin/contacts/notification-email')) ?>"><?= csrf_field() ?><div class="col-12 col-md-8"><label class="form-label" for="notificationEmail">Email quản trị nhận thông báo (để trống để tắt)</label><input class="form-control" id="notificationEmail" type="email" name="email" maxlength="190" value="<?= e($notificationEmail) ?>"></div><div class="col-auto"><button class="btn btn-outline-success">Lưu cấu hình</button></div></form></div></section>
<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5">Thông tin cửa hàng</h2>
        <p class="small text-secondary">Các thông tin này được hiển thị trên trang Liên hệ; để trống trường chưa có thông tin xác thực.</p>
        <form class="row g-3" method="post" action="<?= e(url('/admin/contacts/store-info')) ?>">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6"><label class="form-label" for="storeAddress">Địa chỉ</label><input class="form-control" id="storeAddress" name="address" maxlength="500" value="<?= e($storeContact['address']) ?>"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="storePhone">Số điện thoại</label><input class="form-control" id="storePhone" name="phone" maxlength="30" value="<?= e($storeContact['phone']) ?>"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="storeEmail">Email liên hệ</label><input class="form-control" id="storeEmail" type="email" name="email" maxlength="190" value="<?= e($storeContact['email']) ?>"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="storeBusinessHours">Giờ làm việc</label><input class="form-control" id="storeBusinessHours" name="business_hours" maxlength="180" value="<?= e($storeContact['business_hours']) ?>"></div>
            <div class="col-12"><button class="btn btn-outline-success" type="submit">Lưu thông tin cửa hàng</button></div>
        </form>
    </div>
</section>
<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5">Khung chat Tawk.to</h2>
        <p class="small text-secondary">Chỉ nhập ID từ bảng điều khiển Tawk.to. Website tự tạo script embed; không nhập HTML hoặc JavaScript.</p>
        <form class="row g-3" method="post" action="<?= e(url('/admin/contacts/tawk')) ?>">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label class="form-label" for="tawkPropertyId">Property ID</label>
                <input class="form-control" id="tawkPropertyId" name="property_id" inputmode="text" pattern="[A-Fa-f0-9]{24}" maxlength="24" value="<?= e($tawkConfiguration['property_id']) ?>" aria-describedby="tawkPropertyHelp">
                <div class="form-text" id="tawkPropertyHelp">24 ký tự thập lục phân.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="tawkWidgetId">Widget ID</label>
                <input class="form-control" id="tawkWidgetId" name="widget_id" pattern="[A-Za-z0-9]{1,32}" maxlength="32" value="<?= e($tawkConfiguration['widget_id']) ?>" aria-describedby="tawkWidgetHelp">
                <div class="form-text" id="tawkWidgetHelp">Từ 1 đến 32 ký tự chữ hoặc số.</div>
            </div>
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="form-check">
                    <input type="hidden" name="enabled" value="0">
                    <input class="form-check-input" id="tawkEnabled" name="enabled" type="checkbox" value="1" <?= $tawkConfiguration['enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tawkEnabled">Bật khung chat trên trang khách hàng</label>
                </div>
                <button class="btn btn-outline-success" type="submit">Lưu cấu hình chat</button>
            </div>
        </form>
    </div>
</section>
<div class="table-responsive"><table class="table table-hover align-middle bg-white"><thead><tr><th>Thời gian</th><th>Người gửi</th><th>Chủ đề / Nội dung</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody>
    <?php foreach ($contacts as $contact): ?><tr>
        <td class="text-nowrap"><?= e($contact['created_at']) ?></td>
        <td><?= e($contact['name']) ?><div><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></div><?php if ($contact['phone']): ?><div class="small"><?= e($contact['phone']) ?></div><?php endif; ?></td>
        <td><strong><?= e($contact['subject'] ?: 'Không có tiêu đề') ?></strong><details><summary>Xem nội dung</summary><p class="mt-2 mb-0 text-break"><?= nl2br(e($contact['message'])) ?></p></details></td>
        <td><?= e(['new' => 'Mới', 'processing' => 'Đang xử lý', 'replied' => 'Đã phản hồi', 'closed' => 'Đã đóng'][$contact['status']] ?? $contact['status']) ?></td>
        <td><form method="post" action="<?= e(url('/admin/contacts/' . $contact['id'] . '/status')) ?>" class="d-flex gap-2"><?= csrf_field() ?><select class="form-select form-select-sm" name="status" aria-label="Trạng thái liên hệ"><?php foreach (['new' => 'Mới', 'processing' => 'Đang xử lý', 'replied' => 'Đã phản hồi', 'closed' => 'Đã đóng'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $contact['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-success">Lưu</button></form></td>
    </tr><?php endforeach; ?>
    <?php if (!$contacts): ?><tr><td colspan="5" class="text-center text-secondary py-4">Chưa có liên hệ phù hợp.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($pages > 1): ?><nav aria-label="Phân trang liên hệ"><ul class="pagination"><?php for ($number = 1; $number <= $pages; $number++): ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('/admin/contacts?' . http_build_query(['status' => $status, 'page' => $number]))) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
