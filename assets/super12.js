/* Load synchronously before the deferred public-event.js. */
(function () {
    'use strict';

    const sales = window.MDG_EVENT_SALES;
    const offers = window.MDG_SUPER12_OFFERS;
    const preview = window.MDG_SUPER12_PREVIEW === true;
    const code = 'MDG_SUPER12';
    const units = 12;
    const box = document.querySelector('#bilet-secimi [data-mdg-ticket-options]');
    const picker = document.querySelector('#bilet-secimi [data-mdg-session-picker]');
    const checkout = document.querySelector('#bilet-secimi [data-mdg-checkout-preview]');
    const sessionSelector = '.mdg-session-option[data-session-id][data-session-time]';
    const numeral = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 });
    const id = (v) => /^(?:[1-9]\d*)$/.test(String(v)) && Number.isSafeInteger(Number(v)) ? String(v) : null;
    const own = (o, k) => o && typeof o === 'object' && Object.prototype.hasOwnProperty.call(o, k) ? o[k] : null;
    const eventId = sales && id(sales.eventId);

    if (!eventId || !box || !picker || !checkout ||
        box.querySelector('[data-mdg-super12], [data-ticket-code="MDG_SUPER12"]')) return;
    if (!preview && (document.readyState !== 'loading' || sales.enabled !== true ||
        sales.mode !== 'live' || sales.action !== 'mdg_live_add_to_cart')) return;

    function offerFor(button) {
        if (!button || button.disabled || button.getAttribute('aria-disabled') === 'true' ||
            button.dataset.msClosed === '1' || !button.dataset.sessionTime) return null;
        const sessionId = id(button.dataset.sessionId);
        const offer = sessionId && own(own(offers, eventId), sessionId);
        if (!offer || ![225000, 250000].includes(offer.priceCents) ||
            !Number.isInteger(offer.maxBundles) || offer.maxBundles < 1 || offer.maxBundles > 20) return null;
        return { sessionId, priceCents: offer.priceCents, maxBundles: offer.maxBundles };
    }

    const buttons = Array.from(picker.querySelectorAll(sessionSelector));
    const fallbackOffer = buttons.map(offerFor).find(Boolean);
    if (!fallbackOffer) return;

    const row = document.createElement('div');
    row.className = preview ? 'mdg-super12-option mdg-super12-preview' : 'mdg-ticket-option mdg-super12-option';
    row.setAttribute('data-mdg-super12', '1');
    row.hidden = true;
    row.innerHTML = '<div class="mdg-super12-copy"><span class="mdg-super12-badge">YENİ • GRUP FIRSATI</span>' +
        '<strong class="mdg-super12-title">Süper 12 – Arkadaşını Kap Gel!</strong>' +
        '<span class="mdg-super12-people">4 yetişkin + 8 çocuk</span>' +
        '<span class="mdg-super12-detail">12 kişilik süper grup paketi</span>' +
        '<span class="mdg-super12-detail">Arkadaşlarını topla, eğlenceyi 12\'ye katla!</span>' +
        '<span class="mdg-super12-detail">12 ayrı giriş QR kodu · Yalnızca sitede</span></div>' +
        '<div class="mdg-super12-price mdg-ticket-price"></div>';

    if (preview) {
        const tag = document.createElement('span');
        tag.className = 'mdg-super12-preview-note';
        tag.textContent = 'Önizleme · Satış kapalı';
        row.appendChild(tag);
    } else {
        row.dataset.ticketCode = code;
        row.dataset.ticketUnits = String(units);
        // Native MDG snapshots the row once; its price must always be numeric, even while disabled.
        row.dataset.ticketPrice = (fallbackOffer.priceCents / 100).toFixed(2);
        const quantity = document.createElement('div');
        quantity.className = 'mdg-qty mdg-super12-qty';
        quantity.innerHTML = '<button type="button" data-minus aria-label="Süper 12 paketini azalt">−</button>' +
            '<input type="number" value="0" min="0" max="0" readonly autocomplete="off" inputmode="numeric" aria-label="Süper 12 paket adedi">' +
            '<button type="button" data-plus aria-label="Süper 12 paketini artır">+</button>';
        row.appendChild(quantity);
        const warning = document.createElement('p');
        warning.className = 'mdg-super12-mixed-warning';
        warning.setAttribute('role', 'alert');
        warning.tabIndex = -1;
        warning.hidden = true;
        row.appendChild(warning);
    }

    const priceEl = row.querySelector('.mdg-super12-price');
    const input = row.querySelector('input');
    const minus = row.querySelector('[data-minus]');
    const plus = row.querySelector('[data-plus]');
    const warning = row.querySelector('.mdg-super12-mixed-warning');
    const mixedMessage = 'Süper 12 ayrı bir sipariş olarak alınır. Diğer biletleri veya Süper 12 adedini sıfırlayıp tekrar deneyin.';
    let current = null;
    let original = null;
    let legacySettled = document.readyState !== 'loading';

    function group() {
        // Register the new row before native MDG snapshots listeners, but wait for
        // the legacy DOMContentLoaded grouping before remembering the old order.
        if (!legacySettled) {
            if (row.parentNode !== box) box.appendChild(row);
            return;
        }
        const children = Array.from(box.children);
        const nativeFamily = (codes, units) => children.find(node =>
            codes.includes((node.getAttribute('data-ticket-code') || '').toUpperCase()) &&
            node.getAttribute('data-ticket-units') === units);
        // A native package can coexist with a CSS-hidden duplicate virtual row
        // (Antalya); keep the duplicate untouched and position the actual cards.
        const family22 = nativeFamily(['FAMILY_2_2', 'AILE_2_2', 'FAMILY22', 'AILE22'], '4') ||
            children.find(node => node.hasAttribute('data-mdg-family22') ||
                node.getAttribute('data-ticket-code') === 'MDG_FAMILY_2_2');
        const family24 = nativeFamily(['FAMILY_2_4'], '6') ||
            children.find(node => node.hasAttribute('data-mdg-family24') ||
                node.getAttribute('data-ticket-code') === 'MDG_FAMILY_2_4');
        const families = [family22, family24].filter(Boolean);
        if (!current) {
            if (original) {
                const legacy = original.families;
                // Restore only old family nodes, not the rest of the picker. Skip nodes removed by the host.
                for (let i = original.nodes.length - 1; i >= 0; i--) {
                    const node = original.nodes[i];
                    if (!legacy.has(node) || node.parentNode !== box) continue;
                    const anchor = original.nodes.slice(i + 1).find(next => next.parentNode === box) || null;
                    if (node.nextElementSibling !== anchor) box.insertBefore(node, anchor);
                }
                original = null;
            }
            // Keep the hidden row in the native picker so its one-time initialization registers the listeners.
            if (row.parentNode !== box || row.nextElementSibling) box.appendChild(row);
            return;
        }

        const individual = children.find(node => /^(?:CHILD|ADULT|COCUK|YETISKIN|C|Y)$/.test((node.getAttribute('data-ticket-code') || '').toUpperCase()));
        if (!individual) {
            const last = families[families.length - 1];
            if (last ? last.nextElementSibling !== row : row.parentNode !== box) {
                box.insertBefore(row, last ? last.nextElementSibling : null);
            }
            return;
        }
        if (!original || families.some(node => !original.families.has(node))) {
            original = { nodes: children.filter(node => node !== row), families: new Set(families) };
        }
        // Move the actual nodes as one block, preserving prices and all attached listeners.
        let anchor = individual;
        for (const node of [...families, row].reverse()) {
            if (node.parentNode !== box || node.nextElementSibling !== anchor) box.insertBefore(node, anchor);
            anchor = node;
        }
    }

    const selected = () => picker.querySelector(sessionSelector + '.is-selected');

    function quantityState() {
        if (!input) return;
        const qty = Number(input.value);
        minus.disabled = !current || qty < 1;
        plus.disabled = !current || qty >= current.maxBundles;
    }

    function sync(button) {
        const offer = offerFor(button);
        const changed = !current || !offer || offer.sessionId !== current.sessionId ||
            offer.priceCents !== current.priceCents || offer.maxBundles !== current.maxBundles;
        current = offer;
        row.hidden = !offer;
        if (input) {
            if (changed) input.value = '0';
            input.max = String(offer ? offer.maxBundles : 0);
            row.dataset.ticketPrice = ((offer || fallbackOffer).priceCents / 100).toFixed(2);
            quantityState();
            if (changed) hideWarning();
        }
        priceEl.textContent = offer ? numeral.format(offer.priceCents / 100) + ' TL' : '';
        group();
    }

    sync(selected());
    if (!legacySettled) document.addEventListener('DOMContentLoaded', () => {
        legacySettled = true;
        group();
    }, { once: true });
    if (typeof MutationObserver === 'function') {
        // Other family cards can move after parsing. Move only this same row so native listeners survive.
        new MutationObserver(group).observe(box, { childList: true });
    }

    // Native resetSelections runs from the same pageshow event and resets to the first session.
    // Capture it first so MDG's immediate recalculation sees the right price/quantity.
    window.addEventListener('pageshow', () => {
        sync(buttons[0]);
        queueMicrotask(quantityState);
    }, true);
    picker.addEventListener('click', (event) => {
        const button = event.target.closest && event.target.closest(sessionSelector);
        if (button && picker.contains(button) && !button.disabled) sync(button);
    }, true);
    if (preview) return;

    function hasMixedSelection() {
        return Number(input.value) > 0 && Array.from(box.querySelectorAll('[data-ticket-code]')).some(ticket =>
            ticket !== row && Number(ticket.querySelector('input')?.value) > 0);
    }

    function hideWarning() {
        warning.hidden = true;
        warning.textContent = '';
    }

    box.addEventListener('click', () => {
        if (!warning.hidden) queueMicrotask(() => { if (!hasMixedSelection()) hideWarning(); });
    });

    function guardQuantity(event, adding) {
        const offer = offerFor(selected());
        const qty = Number(input.value);
        if (!offer || !current || offer.sessionId !== current.sessionId ||
            offer.priceCents !== current.priceCents || offer.maxBundles !== current.maxBundles ||
            !Number.isInteger(qty) || qty < 0 || qty > current.maxBundles ||
            (adding ? qty >= current.maxBundles : qty === 0)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        // Native listener updates the input and summary synchronously after capture.
        queueMicrotask(quantityState);
    }

    minus.addEventListener('click', (event) => guardQuantity(event, false), true);
    plus.addEventListener('click', (event) => guardQuantity(event, true), true);
    checkout.addEventListener('click', (event) => {
        const qty = Number(input.value);
        if (qty === 0) return; // Never interfere with an unrelated native order.
        const offer = offerFor(selected());
        if (offer && current && offer.sessionId === current.sessionId &&
            offer.priceCents === current.priceCents && offer.maxBundles === current.maxBundles &&
            Number.isInteger(qty) && qty > 0 && qty <= offer.maxBundles) {
            if (!hasMixedSelection()) return;
            warning.hidden = false;
            warning.textContent = mixedMessage;
            warning.focus();
        }
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
}());
