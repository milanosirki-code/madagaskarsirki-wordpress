const assert=require('node:assert/strict');
const fs=require('node:fs');
const {JSDOM,VirtualConsole}=require('jsdom');
const html=fs.readFileSync(__dirname+'/fixture.html','utf8');
const js=fs.readFileSync(__dirname+'/selection.js','utf8');
function setup(source=html){
  const dom=new JSDOM(source,{url:'https://milanosirki.com/bilet-al/#yalova',runScripts:'outside-only',virtualConsole:new VirtualConsole()});
  const original=[...dom.window.document.querySelectorAll('.milano-ticket-selector form')].map(f=>({action:f.action,method:f.method,fields:[...new dom.window.FormData(f).keys()],hidden:[...f.querySelectorAll('input[type=hidden]')].map(i=>[i.name,i.value])}));
  dom.window.eval(js);dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
  return {dom,w:dom.window,d:dom.window.document,original};
}
const {w,d,original}=setup();
const forms=[...d.querySelectorAll('.milano-ticket-selector form')];
assert.equal(forms.length,9);assert.equal(d.querySelectorAll('[data-ml-selection-enhanced]').length,9);
forms.forEach((f,i)=>{assert.equal(f.action,original[i].action);assert.equal(f.method,original[i].method);assert.deepEqual([...new w.FormData(f).keys()],original[i].fields);assert.deepEqual([...f.querySelectorAll('input[type=hidden]')].map(i=>[i.name,i.value]),original[i].hidden);assert.equal(f.querySelector('button[type=submit]').disabled,true)});
const f=forms.find(f=>f.querySelector('[name=session_id]').value==='154');
function input(k,v){const el=f.querySelector('[name='+k+']');el.value=String(v);el.dispatchEvent(new w.Event('input',{bubbles:true}));}
function total(){return f.querySelector('.ml-summary-total strong').textContent.replace(/[^0-9,]/g,'');}
function people(){return f.querySelector('.ml-summary-people').textContent;}
input('adults',2);input('children',1);assert.equal(total(),'1225,00');assert.match(people(),/3 kişi/);assert.equal(f.querySelector('button[type=submit]').disabled,false);
input('families',1);assert.equal(total(),'2325,00');assert.match(people(),/7 kişi/);
input('adults',0);input('children',0);assert.equal(total(),'1100,00');assert.match(people(),/4 kişi/);
const familyPlus=f.querySelector('button[aria-label$="Aile paketi (2 yetişkin + 2 çocuk) artır"]');familyPlus.click();assert.equal(f.querySelector('[name=families]').value,'2');assert.equal(total(),'2200,00');assert.match(people(),/8 kişi/);
input('families',10);assert.equal(familyPlus.disabled,true);assert.equal(total(),'11000,00');input('adults',1);assert.equal(f.querySelector('button[type=submit]').disabled,true);assert.match(f.querySelector('[role=alert]').textContent,/20 yetişkin/);
input('families',0);input('adults',-1);assert.equal(f.querySelector('button[type=submit]').disabled,true);input('adults',1.5);assert.equal(f.querySelector('button[type=submit]').disabled,true);input('adults',21);assert.equal(f.querySelector('button[type=submit]').disabled,true);
input('adults',0);const ev=new w.Event('submit',{bubbles:true,cancelable:true});f.dispatchEvent(ev);assert.equal(ev.defaultPrevented,true);
input('adults',1);const payload=new w.FormData(f);assert.equal(payload.get('adults'),'1');assert.equal(payload.get('event_id'),'24');assert.equal(payload.get('session_id'),'154');assert.equal(payload.has('price'),false);assert.equal(payload.has('amount'),false);
const before=f.querySelectorAll('.ml-qty-control').length;w.eval(js);assert.equal(f.querySelectorAll('.ml-qty-control').length,before);
const bad=setup(html.replace('Yetişkin — 490,00 TL','Yetişkin — fiyat bekleniyor'));assert.equal(bad.d.querySelectorAll('[data-ml-selection-enhanced]').length,8);assert.equal(bad.d.querySelector('.milano-ticket-selector form').dataset.mlSelectionEnhanced,undefined);
console.log('PASS: nine sessions, unchanged GET actions/hidden IDs/payload keys, adult/child/family totals, four-person family units, stepper bounds, aggregate limits, invalid quantities, empty-submit prevention, repeat-init safety and upstream-format fallback.');
w.close();bad.w.close();
