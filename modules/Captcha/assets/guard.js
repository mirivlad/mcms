(() => {
    'use strict';
    if (window.mcmsCaptchaGuardInitialized) return;
    window.mcmsCaptchaGuardInitialized = true;

    const updateCsrf = payload => {
        if (!payload?.csrf?.name || !payload.csrf.hash) return;
        document.querySelectorAll('input[type="hidden"]').forEach(input => {
            if (input.name === payload.csrf.name) input.value = payload.csrf.hash;
        });
    };

    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-captcha-guard]');
        if (!form || form.dataset.captchaRequired !== '1') return;
        if (form.querySelector('input[name="captcha_token"]')) return;
        event.preventDefault();
        if (form.dataset.captchaLoading === '1') return;

        const submitter = event.submitter;
        if (submitter?.name === 'stars') {
            let selected = form.querySelector('input[data-captcha-selected-stars]');
            if (!selected) {
                selected = document.createElement('input');
                selected.type = 'hidden';
                selected.name = 'stars';
                selected.dataset.captchaSelectedStars = '1';
                form.append(selected);
            }
            selected.value = submitter.value;
            form.querySelectorAll('button[name="stars"]').forEach(button => {
                button.removeAttribute('name');
                button.dataset.captchaStar = button.value;
            });
        }

        const slot = form.querySelector('[data-captcha-slot]');
        if (!slot) return;
        form.dataset.captchaLoading = '1';
        slot.textContent = '';
        try {
            const data = new FormData();
            data.set('purpose', form.dataset.captchaGuard);
            data.set('target_type', form.querySelector('input[name="target_type"]').value);
            data.set('target_id', form.querySelector('input[name="target_id"]').value);
            const csrf = Array.from(form.querySelectorAll('input[type="hidden"]'))
                .find(input => input.name === form.dataset.captchaCsrfName);
            if (csrf) data.set(csrf.name, csrf.value);

            const response = await fetch(form.dataset.captchaEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                body: data,
            });
            const payload = await response.json();
            updateCsrf(payload);
            if (!response.ok || !payload.success) throw new Error(payload.message || form.dataset.captchaError);

            if (!payload.required) {
                form.dataset.captchaRequired = '0';
                form.requestSubmit(submitter?.isConnected ? submitter : undefined);
                return;
            }
            slot.innerHTML = payload.html;
            if (form.dataset.captchaGuard === 'ratings.vote') {
                const confirm = document.createElement('button');
                confirm.type = 'submit';
                confirm.className = 'btn btn-primary btn-sm';
                confirm.textContent = form.dataset.captchaConfirm;
                slot.append(confirm);
            } else if (submitter && form.dataset.captchaConfirm) {
                submitter.textContent = form.dataset.captchaConfirm;
            }
            slot.querySelector('input[name="captcha_answer"]')?.focus();
        } catch (error) {
            slot.setAttribute('role', 'alert');
            slot.textContent = error.message || form.dataset.captchaError;
        } finally {
            delete form.dataset.captchaLoading;
        }
    });

    document.addEventListener('click', event => {
        const star = event.target.closest('button[data-captcha-star]');
        if (!star) return;
        const form = star.closest('form[data-captcha-guard="ratings.vote"]');
        const hidden = form?.querySelector('input[data-captcha-selected-stars]');
        if (hidden) {
            hidden.value = star.dataset.captchaStar;
            if (form.querySelector('input[name="captcha_token"]')) {
                event.preventDefault();
                form.querySelector('input[name="captcha_answer"]')?.focus();
            }
        }
    });
})();
