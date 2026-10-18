const { test } = require('node:test');
const assert = require('node:assert/strict');
const { pack } = require('../tacobout-masonry.js');

test('the next post fills below the shorter card instead of waiting for its row', () => {
 const p = pack([{height:100},{height:400},{height:80}],2,30);
 assert.equal(p[2].column,0);
 assert.equal(p[2].row*4,132);
 assert.ok(p[2].row < p[1].row+p[1].span);
});
test('wide lead and section separators clear both columns without overlaps', () => {
 const p = pack([{height:101,wide:true},{height:100},{height:400},{height:50,wide:true},{height:90}],2,30);
 assert.equal(p[0].width,2);
 assert.equal(p[3].row,Math.max(p[1].row+p[1].span,p[2].row+p[2].span)+8);
 assert.equal(p[4].row,p[3].row+p[3].span+8);
});
test('appending later posts leaves existing positions intact', () => {
 const items=[{height:190},{height:311},{height:78}];
 assert.deepEqual(pack([...items,{height:900},{height:63}],2,30).slice(0,3),pack(items,2,30));
});
test('fractional media heights retain gutters and monotonically ordered top edges', () => {
 for(const columns of [1,2,3]) {
  const items=Array.from({length:150},(_,i)=>({height:43.37+(i*173%701),wide:i%31===0}));
  const p=pack(items,columns,30);
  for(let i=0;i<p.length;i++) {
   assert.ok(i===0||p[i].row>=p[i-1].row);
   for(let j=0;j<i;j++) {
    if(p[i].column<p[j].column+p[j].width&&p[j].column<p[i].column+p[i].width) {
     assert.ok(p[i].row*4-(p[j].row*4+items[j].height)>=30);
    }
   }
  }
 }
});
test('a resized embed causes subsequent cards to repack without changing input order', () => {
 const items=[{height:100},{height:200},{height:60},{height:70}];
 const before=pack(items,2,30);
 const after=pack(items.map((x,i)=>({...x,height:i===0?400:x.height})),2,30);
 assert.notDeepEqual(after,before);
 assert.equal(after[2].column,1);
 assert.equal(items[0].height,100);
});
