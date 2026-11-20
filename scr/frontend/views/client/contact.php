<div class="contact-page">
    <header class="page-heading">
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Liên hệ</li></ol></nav>
        <h1 class="h2 mb-2">Liên hệ Nông Sản Sạch</h1>
        <p class="text-secondary mb-0">Cần tư vấn sản phẩm, đơn hàng hoặc nguồn gốc nông sản? Gửi lời nhắn, cửa hàng sẽ phản hồi sớm.</p>
    </header>
    <div class="row g-3 contact-page__grid">
        <div class="col-12 col-lg-4">
            <section class="card contact-page__info">
                <div class="card-body">
                <h2 class="h5 mb-3">Thông tin cửa hàng</h2>
                <strong><?= e($storeContact['name']) ?></strong>
                <?php $hasStoreInfo = false; ?>
                <?php foreach (['address' => 'Địa chỉ', 'phone' => 'Số điện thoại', 'email' => 'Email', 'business_hours' => 'Giờ làm việc'] as $field => $label): ?>
                    <?php if (trim((string) $storeContact[$field]) !== ''): $hasStoreInfo = true; ?>
                        <div class="small mt-2"><strong><?= e($label) ?>:</strong> <?= e($storeContact[$field]) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$hasStoreInfo): ?><p class="text-secondary mb-0 mt-2">Thông tin địa chỉ và giờ liên hệ chưa được cập nhật. Bạn có thể gửi lời nhắn bên dưới.</p><?php endif; ?>
                </div>
            </section>
            <section class="card contact-page__faq mt-3">
                <div class="card-body">
                    <h2 class="h5">Câu hỏi thường gặp</h2>
                    <p class="text-secondary small mb-3">Tìm câu trả lời về đặt hàng, thanh toán, giao hàng và sản phẩm.</p>
                    <a class="btn btn-outline-success" href="<?= e(url('/cau-hoi-thuong-gap')) ?>">Xem FAQ</a>
                    <?php if ($chatConfigured): ?><p class="text-secondary small mb-0 mt-3">Hoặc mở biểu tượng chat ở góc màn hình để trò chuyện trực tiếp.</p><?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-8">
            <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form class="card contact-page__form" method="post" action="<?= e(url('/lien-he')) ?>" data-submit-once>
                <div class="card-body">
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true"><label for="contactCompany">Để trống trường này</label><input id="contactCompany" name="company" tabindex="-1" autocomplete="off"></div>
                    <h2 class="h5 mb-3">Gửi lời nhắn</h2>
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><label class="form-label" for="contactName">Họ tên *</label><input class="form-control" id="contactName" name="name" maxlength="150" autocomplete="name" required></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="contactEmail">Email *</label><input class="form-control" type="email" id="contactEmail" name="email" maxlength="190" autocomplete="email" required></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="contactPhone">Số điện thoại</label><input class="form-control" id="contactPhone" name="phone" maxlength="30" autocomplete="tel"></div>
                        <div class="col-12 col-md-6"><label class="form-label" for="contactSubject">Chủ đề</label><input class="form-control" id="contactSubject" name="subject" maxlength="200"></div>
                        <div class="col-12"><label class="form-label" for="contactMessage">Nội dung *</label><textarea class="form-control" id="contactMessage" name="message" rows="5" minlength="10" maxlength="5000" required></textarea></div>
                        <div class="col-12"><button class="btn btn-success" type="submit">Gửi liên hệ</button><span class="ms-2 small text-secondary">Không gửi mật khẩu hoặc thông tin thanh toán.</span></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
