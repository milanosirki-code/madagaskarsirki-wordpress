const assert = require('node:assert/strict');
const fs = require('node:fs');
const best = require('../../docs/code-snippets/assets/ms-family-advisor.js');
const cases = [[1,1,735,875],[1,2,980,1165],[1,3,1100,1300],[2,1,1100,1300],
  [2,3,1345,1590],[2,4,1590,1880],[3,2,1590,1885],[3,5,2200,2600],[4,4,2200,2600]];
for (const [a,c,standard,izmir] of cases) {
  for (const [ap,cp,fp,expected] of [[490,245,1100,standard],[585,290,1300,izmir]]) {
    const p=best(a,c,ap*100,cp*100,fp*100);
    assert.equal(p.total,expected*100,`${a} adults ${c} children / ${ap}`);
    assert.equal(2*p.p22+p.p13+p.adults,a);
    assert.ok(2*p.p22+3*p.p13+p.children>=c);
  }
}
for(let a=0;a<=20;a++) for(let c=0;c<=20;c++) {
  const p=best(a,c,49000,24500,110000);
  assert.ok(p.total<=a*49000+c*24500);
  assert.ok(2*(p.p22+p.p13)+p.adults<=20);
  assert.ok(2*(p.p22+p.p13)+p.children<=20);
}
assert.equal(best(1,3,0,24500,110000),null);
assert.equal(best(-1,2,49000,24500,110000),null);
assert.equal(best(21,2,49000,24500,110000),null);
const js=fs.readFileSync('docs/code-snippets/assets/ms-family-advisor.js','utf8').trim();
for(const id of [204,205]) assert.ok(fs.readFileSync(`docs/code-snippets/ms-${id}.php`,'utf8').includes(js));
console.log('PASS: 18 price cases; 441 group-limit comparisons; unchanged ticket adapter');
