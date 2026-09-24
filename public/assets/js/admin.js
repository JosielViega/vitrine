(() => {
    'use strict';

    document.documentElement.classList.add('js');

    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const search = document.querySelector('[data-product-search]');
    const cards = Array.from(document.querySelectorAll('[data-product-card]'));
    const empty = document.querySelector('[data-search-empty]');
    const filters = Array.from(document.querySelectorAll('[data-image-filter]'));
    let activeSubcategory = 'all';

    function applyProductFilters() {
        const term = normalize(search?.value.trim() || '');
        let visible = 0;
        cards.forEach((card) => {
            const matchesSearch = normalize(card.dataset.search || '').includes(term);
            const matchesSubcategory = activeSubcategory === 'all' || card.dataset.subcategoryId === activeSubcategory;
            card.hidden = !matchesSearch || !matchesSubcategory;
            visible += card.hidden ? 0 : 1;
        });
        if (empty) empty.hidden = visible !== 0;
    }

    if (search) {
        search.addEventListener('input', applyProductFilters);
    }
    filters.forEach((filter) => filter.addEventListener('click', () => {
        activeSubcategory = filter.dataset.imageFilter || 'all';
        filters.forEach((candidate) => {
            const active = candidate === filter;
            candidate.classList.toggle('is-active', active);
            candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        applyProductFilters();
    }));

    const previewUrls = new Set();
    document.querySelectorAll('[data-image-file]').forEach((input) => {
        let previewUrl = null;
        const card = input.closest('[data-product-card]');
        const image = card?.querySelector('[data-image-preview]');
        const status = card?.querySelector('[data-image-status]');
        const unsaved = card?.querySelector('[data-image-unsaved]');
        const releasePreview = () => {
            if (!previewUrl) return;
            URL.revokeObjectURL(previewUrl);
            previewUrls.delete(previewUrl);
            previewUrl = null;
        };
        input.addEventListener('change', () => {
            const name = input.closest('form')?.querySelector('[data-file-name]');
            const file = input.files?.[0];
            if (name) name.textContent = file?.name || 'Nenhum arquivo escolhido';
            releasePreview();
            if (!image || !status || !unsaved) return;
            if (!file) {
                image.src = image.dataset.originalSrc || image.src;
                status.textContent = status.dataset.originalLabel || '';
                status.className = status.dataset.originalClass || 'status';
                unsaved.hidden = true;
                return;
            }
            previewUrl = URL.createObjectURL(file);
            previewUrls.add(previewUrl);
            image.src = previewUrl;
            status.textContent = 'Não salvo';
            status.className = 'status status-unsaved';
            unsaved.hidden = false;
        });
    });
    window.addEventListener('beforeunload', () => {
        previewUrls.forEach((url) => URL.revokeObjectURL(url));
        previewUrls.clear();
    });

    document.querySelectorAll('[data-home-highlight-slot]').forEach((slot) => {
        const select = slot.querySelector('[data-highlight-select]');
        const preview = slot.querySelector('[data-highlight-preview]');
        const emptyPreview = slot.querySelector('[data-highlight-empty]');
        const unsaved = slot.querySelector('[data-highlight-unsaved]');
        if (!select || !preview || !emptyPreview || !unsaved) return;

        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            const hasProduct = Boolean(option?.value);
            preview.hidden = !hasProduct;
            emptyPreview.hidden = hasProduct;
            if (hasProduct) {
                preview.querySelector('[data-highlight-image]').src = option.dataset.image || '';
                preview.querySelector('[data-highlight-name]').textContent = option.dataset.name || option.textContent;
                preview.querySelector('[data-highlight-context]').textContent = option.dataset.context || '';
                preview.querySelector('[data-highlight-price]').textContent = option.dataset.price || '';
            }
            unsaved.hidden = select.value === select.dataset.originalValue;
        });
    });

    document.querySelectorAll('[data-image-remove]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm('Remover a imagem cadastrada deste produto?')) event.preventDefault();
        });
    });
    document.querySelectorAll('[data-visibility-form]').forEach((form) => {
        const toggle = form.querySelector('[data-visibility-toggle]');
        if (!toggle) return;

        toggle.addEventListener('change', async () => {
            const previous = !toggle.checked;
            const visible = toggle.checked;
            const formData = new FormData(form);
            formData.set('visible', visible ? '1' : '0');
            toggle.disabled = true;
            form.classList.add('is-saving');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true || typeof result.redirect !== 'string') {
                    throw new Error('Visibility update failed');
                }
                window.location.assign(result.redirect);
            } catch (_error) {
                toggle.checked = previous;
                toggle.disabled = false;
                form.classList.remove('is-saving');
                const feedback = document.querySelector('#admin-feedback');
                if (feedback) {
                    feedback.innerHTML = '<div class="flash flash-error" role="alert">Não foi possível atualizar a visibilidade.</div>';
                    feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    });
    document.querySelectorAll('[data-hours-day]').forEach((day) => {
        const toggle = day.querySelector('[data-hours-toggle]');
        const inputs = day.querySelectorAll('[data-hours-input]');
        if (!toggle) return;

        const sync = () => inputs.forEach((input) => { input.disabled = !toggle.checked; });
        toggle.addEventListener('change', sync);
        sync();
    });
})();
