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
  let resolve,calls=[];
  w.fetch=(url,args)=>{calls.push({url,args});return new Promise(r=>{resolve=r;});};
  let submits=0;d.getElementById('mdg-wa-form').addEventListener('submit',()=>submits++);
  let resets=0;city.addEventListener('change',()=>{resets++;d.getElementById('mdg-wa-session').value='';});
  w.eval(source);
  const input=d.querySelector('textarea'),button=input.closest('section').querySelector('button');input.value='İzmir iki yetişkin üç çocuk';
  return {w,d,input,button,calls,finish:data=>resolve({ok:true,json:async()=>data}),submits:()=>submits,resets:()=>resets};
}
const result={requires_confirmation:true,incomplete:false,proposal:{city_key:'İzmir / Konak',adults:2,children:3,infants:0}};
const tick=()=>new Promise(r=>setImmediate(r));
(async()=>{
 let s=setup();s.button.click();s.button.click();assert.equal(s.calls.length,1);assert.equal(s.calls[0].args.headers['X-WP-Nonce'],'test-nonce');s.finish(result);await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'3');assert.equal(s.d.getElementById('mdg-wa-city').value,'İzmir / Konak');assert.equal(s.submits(),0);assert.equal(s.resets(),1);s.w.close();
 s=setup();s.button.click();s.input.value='başka';s.input.dispatchEvent(new s.w.Event('input'));s.input.value='İzmir iki yetişkin üç çocuk';s.finish(result);await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'2');s.w.close();
 s=setup();s.button.click();s.finish({...result,proposal:{...result.proposal,city_key:'<img src=x onerror=alert(1)>'}});await tick();assert.equal(s.d.getElementById('mdg-wa-children').value,'2');assert.equal(s.d.querySelectorAll('img').length,0);s.w.close();
 s=setup();s.button.click();s.finish({...result,incomplete:true,proposal:{city_key:null,adults:null,children:3,infants:0}});await tick();assert.equal(s.d.getElementById('mdg-wa-adults').value,'');assert.equal(s.d.getElementById('mdg-wa-city').value,'');assert.equal(s.submits(),0);s.w.close();
 s=setup();s.button.click();s.finish({...result,proposal:{...result.proposal,city_key:'I\u0307zmir / Konak'}});await tick();assert.equal(s.d.getElementById('mdg-wa-city').value,'İzmir / Konak');assert.equal(s.d.getElementById('mdg-wa-children').value,'3');s.w.close();
 console.log('PASS Gemini intake UI: confirmation, duplicate clicks, stale/reverted edits, unsafe city, missing counts, Turkish Unicode normalization');
})().catch(e=>{console.error(e);process.exitCode=1;});
