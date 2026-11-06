// Explicit launch validation only. No cookies, login credentials, cart or payment calls.
const fs=require('node:fs');const assert=require('node:assert/strict');
const settings=JSON.parse(fs.readFileSync('docs/VISITOR_LIVE_SMOKE_TRIGGER.json','utf8'));
const origin='https://madagaskarsirki.com',api=origin+'/wp-json/mdg-visitor/v1/';
async function get(path,data){const r=await fetch(api+path,{method:data?'POST':'GET',credentials:'omit',redirect:'error',signal:AbortSignal.timeout(40000),headers:{'Content-Type':'application/json','Origin':origin,'User-Agent':'MadagaskarPilotSmoke/1.0'},body:data?JSON.stringify(data):undefined});let j;try{j=await r.json();}catch{throw new Error(path+': non-JSON HTTP '+r.status);}if(!r.ok)throw new Error(path+': HTTP '+r.status+' '+String(j.code||'REQUEST_FAILED'));return j;}
(async()=>{
 const page=await fetch(origin+'/bilet-onerisi/',{credentials:'omit',redirect:'error',signal:AbortSignal.timeout(30000)});assert.equal(page.status,200);const html=await page.text();assert.ok(html.includes('id="mdg-vp"'));assert.ok(html.includes('id="vp-config"'));assert.ok(!html.includes('wp-login.php?redirect_to='));
 const boot=await get('bootstrap');assert.equal(typeof boot.token,'string');assert.equal(boot.ai_enabled,settings.expect_ai);const token=boot.token;
 const cat=await get('catalogue',{token});assert.equal(cat.pilot_event,10);assert.ok(cat.offers.length>0);assert.ok(cat.offers.every(o=>[88,89,90].includes(o.session_id)));
 const offer=cat.offers[0];assert.equal(new URL(offer.ticket_url).origin,origin);assert.ok(offer.ticket_url.includes('/etkinlik/madagaskar-sirki-izmir-08-kasim-2026/'));
 const q=await get('quote',{token,session_id:offer.session_id,party:{adults:2,children:3,infants:0},source_revision:offer.source_revision});assert.equal(q.reservation,false);assert.equal(q.session_id,offer.session_id);assert.ok(Number.isSafeInteger(q.recommended.total_minor)&&q.recommended.total_minor>0);
 const bad=await fetch(api+'catalogue',{method:'POST',credentials:'omit',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:'forged'})});assert.equal(bad.status,403);
 const health=await fetch(api+'health',{credentials:'omit'});assert.ok([401,403].includes(health.status));
 const foreign=await fetch(api+'bootstrap',{credentials:'omit',headers:{Origin:'https://evil.example'}});assert.equal(foreign.status,403);
 if(settings.expect_ai){const parsed=await get('parse',{token,text:'İzmir için iki yetişkin, üç çocuk ve sıfır bebek geleceğiz.'});assert.equal(parsed.requires_confirmation,true);assert.equal(parsed.proposal.adults,2);assert.equal(parsed.proposal.children,3);assert.equal(parsed.proposal.infants,0);assert.ok(parsed.proposal.city_key);}
 console.log(JSON.stringify({anonymous_http_smoke:'PASS',ai_enabled:settings.expect_ai,page_status:page.status,sessions:cat.offers.map(o=>o.session_id),quote_total_minor:q.recommended.total_minor,reservation:false,forged_session_http:bad.status,private_health_http:health.status,foreign_origin_http:foreign.status,raw_tokens_logged:false}));
})().catch(e=>{console.error(String(e.message).slice(0,250));process.exitCode=1;});
