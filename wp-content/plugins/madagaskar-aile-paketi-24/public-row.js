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
    row.innerHTML = '<div><strong></strong><span>2 Yetişkin + 4 Çocuk · 6 ayrı giriş QR</span></div><div class="mdg-ticket-price"></div><div class="mdg-qty"><button type="button" data-minus aria-label="Azalt">−</button><input type="number" value="0" min="0" max="5" readonly autocomplete="off" inputmode="numeric"><button type="button" data-plus aria-label="Artır">+</button></div>';
    row.querySelector('strong').textContent = offer.name;
    row.querySelector('.mdg-ticket-price').textContent = new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY', maximumFractionDigits: 0}).format(offer.price);
    // Native MDG (deferred script) subsequently owns listeners and the six-person summary.
    box.appendChild(row);
    row.querySelector('[data-plus]').addEventListener('click', function (event) {
        if (Number(row.querySelector('input').value) >= 5) event.stopImmediatePropagation();
    });
}());
