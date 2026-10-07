(() => {
'use strict';
let queue = Promise.resolve();
const updateCsrf = payload => {
if (!payload?.csrf) return;
document.querySelectorAll('input[name="' + payload.csrf.name.replace(/"/g,'\\\"') + '"]').forEach(input => { input.value = payload.csrf.hash; });
};
const updateCount = total => {
if (typeof total === 'undefined') return;
document.querySelectorAll('[data-cart-count]').forEach(node => { node.textContent = String(total); });
};
const request = async (form, overrides = {}) => {
const data = new FormData(form);
Object.entries(overrides).forEach(([key, value]) => data.set(key, String(value)));
const response = await fetch(form.action, {method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},credentials:'same-origin'});
let payload = {};
try { payload = await response.json(); } catch (_) {}
updateCsrf(payload);
if (!response.ok || payload.success === false) throw Object.assign(new Error(payload.message || 'Cart request failed'), {payload});
return payload;
};
const enqueue = task => { queue = queue.catch(() => {}).then(task); return queue; };
const formatMoney = minor => {
const whole = Math.floor(Math.max(0, Number(minor) || 0) / 100);
const cents = String(Math.max(0, Number(minor) || 0) % 100).padStart(2, '0');
return String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + cents + ' ₽';
};
const syncCheckoutTotals = () => {
const totals = document.querySelector('[data-checkout-totals]');
if (!totals) return;
const selected = document.querySelector('input[name="delivery_method_id"]:checked');
const delivery = Number(selected?.dataset.deliveryPriceMinor || 0);
const subtotal = Number(totals.dataset.subtotalMinor || 0);
const deliveryNode = totals.querySelector('[data-checkout-delivery]');
const totalNode = totals.querySelector('[data-checkout-total]');
if (deliveryNode) deliveryNode.textContent = formatMoney(delivery);
if (totalNode) totalNode.textContent = formatMoney(subtotal + delivery);
};
const pulse = row => {
if (!row) return;
row.classList.remove('mcms-cart-stock-adjusted');
void row.offsetWidth;
row.classList.add('mcms-cart-stock-adjusted');
window.setTimeout(() => row.classList.remove('mcms-cart-stock-adjusted'), 1400);
};
const setMessage = (host, value, kind = '') => {
if (!host) return;
host.textContent = value || '';
if (kind) host.dataset.kind = kind; else delete host.dataset.kind;
};
const syncAddForm = form => {
const root = form.closest('[data-product-gallery],[data-cart-product-card]') || form;
const select = root.querySelector('[data-variant-select],[data-cart-variant-select]');
const option = select?.selectedOptions?.[0] || null;
const variant = form.querySelector('[data-cart-variant]');
if (option && variant) variant.value = option.value;
const available = option ? Number(option.dataset.available || 0) : Number(form.dataset.available || 0);
const availability = form.querySelector('[data-cart-available]');
const submit = form.querySelector('[data-cart-submit]');
if (availability) availability.textContent = available > 0 ? availability.dataset.label + ': ' + available : availability.dataset.empty;
if (submit) submit.disabled = available < 1;
};
document.querySelectorAll('[data-cart-add]').forEach(syncAddForm);
document.addEventListener('mcms:stock-updated', event => {
const variantId = String(event.detail?.variantId ?? '');
const available = Number(event.detail?.stock?.available ?? 0);
document.querySelectorAll('[data-cart-variant-select] option[value="' + variantId + '"]').forEach(option => { option.dataset.available = String(available); });
document.querySelectorAll('[data-variant-select] option[value="' + variantId + '"]').forEach(option => { option.dataset.available = String(available); });
document.querySelectorAll('[data-cart-add]').forEach(form => {
const hidden = form.querySelector('[data-cart-variant]');
const root = form.closest('[data-product-gallery],[data-cart-product-card]') || form;
const select = root.querySelector('[data-variant-select],[data-cart-variant-select]');
if (!select && hidden?.value === variantId) form.dataset.available = String(available);
syncAddForm(form);
});
});
document.addEventListener('change', event => {
if (event.target.matches('input[name="delivery_method_id"]')) {
syncCheckoutTotals();
return;
}
if (!event.target.matches('[data-variant-select],[data-cart-variant-select]')) return;
const root = event.target.closest('[data-product-gallery],[data-cart-product-card]');
root?.querySelectorAll('[data-cart-add]').forEach(syncAddForm);
});
syncCheckoutTotals();
document.addEventListener('submit', event => {
const form = event.target.closest('[data-cart-add]');
if (!form) return;
event.preventDefault();
const message = form.querySelector('[data-cart-message]');
const submit = form.querySelector('[data-cart-submit]');
if (submit?.disabled) return;
if (submit) submit.disabled = true;
request(form).then(payload => {
updateCount(payload.total_items);
setMessage(message, payload.message, payload.adjusted ? 'adjusted' : 'success');
const input = form.querySelector('input[name="quantity"]');
if (input) input.value = '1';
}).catch(error => setMessage(message, error.message, 'error')).finally(() => syncAddForm(form));
});
document.addEventListener('click', event => {
const button = event.target.closest('[data-cart-step]');
if (!button) return;
event.preventDefault();
const form = button.closest('[data-cart-update]');
const input = form?.querySelector('input[name="quantity"]');
if (!input) return;
const fallback = Number(input.dataset.savedQuantity || 0);
const current = input.value === '' || !Number.isFinite(Number(input.value)) ? fallback : Number(input.value);
input.value = String(Math.max(0, Math.trunc(current) + Number(button.dataset.cartStep)));
input.dispatchEvent(new Event('change', {bubbles:true}));
});
const scheduleUpdate = (input, immediate) => {
clearTimeout(Number(input.dataset.saveTimer || 0));
if (immediate) { input.closest('[data-cart-update]')?.requestSubmit(); return; }
input.dataset.saveTimer = String(window.setTimeout(() => input.closest('[data-cart-update]')?.requestSubmit(), 350));
};
document.addEventListener('input', event => {
if (event.target.matches('[data-cart-update] input[name="quantity"]')) scheduleUpdate(event.target, false);
});
document.addEventListener('change', event => {
if (event.target.matches('[data-cart-update] input[name="quantity"]')) scheduleUpdate(event.target, true);
});
document.addEventListener('submit', event => {
const form = event.target.closest('[data-cart-update]');
if (!form) return;
event.preventDefault();
const input = form.querySelector('input[name="quantity"]');
clearTimeout(Number(input.dataset.saveTimer || 0));
const requested = input.value;
if (requested === '' || !input.checkValidity() || !/^\\d+$/.test(requested)) { input.value = input.dataset.savedQuantity || '0'; return; }
if (Number(input.dataset.savedQuantity || 0) === Number(requested) && typeof input.dataset.queuedQuantity === 'undefined') return;
if (typeof input.dataset.queuedQuantity !== 'undefined' && Number(input.dataset.queuedQuantity) === Number(requested)) return;
input.dataset.queuedQuantity = requested;
enqueue(async () => {
if (!document.documentElement.contains(form)) return;
const row = form.closest('[data-cart-row]');
row?.classList.add('mcms-cart-pending');
try {
const payload = await request(form, {quantity:requested});
updateCount(payload.total_items);
input.dataset.savedQuantity = String(payload.quantity);
if (Number(input.value) === Number(requested)) input.value = String(payload.quantity);
const itemSubtotal = row?.querySelector('[data-cart-item-subtotal]');
if (itemSubtotal) itemSubtotal.textContent = payload.item?.subtotal_display || '0,00 ₽';
const subtotal = document.querySelector('[data-cart-subtotal]');
if (subtotal) subtotal.textContent = payload.subtotal_display;
const checkoutTotals = document.querySelector('[data-checkout-totals]');
if (checkoutTotals) {
checkoutTotals.dataset.subtotalMinor = String(payload.subtotal_minor || 0);
syncCheckoutTotals();
}
const message = row?.querySelector('[data-cart-stock-message]');
setMessage(message, payload.adjusted ? payload.message : '', payload.adjusted ? 'adjusted' : '');
if (payload.adjusted) pulse(row);
if (Number(payload.quantity) <= 0 && row) { row.remove(); if (!document.querySelector('[data-cart-row]')) window.location.reload(); }
} catch (error) {
if (Number(input.value) === Number(requested)) input.value = input.dataset.savedQuantity || '0';
window.alert(error.message);
} finally {
row?.classList.remove('mcms-cart-pending');
if (input.dataset.queuedQuantity === requested) delete input.dataset.queuedQuantity;
}
});
});
document.addEventListener('submit', event => {
const form = event.target.closest('[data-cart-remove],[data-cart-clear]');
if (!form) return;
event.preventDefault();
enqueue(async () => {
try {
const payload = await request(form);
updateCount(payload.total_items);
if (form.matches('[data-cart-clear]')) { window.location.reload(); return; }
form.closest('[data-cart-row]')?.remove();
const subtotal = document.querySelector('[data-cart-subtotal]');
if (subtotal) subtotal.textContent = payload.subtotal_display;
const checkoutTotals = document.querySelector('[data-checkout-totals]');
if (checkoutTotals) {
checkoutTotals.dataset.subtotalMinor = String(payload.subtotal_minor || 0);
syncCheckoutTotals();
}
if (payload.empty || !document.querySelector('[data-cart-row]')) window.location.reload();
} catch (error) { window.alert(error.message); }
});
});
})();