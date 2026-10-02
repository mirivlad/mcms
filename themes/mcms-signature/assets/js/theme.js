(() => {
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.sig-header details[open]').forEach(menu => { menu.open = false; menu.querySelector('summary').focus(); });
        }
    });
    const dialog = document.getElementById('sig-lightbox');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const image = dialog.querySelector('.sig-lightbox-image');
    const caption = dialog.querySelector('.sig-lightbox-caption');
    const count = dialog.querySelector('.sig-lightbox-count');
    const status = dialog.querySelector('.sig-lightbox-status');
    const previous = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    const original = dialog.querySelector('.sig-lightbox-original');
    let links = [];
    let index = 0;
    let opener = null;
    function show(target) {
        if (target < 0 || target >= links.length) return;
        index = target;
        const link = links[index];
        const thumbnail = link.querySelector('img');
        status.textContent = dialog.dataset.loading;
        image.onload = () => { status.textContent = ''; };
        image.onerror = () => { status.textContent = dialog.dataset.error; };
        image.alt = thumbnail ? thumbnail.alt : '';
        image.src = link.href;
        caption.textContent = link.dataset.caption || '';
        count.textContent = `${index + 1} / ${links.length}`;
        original.href = link.href;
        previous.disabled = index === 0;
        next.disabled = index === links.length - 1;
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-lightbox]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const group = link.closest('[data-gallery]');
        if (!group) return;
        event.preventDefault();
        links = Array.from(group.querySelectorAll('a[data-lightbox]'));
        opener = link;
        dialog.querySelector('#sig-lightbox-title').textContent = group.dataset.galleryTitle;
        show(links.indexOf(link));
        if (!dialog.open) dialog.showModal();
    });
    previous.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); show(index - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); show(index + 1); }
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        image.onload = image.onerror = null;
        if (opener && opener.isConnected) opener.focus();
    });
})();
