'use strict';

document.documentElement.classList.add('js');

function initStorefront() {
    if (!document.querySelector('#cardapio')) return;

    const cards = [...document.querySelectorAll('[data-product-card]')];
    const sections = [...document.querySelectorAll('[data-category]')];
    const categoryLinks = [...document.querySelectorAll('[data-category-link]')];
    const searchInput = document.querySelector('#menu-search');
    const emptyState = document.querySelector('#search-empty');
    const cartBar = document.querySelector('#cart-bar');
    const cartDialog = document.querySelector('#cart-dialog');
    const cartItems = document.querySelector('#cart-items');
    const cartSubtotal = document.querySelector('#cart-subtotal');
    const checkoutNotice = document.querySelector('#checkout-notice');
    const toast = document.querySelector('#toast');
    const cart = new Map();
    const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    let toastTimer;

    function normalizeText(value) {
        return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }

    function showToast(message) {
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('is-visible');
        toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 1800);
    }

    function updateActiveCategory(activeId) {
        categoryLinks.forEach((link) => {
            const isActive = link.dataset.categoryLink === activeId;
            link.classList.toggle('is-active', isActive);
            if (isActive) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
    }

    function renderCart() {
        cartItems.replaceChildren();
        let count = 0;
        let subtotal = 0;

        cart.forEach((item, key) => {
            count += item.quantity;
            subtotal += item.price * item.quantity;
            const row = document.createElement('article');
            row.className = 'cart-item';

            const title = document.createElement('h3');
            title.textContent = item.name;
            if (item.variantLabel) {
                const variant = document.createElement('small');
                variant.textContent = item.variantLabel;
                title.append(variant);
            }

            const price = document.createElement('strong');
            price.textContent = currency.format(item.price * item.quantity);
            const quantity = document.createElement('div');
            quantity.className = 'quantity';
            const decrease = document.createElement('button');
            decrease.type = 'button';
            decrease.textContent = '−';
            decrease.setAttribute('aria-label', `Diminuir quantidade de ${item.name}`);
            decrease.addEventListener('click', () => changeQuantity(key, -1));
            const amount = document.createElement('strong');
            amount.textContent = String(item.quantity);
            const increase = document.createElement('button');
            increase.type = 'button';
            increase.textContent = '+';
            increase.setAttribute('aria-label', `Aumentar quantidade de ${item.name}`);
            increase.addEventListener('click', () => changeQuantity(key, 1));
            quantity.append(decrease, amount, increase);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-item';
            remove.textContent = 'Remover';
            remove.setAttribute('aria-label', `Remover ${item.name} do pedido`);
            remove.addEventListener('click', () => {
                cart.delete(key);
                renderCart();
                showToast(`${item.name} removido`);
            });
            row.append(title, price, quantity, remove);
            cartItems.append(row);
        });

        document.querySelector('#cart-bar-count').textContent = String(count);
        document.querySelector('#cart-bar-label').textContent = count === 1 ? 'item' : 'itens';
        document.querySelector('#cart-bar-total').textContent = currency.format(subtotal);
        cartSubtotal.textContent = currency.format(subtotal);
        cartBar.hidden = count === 0;
        checkoutNotice.hidden = true;
        if (count === 0 && cartDialog.open) cartDialog.close();
    }

    function changeQuantity(key, difference) {
        const item = cart.get(key);
        if (!item) return;
        item.quantity += difference;
        if (item.quantity <= 0) cart.delete(key);
        renderCart();
    }

    cards.forEach((card) => {
        card.querySelector('[data-add-product]').addEventListener('click', () => {
            const variant = card.querySelector('input[type="radio"]:checked') || card.querySelector('[data-single-variant]');
            const variantId = variant.value;
            const key = `${card.dataset.productId}:${variantId}`;
            const existing = cart.get(key);
            if (existing) existing.quantity += 1;
            else cart.set(key, {
                name: card.dataset.productName,
                variantLabel: variant.dataset.variantLabel,
                price: Number(variant.dataset.price),
                quantity: 1,
            });
            renderCart();
            showToast(`${card.dataset.productName} adicionado`);
        });
    });

    searchInput.addEventListener('input', () => {
        const query = normalizeText(searchInput.value);
        let visibleCards = 0;
        cards.forEach((card) => {
            const visible = normalizeText(card.dataset.searchName).includes(query);
            card.hidden = !visible;
            if (visible) visibleCards += 1;
        });
        sections.forEach((section) => {
            section.hidden = !section.querySelector('[data-product-card]:not([hidden])');
        });
        emptyState.hidden = visibleCards > 0;
    });

    categoryLinks.forEach((link) => link.addEventListener('click', () => updateActiveCategory(link.dataset.categoryLink)));
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (visible) updateActiveCategory(visible.target.id);
        }, { rootMargin: '-20% 0px -65% 0px', threshold: [0, .25, .5] });
        sections.forEach((section) => observer.observe(section));
    }
    updateActiveCategory(sections[0]?.id);

    cartBar.addEventListener('click', () => cartDialog.showModal());
    document.querySelector('#cart-close').addEventListener('click', () => cartDialog.close());
    cartDialog.addEventListener('click', (event) => {
        if (event.target === cartDialog) cartDialog.close();
    });
    document.querySelector('#checkout-button').addEventListener('click', () => {
        checkoutNotice.hidden = false;
        checkoutNotice.focus?.();
    });
}

initStorefront();
