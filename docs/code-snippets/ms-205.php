<?php
/* MS Etkinlik Sayfaları Satış İyileştirmeleri V2 (genel) — tüm /etkinlik/ sayfaları, İzmir 8 Kasım hariç (#204).
 * Tasarruf tutarı her sayfanın kendi çocuk/yetişkin/aile fiyatından hesaplanır; tasarruf yoksa rozet gösterilmez.
 * 1) Aile Paketi'ni listenin başına alır, tasarruf rozeti ekler (CSS/JS, ödeme mantığına dokunmaz)
 * 2) Önemli Bilgiler + SSS'deki Biletinial / kapıdan satış cümlelerini online alımı öne çıkaracak şekilde değiştirir
 * 3) Event JSON-LD'yi zenginleştirir (performer, organizer url, subEvent konum/fiyat)
 * Geri almak için: bu snippet'i pasifleştirmek yeterli. */

if ( ! function_exists( 'ms_evtv2_is_target' ) ) {
	function ms_evtv2_is_target() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		// Tüm /etkinlik/ sayfaları; İzmir 8 Kasım hariç (o sayfa snippet #204 ile yönetiliyor)
		return 0 === strpos( $uri, '/etkinlik/' ) && false === strpos( $uri, '/etkinlik/madagaskar-sirki-izmir-08-kasim-2026' );
	}
}

if ( ! function_exists( 'ms_evtv2_filter_html' ) ) {
	function ms_evtv2_filter_html( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '</html>' ) ) {
			return $html;
		}
		$rep = array(
			'<li>Online satış kanalları madagaskarsirki.com ve biletinial.com&#039;dur.</li>' =>
				'<li>Biletinizi madagaskarsirki.com üzerinden online alın; girişte QR kodla sıra beklemeden geçin.</li>',
			'<li>Etkinlik günü salon girişinden nakit veya kredi kartıyla bilet alınabilir.</li>' =>
				'<li>Fiziksel satış yalnızca etkinlik günü, kontenjan kaldığı sürece salon gişesinde nakit veya kartla yapılır. Diğer günlerde fiziksel satış yoktur.</li>',
			'Online biletinizi madagaskarsirki.com veya biletinial.com üzerinden alabilirsiniz. Etkinlik günü salon girişinde nakit veya kredi kartıyla da bilet satışı yapılır.' =>
				'Biletinizi madagaskarsirki.com üzerinden online alabilirsiniz; girişte telefonunuzdaki QR kodu göstermeniz yeterli. Seanslar dolabileceği için önceden almanızı öneririz. Kalan biletler olursa etkinlik günü salon girişinde de satılır.',
		);
		$rep['Online biletler madagaskarsirki.com ve biletinial.com üzerinden alınabilir. Etkinlik günü salon girişinde nakit veya kredi kartıyla bilet satışı yapılır.'] = 'Biletinizi madagaskarsirki.com üzerinden online alın, girişte telefonunuzdaki QR kodla sıra beklemeden salona geçin. Seans kontenjanları sınırlıdır; yerinizi önceden ayırtmanızı öneririz.';
		$html = str_replace( array_keys( $rep ), array_values( $rep ), $html );
		$html = preg_replace_callback( '#<details\b[^>]*>\s*<summary[^>]*>(.*?)</summary>.*?</details>#su', static function ( $m ) {
			$q = html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES, 'UTF-8' );
			return preg_match( '/aile\s*paket|gişe|fiziksel|kapıdan.*bilet|bilet.*nereden|nereden.*bilet|oturma|koltuk|engelli.*ücret/iu', $q ) ? '' : $m[0];
		}, $html );
		$html = str_replace( '<div class="mdg-faq-list">', '<div class="mdg-faq-list"><details><summary>Aile paketini nasıl kullanabiliriz?</summary><div>Aile Paketi 2+2, 2 yetişkin + 2 çocuk veya 1 yetişkin + 3 çocuk için kullanılabilir. 2 yetişkin + 1 çocuk da paket alabilir; ikinci çocuk zorunlu değildir. Kalan çocuk hakkıyla dilerseniz bir yakınınızın 3–12 yaşındaki çocuğunu ek ücret ödemeden getirebilirsiniz. Üçüncü yetişkin çocuk hakkı yerine geçmez. Fazla kişiler için ek bilet gerekir. Güncel paket fiyatını ve tekil biletlerle karşılaştırmayı bilet seçim ekranından görebilirsiniz.</div></details><details><summary>Fiziksel bilet satışı ne zaman yapılır?</summary><div>Fiziksel bilet satışı yalnızca etkinlik günü salon gişesinde, kontenjan kaldığı sürece nakit veya kartla yapılır. Diğer günlerde fiziksel satış yoktur. Etkinlik öncesinde online bilet alabilirsiniz.</div></details><details><summary>Biletli çocuklar ve yetişkinler oturarak mı izler?</summary><div>Evet. Biletli çocuklar ve yetişkinler oturarak izler. Serbest oturma numarasız koltuk demektir; ayakta izleme anlamına gelmez. Bilet satışı oturma kapasitesine göre sınırlandırılır ve kontenjan dolunca kapanır. Belirli bir koltuk veya sıra garanti edilmez. 0–2 yaş ücretsiz giriş ve kucakta izleme uygulaması ayrıdır.</div></details><details><summary>Engelli çocuklar ve yetişkinler ücretli mi?</summary><div>Engelli çocuklar ücretsizdir; bu uygulama yalnızca etkinlik günü salon gişesinde geçerlidir. Online ücretsiz engelli çocuk bileti düzenlenmez. Engelli yetişkinler ücretlidir. Erişilebilirlik için salon koşullarını ayrıca kontrol ediniz.</div></details><details><summary>Biletimi ve QR kodumu nerede bulabilirim?</summary><div>E-posta ve spam/gereksiz klasörünüzü, size iletilmiş WhatsApp bilet bağlantısını ve güvenli Biletlerim bağlantınızı kontrol edin. QR biletinizi telefondan gösterebilirsiniz; çıktı gerekmez. Sipariş onayı ve ödeme dekontu QR bilet değildir. Bulamazsanız yeniden ödeme yapmadan mevcut sipariş numaranızla destek ekibine ulaşın.</div></details>', $html );


		$html = preg_replace_callback(
			'#<script type="application/ld\+json">(\{"@context":"https://schema\.org","@type":"Event".*?)</script>#s',
			'ms_evtv2_fix_schema',
			$html,
			1
		);
		return $html;
	}
}

if ( ! function_exists( 'ms_evtv2_fix_schema' ) ) {
	function ms_evtv2_fix_schema( $m ) {
		$data = json_decode( $m[1], true );
		if ( ! is_array( $data ) ) {
			return $m[0];
		}
		$home      = home_url( '/' );
		$performer = array( '@type' => 'PerformingGroup', 'name' => 'Madagaskar Sirki' );

		$data['performer'] = $performer;
		if ( isset( $data['organizer'] ) && is_array( $data['organizer'] ) ) {
			$data['organizer']['url'] = $home;
		}
		if ( ! empty( $data['subEvent'] ) && is_array( $data['subEvent'] ) ) {
			foreach ( $data['subEvent'] as $i => $se ) {
				$se['eventStatus']         = isset( $data['eventStatus'] ) ? $data['eventStatus'] : 'https://schema.org/EventScheduled';
				$se['eventAttendanceMode'] = isset( $data['eventAttendanceMode'] ) ? $data['eventAttendanceMode'] : 'https://schema.org/OfflineEventAttendanceMode';
				if ( isset( $data['location'] ) ) { $se['location'] = $data['location']; }
				if ( isset( $data['image'] ) ) { $se['image'] = $data['image']; }
				if ( isset( $data['description'] ) ) { $se['description'] = $data['description']; }
				if ( isset( $data['offers'] ) ) { $se['offers'] = $data['offers']; }
				if ( isset( $data['organizer'] ) ) { $se['organizer'] = $data['organizer']; }
				$se['performer'] = $performer;
				$se['url']       = isset( $data['url'] ) ? $data['url'] : '';
				$data['subEvent'][ $i ] = $se;
			}
		}
		return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	}
}

add_action( 'init', function () {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! ms_evtv2_is_target() ) {
		return;
	}
	ob_start( 'ms_evtv2_filter_html' );
}, 0 );

add_action( 'wp_footer', function () {
	if ( ! ms_evtv2_is_target() ) {
		return;
	}
	?>
<style id="ms-evtv2-family-badge">
.mdg-ticket-option.mdg-family22-option{position:relative;border:2px solid #daa520 !important;background:rgba(218,165,32,.08) !important}
.mdg-ticket-option.mdg-family22-option > div:first-child span.ms-evtv2-badge{display:inline-block !important;width:auto !important;margin:0 0 0 8px !important;padding:2px 9px !important;border-radius:999px;background:#c0392b !important;color:#fff !important;opacity:1 !important;font-size:11px !important;font-weight:700;line-height:1.5;vertical-align:middle;white-space:nowrap}
.ms-evtv2-old{display:block;margin-top:2px;font-size:12px;opacity:.75}
.ms-evtv2-old s{opacity:.8}
</style>
<script id="ms-evtv2-family-ui">
(function(){
	'use strict';
	function apply(){
		var opt = document.querySelector('.mdg-ticket-option.mdg-family22-option');
		if(!opt || opt.getAttribute('data-ms-evtv2')){ return !!opt; }
		opt.setAttribute('data-ms-evtv2','1');
		var parent = opt.parentNode;
		if(parent && parent.firstElementChild !== opt){ parent.insertBefore(opt, parent.firstElementChild); }
		var box = opt.parentNode, child = 0, adult = 0;
		[].forEach.call(box.querySelectorAll('.mdg-ticket-option[data-ticket-code]'), function(o){
			var c = (o.getAttribute('data-ticket-code')||'').toUpperCase(), p = parseFloat(o.getAttribute('data-ticket-price')||'0');
			if(/COCUK|CHILD/.test(c)){ child = p; }
			if(/YETISKIN|ADULT/.test(c)){ adult = p; }
		});
		var pack = parseFloat(opt.getAttribute('data-ticket-price')||'0');
		var full = 2*child + 2*adult, save = full - pack;
		if(!(child>0 && adult>0 && pack>0 && save>0)){ return true; }
		var fmt = function(n){ try{ return new Intl.NumberFormat('tr-TR').format(n); }catch(e){ return String(n); } };
		var title = opt.querySelector('strong');
		if(title){
			var b = document.createElement('span');
			b.className = 'ms-evtv2-badge';
			b.textContent = fmt(save) + ' TL kazanç';
			title.appendChild(b);
		}
		var info = opt.firstElementChild;
		if(info){
			var o = document.createElement('span');
			o.className = 'ms-evtv2-old';
			o.innerHTML = 'Ayrı ayrı: <s>₺' + fmt(full) + '</s> → Paket: ₺' + fmt(pack);
			info.appendChild(o);
		}
		return true;
	}
	if(apply()){ return; }
	var obs = new MutationObserver(function(){ if(apply()){ obs.disconnect(); } });
	obs.observe(document.documentElement, {childList:true, subtree:true});
	setTimeout(function(){ obs.disconnect(); }, 15000);
})();
</script>
<script id="ms-family-advisor-js">
(function () {
    'use strict';
    // Display-only comparison. The existing 2+2 ticket, AJAX handler and QR flow stay intact.
    function bestPlan(adults, children, ap, cp, fp) {
        if (![adults, children, ap, cp, fp].every(Number.isSafeInteger) || adults < 0 || children < 0 ||
            adults > 20 || children > 20 || ap <= 0 || cp <= 0 || fp <= 0) return null;
        var best = {p22: 0, p13: 0, adults: adults, children: children, total: adults * ap + children * cp};
        for (var x = 0; x <= 10; x++) for (var y = 0; x + y <= 10; y++) {
            if (2 * x + y > adults || x + 3 * y > children) continue;
            var ea = adults - 2 * x - y, ec = Math.max(0, children - 2 * x - 3 * y);
            // Existing backend emits 2 adult + 2 child ticket lines per package.
            if (2 * (x + y) + ea > 20 || 2 * (x + y) + ec > 20) continue;
            var total = (x + y) * fp + ea * ap + ec * cp;
            if (total < best.total || (total === best.total && x + y === best.p22 + best.p13 && x > best.p22)) {
                best = {p22: x, p13: y, adults: ea, children: ec, total: total};
            }
        }
        return best;
    }
    if (typeof module === 'object' && module.exports) { module.exports = bestPlan; return; }
    function init() {
        var box = document.querySelector('[data-mdg-ticket-options]');
        if (!box || document.getElementById('ms-family-advisor')) return;
        var rows = Array.from(box.querySelectorAll('[data-ticket-code]'));
        var family = rows.find(function (r) { return r.classList.contains('mdg-family22-option'); });
        var adult = rows.find(function (r) { return /YETISKIN|ADULT/.test(r.dataset.ticketCode.toUpperCase()); });
        var child = rows.find(function (r) { return /COCUK|CHILD/.test(r.dataset.ticketCode.toUpperCase()); });
        if (!family || !adult || !child) return;
        function price(r) { return Math.round(Number(r.dataset.ticketPrice) * 100); }
        function qty(r) { return parseInt(r.querySelector('input').value, 10) || 0; }
        function money(n) { return (n / 100).toLocaleString('tr-TR', {maximumFractionDigits: 2}) + ' TL'; }
        var note = document.createElement('p');
        note.textContent = 'Aile paketi 2 yetişkin + 2 çocuk veya 1 yetişkin + 3 çocuk için kullanılabilir. 2 yetişkin + 1 çocuk da paket alabilir. Kalan çocuk hakkıyla dilerseniz bir yakınınızın 3–12 yaşındaki çocuğunu ek ücret ödemeden getirebilirsiniz. Üçüncü yetişkin çocuk hakkı yerine geçmez.';
        note.style.cssText = 'font-size:14px;line-height:1.6;margin:14px 0';
        var panel = document.createElement('div');
        panel.id = 'ms-family-advisor';
        panel.style.cssText = 'margin:14px 0;padding:16px;border:1px solid #daa520;border-radius:12px;background:#fff8e6;color:#18213a';
        var text = document.createElement('p');
        text.setAttribute('role', 'status');
        text.setAttribute('aria-live', 'polite');
        text.style.margin = '0 0 10px';
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Önerilen biletleri seç';
        button.style.cssText = 'padding:10px 14px;border:0;border-radius:8px;background:#18213a;color:white;cursor:pointer';
        panel.append(text, button);
        box.after(note, panel);
        var recommendation = null;
        function update() {
            // Once a package is selected, its actual group composition is intentionally not inferred.
            var a = qty(adult), c = qty(child);
            recommendation = bestPlan(a, c, price(adult), price(child), price(family));
            panel.hidden = qty(family) > 0 || !a || !c || !recommendation;
            if (panel.hidden) return;
            var current = a * price(adult) + c * price(child);
            button.hidden = recommendation.total >= current;
            if (button.hidden) {
                text.textContent = 'Bu kişi sayısında tekil biletler aile paketinden daha uygundur veya aynı tutardadır.';
                return;
            }
            var parts = [];
            if (recommendation.p22) parts.push(recommendation.p22 + ' aile paketi (2 yetişkin + 2 çocuk hakkı)');
            if (recommendation.p13) parts.push(recommendation.p13 + ' aile paketi (1 yetişkin + 3 çocuk kullanımı)');
            if (recommendation.adults) parts.push(recommendation.adults + ' ek yetişkin bileti');
            if (recommendation.children) parts.push(recommendation.children + ' ek çocuk bileti');
            text.textContent = parts.join(' + ') + ': ' + money(recommendation.total) + '. Tekil biletlere göre ' +
                money(current - recommendation.total) + ' daha uygun. İsterseniz aşağıdaki düğmeyle bu seçimi uygulayabilirsiniz.';
        }
        function selectQty(row, target) {
            var difference = target - qty(row);
            var control = row.querySelector(difference > 0 ? '[data-plus]' : '[data-minus]');
            for (var i = 0; i < Math.abs(difference); i++) control.click();
        }
        button.addEventListener('click', function () {
            var plan = recommendation;
            if (!plan || qty(family)) return;
            // Drive the original controls; no direct cart, price, ticket or hidden state writes.
            selectQty(adult, plan.adults);
            selectQty(child, plan.children);
            selectQty(family, plan.p22 + plan.p13);
            update();
        });
        box.addEventListener('click', update);
        window.addEventListener('pageshow', update);
        update();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
	<?php
}, 99 );

