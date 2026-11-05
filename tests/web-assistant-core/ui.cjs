const {JSDOM} = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const html = execFileSync('php', [path.join(__dirname, 'export-preview.php')], {encoding:'utf8'});
const script = fs.readFileSync(path.join(__dirname, '../../wp-content/plugins/madagaskar-web-assistant-core/assets/preview.js'), 'utf8');
const origin = 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com';
const sample = {session_id:223,source_revision:'a'.repeat(64),city:'Tekirdağ',district:'Çerkezköy',starts_at:'2026-11-12T18:00:00+03:00',venue:{name:'Fixture <img src=x onerror=alert(1)>'}};
const quote = {session_id:223,reservation:false,ticket_url:origin+'/etkinlik/fixture-2026/',regular_option_available:true,recommended:{lines:[{code:'family_2_2',quantity:1,unit_minor:110000}],total_minor:110000,savings_minor:37000,family_quantity:1}};
const tick = () => new Promise(resolve => setImmediate(resolve));
function response(data, status=200) {return {ok:status===200,json:async()=>data};}
async function setup(fetcher) {
 const dom = new JSDOM(html, {runScripts:'outside-only',url:origin+'/wp-admin/tools.php'});
 dom.window.fetch=fetcher; dom.window.eval(script);
 dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
 await tick(); await tick(); return dom;
}
function choose(dom) {
 const d=dom.window.document;
 const c=d.getElementById('mdg-wa-city'); c.value='Tekirdağ / Çerkezköy'; c.dispatchEvent(new dom.window.Event('change'));
 const s=d.getElementById('mdg-wa-session'); s.value='223'; s.dispatchEvent(new dom.window.Event('change'));
}
function submit(dom) {dom.window.document.getElementById('mdg-wa-form').dispatchEvent(new dom.window.Event('submit',{bubbles:true,cancelable:true}));}
(async()=>{
 let calls=[];
 const dom=await setup(async(url,opt)=>{calls.push({url,opt});return response(url.endsWith('catalogue')?{offers:[sample]}:quote);});
 const d=dom.window.document;
 assert.equal(d.getElementById('mdg-wa-submit').disabled,true); // no automatic session choice
 choose(dom); assert.equal(d.getElementById('mdg-wa-submit').disabled,false);
 submit(dom);submit(dom); await tick();await tick();
 assert.equal(calls.filter(c=>c.url.endsWith('quote')).length,1);
 const posted=JSON.parse(calls[1].opt.body);
 assert.deepEqual(posted.party,{adults:2,children:2,infants:0});
 assert.equal(posted.source_revision,sample.source_revision);
 assert.equal(calls[1].opt.headers['X-WP-Nonce'],'fixture-nonce');
 assert.equal(calls[1].opt.credentials,'same-origin');
 assert.equal(d.getElementById('mdg-wa-result').hidden,false);
 assert.ok(d.getElementById('mdg-wa-total').textContent.includes('1.100'));
 assert.ok(d.getElementById('mdg-wa-result-session').textContent.includes('<img'));
 assert.equal(d.querySelectorAll('img').length,0); // source content is always text
 assert.equal(d.getElementById('mdg-wa-ticket').href,quote.ticket_url);
 d.getElementById('mdg-wa-adults').value='3';d.getElementById('mdg-wa-adults').dispatchEvent(new dom.window.Event('input'));
 assert.equal(d.getElementById('mdg-wa-result').hidden,true);
 assert.equal(d.getElementById('mdg-wa-ticket').hasAttribute('href'),false);
 dom.window.close();

 let pending,posts=0;
 const late=await setup(async(url)=>url.endsWith('catalogue')?response({offers:[sample]}):new Promise(resolve=>{posts++;pending=resolve;}));
 choose(late);submit(late);
 late.window.document.getElementById('mdg-wa-children').dispatchEvent(new late.window.Event('input'));
 pending(response(quote));await tick();await tick();
 assert.equal(posts,1);assert.equal(late.window.document.getElementById('mdg-wa-result').hidden,true);
 late.window.close();

 let catalogueCalls=0;
 const stale=await setup(async(url)=>{if(url.endsWith('catalogue')){catalogueCalls++;return response({offers:[sample]});}return response({code:'STALE_SELECTION'},409);});
 choose(stale);submit(stale);await tick();await tick();
 assert.equal(catalogueCalls,2);assert.equal(stale.window.document.getElementById('mdg-wa-session').value,'');
 assert.ok(stale.window.document.getElementById('mdg-wa-status').textContent.includes('yeniden seçin'));
 stale.window.close();

 const unsafe=await setup(async(url)=>response(url.endsWith('catalogue')?{offers:[sample]}:{...quote,ticket_url:'https://evil.example/checkout/'}));
 choose(unsafe);submit(unsafe);await tick();await tick();
 assert.equal(unsafe.window.document.getElementById('mdg-wa-result').hidden,true);
 assert.equal(unsafe.window.document.getElementById('mdg-wa-ticket').hasAttribute('href'),false);
 unsafe.window.close();
 const unavailable=await setup(async()=>response({code:'FORBIDDEN'},403));
 assert.equal(unavailable.window.document.getElementById('mdg-wa-submit').disabled,true);
 assert.ok(unavailable.window.document.getElementById('mdg-wa-status').classList.contains('is-error'));
 unavailable.window.close();
 console.log('PASS: UI load, explicit selection, nonce, quote, duplicate-submit, XSS, invalidation, late response, stale revision, unsafe URL and auth error');
})().catch(error=>{console.error(error);process.exit(1);});
