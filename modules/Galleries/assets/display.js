(() => {
    document.querySelectorAll('[data-gallery-slider]').forEach(slider => {
        const slides = Array.from(slider.querySelectorAll('[data-slide]'));
        if (slides.length < 2) return;
        const indicators = Array.from(slider.querySelectorAll('[data-slider-go]'));
        const count = slider.querySelector('[data-slider-count]');
        let index = 0;
        const show = target => {
            index = (target + slides.length) % slides.length;
            slides.forEach((slide, position) => { slide.hidden = position !== index; });
            indicators.forEach((button, position) => {
                if (position === index) button.setAttribute('aria-current', 'true');
                else button.removeAttribute('aria-current');
            });
            count.textContent = slides[index].getAttribute('aria-label');
        };
        slider.querySelector('[data-slider-prev]').addEventListener('click', () => show(index - 1));
        slider.querySelector('[data-slider-next]').addEventListener('click', () => show(index + 1));
        indicators.forEach(button => button.addEventListener('click', () => show(Number(button.dataset.sliderGo))));
        slider.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                show(index + (event.key === 'ArrowRight' ? 1 : -1));
            }
        });
        slider.querySelector('[data-slider-controls]').hidden = false;
        const indicatorGroup = slider.querySelector('[data-slider-indicators]');
        if (indicatorGroup) indicatorGroup.hidden = false;
    });
    document.querySelectorAll('[data-gallery-carousel]').forEach(carousel => {
        const track = carousel.querySelector('[data-carousel-track]');
        const items = Array.from(track.querySelectorAll('.mcms-gallery-card'));
        const controls = carousel.querySelector('[data-carousel-controls]');
        if (items.length < 2 || !controls) return;
        const previous = controls.querySelector('[data-carousel-prev]');
        const next = controls.querySelector('[data-carousel-next]');
        const update = () => {
            const focused = document.activeElement;
            previous.disabled = track.scrollLeft < 2;
            next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
            if (focused === next && next.disabled) {
                (previous.disabled ? carousel.querySelector('.mcms-gallery-collection-heading > a') : previous).focus();
            } else if (focused === previous && previous.disabled) {
                (next.disabled ? carousel.querySelector('.mcms-gallery-collection-heading > a') : next).focus();
            }
        };
        const move = direction => {
            const step = items[1].getBoundingClientRect().left - items[0].getBoundingClientRect().left;
            const target = Math.max(0, Math.min(items.length - 1, Math.round(track.scrollLeft / step) + direction));
            track.scrollTo({left: target * step, behavior: 'instant'});
            update();
        };
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        carousel.addEventListener('keydown', event => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                move(event.key === 'ArrowRight' ? 1 : -1);
            }
        });
        track.addEventListener('scroll', update, {passive: true});
        new ResizeObserver(update).observe(track);
        controls.hidden = false;
        update();
    });
})();
