<script>
(() => {
    const form = document.getElementById('theme-menus-form');
    if (!form) return;
    const endpoint = <?= json_encode(site_url('admin/themes/package/resources'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const changed = <?= json_encode(lang('MCMS.themes.menuOrderChanged'), JSON_HEX_TAG) ?>;
    const failed = <?= json_encode(lang('MCMS.themes.resourceSearchFailed'), JSON_HEX_TAG) ?>;
    const found = <?= json_encode(lang('MCMS.themes.resourceSearchDone'), JSON_HEX_TAG) ?>;
    const rowLabel = <?= json_encode(lang('MCMS.themes.extraAssignment'), JSON_HEX_TAG) ?>;
    const limit = list => list.dataset.limit === '' ? Infinity : Number(list.dataset.limit);
    const refresh = location => {
        const list = location.querySelector('[data-extra-menu-list]');
        const rows = [...list.children];
        location.querySelector('[data-extra-add]').disabled = rows.length >= limit(list);
        rows.forEach((row, index) => {
            row.querySelector('[data-extra-position]').textContent = rowLabel + ' ' + (index + 1);
            row.querySelector('[data-extra-up]').disabled = index === 0;
            row.querySelector('[data-extra-down]').disabled = index === rows.length - 1;
            const scoped = row.querySelector('[data-extra-visibility]').value === 'resource';
            row.querySelector('[data-extra-resource]').hidden = !scoped;
            row.querySelectorAll('[data-extra-provider], [data-extra-target]').forEach(select => {
                select.required = scoped && row.querySelector('[name="extra_source[]"]').value !== '';
            });
        });
    };
    form.querySelectorAll('[data-extra-menu-location]').forEach(refresh);
    form.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('[data-extra-query]')) {
            event.preventDefault();
            event.target.closest('[data-extra-menu-row]').querySelector('[data-extra-search]').click();
        }
    });
    form.addEventListener('change', event => {
        const location = event.target.closest('[data-extra-menu-location]');
        if (!location) return;
        if (event.target.matches('[data-extra-provider]')) {
            event.target.closest('[data-extra-menu-row]').querySelector('[data-extra-target]').replaceChildren(new Option('—', ''));
        }
        refresh(location);
    });
    form.addEventListener('click', async event => {
        const button = event.target.closest('button');
        const location = button?.closest('[data-extra-menu-location]');
        if (!location) return;
        const list = location.querySelector('[data-extra-menu-list]');
        const row = button.closest('[data-extra-menu-row]');
        const status = location.querySelector('[data-extra-status]');
        if (button.matches('[data-extra-add]')) {
            if (list.children.length >= limit(list)) return;
            list.append(location.querySelector('template').content.cloneNode(true));
            refresh(location);
            list.lastElementChild.querySelector('select').focus();
        } else if (button.matches('[data-extra-remove]')) {
            const focus = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            refresh(location);
            (focus?.querySelector('select') || location.querySelector('[data-extra-add]')).focus();
        } else if (button.matches('[data-extra-up]') && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
            refresh(location);
            row.querySelector('[data-extra-down]').focus();
        } else if (button.matches('[data-extra-down]') && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
            refresh(location);
            row.querySelector('[data-extra-up]').focus();
        } else if (button.matches('[data-extra-search]')) {
            const provider = row.querySelector('[data-extra-provider]').value;
            if (!provider) return;
            const query = row.querySelector('[data-extra-query]').value;
            const target = row.querySelector('[data-extra-target]');
            button.disabled = true;
            try {
                const response = await fetch(endpoint + '?' + new URLSearchParams({provider, q: query}), {headers:{Accept:'application/json'}});
                if (!response.ok) throw new Error('Resource search');
                const data = await response.json();
                // A response from an earlier selection must not overwrite the new provider.
                if (row.querySelector('[data-extra-provider]').value !== provider) return;
                const selected = target.value;
                const selectedText = target.selectedOptions[0]?.textContent || selected;
                target.replaceChildren(new Option('—', ''));
                const items = Array.isArray(data.items) ? data.items : [];
                items.forEach(item => target.add(new Option(String(item.label), String(item.id))));
                if (selected && !items.some(item => String(item.id) === selected)) target.add(new Option(selectedText, selected));
                target.value = selected;
                status.textContent = found + ' ' + items.length;
                target.focus();
            } catch (error) {
                status.textContent = failed;
            } finally {
                button.disabled = false;
            }
            return;
        } else return;
        status.textContent = changed;
    });
})();
</script>
