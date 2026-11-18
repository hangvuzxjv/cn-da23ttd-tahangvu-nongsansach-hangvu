<?php
$profileName = trim((string) ($profile['name'] ?? ''));
$profileInitial = $profileName !== ''
    ? mb_strtoupper(mb_substr($profileName, 0, 1, 'UTF-8'), 'UTF-8')
    : '?';
$emailVerified = !empty($profile['email_verified_at']);
$activeAccountTab = 'profile';
?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
        <header class="page-heading">
            <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page">Tài khoản</li></ol></nav>
            <h1 class="h2">Hồ sơ của tôi</h1>
            <p class="text-secondary">Cập nhật thông tin liên hệ và bảo mật tài khoản.</p>
        </header>
        <section class="card profile-summary-card mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="profile-summary-card__avatar" aria-hidden="true"><?= e($profileInitial) ?></span>
                <div class="profile-summary-card__details">
                    <h2 class="h5 mb-1"><?= e($profileName !== '' ? $profileName : 'Tài khoản') ?></h2>
                    <div class="text-secondary"><?= e($profile['email']) ?></div>
                    <span class="badge text-bg-<?= $emailVerified ? 'success' : 'warning' ?> mt-2">
                        <?= $emailVerified ? 'Email đã xác thực' : 'Chưa xác thực' ?>
                    </span>
                </div>
                <?php if (!$emailVerified): ?>
                    <form method="post" action="<?= e(url('/email/xac-minh/gui-lai')) ?>" id="resend-verification-form" class="profile-summary-card__resend">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-success" type="submit">Gửi lại email xác minh</button>
                        <span class="small" id="verification-resend-message" role="status"></span>
                    </form>
                <?php endif; ?>
            </div>
        </section>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg-6 d-flex">
                <section class="card profile-form-card w-100">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5">Thông tin tài khoản</h2>
                        <form method="post" action="<?= e(url('/profile')) ?>" class="d-flex flex-column flex-grow-1">
                            <?= csrf_field() ?>
                            <?php foreach (['name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại'] as $field => $label): ?>
                                <?php $value = old($field, $profile[$field] ?? ''); ?>
                                <div class="mb-3">
                                    <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                                    <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text') ?>" value="<?= e($value) ?>" required>
                                    <?php if (isset($errors[$field])): ?><div class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <button class="btn btn-success align-self-start mt-auto" type="submit">Lưu thông tin</button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-6 d-flex">
                <section class="card profile-form-card w-100">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5">Đổi mật khẩu</h2>
                        <p class="small text-secondary">Mật khẩu mới cần ít nhất 8 ký tự, có chữ và số, tối đa 72 byte.</p>
                        <form method="post" action="<?= e(url('/profile/password')) ?>" class="d-flex flex-column flex-grow-1">
                            <?= csrf_field() ?>
                            <?php foreach ([
                                'current_password' => 'Mật khẩu hiện tại',
                                'new_password' => 'Mật khẩu mới',
                                'password_confirmation' => 'Nhập lại mật khẩu mới',
                            ] as $field => $label): ?>
                                <?php $passwordLabel = [
                                    'current_password' => 'mật khẩu hiện tại',
                                    'new_password' => 'mật khẩu mới',
                                    'password_confirmation' => 'mật khẩu nhập lại',
                                ][$field]; ?>
                                <div class="mb-3">
                                    <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                                    <div class="input-group">
                                        <input class="form-control<?= isset($errors[$field]) ? ' is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="password" autocomplete="<?= $field === 'current_password' ? 'current-password' : 'new-password' ?>" required>
                                        <button class="btn btn-outline-secondary profile-password-toggle" type="button"
                                                aria-label="Hiện <?= e($passwordLabel) ?>" aria-controls="<?= e($field) ?>" aria-pressed="false"
                                                data-password-toggle data-password-label="<?= e($passwordLabel) ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true" data-password-eye><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            <svg viewBox="0 0 24 24" aria-hidden="true" data-password-eye-slash hidden><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle><path d="m3 3 18 18"></path></svg>
                                        </button>
                                    </div>
                                    <?php if (isset($errors[$field])): ?><div class="invalid-feedback d-block"><?= e($errors[$field]) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <button class="btn btn-success align-self-start mt-auto" type="submit">Đổi mật khẩu</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<script src="<?= e(asset('/js/profile-password-toggle.js')) ?>" defer></script>
