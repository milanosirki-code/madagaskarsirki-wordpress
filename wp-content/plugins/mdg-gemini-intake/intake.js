(function () {
    'use strict';
    var root = document.getElementById('mdg-wa'), config = window.mdgGeminiIntake;
    if (!root || !config) return;
    var url;
    try {
        url = new URL(config.url);
        if (url.origin !== location.origin || url.origin !== 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com' || url.pathname !== '/wp-json/mdg-gemini-intake/v1/parse' || url.search || url.hash || url.username || url.password) return;
    } catch (_) { return; }
    var box = document.createElement('section'); box.className = 'mdg-wa-intake'; box.setAttribute('aria-label', 'Gemini ile planınızı forma aktarın');
    var label = document.createElement('label'); label.textContent = 'Nasıl bir planınız var?';
    var input = document.createElement('textarea'); input.id = 'mdg-wa-intake-text'; input.maxLength = 600; input.rows = 3;
    input.setAttribute('aria-describedby', 'mdg-wa-intake-note');
    input.placeholder = 'Örnek: İzmir’de iki yetişkin, üç çocuk geleceğiz.'; label.appendChild(input);
    var note = document.createElement('p'); note.className = 'mdg-wa-note'; note.id = 'mdg-wa-intake-note';
    note.textContent = 'Bu metin Google Gemini’ye gönderilir. Telefon, sipariş veya ödeme bilgisi yazmayın. Aktarılan bilgileri kontrol ederek seansınızı kendiniz seçin.';
    var button = document.createElement('button'); button.type = 'button'; button.className = 'mdg-wa-primary'; button.textContent = 'Gemini ile forma aktar';
    var status = document.createElement('p'); status.id = 'mdg-wa-intake-status'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
    box.append(label, note, button, status); root.querySelector('header').after(box);
    var busy = false, revision = 0;
    ['city', 'session', 'adults', 'children', 'infants'].forEach(function (key) {
        var field = document.getElementById('mdg-wa-' + key);
        ['input', 'change'].forEach(function (event) { field.addEventListener(event, function () { revision++; }); });
    });
    input.addEventListener('input', function () { revision++; input.removeAttribute('aria-invalid'); });
    function snapshot() { return ['city', 'session', 'adults', 'children', 'infants'].map(function (key) { return document.getElementById('mdg-wa-' + key).value; }).concat(input.value).join('|'); }
    button.addEventListener('click', async function () {
        if (busy) return;
        if (input.value.trim().length < 3) { input.setAttribute('aria-invalid', 'true'); status.textContent = 'Şehrinizi ve kaç kişi geleceğinizi birkaç kelimeyle yazın.'; input.focus(); return; }
        var before = snapshot(), requestRevision = revision;
        var controller = new AbortController(), timedOut = false;
        var timer = setTimeout(function () { timedOut = true; controller.abort(); }, 35000);
        busy = true; button.disabled = true; box.setAttribute('aria-busy', 'true'); status.textContent = 'Bilgileriniz forma hazırlanıyor…';
        try {
            var response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', signal: controller.signal, headers: {'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce}, body: JSON.stringify({text: input.value})});
            var data = await response.json();
            if (controller.signal.aborted) throw new Error('TIMEOUT');
            if (requestRevision !== revision || before !== snapshot()) { status.textContent = 'Bilgiler değiştiği için önceki yanıt uygulanmadı.'; return; }
            if (!response.ok) throw new Error(data && data.code);
            if (data.requires_confirmation !== true || !data.proposal) throw new Error();
            var p = data.proposal, city = document.getElementById('mdg-wa-city');
            if (p.city_key !== null && typeof p.city_key !== 'string') throw new Error();
            var proposedCity = p.city_key === null ? null : p.city_key.normalize('NFC');
            // Validate the whole response before changing any form field.
            ['adults', 'children', 'infants'].forEach(function (key) { if (p[key] !== null && (!Number.isInteger(p[key]) || p[key] < (key === 'adults' ? 1 : 0) || p[key] > 20)) throw new Error(); });
            if (proposedCity !== null && !Array.from(city.options).some(function (o) { return o.value === proposedCity && o.value; })) throw new Error();
            city.value = proposedCity === null ? '' : proposedCity; city.dispatchEvent(new Event('change', {bubbles: true}));
            ['adults', 'children', 'infants'].forEach(function (key) {
                var field = document.getElementById('mdg-wa-' + key); field.value = p[key] === null ? '' : String(p[key]); field.dispatchEvent(new Event('input', {bubbles: true}));
            });
            var labels = {city_key: 'şehir/ilçe', adults: 'yetişkin sayısı', children: 'çocuk sayısı', infants: '0–2 yaş misafir sayısı'};
            var missing = Object.keys(labels).filter(function (key) { return p[key] === null; }).map(function (key) { return labels[key]; });
            status.textContent = missing.length ? 'Eksik bilgiler: ' + missing.join(', ') + '. Formda tamamlayıp seansınızı seçin.' : 'Bilgiler forma aktarıldı. Şehri ve kişi sayılarını kontrol edip seansınızı seçin.';
        } catch (error) {
            if (timedOut) status.textContent = 'Yanıt gecikti. Aşağıdaki formu kullanabilirsiniz; isterseniz yeniden deneyin.';
            else if (error.message === 'PLEASE_WAIT') status.textContent = 'Yeni bir deneme için kısa bir süre bekleyin. Aşağıdaki formu hemen kullanabilirsiniz.';
            else if (error.message === 'DAILY_LIMIT') status.textContent = 'Bugünkü yapay zekâ deneme sınırına ulaşıldı. Aşağıdaki formdan devam edebilirsiniz.';
            else status.textContent = 'Metin şu anda aktarılamadı. Aşağıdaki formu kullanarak devam edebilirsiniz.';
        }
        finally { clearTimeout(timer); busy = false; button.disabled = false; box.removeAttribute('aria-busy'); }
    });
})();
