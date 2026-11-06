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
    set innerHTML(v) {
        for (const k of ['strong', 'input', '.mdg-ticket-price', '[data-plus]', '[data-minus]', '.mdg-family24-saving', '.mdg-family24-comparison', '.mdg-family24-regular', '.mdg-family24-package']) this.parts[k] = new Element();
        this.parts['.mdg-family24-saving'].hidden = true; this.parts['.mdg-family24-comparison'].hidden = true;
    }
}
function run(price, enabled = true, adultPrice = 490, childPrice = 245, expectedSaving = 710) {
    const rows = [];
    const box = new Element(); box.appendChild = row => rows.push(row);
    box.querySelector = sel => rows.find(row => row.getAttribute(sel === '[data-mdg-family22]' ? 'data-mdg-family22' : 'data-mdg-family24')) || null;
    box.querySelectorAll = () => rows;
    box.insertBefore = (row, anchor) => { const old = rows.indexOf(row); if (old !== -1) rows.splice(old,1); const at = rows.indexOf(anchor); rows.splice(at === -1 ? rows.length : at,0,row); };
    const total = new Element(), people = new Element(), checkout = new Element(), session = new Element();
    session.setAttribute('data-session-id', '88');
    function normal(code, price, units) {const e = new Element(); e.innerHTML = ''; e.setAttribute('data-ticket-code',code);e.setAttribute('data-ticket-price',String(price));e.setAttribute('data-ticket-units',String(units));Object.defineProperty(e,'nextSibling',{get(){return rows[rows.indexOf(e)+1]||null;}});rows.push(e);return e;}
    const child = normal('CHILD', childPrice, 1), adult = normal('ADULT', adultPrice, 1), family22 = normal('MDG_FAMILY_2_2', 1100, 4);
    family22.setAttribute('data-mdg-family22','1');
    const doc = {
        querySelector(sel) {return ({'[data-mdg-ticket-options]':box,'[data-mdg-total]':total,'[data-mdg-people]':people,'[data-mdg-checkout-preview]':checkout,'.mdg-session-option.is-selected':session})[sel] || null;},
        querySelectorAll(sel) {return sel === '.mdg-ticket-option' ? rows : sel === '.mdg-session-option' ? [session] : [];},
        createElement() {return new Element();}, addEventListener() {}
    };
    const win = {MDG_EVENT_SALES:{eventId:10,enabled:true},MDG_FAMILY24_OFFERS:enabled?{10:{price,name:'Aile Paketi 2 (2 Yetişkin + 4 Çocuk)'}}:{},addEventListener(){}};
    const ctx = vm.createContext({window:win,document:doc,Intl,Number,URLSearchParams});
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-aile-paketi-24/public-row.js','utf8'),ctx);
    if (!enabled) {assert.equal(rows.length,3); return;}
    assert.equal(rows.length,4);
    const row = rows[3]; assert.equal(row.getAttribute('data-ticket-units'),'6');
    assert.equal(row.parts.strong.textContent,'Aile Paketi 2');
    assert.equal(row.parts['.mdg-family24-saving'].hidden, expectedSaving <= 0);
    if (expectedSaving > 0) {
        assert.equal(row.parts['.mdg-family24-saving'].textContent, expectedSaving+' TL kazanç');
        assert.equal(row.parts['.mdg-family24-regular'].textContent.replace(/\D/g,''),String(4*childPrice+2*adultPrice));
        assert.equal(row.parts['.mdg-family24-package'].textContent.replace(/\D/g,''),String(price));
        assert.equal(row.parts['.mdg-family24-comparison'].hidden,false);
    }
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-aile-paketi-24/public-row.js','utf8'),ctx);
    assert.equal(rows.length,4,'no duplicate row');
    vm.runInContext(fs.readFileSync(root+'/wp-content/plugins/madagaskar-bilet-yonetimi/assets/public-event.js','utf8'),ctx);
    row.parts['[data-plus]'].click(); assert.equal(people.textContent,'6'); assert.equal(total.textContent.replace(/\D/g,''),String(price));
    child.parts['[data-plus]'].click(); assert.equal(people.textContent,'7');assert.equal(total.textContent.replace(/\D/g,''),String(price+childPrice));
    family22.parts['[data-plus]'].click();assert.equal(people.textContent,'11'); assert.equal(total.textContent.replace(/\D/g,''),String(price+childPrice+1100));
    for(let i=0;i<8;i++) row.parts['[data-plus]'].click();
    assert.equal(Number(row.parts.input.value),5,'native plus respects family-specific limit');
    row.parts['[data-minus]'].click();assert.equal(Number(row.parts.input.value),4);
    assert.equal(checkout.disabled,false);
}
run(1500,true,585,290,830); run(1250); run(1250,true,500,250,750); run(2000,true,490,245,-40); run(1250,false);
console.log('PASS family24 DOM + unmodified native selection, totals, capacity, mixed packages, max and reset');
