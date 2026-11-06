(function () {
    'use strict';
    var sales = window.MDG_EVENT_SALES || {};
    var offer = (window.MDG_FAMILY24_OFFERS || {})[sales.eventId];
    var box = document.querySelector('[data-mdg-ticket-options]');
    if (!offer || !box || box.querySelector('[data-mdg-family24]')) return;
    var row = document.createElement('div');
    row.className = 'mdg-ticket-option mdg-family22-option';
    row.setAttribute('data-mdg-family24', '1');
    row.setAttribute('data-ticket-code', 'MDG_FAMILY_2_4');
    row.setAttribute('data-ticket-price', String(offer.price));
    row.setAttribute('data-ticket-units', '6');
    row.innerHTML = '<div class="mdg-family24-copy"><div class="mdg-family24-heading"><strong>Aile Paketi 2</strong><span class="mdg-family24-saving" hidden></span></div><span class="mdg-family24-people">2 Yetişkin + 4 Çocuk · 6 ayrı giriş QR</span><span class="mdg-family24-comparison" hidden>Ayrı ayrı: <s class="mdg-family24-regular"></s> → Paket: <b class="mdg-family24-package"></b></span></div><div class="mdg-ticket-price"></div><div class="mdg-qty"><button type="button" data-minus aria-label="Azalt">−</button><input type="number" value="0" min="0" max="5" readonly autocomplete="off" inputmode="numeric"><button type="button" data-plus aria-label="Artır">+</button></div>';
    row.querySelector('strong').textContent = 'Aile Paketi 2';
    function money(value) { return new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY', maximumFractionDigits: Number.isInteger(value) ? 0 : 2}).format(value); }
    row.querySelector('.mdg-ticket-price').textContent = money(offer.price);
    // Display the saving against this event's actual visible child/adult prices.
    // No city-specific saving is hardcoded, and checkout prices are never changed.
    var prices = {};
    Array.prototype.forEach.call(box.querySelectorAll('[data-ticket-code]'), function (ticket) {
        var code = (ticket.getAttribute('data-ticket-code') || '').toUpperCase();
        var role = /^(CHILD|COCUK|C)$/.test(code) ? 'child' : (/^(ADULT|YETISKIN|Y)$/.test(code) ? 'adult' : '');
        var price = Number(ticket.getAttribute('data-ticket-price'));
        if (role && Number.isFinite(price) && price > 0) prices[role] = Math.round(price * 100);
    });
    var packageMinor = Math.round(Number(offer.price) * 100);
    var regularMinor = 4 * prices.child + 2 * prices.adult;
    var savingMinor = regularMinor - packageMinor;
    if (Number.isSafeInteger(savingMinor) && savingMinor > 0) {
        var badge = row.querySelector('.mdg-family24-saving');
        badge.textContent = new Intl.NumberFormat('tr-TR', {maximumFractionDigits: 2}).format(savingMinor / 100) + ' TL kazanç';
        badge.hidden = false;
        row.querySelector('.mdg-family24-regular').textContent = money(regularMinor / 100);
        row.querySelector('.mdg-family24-package').textContent = money(packageMinor / 100);
        row.querySelector('.mdg-family24-comparison').hidden = false;
    }
    // Native MDG (deferred script) subsequently owns listeners and the six-person summary.
    box.appendChild(row);
    box.setAttribute('data-mdg-family24-design', '1');
    function groupFamilies() {
        var family22 = box.querySelector('[data-mdg-family22]');
        if (family22 && family22.nextSibling !== row) box.insertBefore(row, family22.nextSibling);
    }
    groupFamilies();
    // The existing 2+2 decoration moves that card to the top later in the footer.
    // Move the same 2+4 node after it once parsing ends; native listeners survive.
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', groupFamilies, {once: true});
    row.querySelector('[data-plus]').addEventListener('click', function (event) {
        if (Number(row.querySelector('input').value) >= 5) event.stopImmediatePropagation();
    });
}());
