const {execFileSync}=require('node:child_process');
const {JSDOM}=require('jsdom');
const assert=require('node:assert/strict');
const html=execFileSync(process.env.PHP_BINARY||'php',[__dirname+'/milano-view.php'],{encoding:'utf8'});
const dom=new JSDOM(html,{runScripts:'dangerously',url:'https://milanosirki.com/kampanya/'});
const d=dom.window.document,e=d.querySelector('[data-ms-event]'),s=d.querySelector('[data-ms-session]'),p=d.querySelector('[data-ms-packages]'),c=d.querySelector('[data-ms-child]'),a=d.querySelector('[data-ms-adult]');
const input=x=>x.dispatchEvent(new dom.window.Event('input'));
const cents=()=>d.querySelector('[data-ms-total]').textContent.replace(/\D/g,'');
assert.equal(d.querySelector('form').action,'https://madagaskarsirki.com/?milano_campaign=1');assert.equal(d.querySelector('form').method,'post');
assert.equal(e.options.length,4);assert.equal(s.options.length,2);assert.equal(cents(),'47500');
p.value='2';input(p);assert.equal(cents(),'95000');
p.value='1';c.value='1';input(c);assert.equal(cents(),'72000');
a.value='1';input(a);assert.equal(cents(),'121000');
for(const [eid,sids] of Object.entries({28:['181','182'],24:['154','155'],27:['179','180'],26:['176','177','178']})){
 e.value=eid;e.dispatchEvent(new dom.window.Event('change'));assert.deepEqual(Array.from(s.options,o=>o.value),sids);
}
assert(d.querySelector('input[name="code"]').required);assert(!d.querySelector('[name="price"]'));
assert(html.includes('@media(max-width:600px)'));assert(!html.includes('disabled'));
console.log('PASS Milano rendered DOM: 4 cities/9 scoped sessions, fixed POST target, package and extras display, required code, responsive CSS. This is not browser/device or payment QA.');
