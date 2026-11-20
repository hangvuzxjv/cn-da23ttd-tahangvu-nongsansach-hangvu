(() => {
    const form = document.querySelector('[data-stock-adjustment-form]');
    const modal = document.getElementById('stockAdjustmentModal');
    if (!form || !modal) {
        return;
    }

    const type = form.querySelector('[data-stock-adjustment-type]');
    const action = form.querySelector('[data-stock-adjustment-action]');
    const direction = form.querySelector('[data-stock-adjustment-direction]');
    const batchId = form.querySelector('[data-stock-adjustment-batch-id]');
    const batchLabel = form.querySelector('[data-stock-adjustment-batch]');
    const unitLabel = form.querySelector('[data-stock-adjustment-unit]');
    const reason = form.querySelector('[data-stock-adjustment-reason]');
    const notes = form.querySelector('[data-stock-adjustment-notes]');

    const updateMovement = () => {
        action.value = type.value === 'issue' ? 'issue' : 'adjust';
        direction.value = type.value === 'increase' ? 'increase' : 'decrease';
    };

    const updateNotesRequirement = () => {
        notes.required = reason.value === 'other';
    };

    type.addEventListener('change', updateMovement);
    reason.addEventListener('change', updateNotesRequirement);
    modal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        form.reset();
        updateMovement();
        updateNotesRequirement();
        batchId.value = trigger.dataset.batchId;
        batchLabel.textContent = `${trigger.dataset.productName} · ${trigger.dataset.batchCode}`;
        unitLabel.textContent = trigger.dataset.unitName || 'đơn vị';
    });

    updateMovement();
    updateNotesRequirement();
})();
