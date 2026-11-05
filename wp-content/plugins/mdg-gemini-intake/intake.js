(function () {
    'use strict';
    var root = document.getElementById('mdg-wa'), config = window.mdgGeminiIntake;
    if (!root || !config) return;
    var url;
    try {
        url = new URL(config.url);
        if (url.origin !== location.origin || url.origin !== 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com' || url.pathname !== '/wp-json/mdg-gemini-intake/v1/parse' || url.search || url.hash || url.username || url.password) return;
    } catch (_) { return; }
    var box = document.createElement('section');
    var label = document.createElement('label'); label.textContent = 'Nasıl bir planınız var?';
    var input = document.createElement('textarea'); input.maxLength = 600; input.rows = 3;
    input.placeholder = 'Örnek: İzmir’de iki yetişkin, üç çocuk geleceğiz.'; label.appendChild(input);
    var note = document.createElement('p'); note.className = 'mdg-wa-note';
    note.textContent = 'Bu metin Google Gemini’ye gönderilir. Telefon, sipariş veya ödeme bilgisi yazmayın. Aktarılan bilgileri kontrol ederek seansınızı kendiniz seçin.';
    var button = document.createElement('button'); button.type = 'button'; button.className = 'mdg-wa-primary'; button.textContent = 'Gemini ile forma aktar';
    var status = document.createElement('p'); status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
    box.append(label, note, button, status); root.querySelector('header').after(box);
    var busy = false, revision = 0;
    ['city', 'session', 'adults', 'children', 'infants'].forEach(function (key) {
        var field = document.getElementById('mdg-wa-' + key);
        ['input', 'change'].forEach(function (event) { field.addEventListener(event, function () { revision++; }); });
    });
    input.addEventListener('input', function () { revision++; });
    function snapshot() { return ['city', 'session', 'adults', 'children', 'infants'].map(function (key) { return document.getElementById('mdg-wa-' + key).value; }).concat(input.value).join('|'); }
    button.addEventListener('click', async function () {
        if (busy || input.value.trim().length < 3) return;
        var before = snapshot(), requestRevision = revision; busy = true; button.disabled = true; status.textContent = 'Bilgileriniz forma hazırlanıyor…';
        try {
            var response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce}, body: JSON.stringify({text: input.value})});
            var data = await response.json();
            if (requestRevision !== revision || before !== snapshot()) { status.textContent = 'Bilgiler değiştiği için önceki yanıt uygulanmadı.'; return; }
            if (!response.ok || data.requires_confirmation !== true || !data.proposal) throw new Error();
            var p = data.proposal, city = document.getElementById('mdg-wa-city');
            // Validate the whole response before changing any form field.
            ['adults', 'children', 'infants'].forEach(function (key) { if (p[key] !== null && (!Number.isInteger(p[key]) || p[key] < (key === 'adults' ? 1 : 0) || p[key] > 20)) throw new Error(); });
            if (p.city_key !== null && !Array.from(city.options).some(function (o) { return o.value === p.city_key && o.value; })) throw new Error();
            city.value = p.city_key === null ? '' : p.city_key; city.dispatchEvent(new Event('change', {bubbles: true}));
            ['adults', 'children', 'infants'].forEach(function (key) {
                var field = document.getElementById('mdg-wa-' + key); field.value = p[key] === null ? '' : String(p[key]); field.dispatchEvent(new Event('input', {bubbles: true}));
            });
            status.textContent = data.incomplete ? 'Bazı bilgiler eksik. Şehri ve kişi sayılarını tamamlayıp seansınızı seçin.' : 'Bilgiler forma aktarıldı. Şehri ve kişi sayılarını kontrol edip seansınızı seçin.';
        } catch (_) { status.textContent = 'Metin şu anda aktarılamadı. Aşağıdaki formu kullanarak devam edebilirsiniz.'; }
        finally { busy = false; button.disabled = false; }
    });
})();
