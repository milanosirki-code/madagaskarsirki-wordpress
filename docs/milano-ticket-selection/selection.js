(function () {
  'use strict';
  function start() {
    var root = document.getElementById('ml-tickets');
    if (!root) return;
    var money = new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'});
    var names = {adults: 'Yetişkin', children: 'Çocuk', families: 'Aile paketi (2 yetişkin + 2 çocuk)'};
    var forms = root.querySelectorAll('.milano-ticket-selector form');
    forms.forEach(function (form) {
      if (form.dataset.mlSelectionEnhanced) return;
      var heading = form.querySelector('h3');
      var submit = form.querySelector('button[type="submit"]');
      var session = form.querySelector('input[name="session_id"]');
      var event = form.querySelector('input[name="event_id"]');
      var title = heading ? heading.textContent.match(/^(.*?) — (\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/) : null;
      if (!title || !submit || !session || !event) return;
      var city = title[1].split(' — ').pop().trim();
      var time = title[5] + '.' + title[6];
      var date = new Date(Date.UTC(Number(title[2]), Number(title[3]) - 1, Number(title[4]), 12));
      var dateText = new Intl.DateTimeFormat('tr-TR', {day: 'numeric', month: 'long', year: 'numeric', weekday: 'long', timeZone: 'UTC'}).format(date);
      var entries = [];
      var validPrices = true;
      Object.keys(names).forEach(function (key) {
        var input = form.querySelector('input[type="number"][name="' + key + '"]');
        if (!input) { if (key !== 'families') validPrices = false; return; }
        var label = input.closest('label');
        var labelText = label ? label.textContent.trim() : '';
        var price = labelText.match(/ — ([0-9]+(?:\.[0-9]{3})*),([0-9]{2}) TL\s*$/);
        if (!price) { validPrices = false; return; }
        var cents = Number(price[1].replace(/\./g, '')) * 100 + Number(price[2]);
        if (!Number.isSafeInteger(cents) || 0 > cents) { validPrices = false; return; }
        entries.push({key: key, input: input, label: label, text: labelText, cents: cents});
      });
      // A changed upstream form/price format retains the unmodified server form.
      if (!validPrices || 2 > entries.length) return;
      form.dataset.mlSelectionEnhanced = '1';
      var eyebrow = document.createElement('p');
      eyebrow.className = 'ml-session-meta';
      eyebrow.textContent = city + ' · ' + dateText;
      heading.before(eyebrow);
      heading.classList.add('ml-session-heading');
      heading.textContent = time + ' Seansı';
      var side = Array.from(root.querySelectorAll('.ml-ticket-side')).find(function (s) {
        var h = s.querySelector('h2'); return h ? h.textContent.trim() === city : false;
      });
      var venue = side ? side.querySelector('strong') : null;
      var summary = document.createElement('section');
      summary.className = 'ml-selection-summary';
      summary.setAttribute('aria-label', city + ' ' + time + ' seçim özeti');
      summary.setAttribute('aria-live', 'polite');
      summary.setAttribute('aria-atomic', 'true');
      var summaryTitle = document.createElement('h4');
      summaryTitle.textContent = 'Seçiminiz';
      var meta = document.createElement('p');
      meta.className = 'ml-summary-meta';
      meta.textContent = city + ' · ' + dateText + ' · ' + time + (venue ? ' · ' + venue.textContent.trim() : '');
      var lines = document.createElement('div');
      lines.className = 'ml-summary-lines';
      var total = document.createElement('p');
      total.className = 'ml-summary-total';
      var totalLabel = document.createElement('span');
      totalLabel.textContent = 'Bilet toplamı';
      var totalAmount = document.createElement('strong');
      total.append(totalLabel, totalAmount);
      var people = document.createElement('p');
      people.className = 'ml-summary-people';
      var note = document.createElement('p');
      note.className = 'ml-summary-note';
      note.textContent = 'Varsa hizmet bedeli ve kesin toplam, ortak ödeme ekranında gösterilir.';
      summary.append(summaryTitle, meta, lines, total, people, note);
      var error = document.createElement('p');
      error.className = 'ml-selection-error';
      error.setAttribute('role', 'alert');
      submit.before(summary, error);
      submit.textContent = 'Seçimi Onayla, Ödemeye Geç →';
      function quantities() {
        var values = {adults: 0, children: 0, families: 0};
        var invalid = false;
        entries.forEach(function (e) {
          var raw = e.input.value;
          if (!/^\d+$/.test(raw)) { invalid = true; return; }
          var n = Number(raw);
          if (!Number.isSafeInteger(n) || 0 > n || n > Number(e.input.max || (e.key === 'families' ? 10 : 20))) invalid = true;
          values[e.key] = n;
        });
        return {values: values, invalid: invalid};
      }
      function withinLimit(v) {
        return !(v.families > 10 || v.adults + 2 * v.families > 20 || v.children + 2 * v.families > 20);
      }
      function update() {
        var result = quantities(), v = result.values;
        var units = v.adults + v.children + 4 * v.families;
        var invalid = result.invalid || !withinLimit(v);
        var cents = 0;
        lines.replaceChildren();
        entries.forEach(function (e) {
          if (invalid ? false : v[e.key]) {
            var line = document.createElement('p');
            var description = document.createElement('span');
            var amount = document.createElement('span');
            description.textContent = v[e.key] + ' × ' + names[e.key];
            amount.textContent = money.format(v[e.key] * e.cents / 100);
            line.append(description, amount);
            lines.append(line);
            cents += v[e.key] * e.cents;
          }
          e.minus.disabled = result.invalid || 0 >= v[e.key];
          var next = Object.assign({}, v); next[e.key]++;
          e.plus.disabled = result.invalid || !withinLimit(next) || next[e.key] > Number(e.input.max || 20);
        });
        if (invalid ? false : !units) {
          var empty = document.createElement('p'); empty.textContent = 'Henüz bilet seçmediniz.'; lines.append(empty);
        }
        totalAmount.textContent = invalid ? '—' : money.format(cents / 100);
        people.textContent = invalid ? '' : 'Toplam ' + units + ' kişi' + (v.families ? ' · Her aile paketi 4 giriş hakkıdır.' : '');
        error.textContent = result.invalid ? 'Lütfen sıfır veya pozitif tam sayı girin; alan sınırını aşmayın.' : !withinLimit(v) ? 'Aile paketi dahil en fazla 20 yetişkin ve 20 çocuk seçebilirsiniz.' : '';
        submit.disabled = invalid || 1 > units;
        return invalid ? false : units > 0;
      }
      entries.forEach(function (e) {
        var label = document.createElement('span');
        label.className = 'ml-ticket-label';
        var ticketName = document.createElement('span');
        ticketName.textContent = e.key === 'families' ? 'Aile 2+2' : names[e.key];
        var unitPrice = document.createElement('small');
        unitPrice.textContent = money.format(e.cents / 100);
        label.append(ticketName, unitPrice);
        var control = document.createElement('span');
        control.className = 'ml-qty-control';
        var minus = document.createElement('button');
        minus.type = 'button'; minus.textContent = '−';
        minus.setAttribute('aria-label', city + ' ' + time + ' ' + names[e.key] + ' azalt');
        var plus = document.createElement('button');
        plus.type = 'button'; plus.textContent = '+';
        plus.setAttribute('aria-label', city + ' ' + time + ' ' + names[e.key] + ' artır');
        e.minus = minus; e.plus = plus;
        e.input.setAttribute('aria-label', city + ' ' + time + ' ' + names[e.key] + ' adedi');
        e.input.setAttribute('inputmode', 'numeric');
        e.label.replaceChildren(label, control);
        control.append(minus, e.input, plus);
        minus.addEventListener('click', function () { e.input.stepDown(); e.input.dispatchEvent(new Event('input', {bubbles: true})); });
        plus.addEventListener('click', function () { e.input.stepUp(); e.input.dispatchEvent(new Event('input', {bubbles: true})); });
        e.input.addEventListener('input', update);
        e.input.addEventListener('change', function () { if (e.input.value === '') e.input.value = '0'; update(); });
      });
      form.addEventListener('submit', function (ev) {
        if (!update()) { ev.preventDefault(); error.textContent = error.textContent || 'Devam etmek için en az bir bilet seçin.'; }
      });
      update();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
  else start();
})();
