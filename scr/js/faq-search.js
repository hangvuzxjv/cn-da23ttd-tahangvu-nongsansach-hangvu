(() => {
    const search = document.getElementById('faqSearch');
    const items = Array.from(document.querySelectorAll('[data-faq-item]'));
    const groups = Array.from(document.querySelectorAll('[data-faq-category]'));
    const emptyMessage = document.getElementById('faqNoResults');

    if (!search || !emptyMessage) {
        return;
    }

    const normalize = (value) => String(value)
        .toLocaleLowerCase('vi')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd');

    const filterItems = () => {
        const query = normalize(search.value.trim());
        items.forEach((item) => {
            item.hidden = !normalize(item.textContent).includes(query);
        });
        groups.forEach((group) => {
            group.hidden = !group.querySelector('[data-faq-item]:not([hidden])');
        });
        emptyMessage.hidden = items.some((item) => !item.hidden);
    };

    search.addEventListener('input', filterItems);
})();
