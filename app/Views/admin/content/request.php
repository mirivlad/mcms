<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-content-preview]').closest('form');
    const csrfName = <?= json_encode(csrf_token()) ?>;
    let queue = Promise.resolve();
    let pending = 0;
    let submitting = false;
    let awaitingSubmit = false;
    form.mcmsRequest = (url, body) => {
        ++pending;
        const request = queue.then(async () => {
            body.set(csrfName, form.elements.namedItem(csrfName).value);
            const response = await fetch(url, {method:'POST', body, credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}});
            const data = await response.json();
            if (data.csrfHash) form.elements.namedItem(csrfName).value = data.csrfHash;
            if (!response.ok) throw new Error(data.error || <?= json_encode(lang('MCMS.content.saveFailed')) ?>);
            return data;
        });
        queue = request.catch(() => {}).finally(() => --pending);
        return request;
    };
    form.addEventListener('submit', event => {
        if (submitting) return;
        form.dispatchEvent(new Event('mcms:submit'));
        if (!pending) return;
        event.preventDefault();
        if (awaitingSubmit) return;
        awaitingSubmit = true;
        const submitter = event.submitter;
        queue.then(() => {
            submitting = true;
            form.requestSubmit(submitter);
        });
    });
});
</script>
