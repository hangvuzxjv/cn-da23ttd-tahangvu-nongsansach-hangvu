(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function submit(form, endpoint) {
        const body = new URLSearchParams(new FormData(form));
        if (!body.has('_token')) body.set('_token', csrf);
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
            body,
            credentials: 'same-origin'
        });
        const result = await readResponse(response);
        if (!response.ok || !result.success) throw new Error(result.message || 'Không thể cập nhật giỏ hàng.');
        return result;
    }

    async function readResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        const body = await response.text();
        let result;
        try {
            result = JSON.parse(body);
        } catch (error) {
            console.error('Phản hồi AJAX không phải JSON.', {
                status: response.status,
                statusText: response.statusText,
                contentType,
                body: body.slice(0, 2000),
                error
            });
            throw new Error('Máy chủ trả về phản hồi không hợp lệ. Vui lòng tải lại trang và thử lại.');
        }
        if (
            !result
            || typeof result !== 'object'
            || typeof result.success !== 'boolean'
            || typeof result.message !== 'string'
            || !result.data
            || typeof result.data !== 'object'
        ) {
            console.error('Phản hồi AJAX không đúng định dạng.', {
                status: response.status,
                statusText: response.statusText,
                contentType,
                body: body.slice(0, 2000),
                result
            });
            throw new Error('Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.');
        }
        return result;
    }

    function updateMiniCart(data) {
        document.querySelectorAll('#miniCartCount').forEach((node) => { node.textContent = data.cart_count; });
        document.querySelectorAll('#miniCartTotal').forEach((node) => { node.textContent = data.formatted_subtotal; });
    }

    document.querySelectorAll('form[data-cart-add]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = form.querySelector('[data-cart-message]');
            try {
                const result = await submit(form, form.dataset.cartAdd);
                updateMiniCart(result.data);
                if (message) {
                    message.className = 'small text-success mt-2';
                    message.textContent = result.message;
                }
            } catch (error) {
                if (message) {
                    message.className = 'small text-danger mt-2';
                    message.textContent = error.message;
                }
            }
        });
    });

    document.querySelectorAll('form[data-cart-update]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const result = await submit(form, form.dataset.cartUpdate);
                updateMiniCart(result.data);
                window.location.reload();
            } catch (error) {
                const message = document.getElementById('cartPageMessage');
                if (message) {
                    message.className = 'alert alert-danger';
                    message.textContent = error.message;
                }
            }
        });
    });

    const addressSelect = document.getElementById('checkoutAddress');
    const newAddress = document.getElementById('newCheckoutAddress');
    const regionSelect = document.getElementById('checkoutRegion');
    const voucherForm = document.getElementById('voucherPreviewForm');
    const quoteTarget = document.getElementById('checkoutQuote');
    const quoteMessage = document.getElementById('voucherPreviewMessage');
    const toggleAddress = () => {
        if (!newAddress) return;
        newAddress.hidden = Boolean(addressSelect?.value);
        newAddress.querySelectorAll('input, select').forEach((field) => {
            field.disabled = newAddress.hidden;
        });
    };
    addressSelect?.addEventListener('change', toggleAddress);
    toggleAddress();

    async function preview() {
        if (!voucherForm || !quoteTarget) return;
        const data = new URLSearchParams(new FormData(voucherForm));
        const selectedRegion = newAddress?.hidden
            ? addressSelect?.selectedOptions[0]?.dataset.region || ''
            : regionSelect?.value || '';
        if (!selectedRegion) return;
        data.set('region_id', selectedRegion);
        const token = csrf;
        data.set('_token', token);
        try {
            const response = await fetch(voucherForm.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
                body: data,
                credentials: 'same-origin'
            });
            const result = await readResponse(response);
            if (!response.ok || !result.success) throw new Error(result.message || 'Không thể tính tổng tiền.');
            const quote = result.data;
            quoteTarget.querySelector('[data-subtotal]').textContent = quote.formatted_subtotal;
            quoteTarget.querySelector('[data-discount]').textContent = quote.formatted_discount;
            quoteTarget.querySelector('[data-shipping]').textContent = quote.formatted_shipping;
            quoteTarget.querySelector('[data-total]').textContent = quote.formatted_total;
            if (quoteMessage) {
                quoteMessage.className = 'small text-success';
                quoteMessage.textContent = 'Đã tính lại tạm tính, giảm giá và phí vận chuyển.';
            }
        } catch (error) {
            if (quoteMessage) {
                quoteMessage.className = 'small text-danger';
                quoteMessage.textContent = error.message;
            }
        }
    }
    voucherForm?.addEventListener('submit', (event) => { event.preventDefault(); preview(); });
    addressSelect?.addEventListener('change', preview);
    regionSelect?.addEventListener('change', preview);
})();
