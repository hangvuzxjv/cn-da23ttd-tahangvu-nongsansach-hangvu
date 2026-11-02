<div class="row justify-content-center"><div class="col-12 col-lg-8">
    <header class="page-heading"><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Liên hệ</li></ol></nav><h1 class="h2 mb-2">Liên hệ Nông Sản Sạch</h1><p class="text-secondary">Cần tư vấn sản phẩm, đơn hàng hoặc nguồn gốc nông sản? Gửi lời nhắn, cửa hàng sẽ phản hồi sớm.</p></header>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form class="card card-body" method="post" action="<?= e(url('/lien-he')) ?>" data-submit-once>
        <?= csrf_field() ?>
        <div class="visually-hidden" aria-hidden="true"><label for="contactCompany">Để trống trường này</label><input id="contactCompany" name="company" tabindex="-1" autocomplete="off"></div>
        <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label" for="contactName">Họ tên *</label><input class="form-control" id="contactName" name="name" maxlength="150" autocomplete="name" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="contactEmail">Email *</label><input class="form-control" type="email" id="contactEmail" name="email" maxlength="190" autocomplete="email" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="contactPhone">Số điện thoại</label><input class="form-control" id="contactPhone" name="phone" maxlength="30" autocomplete="tel"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="contactSubject">Chủ đề</label><input class="form-control" id="contactSubject" name="subject" maxlength="200"></div>
            <div class="col-12"><label class="form-label" for="contactMessage">Nội dung *</label><textarea class="form-control" id="contactMessage" name="message" rows="6" minlength="10" maxlength="5000" required></textarea></div>
            <div class="col-12"><button class="btn btn-success" type="submit">Gửi liên hệ</button><span class="ms-2 small text-secondary">Không gửi mật khẩu hoặc thông tin thanh toán.</span></div>
        </div>
    </form>
</div></div>
