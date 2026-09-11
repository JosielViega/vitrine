(() => {
    'use strict';

    document.documentElement.classList.add('js');

    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const search = document.querySelector('[data-product-search]');
    const cards = Array.from(document.querySelectorAll('[data-product-card]'));
    const empty = document.querySelector('[data-search-empty]');

    if (search && cards.length > 0) {
        search.addEventListener('input', () => {
            const term = normalize(search.value.trim());
            let visible = 0;
            cards.forEach((card) => {
                const matches = normalize(card.dataset.search || '').includes(term);
                card.hidden = !matches;
                visible += matches ? 1 : 0;
            });
            if (empty) empty.hidden = visible !== 0;
        });
    }

    document.querySelectorAll('[data-image-file]').forEach((input) => {
        input.addEventListener('change', () => {
            const name = input.closest('form')?.querySelector('[data-file-name]');
            if (name) name.textContent = input.files?.[0]?.name || 'Nenhum arquivo escolhido';
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
})();
