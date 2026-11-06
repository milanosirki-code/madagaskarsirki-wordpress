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
