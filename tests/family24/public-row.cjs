const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const root = require('node:path').resolve(__dirname, '../..');
class Element {
    constructor() { this.attrs = {}; this.parts = {}; this.listeners = {}; this.classList = {toggle() {}, add() {}, remove() {}}; this.value = '0'; }
    setAttribute(k, v) { this.attrs[k] = v; }
    getAttribute(k) { return this.attrs[k] ?? ''; }
    querySelector(k) { return this.parts[k] || null; }
    addEventListener(k, fn) { (this.listeners[k] ||= []).push(fn); }
    click() { let stopped = false; const event = {stopImmediatePropagation() {stopped = true;}}; for (const fn of this.listeners.click || []) {fn(event); if (stopped) break;} }
    set innerHTML(v) { for (const k of ['strong', 'input', '.mdg-ticket-price', '[data-plus]', '[data-minus]']) this.parts[k] = new Element(); }
}
function run(price, enabled = true) {
    const rows = [];
    const box = new Element(); box.appendChild = row => rows.push(row);
    box.querySelector = () => rows.find(row => row.getAttribute('data-mdg-family24')) || null;
    const total = new Element(), people = new Element(), checkout = new Element(), session = new Element();
    session.setAttribute('data-session-id', '88');
    function normal(code, price, units) {const e = new Element(); e.innerHTML = ''; e.setAttribute('data-ticket-code',code);e.setAttribute('data-ticket-price',String(price));e.setAttribute('data-ticket-units',String(units));rows.push(e);return e;}
    const child = normal('CHILD', 245, 1), family22 = normal('MDG_FAMILY_2_2', 1100, 4);
    const doc = {
        querySelector(sel) {return ({'[data-mdg-ticket-options]':box,'[data-mdg-total]':total,'[data-mdg-people]':people,'[data-mdg-checkout-preview]':checkout,'.mdg-session-option.is-selected':session})[sel] || null;},
        querySelectorAll(sel) {return sel === '.mdg-ticket-option' ? rows : sel === '.mdg-session-option' ? [session] : [];},
        createElement() {return new Element();}, addEventListener() {}
    };
    const win = {MDG_EVENT_SALES:{eventId:10,enabled:true},MDG_FAMILY24_OFFERS:enabled?{10:{price,name:'Aile Paketi 2 (2 Yetişkin + 4 Çocuk)'}}:{},addEventListener(){}};
    const ctx = vm.createContext({window:win,document:doc,Intl,Number,URLSearchParams});
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-aile-paketi-24/public-row.js','utf8'),ctx);
    if (!enabled) {assert.equal(rows.length,2); return;}
    assert.equal(rows.length,3);
    const row = rows[2]; assert.equal(row.getAttribute('data-ticket-units'),'6');
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-aile-paketi-24/public-row.js','utf8'),ctx);
    assert.equal(rows.length,3,'no duplicate row');
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-bilet-yonetimi/assets/public-event.js','utf8'),ctx);
    row.parts['[data-plus]'].click(); assert.equal(people.textContent,'6'); assert.equal(total.textContent.replace(/\D/g,''),String(price));
    child.parts['[data-plus]'].click(); assert.equal(people.textContent,'7');assert.equal(total.textContent.replace(/\D/g,''),String(price+245));
    family22.parts['[data-plus]'].click();assert.equal(people.textContent,'11'); assert.equal(total.textContent.replace(/\D/g,''),String(price+245+1100));
    for(let i=0;i<8;i++) row.parts['[data-plus]'].click();
    assert.equal(Number(row.parts.input.value),5,'native plus respects family-specific limit');
    row.parts['[data-minus]'].click();assert.equal(Number(row.parts.input.value),4);
    assert.equal(checkout.disabled,false);
}
run(1500); run(1250); run(1250,false);
console.log('PASS family24 DOM + unmodified native selection, totals, capacity, mixed packages, max and reset');
