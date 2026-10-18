(() => {
    'use strict';
    const filters = document.getElementById('users-filters');
    let results = document.getElementById('users-results');
    if (!filters || !results) return;
    const loading = document.getElementById('users-loading');
    let controller;
    let timeout;
    const linkUrl = () => {
        const url = new URL(filters.action, window.location.href);
        new FormData(filters).forEach((value, key) => {
            if (typeof value === 'string' && value !== '' && !(key === 'per_page' && value === '25')) url.searchParams.set(key, value);
        });
        return url;
    };
    async function refresh(url) {
        controller?.abort();
        const requestController = new AbortController();
        controller = requestController;
        loading.hidden = false;
        try {
            const response = await fetch(url, { signal: requestController.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error('Request failed');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = html.getElementById('users-results');
            if (!replacement) throw new Error('Missing results');
            results.replaceWith(replacement);
            results = replacement;
            // Events are delegated on document because rows are replaced on each request.
            window.history.replaceState(null, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            if (!requestController.signal.aborted) loading.hidden = true;
        }
    }
    filters.addEventListener('submit', event => {
        event.preventDefault();
        clearTimeout(timeout);
        refresh(linkUrl());
    });
    filters.addEventListener('input', event => {
        if (!(event.target instanceof HTMLInputElement)) return;
        clearTimeout(timeout);
        timeout = setTimeout(() => refresh(linkUrl()), 300);
    });
    filters.addEventListener('change', event => {
        if (!(event.target instanceof HTMLSelectElement)) return;
        clearTimeout(timeout);
        refresh(linkUrl());
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('#users-results nav a');
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            const url = new URL(link.href);
            refresh(url);
        }
    });
    document.addEventListener('change', event => {
        if (!event.target.closest('#users-results')) return;
        const all = document.querySelector('#users-select-all');
        const checkboxes = [...document.querySelectorAll('.user-select:not(:disabled)')];
        if (event.target === all) checkboxes.forEach(cb => cb.checked = all.checked);
        const selected = checkboxes.filter(cb => cb.checked).length;
        if (all) all.checked = checkboxes.length > 0 && selected === checkboxes.length;
        const label = document.getElementById('users-selected');
        if (label) label.textContent = String(selected);
    });
    document.addEventListener('submit', event => {
        if (event.target.id !== 'users-bulk') return;
        const form = event.target;
        const selected = form.querySelectorAll('.user-select:checked');
        if (!selected.length) {
            event.preventDefault();
            return;
        }
        const action = form.querySelector('[name="action"]').value;
        if (!action || !window.confirm(form.dataset.confirm || 'Confirm action?')) event.preventDefault();
    });
})();
