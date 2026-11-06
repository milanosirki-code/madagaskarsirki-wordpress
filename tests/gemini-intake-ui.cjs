const {JSDOM} = require('jsdom');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const html = execFileSync('php', ['tests/web-assistant-core/export-preview.php'], {encoding:'utf8'});
const origin = 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com';
const source = fs.readFileSync('wp-content/plugins/mdg-gemini-intake/intake.js','utf8');
function setup(){
  const dom = new JSDOM(html,{url:origin+'/wp-admin/tools.php',runScripts:'outside-only'});
  const w=dom.window,d=w.document;
  const city=d.getElementById('mdg-wa-city');city.innerHTML='<option value=""></option><option>İzmir / Konak</option>';city.disabled=false;
  w.mdgGeminiIntake={url:origin+'/wp-json/mdg-gemini-intake/v1/parse',nonce:'test-nonce'};
  let resolve,calls=[],timers=[];
  const originalTimeout=w.setTimeout.bind(w);
  w.setTimeout=(callback,delay)=>{if(delay===35000){timers.push(callback);return 9999;}return originalTimeout(callback,delay);};
  w.fetch=(url,args)=>{calls.push({url,args});return new Promise((r,reject)=>{resolve=r;args.signal.addEventListener('abort',()=>reject(new w.DOMException('Timed out','AbortError')),{once:true});});};
  let submits=0;d.getElementById('mdg-wa-form').addEventListener('submit',()=>submits++);
  let resets=0;city.addEventListener('change',()=>{resets++;d.getElementById('mdg-wa-session').value='';});
  w.eval(source);
  const input=d.querySelector('textarea'),button=input.closest('section').querySelector('button');input.value='İzmir iki yetişkin üç çocuk';
  return {w,d,input,button,calls,expire:()=>timers.at(-1)(),finish:(data,status=200)=>resolve({ok:status===200,status,json:async()=>data}),htmlRateLimit:()=>resolve({ok:false,status:429,json:async()=>{throw new Error('HTML response');}}),submits:()=>submits,resets:()=>resets};
}
const result={requires_confirmation:true,incomplete:false,proposal:{city_key:'İzmir / Konak',adults:2,children:3,infants:0}};
const tick=()=>new Promise(r=>setImmediate(r));
(async()=>{
 let s=setup();s.button.click();s.button.click();assert.equal(s.calls.length,1);assert.equal(s.calls[0].args.headers['X-WP-Nonce'],'test-nonce');s.finish(result);await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'3');assert.equal(s.d.getElementById('mdg-wa-city').value,'İzmir / Konak');assert.equal(s.submits(),0);assert.equal(s.resets(),1);s.w.close();
 s=setup();s.button.click();s.input.value='başka';s.input.dispatchEvent(new s.w.Event('input'));s.input.value='İzmir iki yetişkin üç çocuk';s.finish(result);await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'2');s.w.close();
 s=setup();s.button.click();s.finish({...result,proposal:{...result.proposal,city_key:'<img src=x onerror=alert(1)>'}});await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'2');assert.equal(s.d.querySelectorAll('img').length,0);s.w.close();
 s=setup();s.button.click();s.finish({...result,incomplete:true,proposal:{city_key:null,adults:null,children:3,infants:0}});await tick();assert.equal(s.d.getElementById('mdg-wa-adults').value,'');assert.equal(s.d.getElementById('mdg-wa-city').value,'');assert.equal(s.submits(),0);s.w.close();
 s=setup();s.button.click();s.finish({...result,proposal:{...result.proposal,city_key:'I\u0307zmir / Konak'}});await tick();assert.equal(s.d.getElementById('mdg-wa-city').value,'İzmir / Konak');assert.equal(s.d.getElementById('mdg-wa-children').value,'3');s.w.close();
 s=setup();s.input.value=' ';s.button.click();assert.equal(s.calls.length,0);assert.equal(s.input.getAttribute('aria-invalid'),'true');assert.equal(s.d.activeElement,s.input);s.w.close();
 s=setup();assert.equal(s.input.getAttribute('aria-describedby'),'mdg-wa-intake-note');s.button.click();assert.equal(s.input.closest('section').getAttribute('aria-busy'),'true');s.d.getElementById('mdg-wa-adults').value='9';s.expire();await tick();assert.equal(s.calls[0].args.signal.aborted,true);assert.equal(s.button.disabled,false);assert.equal(s.d.getElementById('mdg-wa-adults').value,'9');assert.ok(s.d.getElementById('mdg-wa-intake-status').textContent.includes('Yanıt gecikti'));assert.equal(s.input.closest('section').hasAttribute('aria-busy'),false);s.w.close();
 s=setup();s.button.click();s.finish({code:'PLEASE_WAIT'},429);await tick();assert.ok(s.d.getElementById('mdg-wa-intake-status').textContent.includes('bekleyin'));assert.equal(s.button.disabled,false);s.w.close();
 s=setup();s.button.click();s.htmlRateLimit();await tick();assert.ok(s.d.getElementById('mdg-wa-intake-status').textContent.includes('bekleyin'));assert.equal(s.d.getElementById('mdg-wa-adults').disabled,false);assert.equal(s.button.disabled,false);s.w.close();
 s=setup();s.button.click();s.finish({...result,incomplete:true,proposal:{city_key:null,adults:null,children:null,infants:0}});await tick();assert.ok(s.d.getElementById('mdg-wa-intake-status').textContent.includes('şehir/ilçe, yetişkin sayısı, çocuk sayısı'));s.w.close();
 // Exercise both real scripts together: intake cannot select a session or submit a quote.
 const dom=new JSDOM(html,{url:origin+'/wp-admin/tools.php',runScripts:'outside-only'}),w=dom.window,d=w.document;
 const offer={session_id:88,source_revision:'a'.repeat(64),city:'I\u0307zmir',district:'Konak',starts_at:'2026-11-08T12:00:00+03:00',venue:{name:'Fixture venue'}};
 const quote={session_id:88,reservation:false,verified_at:1000,expires_at:1060,ticket_url:origin+'/etkinlik/fixture-2026/',regular_option_available:true,recommended:{lines:[{code:'family_2_2',quantity:1,unit_minor:130000},{code:'COCUK',quantity:1,unit_minor:29000}],total_minor:159000,savings_minor:45000,family_quantity:1}};
 const combinedCalls=[];
 w.fetch=async(url,args)=>{combinedCalls.push({url,args});return {ok:true,json:async()=>url.endsWith('catalogue')?{offers:[offer]}:url.endsWith('parse')?result:quote};};
 w.eval(fs.readFileSync('wp-content/plugins/madagaskar-web-assistant-core/assets/preview.js','utf8'));d.dispatchEvent(new w.Event('DOMContentLoaded'));await tick();await tick();
 w.mdgGeminiIntake={url:origin+'/wp-json/mdg-gemini-intake/v1/parse',nonce:'test-nonce'};w.eval(source);d.querySelector('textarea').value='İzmir iki yetişkin üç çocuk';d.querySelector('.mdg-wa-intake button').click();await tick();await tick();
 assert.equal(d.getElementById('mdg-wa-session').value,'');assert.equal(d.getElementById('mdg-wa-submit').disabled,true);assert.equal(combinedCalls.filter(x=>x.url.endsWith('quote')).length,0);
 const session=d.getElementById('mdg-wa-session');session.value='88';session.dispatchEvent(new w.Event('change'));assert.equal(d.getElementById('mdg-wa-submit').disabled,false);
 d.getElementById('mdg-wa-form').dispatchEvent(new w.Event('submit',{cancelable:true}));await tick();await tick();
 const posted=JSON.parse(combinedCalls.find(x=>x.url.endsWith('quote')).args.body);assert.deepEqual(posted.party,{adults:2,children:3,infants:0});assert.equal(d.getElementById('mdg-wa-result').hidden,false);assert.ok(d.getElementById('mdg-wa-total').textContent.includes('1.590'));w.close();
 console.log('PASS Gemini intake UI: confirmation, stale edits, Unicode, accessible input, missing-field guidance, rate errors, timeout recovery, combined parse-to-explicit-session-to-quote');
})().catch(e=>{console.error(e);process.exitCode=1;});
