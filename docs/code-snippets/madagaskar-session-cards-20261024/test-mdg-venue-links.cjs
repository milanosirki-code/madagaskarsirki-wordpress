const fs=require('fs'),vm=require('vm'),assert=require('assert');
const code=fs.readFileSync('mdg-session-cards.js','utf8');new vm.Script(code);
const context={document:{getElementById:()=>({textContent:'{"serverNow":0,"events":[]}'}),readyState:'loading',addEventListener(){}},performance:{now:()=>0},URL,location:{origin:'https://madagaskarsirki.com'}};
vm.runInNewContext(code.replace('  function buildCards(){','  globalThis.venueLinksForTest=venueLinks;\n  function buildCards(){'),context);
const event={venueName:'Atatürk Spor Salonu',venueAddress:'Papuççular, Adnan Menderes Cd. No:118, 54100 Adapazarı/Sakarya',venueMap:'https://maps.app.goo.gl/bB8fukhJysrUSA656'};
const links=context.venueLinksForTest(event);assert.equal(links.map,event.venueMap);const route=new URL(links.route);assert.equal(route.origin,'https://www.google.com');assert.equal(route.pathname,'/maps/dir/');assert.equal(route.searchParams.get('destination'),event.venueName+', '+event.venueAddress);
for(const bad of ['javascript:alert(1)','https://example.com/private','http://maps.app.goo.gl/x']){const v=context.venueLinksForTest({...event,venueMap:bad});assert.equal(new URL(v.map).hostname,'www.google.com');assert.equal(new URL(v.map).pathname,'/maps/search/');}
assert.equal(context.venueLinksForTest({venueName:'',venueAddress:''}),null);
assert(code.includes('container.after(box)'));assert(code.includes("a.rel='noopener noreferrer'"));assert(!code.includes('innerHTML'));
console.log('PASS: canonical map retained, exact recorded route destination, unsafe URL fallback, missing venue fallback, card under session container, safe new tab links.');
