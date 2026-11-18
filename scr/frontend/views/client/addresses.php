<?php
$oldAddressId = (int) ($formState['address_id'] ?? 0);
$initialFormAction = $oldAddressId > 0
    ? url('/addresses/' . $oldAddressId)
    : url('/addresses');
$initialFormTitle = $oldAddressId > 0 ? 'Sửa địa chỉ' : 'Thêm địa chỉ';
$initialSubmitLabel = $oldAddressId > 0 ? 'Lưu thay đổi' : 'Thêm địa chỉ';
$activeAccountTab = 'addresses';
?>
<div class="client-account-layout">
    <aside class="client-account-layout__sidebar">
        <?php include FRONTEND_PATH . '/views/client/_account-navigation.php'; ?>
    </aside>
    <div class="client-account-layout__content">
<header class="page-heading">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= e(url('/profile')) ?>">Tài khoản</a></li><li class="breadcrumb-item active" aria-current="page">Sổ địa chỉ</li></ol></nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1 class="h2 mb-1">Sổ địa chỉ</h1><p class="text-secondary mb-0">Quản lý địa chỉ nhận hàng đã lưu trong tài khoản.</p></div>
        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#addressFormModal"
                data-address-mode="add" data-address-action="<?= e(url('/addresses')) ?>">+ Thêm địa chỉ</button>
    </div>
</header>
<?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$addresses): ?>
    <div class="empty-state address-empty-state">
        <h2 class="h5">Bạn chưa lưu địa chỉ nào</h2>
        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#addressFormModal"
                data-address-mode="add" data-address-action="<?= e(url('/addresses')) ?>">+ Thêm địa chỉ</button>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($addresses as $address): ?>
            <?php $isDefault = (int) $address['is_default'] === 1; ?>
            <div class="col-12">
                <article class="card address-card h-100">
                    <div class="card-body">
                        <div class="address-card__recipient">
                            <strong><?= e($address['recipient_name']) ?></strong>
                            <?php if ($isDefault): ?><span class="badge text-bg-success">Mặc định</span><?php endif; ?>
                        </div>
                        <div class="address-card__phone"><?= e($address['phone']) ?></div>
                        <div class="address-card__full">
                            <?= e($address['address_line']) ?><?php if (!empty($address['ward'])): ?>, <?= e($address['ward']) ?><?php endif; ?><?php if (!empty($address['region_name'])): ?>, <?= e($address['region_name']) ?><?php endif; ?>
                        </div>
                        <div class="address-card__actions">
                            <?php if (!$isDefault): ?>
                                <form method="post" action="<?= e(url('/addresses/' . (int) $address['id'] . '/default')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-link address-default-action" type="submit">Đặt làm mặc định</button>
                                </form>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-success" type="button"
                                    data-bs-toggle="modal" data-bs-target="#addressFormModal"
                                    data-address-mode="edit"
                                    data-address-action="<?= e(url('/addresses/' . (int) $address['id'])) ?>"
                                    data-recipient-name="<?= e($address['recipient_name']) ?>"
                                    data-phone="<?= e($address['phone']) ?>"
                                    data-region-id="<?= (int) ($address['region_id'] ?? 0) ?>"
                                    data-address-line="<?= e($address['address_line']) ?>"
                                    data-ward="<?= e($address['ward'] ?? '') ?>"
                                    data-is-default="<?= $isDefault ? '1' : '0' ?>">Sửa</button>
                            <form method="post" action="<?= e(url('/addresses/' . (int) $address['id'] . '/delete')) ?>"
                                  data-confirm="Bạn có chắc muốn xóa địa chỉ này?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                            </form>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal fade address-modal" id="addressFormModal" tabindex="-1" aria-labelledby="addressFormTitle"
     aria-hidden="true" data-show-on-load="<?= $errors ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered address-modal__dialog">
        <div class="modal-content">
            <form method="post" action="<?= e($initialFormAction) ?>" data-address-form>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="addressFormTitle" data-address-title><?= e($initialFormTitle) ?></h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="addressRecipientName">Tên người nhận</label>
                        <input class="form-control<?= isset($errors['recipient_name']) ? ' is-invalid' : '' ?>" id="addressRecipientName" name="recipient_name" value="<?= e($formState['recipient_name']) ?>" required>
                        <?php if (isset($errors['recipient_name'])): ?><div class="invalid-feedback"><?= e($errors['recipient_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="addressPhone">Số điện thoại</label>
                        <input class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="addressPhone" name="phone" value="<?= e($formState['phone']) ?>" required>
                        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="addressLine">Số nhà, đường</label>
                        <input class="form-control<?= isset($errors['address_line']) ? ' is-invalid' : '' ?>" id="addressLine" name="address_line" value="<?= e($formState['address_line']) ?>" required>
                        <?php if (isset($errors['address_line'])): ?><div class="invalid-feedback"><?= e($errors['address_line']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="addressWard">Phường/xã (không bắt buộc)</label>
                        <input class="form-control" id="addressWard" name="ward" value="<?= e($formState['ward']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="addressRegion">Tỉnh/vùng</label>
                        <select class="form-select<?= isset($errors['region_id']) ? ' is-invalid' : '' ?>" id="addressRegion" name="region_id" required>
                            <option value="">Chọn tỉnh/vùng</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= (int) $region['id'] ?>" <?= $formState['region_id'] === (string) $region['id'] ? 'selected' : '' ?>><?= e($region['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['region_id'])): ?><div class="invalid-feedback"><?= e($errors['region_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" id="addressIsDefault" name="is_default" type="checkbox" value="1" <?= $formState['is_default'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="addressIsDefault">Đặt làm địa chỉ mặc định</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn btn-success" type="submit" data-address-submit><?= e($initialSubmitLabel) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="<?= e(asset('/js/addresses.js')) ?>" defer></script>
    </div>
</div>
