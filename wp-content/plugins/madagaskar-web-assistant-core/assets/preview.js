(function () {
    'use strict';
    function start() {
        var configEl = document.getElementById('mdg-wa-config');
        var form = document.getElementById('mdg-wa-form');
        if (!configEl || !form) return;
        var config, api;
        try {
            config = JSON.parse(configEl.textContent);
            api = new URL(config.api);
            if (api.origin !== 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com' || api.origin !== config.origin ||
                api.pathname !== '/wp-json/mdg-web-assistant/v1/' || api.search || api.hash || api.username || api.password ||
                typeof config.nonce !== 'string') throw new Error('INVALID_CONFIG');
        } catch (error) { return; }
        var city = document.getElementById('mdg-wa-city');
        var session = document.getElementById('mdg-wa-session');
        var submit = document.getElementById('mdg-wa-submit');
        var status = document.getElementById('mdg-wa-status');
        var result = document.getElementById('mdg-wa-result');
        var offers = [], busy = false, epoch = 0, controller = null, expiryTimer = null, expiresAt = 0;
        var money = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
        var date = new Intl.DateTimeFormat('tr-TR', { timeZone: 'Europe/Istanbul', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
        function message(text, error) { status.textContent = text; status.classList.toggle('is-error', !!error); }
        function selected() { return offers.find(function (o) { return String(o.session_id) === session.value; }); }
        function ready() { submit.disabled = busy || !selected(); }
        function clear() {
            epoch++;
            clearTimeout(expiryTimer); expiryTimer = null; expiresAt = 0;
            if (controller) controller.abort();
            controller = null; busy = false; result.hidden = true;
            document.getElementById('mdg-wa-ticket').removeAttribute('href');
            message(''); ready();
        }
        function expire() { clear(); message('Önerinin süresi doldu. Güncel fiyat için bilet seçeneklerini yeniden karşılaştırın.'); }
        function checkExpiry(event) {
            if (expiresAt && performance.now() >= expiresAt) { if (event) event.preventDefault(); expire(); }
        }
        function option(select, text, value) {
            var el = document.createElement('option'); el.textContent = text; el.value = value; select.appendChild(el);
        }
        function cityKey(o) { return o.city.normalize('NFC') + ' / ' + o.district.normalize('NFC'); }
        function sessions() {
            session.replaceChildren(); option(session, 'Size uygun seansı seçin', '');
            offers.filter(function (o) { return cityKey(o) === city.value; }).forEach(function (o) {
                option(session, date.format(new Date(o.starts_at)) + ' · ' + o.venue.name, String(o.session_id));
            });
            session.disabled = !city.value; ready();
        }
        async function request(path, options) {
            var response = await fetch(new URL(path, api.href).href, Object.assign({ credentials: 'same-origin' }, options, {
                headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' }
            }));
            var data = await response.json();
            if (!response.ok) { var error = new Error(data.code || 'REQUEST_FAILED'); throw error; }
            return data;
        }
        async function load() {
            submit.disabled = true; city.disabled = true; session.disabled = true;
            offers = []; city.replaceChildren(); option(city, 'Şehirler yükleniyor…', '');
            session.replaceChildren(); option(session, 'Önce şehrinizi seçin', '');
            try {
                var data = await request('catalogue', { method: 'GET' });
                if (!Array.isArray(data.offers)) throw new Error('INVALID_CATALOGUE');
                offers = data.offers;
                city.replaceChildren(); option(city, 'Şehrinizi seçin', '');
                Array.from(new Set(offers.map(cityKey))).sort(function (a, b) { return a.localeCompare(b, 'tr'); }).forEach(function (c) { option(city, c, c); });
                city.disabled = !offers.length; sessions();
                message(offers.length ? 'Şehrinizi ve seansınızı seçerek başlayın.' : 'Şu anda öneri sunabileceğimiz güncel bir gösteri bulunmuyor. Mevcut Bilet Al sayfasını kontrol edebilirsiniz.');
            } catch (error) { message('Güncel gösteriler şu anda yüklenemedi. Lütfen sayfayı yenileyin.', true); }
        }
        function minor(n) { if (!Number.isSafeInteger(n) || n < 0) throw new Error('INVALID_QUOTE'); return money.format(n / 100); }
        function eventURL(value) {
            var u = new URL(value);
            if (u.origin !== config.origin || u.username || u.password || u.search || u.hash || !/^\/etkinlik\/[a-z0-9-]+\/$/.test(u.pathname)) throw new Error('INVALID_URL');
            u.hash = 'bilet-secimi';
            return u.href;
        }
        function render(quote, offer, started) {
            if (quote.session_id !== offer.session_id || quote.reservation !== false || !quote.recommended || !Array.isArray(quote.recommended.lines)) throw new Error('INVALID_QUOTE');
            if (!Number.isSafeInteger(quote.verified_at) || !Number.isSafeInteger(quote.expires_at) || quote.verified_at < 1 ||
                quote.expires_at <= quote.verified_at || quote.expires_at - quote.verified_at > 60) throw new Error('INVALID_QUOTE');
            var remaining = (quote.expires_at - quote.verified_at) * 1000 - (performance.now() - started);
            if (remaining <= 0) throw new Error('STALE_OFFER');
            var url = eventURL(quote.ticket_url);
            var labels = { ADULT: 'Yetişkin bileti', YETISKIN: 'Yetişkin bileti', CHILD: 'Çocuk bileti', COCUK: 'Çocuk bileti', family_2_2: 'Aile Paketi 2+2' };
            var list = document.getElementById('mdg-wa-lines'); list.replaceChildren();
            quote.recommended.lines.forEach(function (line) {
                if (!labels[line.code] || !Number.isInteger(line.quantity) || line.quantity < 1 || line.quantity > 20) throw new Error('INVALID_QUOTE');
                var li = document.createElement('li'); li.textContent = line.quantity + ' × ' + labels[line.code] + ' · ' + minor(line.unit_minor); list.appendChild(li);
            });
            document.getElementById('mdg-wa-result-title').textContent = 'En uygun bilet birleşimi';
            document.getElementById('mdg-wa-result-session').textContent = date.format(new Date(offer.starts_at)) + ' · ' + offer.venue.name;
            document.getElementById('mdg-wa-total').textContent = minor(quote.recommended.total_minor);
            document.getElementById('mdg-wa-savings').textContent = quote.regular_option_available && quote.recommended.savings_minor > 0
                ? 'Tekil biletlere göre ' + minor(quote.recommended.savings_minor) + ' daha uygun.' : 'Bu grup için güncel fiyatlarla en uygun geçerli seçenek.';
            document.getElementById('mdg-wa-capacity').textContent = quote.recommended.family_quantity > 0
                ? 'Aile paketinin kullanılmayan hakları olsa da her paket 4 kişilik kapasite kullanır.' : '';
            document.getElementById('mdg-wa-ticket').href = url;
            document.getElementById('mdg-wa-handoff').textContent = 'Bilet ekranında ' + date.format(new Date(offer.starts_at)) +
                ' seansını ve yukarıdaki bilet adetlerini yeniden seçin. Bu öneri rezervasyon oluşturmaz; seçimleriniz otomatik aktarılmaz.';
            expiresAt = performance.now() + remaining; expiryTimer = setTimeout(expire, remaining);
            result.hidden = false; message('Bilet öneriniz hazır. Bilet ekranında seansınızı ve adetlerinizi yeniden seçin.');
        }
        document.getElementById('mdg-wa-ticket').addEventListener('click', checkExpiry);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') checkExpiry(); });
        city.addEventListener('change', function () { clear(); sessions(); });
        session.addEventListener('change', clear);
        ['adults', 'children', 'infants'].forEach(function (key) { document.getElementById('mdg-wa-' + key).addEventListener('input', clear); });
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy || !selected() || !form.reportValidity()) return;
            var party = {};
            for (var key of ['adults', 'children', 'infants']) {
                var raw = document.getElementById('mdg-wa-' + key).value;
                if (!/^(0|[1-9][0-9]?)$/.test(raw) || Number(raw) > 20 || (key === 'adults' && Number(raw) < 1)) { message('Lütfen kişi sayılarını kontrol edin.', true); return; }
                party[key] = Number(raw);
            }
            clear(); var offer = selected(); var token = epoch, started = performance.now();
            controller = new AbortController(); busy = true; ready(); message('Güncel bilet seçenekleri karşılaştırılıyor…');
            try {
                var quote = await request('quote', { method: 'POST', signal: controller.signal, body: JSON.stringify({ session_id: offer.session_id, party: party, source_revision: offer.source_revision }) });
                if (epoch === token) render(quote, offer, started);
            } catch (error) {
                if (error.name === 'AbortError' || epoch !== token) return;
                result.hidden = true;
                if (['STALE_SELECTION', 'STALE_OFFER', 'SESSION_UNAVAILABLE', 'SESSION_CLOSED', 'STATE_CONFLICT'].includes(error.message)) {
                    await load(); message('Gösteri veya fiyat bilgisi değişti. Lütfen seansınızı yeniden seçin.', true);
                } else if (error.message === 'NO_FEASIBLE_COMBINATION') message('Bu kişi sayısı için uygun bilet birleşimi bulunamadı. Başka bir seans seçebilir veya destek alabilirsiniz.', true);
                else message('Öneri şu anda hesaplanamadı. Lütfen tekrar deneyin veya mevcut bilet ekranını kullanın.', true);
            } finally { if (epoch === token) { busy = false; controller = null; ready(); } }
        });
        load();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
