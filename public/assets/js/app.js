'use strict';

document.documentElement.classList.add('js');

const CART_KEY = 'saoJorgeCart';
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function loadCart() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(CART_KEY) || '[]');
        if (!Array.isArray(stored)) return [];
        return stored.filter((item) => item && typeof item.key === 'string' && typeof item.name === 'string' && Number.isFinite(item.price) && Number.isInteger(item.quantity))
            .map((item) => ({
                key: item.key,
                id: String(item.id || ''),
                name: item.name,
                variantId: String(item.variantId || ''),
                variantLabel: String(item.variantLabel || ''),
                price: Number(item.price),
                quantity: Math.max(1, item.quantity),
                notes: String(item.notes || '').slice(0, 180),
                image: String(item.image || '').startsWith('/assets/images/') ? item.image : '',
            }));
    } catch {
        return [];
    }
}

function saveCart(cart) {
    window.localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartIndicators(cart);
}

function cartCount(cart) {
    return cart.reduce((total, item) => total + item.quantity, 0);
}

function updateCartIndicators(cart = loadCart()) {
    const count = cartCount(cart);
    document.querySelectorAll('[data-cart-count]').forEach((badge) => {
        badge.textContent = String(count);
        badge.hidden = count === 0;
    });
}

function normalizeText(value) {
    return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function showToast(message) {
    const toast = document.querySelector('[data-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.setTimeout(() => toast.classList.remove('is-visible'), 1800);
}

function initMenu() {
    const menu = document.querySelector('[data-page="menu"]');
    if (!menu) return;
    const cards = [...menu.querySelectorAll('[data-product-row]')];
    const sections = [...menu.querySelectorAll('[data-category]')];
    const links = [...menu.querySelectorAll('[data-category-link]')];
    const search = menu.querySelector('#menu-search');
    const empty = menu.querySelector('#search-empty');

    function setActive(id) {
        links.forEach((link) => {
            const active = link.dataset.categoryLink === id;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
    }

    search.addEventListener('input', () => {
        const query = normalizeText(search.value);
        let visibleCount = 0;
        cards.forEach((card) => {
            const visible = normalizeText(card.dataset.searchName).includes(query);
            card.hidden = !visible;
            if (visible) visibleCount += 1;
        });
        sections.forEach((section) => {
            section.hidden = !section.querySelector('[data-product-row]:not([hidden])');
        });
        empty.hidden = visibleCount > 0;
    });

    links.forEach((link) => link.addEventListener('click', () => setActive(link.dataset.categoryLink)));
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.find((entry) => entry.isIntersecting);
            if (visible) setActive(visible.target.id);
        }, { rootMargin: '-25% 0px -65% 0px' });
        sections.forEach((section) => observer.observe(section));
    }
    setActive(window.location.hash.slice(1) || sections[0]?.id);
}

function initProduct() {
    const form = document.querySelector('[data-product-detail]');
    if (!form) return;
    const output = form.querySelector('[data-quantity]');
    let quantity = 1;

    form.querySelector('[data-quantity-minus]').addEventListener('click', () => {
        quantity = Math.max(1, quantity - 1);
        output.textContent = String(quantity);
    });
    form.querySelector('[data-quantity-plus]').addEventListener('click', () => {
        quantity += 1;
        output.textContent = String(quantity);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const variant = form.querySelector('input[name="variant"]:checked');
        const notes = form.querySelector('[data-notes]').value.trim();
        const key = `${form.dataset.productId}:${variant.value}:${normalizeText(notes)}`;
        const cart = loadCart();
        const existing = cart.find((item) => item.key === key);
        if (existing) existing.quantity += quantity;
        else cart.push({
            key,
            id: form.dataset.productId,
            name: form.dataset.productName,
            variantId: variant.value,
            variantLabel: variant.dataset.label,
            price: Number(variant.dataset.price),
            quantity,
            notes,
            image: form.dataset.productImage,
        });
        saveCart(cart);
        showToast(`${form.dataset.productName} adicionado`);
        window.setTimeout(() => { window.location.href = '/pedido'; }, 250);
    });
}

function createQuantityButton(label, symbol, action) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = symbol;
    button.setAttribute('aria-label', label);
    button.addEventListener('click', action);
    return button;
}

function initOrder() {
    const screen = document.querySelector('[data-page="order"]');
    if (!screen) return;
    const list = screen.querySelector('[data-order-items]');
    const empty = screen.querySelector('[data-empty-order]');
    const subtotal = screen.querySelector('[data-order-subtotal]');
    const total = screen.querySelector('[data-order-total]');
    const checkout = screen.querySelector('[data-checkout]');
    const notice = screen.querySelector('[data-checkout-notice]');
    let cart = loadCart();

    function render() {
        list.replaceChildren();
        cart.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'order-item';
            const image = document.createElement('img');
            image.src = item.image;
            image.alt = '';
            image.width = 62;
            image.height = 62;
            const copy = document.createElement('div');
            copy.className = 'order-item-copy';
            const name = document.createElement('h2');
            name.textContent = item.name;
            const variant = document.createElement('p');
            variant.textContent = item.variantLabel || 'Porção';
            const price = document.createElement('strong');
            price.textContent = currency.format(item.price * item.quantity);
            copy.append(name, variant, price);
            if (item.notes) {
                const notes = document.createElement('small');
                notes.textContent = item.notes;
                copy.append(notes);
            }
            const controls = document.createElement('div');
            controls.className = 'order-item-controls';
            controls.append(
                createQuantityButton(`Diminuir quantidade de ${item.name}`, '−', () => change(item.key, -1)),
                Object.assign(document.createElement('strong'), { textContent: String(item.quantity) }),
                createQuantityButton(`Aumentar quantidade de ${item.name}`, '+', () => change(item.key, 1)),
            );
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-order-item';
            remove.setAttribute('aria-label', `Remover ${item.name} do pedido`);
            remove.innerText = 'Remover';
            remove.addEventListener('click', () => {
                cart = cart.filter((candidate) => candidate.key !== item.key);
                persistAndRender();
                showToast(`${item.name} removido`);
            });
            row.append(image, copy, controls, remove);
            list.append(row);
        });
        const value = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        subtotal.textContent = currency.format(value);
        total.textContent = currency.format(value);
        empty.hidden = cart.length > 0;
        checkout.disabled = cart.length === 0;
        notice.hidden = true;
    }

    function persistAndRender() {
        saveCart(cart);
        render();
    }

    function change(key, difference) {
        const item = cart.find((candidate) => candidate.key === key);
        if (!item) return;
        item.quantity += difference;
        if (item.quantity <= 0) cart = cart.filter((candidate) => candidate.key !== key);
        persistAndRender();
    }

    screen.querySelector('[data-clear-cart]').addEventListener('click', () => {
        cart = [];
        persistAndRender();
        showToast('Pedido limpo');
    });
    checkout.addEventListener('click', () => { notice.hidden = false; });
    render();
}

updateCartIndicators();
initMenu();
initProduct();
initOrder();
