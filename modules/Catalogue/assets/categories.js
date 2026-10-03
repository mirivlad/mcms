(() => {
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    const deleteDialog = document.querySelector('[data-delete-category-dialog]');
    document.querySelectorAll('[data-delete-category]').forEach(button => button.addEventListener('click', () => {
        deleteDialog.querySelector('h2').textContent = button.dataset.title;
        deleteDialog.querySelector('form').action = button.dataset.deleteCategory;
        deleteDialog.showModal();
    }));
    const form = document.querySelector('[data-category-form]');
    if (!form) return;
    const type = form.elements.icon_type;
    const color = form.elements.icon_color;
    const icon = form.elements.icon;
    const preview = form.querySelector('[data-icon-preview]');
    const imageInput = form.elements.image;
    const imagePreview = form.querySelector('[data-image-preview]');
    const updateType = () => {
        form.querySelector('[data-tabler-fields]').hidden = type.value !== 'tabler';
        form.querySelector('[data-image-fields]').hidden = type.value !== 'image';
        imageInput.disabled = type.value !== 'image';
        imageInput.required = type.value === 'image' && imagePreview.dataset.existing !== '1';
    };
    type.addEventListener('change', updateType);
    updateType();
    let objectUrl;
    imageInput.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        if (imageInput.files[0]) {
            objectUrl = URL.createObjectURL(imageInput.files[0]);
            imagePreview.src = objectUrl;
            imagePreview.hidden = false;
        } else {
            imagePreview.hidden = imagePreview.dataset.existing !== '1';
            imagePreview.src = imagePreview.dataset.original || '';
        }
    });
    imagePreview.dataset.original = imagePreview.getAttribute('src');
    const dialog = document.querySelector('[data-icon-picker]');
    const search = dialog.querySelector('[data-icon-search]');
    const grid = dialog.querySelector('[data-icon-grid]');
    const status = dialog.querySelector('[data-icon-status]');
    const prev = dialog.querySelector('[data-icon-prev]');
    const next = dialog.querySelector('[data-icon-next]');
    let icons = null;
    let page = 0;
    const render = () => {
        if (!icons) return;
        const query = search.value.trim().toLowerCase();
        const matches = icons.filter(name => name.includes(query));
        const pages = Math.max(1, Math.ceil(matches.length / 100));
        page = Math.max(0, Math.min(page, pages - 1));
        grid.replaceChildren();
        matches.slice(page * 100, page * 100 + 100).forEach(name => {
            const button = document.createElement('button');
            button.type = 'button';
            button.title = name;
            button.setAttribute('aria-label', name);
            button.setAttribute('aria-pressed', String(name === icon.value));
            const glyph = document.createElement('i');
            glyph.className = 'ti ti-' + name;
            glyph.style.color = color.value;
            glyph.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.textContent = name;
            button.append(glyph, label);
            button.addEventListener('click', () => {
                icon.value = name;
                preview.className = 'ti ti-' + name + ' mcms-category-icon';
                dialog.close();
            });
            grid.append(button);
        });
        status.textContent = matches.length ? String(matches.length) : status.dataset.empty;
        dialog.querySelector('[data-icon-page]').textContent = (page + 1) + ' / ' + pages;
        prev.disabled = page === 0;
        next.disabled = page >= pages - 1;
        grid.scrollTop = 0;
    };
    form.querySelector('[data-open-icon-picker]').addEventListener('click', async () => {
        dialog.showModal();
        search.focus();
        if (!icons) {
            status.textContent = status.dataset.loading;
            try {
                const response = await fetch(dialog.dataset.source);
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (!Array.isArray(data) || !data.every(name => typeof name === 'string' && /^[a-z0-9-]+$/.test(name))) throw new Error();
                icons = data;
            } catch {
                status.textContent = status.dataset.error;
                return;
            }
        }
        render();
    });
    color.addEventListener('input', () => { preview.style.color = color.value; render(); });
    search.addEventListener('input', () => { page = 0; render(); });
    prev.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
})();
