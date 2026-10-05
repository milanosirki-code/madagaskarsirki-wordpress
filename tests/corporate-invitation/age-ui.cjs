// Run with jsdom 26 available in NODE_PATH; exercises the embedded UI script.
const {JSDOM}=require('jsdom');
const fs=require('fs');
const path=require('path');
const assert=require('assert/strict');
const php=fs.readFileSync(path.join(__dirname,'../../docs/code-snippets/mdg-corporate-campaigns.php'),'utf8');
const script=php.match(/<script>\s*([\s\S]*?)\s*<\/script>/)[1];
const d=new JSDOM(`<section class="mdg-campaigns"><form data-age-quote data-today="2026-10-05">
<select name="mdg_campaign_adults"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select>
<select name="mdg_campaign_children"><option value="0">0</option><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="5">5</option></select>
<select name="mdg_campaign_session"><option data-show-date="2026-10-08" data-adult-price="500" data-child-price="250" value="99">Denizli</option><option data-show-date="2026-11-08" data-adult-price="600" data-child-price="300" value="88">İzmir</option></select>
<div data-child-ages></div><p data-live-quote></p><button data-count-fallback formnovalidate>Update</button></form></section>`,{runScripts:'outside-only'});
d.window.eval(script);
const doc=d.window.document;
const select=n=>doc.querySelector(`[name="mdg_campaign_${n}"]`);
const summary=()=>doc.querySelector('[data-live-quote]').textContent;
const inputs=()=>Array.from(doc.querySelectorAll('[data-child-birthdate]'));
const change=(name,value)=>{select(name).value=value;select(name).dispatchEvent(new d.window.Event('change'));};
const ages=values=>values.forEach((v,i)=>{inputs()[i].value=String(2026-v)+'-01-01';inputs()[i].dispatchEvent(new d.window.Event('input',{bubbles:true}));});
assert.equal(inputs().length,2);
assert.match(summary(),/bütün çocukların doğum tarihini girin/);
change('children','1');assert.equal(inputs().length,1);
inputs()[0].value='2023-10-05';inputs()[0].dispatchEvent(new d.window.Event('input',{bubbles:true}));
assert.match(summary(),/1 ücretsiz çocuk/);assert.match(summary(),/500/);
assert.equal(doc.querySelector('form').checkValidity(),true);
assert.equal(doc.querySelector('[data-count-fallback]').hidden,true);
select('children').value='2';select('children').dispatchEvent(new d.window.Event('input'));assert.equal(inputs().length,2);
select('children').value='1';d.window.dispatchEvent(new d.window.Event('pageshow'));assert.equal(inputs().length,1);
change('children','3');assert.equal(inputs().length,3);ages([5,7,12]);
assert.match(summary(),/2 ücretsiz çocuk · 1 ücretli çocuk/);assert.match(summary(),/750/);
assert.match(inputs()[2].parentElement.textContent,/Ücretli çocuk bileti/);
change('adults','2');assert.match(summary(),/3 ücretsiz çocuk · 0 ücretli çocuk/);assert.match(summary(),/1.000/);
assert.match(inputs()[2].parentElement.textContent,/Kampanyadan ücretsiz/);
change('children','5');assert.equal(inputs()[0].value,'2021-01-01');ages([5,7,12,4,8]);
assert.match(summary(),/4 ücretsiz çocuk · 1 ücretli çocuk/);assert.match(summary(),/1.250/);
change('children','3');change('adults','1');ages([0,2,12]);
assert.match(summary(),/1 ücretsiz çocuk · 0 ücretli çocuk · 2 kişi 0–2 yaş ücretsiz/);assert.match(summary(),/500/);
ages([13,5,12]);assert.match(summary(),/2 ücretli yetişkin bileti · 2 ücretsiz çocuk/);assert.match(summary(),/1.000/);
change('session','88');assert.match(summary(),/1.200/);
ages([5,7,12]);assert.match(summary(),/900/);
inputs()[0].value='';inputs()[0].dispatchEvent(new d.window.Event('input',{bubbles:true}));
assert.match(summary(),/bütün çocukların doğum tarihini girin/);
change('children','2');change('session','99');ages([12,5]);
inputs()[0].value='2013-10-20';inputs()[0].dispatchEvent(new d.window.Event('input',{bubbles:true}));assert.match(inputs()[0].parentElement.textContent,/12 yaş/);assert.match(summary(),/500/);
change('session','88');assert.match(inputs()[0].parentElement.textContent,/13 yaş/);assert.match(summary(),/1.200/);
inputs()[0].value='2026-10-06';inputs()[0].dispatchEvent(new d.window.Event('input',{bubbles:true}));assert.match(summary(),/doğum tarihini girin/);
change('children','0');assert.equal(inputs().length,0);assert.match(summary(),/600/);
console.log('PASS DOM: age field count, preserved values, paid/free conversion, adult scaling, age boundaries, live session prices, missing age, zero children');
