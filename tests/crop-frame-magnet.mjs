import fs from 'node:fs';import assert from 'node:assert/strict';
const source=fs.readFileSync('../system/public/js/gui/imageEditor.js','utf8');
function fn(name){const start=source.indexOf('function '+name+'(');let braces=0,begin=source.indexOf('{',start),end=begin;do{if(source[end]==='{')braces++;if(source[end]==='}')braces--;end++;}while(braces);return source.slice(start,end);}
const setup=source.match(/const magnetic = .*?;/)[0];
const run=new Function('ratio','grid',`${setup}const unified=false,tune={r:0,f:0},image={x:0,y:0,w:400,h:300},editor={querySelector:()=>({hidden:!grid})};${fn('resized')}${fn('snapFrame')}return snapFrame;`);
let snap=run(0,false);assert.equal(snap({x:0,y:0,w:200,h:150},'e',194,0).w,400);assert.equal(snap({x:0,y:0,w:200,h:150},'s',0,144).h,300);
assert.equal(snap({x:0,y:0,w:500,h:300},'e',-94,0).w,400);assert.equal(snap({x:200,y:0,w:200,h:300},'w',-194,0).x,0);
assert.ok(snap({x:0,y:0,w:200,h:150},'e',240,0).w>400,'continues beyond magnet');
snap=run(0,true);assert.ok(Math.abs(snap({x:0,y:0,w:100,h:100},'e',29,0).w-400/3)<.001,'grid third');
snap=run(4/3,false);const fixed=snap({x:0,y:0,w:200,h:150},'se',194,144);assert.ok(Math.abs(fixed.w/fixed.h-4/3)<.0001,'ratio remains constrained');
console.log('PASS inverse crop magnet: grow/shrink, edges, grid thirds, fixed ratio, continue beyond snap');
