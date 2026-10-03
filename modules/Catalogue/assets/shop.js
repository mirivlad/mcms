(() => {
    'use strict';
    const root = document.querySelector('[data-product-gallery]');
    if (!root) return;
    const select = root.querySelector('[data-variant-select]');
    select?.addEventListener('change', () => {
        const selected = select.selectedOptions[0];
        root.querySelector('[data-variant-price]').textContent = selected.dataset.price;
        root.querySelector('[data-variant-sku]').textContent = selected.dataset.sku;
    });
    const thumbs = [...root.querySelectorAll('[data-photo-thumb]')];
    if (!thumbs.length) return;
    const hero = root.querySelector('[data-photo-hero]');
    const link = root.querySelector('[data-photo-open]');
    const dialog = root.querySelector('[data-photo-dialog]');
    let current = Math.max(0, thumbs.findIndex(thumb => thumb.getAttribute('aria-current') === 'true'));
    function choose(index) {
        current = (index + thumbs.length) % thumbs.length;
        const thumb = thumbs[current];
        hero.src = thumb.dataset.display; link.href = thumb.href;
        thumbs.forEach((item, i) => item.setAttribute('aria-current', String(i === current)));
        root.querySelector('[data-photo-full]').src = thumb.href;
        root.querySelector('[data-photo-counter]').textContent = `${current + 1} / ${thumbs.length}`;
    }
    thumbs.forEach((thumb, i) => thumb.addEventListener('click', event => { event.preventDefault(); choose(i); }));
    link.addEventListener('click', event => { event.preventDefault(); choose(current); dialog.showModal(); });
    root.querySelector('[data-photo-close]').addEventListener('click', () => dialog.close());
    root.querySelector('[data-photo-prev]').addEventListener('click', () => choose(current - 1));
    root.querySelector('[data-photo-next]').addEventListener('click', () => choose(current + 1));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); choose(current + (event.key === 'ArrowLeft' ? -1 : 1)); }
    });
    let startX = null;
    dialog.addEventListener('touchstart', event => { startX = event.touches[0].clientX; }, {passive: true});
    dialog.addEventListener('touchend', event => { if (startX !== null && Math.abs(event.changedTouches[0].clientX - startX) > 50) choose(current + (event.changedTouches[0].clientX < startX ? 1 : -1)); startX = null; }, {passive: true});
    dialog.addEventListener('close', () => link.focus());
})();
