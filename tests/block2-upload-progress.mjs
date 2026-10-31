import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';
let upload, callbacks={},globalUpdates=0,localUpdates=0;
const context={
 sys:{lib:{applyParentConstructor(){},add(s,c){upload={};c.call(upload);}},core:{server:{call:class{constructor(){this.data={};}send(){const chain={};for(const name of ['onloadstart','onupdate','done','fail'])chain[name]=fn=>{callbacks[name]=fn;return chain;};return chain;}}}}},
 window:{addEventListener(){},cmsJobs:{}},cmsJobs:{update(){globalUpdates++;},clear(){}},document:{addEventListener(){}},
 location:{pathname:'/cms/pages/',search:'',origin:'https://fixture.test'},URL,Blob,console,l(){}
};
vm.runInNewContext(await readFile('panels/cms/010_pages/upload/panel.js','utf8'),context);
upload.fields={};upload.query={};upload.address='/upload';upload.from='/cms/pages/';upload.size=100;upload.spanCount=1;upload.spanIndex=0;
upload.lightboxShown=()=>true;upload.updateBar=()=>localUpdates++;upload.bustTile=()=>{};upload.reloadLibrary=()=>{};
upload.doc={box:{},footer:{},upload:{classList:{remove(){}}}};
upload.sendFileSpan(new ArrayBuffer(100),100,true);
callbacks.onloadstart({});callbacks.onupdate({loaded:50,total:100});callbacks.done({});
assert.ok(localUpdates>=2);assert.equal(globalUpdates,0,'single-file dialog uses only its local progress');assert.equal(upload.doc.box.className,'done');
upload.reportQueue(0,2,null,.5);assert.equal(globalUpdates,1,'multi-file queue retains global jobs feedback');
console.log('ok: single upload local-only; multi-file queue still reports global progress');
