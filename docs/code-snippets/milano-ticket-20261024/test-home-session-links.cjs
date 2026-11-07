const fs=require('fs'),vm=require('vm'),assert=require('assert');
const d=JSON.parse(fs.readFileSync('session-link-test-payload.json'));
const code=[...d.home.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)].map(m=>m[1]).find(s=>s.includes('var sessions='));
class El{constructor(tag){this.tag=tag;this.children=[];this.dataset={};this.attrs={};}append(...children){this.children.push(...children);}setAttribute(k,v){this.attrs[k]=v;}replaceChildren(){this.children=[];}before(e){this.beforeElement=e;}}
function render(now){const times=new El('p'),card={querySelector:s=>s==='h3'?{textContent:'Derince'}:s==='.ml-home-city-times'?times:times.beforeElement};class Clock extends Date{static now(){return Date.parse(now);}}const document={readyState:'complete',querySelectorAll:()=>[card],createElement:tag=>new El(tag),addEventListener(){}};vm.runInNewContext(code,{document,Date:Clock,setInterval(){}});return times.children;}
let links=render('2026-10-24T12:30:00+03:00');assert.equal(links.length,3);assert.equal(links[0].dataset.state,'started');assert.equal(links[1].href,'/bilet-al/?seans=177#derince');assert.equal(links[2].href,'/bilet-al/?seans=178#derince');assert.equal(links[1].children[0].textContent,'14:00');
links=render('2026-10-24T13:00:00+03:00');assert.equal(links[0].tag,'span');assert.equal(links[0].attrs['aria-disabled'],'true');assert.equal(links[0].href,undefined);assert.equal(links[1].dataset.state,'upcoming');
for(const html of [d.home,d.ticket])for(const s of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(s[1]);
assert(d.ticket.includes('/^\\d+$/.test(requested)'));
console.log('PASS: exact Derince session 177/178 links, large time labels, started state, +60m disabled link, homepage/ticket syntax and numeric parameter guard.');
