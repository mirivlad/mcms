(() => {
    // Flat lists and card grids share the same event-delegated sorting behavior.
    document.querySelectorAll('[data-sortable-list]').forEach(list => {
        const save = list.closest('form')?.querySelector('[data-save-order]');
        let dragged = null;
        const items = () => Array.from(list.children).filter(el => el.matches('[data-sort-item]'));
        const refresh = () => {
            const rows = items();
            rows.forEach((item, index) => {
                const up = item.querySelector('[data-sort-up]');
                const down = item.querySelector('[data-sort-down]');
                if (up) up.disabled = index === 0;
                if (down) down.disabled = index === rows.length - 1;
            });
        };
        const changed = () => { refresh(); if (save) save.disabled = false; };
        list.addEventListener('click', event => {
            const button = event.target.closest('[data-sort-up], [data-sort-down]');
            if (!button || !list.contains(button)) return;
            const item = button.closest('[data-sort-item]');
            const rows = items();
            const index = rows.indexOf(item);
            if (index < 0) return;
            if (button.hasAttribute('data-sort-up') && index > 0) list.insertBefore(item, rows[index - 1]);
            else if (button.hasAttribute('data-sort-down') && index < rows.length - 1) list.insertBefore(rows[index + 1], item);
            else return;
            changed();
            button.focus();
        });
        list.addEventListener('dragstart', event => {
            if (!event.target.matches('[data-sort-handle]')) return;
            const item = event.target.closest('[data-sort-item]');
            if (!item || item.parentElement !== list) return;
            dragged = item;
            item.classList.add('is-sorting');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'move');
        });
        list.addEventListener('dragover', event => {
            if (!dragged) return;
            const target = event.target.closest('[data-sort-item]');
            if (!target || target === dragged || target.parentElement !== list) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            const rect = target.getBoundingClientRect();
            const origin = dragged.getBoundingClientRect();
            const sameRow = Math.abs(origin.top - rect.top) < rect.height * .45;
            const before = sameRow ? event.clientX < rect.left + rect.width / 2
                                   : event.clientY < rect.top + rect.height / 2;
            const reference = before ? target : target.nextElementSibling;
            if (reference !== dragged) { list.insertBefore(dragged, reference); changed(); }
        });
        list.addEventListener('drop', event => { if (dragged) event.preventDefault(); });
        list.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('is-sorting');
            dragged = null;
        });
        list.addEventListener('sortable:refresh', refresh);
        refresh();
    });
})();
