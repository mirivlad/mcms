(() => {
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    const deleteDialog = document.querySelector('[data-delete-category-dialog]');
    document.querySelectorAll('[data-delete-category]').forEach(button => button.addEventListener('click', () => {
        deleteDialog.querySelector('h2').textContent = button.dataset.title;
        deleteDialog.querySelector('form').action = button.dataset.deleteCategory;
        deleteDialog.showModal();
    }));
    const orderForm = document.querySelector('[data-category-order-form]');
    if (orderForm) {
        const container = orderForm.querySelector('[data-category-sort-container]');
        const root = container.querySelector('[data-category-root]');
        const structure = orderForm.querySelector('[data-category-structure]');
        const saveOrder = orderForm.querySelector('[data-category-save-order]');
        const directItems = list => Array.from(list.children).filter(el => el.matches('[data-category-item]'));
        const childrenOf = item => Array.from(item.children).find(el => el.matches('[data-category-children]'));
        let dragging = null;

        const refresh = () => {
            root.querySelectorAll('[data-category-item]').forEach(item => {
                const list = item.parentElement;
                const siblings = directItems(list);
                const index = siblings.indexOf(item);
                for (const button of item.querySelectorAll(':scope > .mcms-category-sort-row [data-category-move]')) {
                    const action = button.dataset.categoryMove;
                    button.disabled = (action === 'up' && index === 0)
                        || (action === 'down' && index === siblings.length - 1)
                        || (action === 'nest' && index === 0)
                        || (action === 'unnest' && list === root);
                }
            });
        };

        const serialize = () => {
            const items = [];
            const walk = (list, parentId = null) => {
                directItems(list).forEach(item => {
                    const id = Number(item.dataset.id);
                    items.push({id, parent_id: parentId});
                    walk(childrenOf(item), id);
                });
            };
            walk(root);
            structure.value = JSON.stringify(items);
        };
        const changed = () => { serialize(); refresh(); saveOrder.disabled = false; };

        container.addEventListener('click', event => {
            const button = event.target.closest('[data-category-move]');
            if (!button || !container.contains(button)) return;
            const item = button.closest('[data-category-item]');
            const list = item.parentElement;
            const siblings = directItems(list);
            const index = siblings.indexOf(item);
            switch (button.dataset.categoryMove) {
                case 'up':
                    if (index === 0) return;
                    list.insertBefore(item, siblings[index - 1]);
                    break;
                case 'down':
                    if (index === siblings.length - 1) return;
                    list.insertBefore(siblings[index + 1], item);
                    break;
                case 'nest':
                    if (index === 0) return;
                    childrenOf(siblings[index - 1]).append(item);
                    break;
                case 'unnest': {
                    const parent = list.closest('[data-category-item]');
                    if (!parent) return;
                    parent.parentElement.insertBefore(item, parent.nextElementSibling);
                    break;
                }
                default: return;
            }
            changed();
            button.focus();
        });
        container.addEventListener('dragstart', event => {
            if (!event.target.matches('[data-category-handle]')) return;
            dragging = event.target.closest('[data-category-item]');
            if (!dragging) return;
            dragging.classList.add('is-sorting');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', dragging.dataset.id);
        });
        container.addEventListener('dragover', event => {
            if (!dragging) return;
            const zone = event.target.closest('[data-category-root-zone]');
            if (zone) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                container.querySelectorAll('.is-drop-target').forEach(el => el.classList.remove('is-drop-target'));
                zone.classList.add('is-drop-target');
                const previous = dragging.parentElement;
                if (zone.dataset.categoryRootZone === 'start') root.prepend(dragging);
                else root.append(dragging);
                if (dragging.parentElement !== previous || zone.dataset.categoryRootZone === 'start' || zone.dataset.categoryRootZone === 'end') changed();
                return;
            }
            const target = event.target.closest('[data-category-item]');
            if (!target || target === dragging || dragging.contains(target)) return;
            const row = target.querySelector(':scope > .mcms-category-sort-row');
            const rect = row.getBoundingClientRect();
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            container.querySelectorAll('.is-drop-target').forEach(el => el.classList.remove('is-drop-target'));
            row.classList.add('is-drop-target');
            const nest = event.clientX > rect.left + Math.min(180, rect.width * .55);
            if (nest) {
                const list = childrenOf(target);
                if (dragging.parentElement !== list || dragging !== list.lastElementChild) {
                    list.append(dragging);
                    changed();
                }
                return;
            }
            const before = event.clientY < rect.top + rect.height / 2;
            const reference = before ? target : target.nextElementSibling;
            if (dragging.parentElement !== target.parentElement || dragging !== reference) {
                target.parentElement.insertBefore(dragging, reference);
                changed();
            }
        });
        container.addEventListener('drop', event => { if (dragging) event.preventDefault(); });
        container.addEventListener('dragend', () => {
            container.querySelectorAll('.is-drop-target').forEach(el => el.classList.remove('is-drop-target'));
            if (dragging) dragging.classList.remove('is-sorting');
            dragging = null;
        });
        serialize();
        refresh();
    }

    const form = document.querySelector('[data-category-form]');
    if (!form) return;
    const type = form.elements.icon_type;
    const color = form.elements.icon_color;
    const icon = form.elements.icon;
    const preview = form.querySelector('[data-icon-preview]');
    const imageInput = form.elements.image;
    const imagePreview = form.querySelector('[data-image-preview]');
    const updateType = () => {
        imageInput.required = type.value === 'image' && imagePreview.dataset.existing !== '1';
    };
    form.querySelectorAll('[name="icon_type"]').forEach(input => input.addEventListener('change', updateType));
    updateType();
    let objectUrl;
    imageInput.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        if (imageInput.files[0]) {
            type.value = 'image';
            updateType();
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
                type.value = 'tabler';
                updateType();
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
