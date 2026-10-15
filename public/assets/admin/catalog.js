(() => {
    const refresh = document.querySelector('[data-catalog-refresh]');
    if (!refresh) return;
    fetch(refresh.dataset.url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
        .then(response => response.ok ? response.json() : Promise.reject(new Error('Catalog check failed')))
        .then(result => {
            const notice = document.querySelector('[data-catalog-notice]');
            if (notice) {
                notice.classList.toggle('d-none', !result.updates);
                notice.querySelector('[data-catalog-count]').textContent = String(result.updates || 0);
            }
            const status = document.querySelector('[data-catalog-status]');
            if (status && result.error) {
                status.textContent = result.error;
                status.classList.remove('d-none');
            }
        })
        .catch(() => { /* Manual refresh and the CLI remain available. */ });
})();
