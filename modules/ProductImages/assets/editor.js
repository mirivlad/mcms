(() => {
    'use strict';
    const root = document.querySelector('#product-images-editor');
    if (!root) return;
    const config = JSON.parse(root.dataset.config);
    const t = config.labels;
    const form = root.closest('form');
    const csrf = () => form.querySelector(`input[name="${config.csrfName}"]`);
    const list = root.querySelector('[data-image-list]');
    const status = root.querySelector('[data-image-status]');
    const dialog = root.querySelector('[data-crop-dialog]');
    const canvas = root.querySelector('canvas');
    const context = canvas.getContext('2d');
    const sizeInput = root.querySelector('[data-crop-size]');
    const cropError = root.querySelector('[data-crop-error]');
    let items = config.items;
    let busy = false;
    let dragged = null;
    let replacing = null;
    let cropImage = null;
    let bitmap = null;
    let recipe = null;
    let gesture = null;
    let focusAfterDialog = null;
    const imageUrl = (item, size) => `${config.base}/${item.id}/${size}?v=${item.revision}`;

    function setBusy(value) {
        busy = value;
        root.querySelectorAll('button').forEach(button => { button.disabled = value || button.dataset.atBoundary === 'true'; });
        form.querySelectorAll('[data-product-save]').forEach(button => { button.disabled = value || button.dataset.atBoundary === 'true'; });
        root.setAttribute('aria-busy', String(value));
    }
    function updateToken(data) {
        if (data.csrfHash) csrf().value = data.csrfHash;
    }
    async function action(path, data, onProgress) {
        const body = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            Object.entries(data).forEach(([key, value]) => {
                if (Array.isArray(value)) value.forEach(item => body.append(`${key}[]`, item));
                else body.append(key, value);
            });
        }
        body.append(config.csrfName, csrf().value);
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${config.base}/${path}`);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.timeout = 120000;
            if (onProgress) xhr.upload.onprogress = event => onProgress(event.lengthComputable ? event.loaded / event.total : 0);
            xhr.onload = () => {
                let data;
                try { data = JSON.parse(xhr.responseText); } catch { reject(new Error(t.failed)); return; }
                updateToken(data);
                if (xhr.status >= 200 && xhr.status < 300 && Array.isArray(data.items)) resolve(data.items);
                else reject(new Error(data.error || t.failed));
            };
            xhr.onerror = xhr.ontimeout = () => reject(new Error(t.failed));
            xhr.send(body);
        });
    }
    async function change(path, data, focusId) {
        if (busy) return;
        setBusy(true);
        status.textContent = t.saving;
        status.classList.remove('text-danger');
        try {
            items = await action(path, data);
            render();
            status.textContent = t.saved;
            if (focusId) list.querySelector(`[data-id="${focusId}"] button`)?.focus();
        } catch (error) {
            status.textContent = error.message;
            status.classList.add('text-danger');
        } finally { setBusy(false); }
    }
    function button(label, handler, className = 'btn btn-sm') {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = className;
        element.textContent = label;
        element.addEventListener('click', handler);
        return element;
    }
    function arrange(next, mainId, focusId) {
        return change('arrange', {ids: next.map(item => item.id), main_id: mainId}, focusId);
    }
    function render() {
        list.replaceChildren();
        root.querySelector('[data-image-count]').textContent = `${items.length} / 20`;
        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted'; empty.textContent = t.empty; list.append(empty);
        }
        items.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = `product-image-card${item.main ? ' is-main' : ''}`;
            card.dataset.id = item.id;
            card.setAttribute('role', 'listitem');
            card.draggable = true;
            card.title = t.drag;
            const image = document.createElement('img');
            image.src = imageUrl(item, 'thumb'); image.alt = item.name; image.draggable = false;
            const body = document.createElement('div'); body.className = 'product-image-card-body';
            const main = button(item.main ? `✓ ${t.main}` : t.makeMain, () => arrange(items, item.id, item.id), 'btn btn-sm product-image-main ' + (item.main ? 'btn-primary' : 'btn-outline-secondary'));
            main.setAttribute('aria-pressed', String(item.main));
            const name = document.createElement('div'); name.className = 'product-image-name'; name.textContent = item.name;
            const actions = document.createElement('div'); actions.className = 'product-image-actions';
            const earlier = button(`← ${t.up}`, () => move(index, -1)); earlier.disabled = index === 0; earlier.dataset.atBoundary = String(index === 0);
            const later = button(`${t.down} →`, () => move(index, 1)); later.disabled = index === items.length - 1; later.dataset.atBoundary = String(index === items.length - 1);
            actions.append(button(t.crop, event => openCrop(item, event.currentTarget)),
                button(t.replace, () => { replacing = item.id; root.querySelector('[data-replacement]').click(); }), earlier, later,
                button(t.remove, () => { if (window.confirm(t.removeConfirm)) change(`${item.id}/remove`, {}); }, 'btn btn-sm btn-outline-danger'));
            body.append(main, name, actions); card.append(image, body); list.append(card);
            card.addEventListener('dragstart', event => {
                if (busy) { event.preventDefault(); return; }
                dragged = item.id; event.dataTransfer.setData('text/plain', String(item.id)); event.dataTransfer.effectAllowed = 'move'; card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', () => { dragged = null; card.classList.remove('is-dragging'); });
            card.addEventListener('dragover', event => { if (dragged !== null) event.preventDefault(); });
            card.addEventListener('drop', event => {
                if (dragged === null || busy) return;
                event.preventDefault(); event.stopPropagation();
                const moved = items.find(image => image.id === dragged);
                const next = items.filter(image => image.id !== dragged);
                next.splice(index, 0, moved);
                arrange(next, items.find(image => image.main)?.id || 0, dragged);
                dragged = null;
            });
        });
    }
    function move(index, delta) {
        if (busy || index + delta < 0 || index + delta >= items.length) return;
        const next = [...items];
        [next[index], next[index + delta]] = [next[index + delta], next[index]];
        arrange(next, items.find(item => item.main)?.id || 0, items[index].id);
    }
    async function upload(files, replaceId = null) {
        if (busy || !files.length) return;
        setBusy(true); status.textContent = '';
        const progressArea = root.querySelector('[data-progress]');
        progressArea.replaceChildren();
        for (const file of files) {
            const row = document.createElement('div');
            const label = document.createElement('span'); label.textContent = `${file.name} — ${t.uploading}`;
            const progress = document.createElement('progress'); progress.max = 1; progress.value = 0;
            row.append(label, progress); progressArea.append(row);
            const data = new FormData(); data.append('file', file);
            if (replaceId) data.append('replace_id', replaceId);
            try {
                if (file.size > 10 * 1024 * 1024) throw new Error(t.invalidFile);
                items = await action('upload', data, value => { progress.value = value; });
                progress.value = 1; label.textContent = `${file.name} — ${t.saved}`;
                render(); setBusy(true);
            } catch (error) { label.textContent = `${file.name} — ${error.message}`; row.classList.add('text-danger'); }
        }
        setBusy(false);
    }
    root.querySelector('[data-choose]').addEventListener('click', () => root.querySelector('[data-files]').click());
    root.querySelector('[data-files]').addEventListener('change', event => { upload([...event.target.files]); event.target.value = ''; });
    root.querySelector('[data-replacement]').addEventListener('change', event => { upload([...event.target.files], replacing); event.target.value = ''; });
    const drop = root.querySelector('[data-drop-zone]');
    drop.addEventListener('dragover', event => { event.preventDefault(); if (!busy) drop.classList.add('is-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', event => { event.preventDefault(); drop.classList.remove('is-over'); upload([...event.dataTransfer.files]); });
    form.addEventListener('submit', event => { if (busy || dialog.open) { event.preventDefault(); status.textContent = t.busy; } });
    window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });

    function draw() {
        if (!bitmap || !recipe) return;
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const scale = canvas.width / cropImage.width;
        const x = recipe.x * scale, y = recipe.y * scale, w = recipe.width * scale, h = recipe.height * scale;
        context.fillStyle = 'rgba(0,0,0,.55)';
        context.fillRect(0, 0, canvas.width, y); context.fillRect(0, y + h, canvas.width, canvas.height - y - h);
        context.fillRect(0, y, x, h); context.fillRect(x + w, y, canvas.width - x - w, h);
        context.strokeStyle = '#fff'; context.lineWidth = 2; context.strokeRect(x, y, w, h);
        context.strokeStyle = 'rgba(255,255,255,.5)'; context.lineWidth = 1;
        for (const third of [1 / 3, 2 / 3]) {
            context.beginPath(); context.moveTo(x + w * third, y); context.lineTo(x + w * third, y + h); context.stroke();
            context.beginPath(); context.moveTo(x, y + h * third); context.lineTo(x + w, y + h * third); context.stroke();
        }
        root.querySelectorAll('[data-crop-field]').forEach(input => { input.value = recipe[input.dataset.cropField]; });
        const scaleOut = Math.min(1, Number(sizeInput.value) / Math.max(recipe.width, recipe.height));
        root.querySelector('[data-crop-dimensions]').textContent = `${t.originalSize}: ${cropImage.width}×${cropImage.height} · ${t.selectedSize}: ${Math.round(recipe.width * scaleOut)}×${Math.round(recipe.height * scaleOut)}`;
    }
    async function openCrop(item, opener) {
        if (busy) return;
        cropImage = item; recipe = {...item.recipe}; bitmap = null; focusAfterDialog = opener;
        sizeInput.value = recipe.size; cropError.textContent = ''; dialog.showModal();
        root.querySelector('[data-crop-apply]').disabled = true;
        const image = new Image();
        image.onload = () => {
            if (!dialog.open || cropImage !== item) return;
            bitmap = image; canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
            draw(); root.querySelector('[data-crop-apply]').disabled = false;
        };
        image.onerror = () => { cropError.textContent = t.failed; };
        image.src = imageUrl(item, 'original');
    }
    function point(event) {
        const bounds = canvas.getBoundingClientRect();
        return {x: Math.max(0, Math.min(cropImage.width, Math.round((event.clientX - bounds.left) / bounds.width * cropImage.width))),
            y: Math.max(0, Math.min(cropImage.height, Math.round((event.clientY - bounds.top) / bounds.height * cropImage.height)))};
    }
    canvas.addEventListener('pointerdown', event => {
        if (!bitmap || busy) return;
        const start = point(event);
        const full = recipe.x === 0 && recipe.y === 0 && recipe.width === cropImage.width && recipe.height === cropImage.height;
        const move = !full && start.x > recipe.x && start.x < recipe.x + recipe.width && start.y > recipe.y && start.y < recipe.y + recipe.height;
        gesture = {start, recipe: {...recipe}, move}; canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', event => {
        if (!gesture) return;
        const end = point(event);
        if (gesture.move) {
            recipe.x = Math.max(0, Math.min(cropImage.width - recipe.width, gesture.recipe.x + end.x - gesture.start.x));
            recipe.y = Math.max(0, Math.min(cropImage.height - recipe.height, gesture.recipe.y + end.y - gesture.start.y));
        } else {
            recipe.x = Math.min(gesture.start.x, end.x); recipe.y = Math.min(gesture.start.y, end.y);
            recipe.width = Math.abs(end.x - gesture.start.x); recipe.height = Math.abs(end.y - gesture.start.y);
        }
        draw();
    });
    function endGesture() {
        if (gesture && (recipe.width < 16 || recipe.height < 16)) recipe = gesture.recipe;
        gesture = null; draw();
    }
    canvas.addEventListener('pointerup', endGesture); canvas.addEventListener('pointercancel', endGesture);
    root.querySelector('[data-crop-reset]').addEventListener('click', () => { recipe = {x: 0, y: 0, width: cropImage.width, height: cropImage.height, size: Number(sizeInput.value)}; draw(); });
    root.querySelector('[data-crop-square]').addEventListener('click', () => {
        const side = Math.min(recipe.width, recipe.height);
        recipe.x += Math.floor((recipe.width - side) / 2); recipe.y += Math.floor((recipe.height - side) / 2); recipe.width = recipe.height = side; draw();
    });
    root.querySelectorAll('[data-crop-field]').forEach(input => input.addEventListener('change', () => {
        recipe[input.dataset.cropField] = Number(input.value);
        recipe.width = Math.max(16, Math.min(cropImage.width, recipe.width)); recipe.height = Math.max(16, Math.min(cropImage.height, recipe.height));
        recipe.x = Math.max(0, Math.min(cropImage.width - recipe.width, recipe.x)); recipe.y = Math.max(0, Math.min(cropImage.height - recipe.height, recipe.y)); draw();
    }));
    sizeInput.addEventListener('input', draw);
    root.querySelectorAll('[data-crop-cancel]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => { bitmap = null; focusAfterDialog?.focus(); });
    root.querySelector('[data-crop-apply]').addEventListener('click', async () => {
        if (busy || !bitmap) return;
        if (!sizeInput.reportValidity()) return;
        setBusy(true); cropError.textContent = '';
        try {
            items = await action(`${cropImage.id}/crop`, {...recipe, size: sizeInput.value});
            dialog.close(); render(); status.textContent = t.saved;
            list.querySelector(`[data-id="${cropImage.id}"] button`)?.focus();
        } catch (error) { cropError.textContent = error.message; }
        finally { setBusy(false); }
    });
    render();
})();
