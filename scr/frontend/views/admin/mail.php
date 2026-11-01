<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h2 mb-1">Email và hộp thư chờ</h1>
        <p class="text-muted mb-0">Địa chỉ nhận thư kiểm tra: <?= e(MailService::maskedRecipient($adminEmail)) ?></p>
    </div>
    <?php if (!empty($loadError)): ?><div class="alert alert-danger"><?= e($loadError) ?></div><?php endif; ?>
    <button class="btn btn-outline-success" type="button" id="mail-test-button" data-url="<?= e(url('/admin/mail/test')) ?>">Gửi thư thử</button>
</div>
<div class="alert alert-info">
    <div>Kênh đang chọn: <strong><?= e($driver) ?></strong></div>
    <div class="small mt-1">
        From address <?= $fromConfigured ? 'đã cấu hình' : 'chưa cấu hình' ?> ·
        SMTP <?= $smtpConfigured ? 'đã cấu hình' : 'chưa cấu hình' ?> ·
        Brevo <?= $brevoConfigured ? 'đã cấu hình' : 'chưa cấu hình' ?>.
        Giá trị bí mật chỉ được đọc từ config.php.
    </div>
</div>
<div id="mail-admin-message" class="alert d-none" role="status"></div>
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="post" action="<?= e(url('/admin/mail/driver')) ?>" id="mail-driver-form">
            <?= csrf_field() ?>
            <div class="col-sm-6 col-lg-4">
                <label class="form-label" for="mail-driver">Kiểu gửi</label>
                <select class="form-select" id="mail-driver" name="driver">
                    <?php foreach (['smtp' => 'SMTP (PHPMailer)', 'brevo' => 'Brevo API (HTTPS)', 'outbox' => 'Chỉ lưu hộp thư chờ'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $driver === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-success" type="submit">Lưu kiểu gửi</button></div>
        </form>
    </div>
</div>
<form class="mb-3" method="get" action="<?= e(url('/admin/mail')) ?>">
    <label class="visually-hidden" for="mail-status">Lọc trạng thái email</label>
    <select class="form-select w-auto d-inline-block" id="mail-status" name="status">
        <option value="">Tất cả thư</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Đang chờ / lỗi</option>
        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Đã gửi</option>
    </select>
    <button class="btn btn-outline-secondary" type="submit">Lọc</button>
</form>
<div class="table-responsive">
    <table class="table table-striped align-middle bg-white">
        <thead><tr><th>Ngày tạo</th><th>Người nhận</th><th>Tiêu đề</th><th>Kênh</th><th>Trạng thái</th><th>Lần thử</th><th>Lỗi</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($messages as $message): ?>
            <tr>
                <td><?= e($message['created_at']) ?></td>
                <td><?= e($message['masked_recipient']) ?></td>
                <td><?= e($message['subject']) ?></td>
                <td><?= e($message['driver']) ?></td>
                <td><?= $message['status'] === 'sent' ? 'Đã gửi' : 'Đang chờ' ?></td>
                <td><?= (int) $message['attempts'] ?></td>
                <td><?= e($message['last_error'] ?? '') ?></td>
                <td>
                    <?php if ($message['status'] === 'pending'): ?>
                        <button class="btn btn-sm btn-outline-primary mail-resend-button" type="button"
                                data-url="<?= e(url('/admin/mail/' . (int) $message['id'] . '/resend')) ?>">Gửi lại</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($messages === []): ?><tr><td colspan="8" class="text-center text-muted">Chưa có email trong nhật ký.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
