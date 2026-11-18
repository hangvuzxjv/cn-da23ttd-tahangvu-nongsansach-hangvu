(() => {
    const modal = document.getElementById('addressFormModal');
    if (!(modal instanceof HTMLElement)) return;

    const form = modal.querySelector('[data-address-form]');
    const title = modal.querySelector('[data-address-title]');
    const submit = modal.querySelector('[data-address-submit]');
    const recipientName = modal.querySelector('#addressRecipientName');
    const phone = modal.querySelector('#addressPhone');
    const addressLine = modal.querySelector('#addressLine');
    const ward = modal.querySelector('#addressWard');
    const region = modal.querySelector('#addressRegion');
    const isDefault = modal.querySelector('#addressIsDefault');
    if (
        !(form instanceof HTMLFormElement)
        || !(title instanceof HTMLElement)
        || !(submit instanceof HTMLButtonElement)
        || !(recipientName instanceof HTMLInputElement)
        || !(phone instanceof HTMLInputElement)
        || !(addressLine instanceof HTMLInputElement)
        || !(ward instanceof HTMLInputElement)
        || !(region instanceof HTMLSelectElement)
        || !(isDefault instanceof HTMLInputElement)
    ) return;

    document.querySelectorAll('[data-bs-target="#addressFormModal"]').forEach((trigger) => {
        if (!(trigger instanceof HTMLButtonElement)) return;
        trigger.addEventListener('click', () => {
            const editing = trigger.dataset.addressMode === 'edit';
            form.action = trigger.dataset.addressAction || form.action;
            title.textContent = editing ? 'Sửa địa chỉ' : 'Thêm địa chỉ';
            submit.textContent = editing ? 'Lưu thay đổi' : 'Thêm địa chỉ';

            recipientName.value = editing ? trigger.dataset.recipientName || '' : '';
            phone.value = editing ? trigger.dataset.phone || '' : '';
            addressLine.value = editing ? trigger.dataset.addressLine || '' : '';
            ward.value = editing ? trigger.dataset.ward || '' : '';
            region.value = editing ? trigger.dataset.regionId || '' : '';
            isDefault.checked = editing && trigger.dataset.isDefault === '1';
        });
    });

    if (modal.dataset.showOnLoad === 'true') {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
})();
