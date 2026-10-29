import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const listeners={},alerts=[];
let upload,picker;
const doc={addEventListener:(name,fn)=>{(listeners[name]??=[]).push(fn);},body:{appendChild(){}},createElement:()=>picker={style:{},addEventListener(){},click(){this.clicked=true;}}};
const group={getAttribute:name=>name==='data-media_group'?'images':'.png,.jpg,.jpeg'};
const root={getAttribute:()=>10};
const tile={closest:selector=>selector==='li.title'?group:selector==='[data-node_parent_node_id]'?root:null};
vm.runInNewContext(await readFile(new URL('../panels/cms/010_pages/upload/panel.js',import.meta.url),'utf8'),{
 sys:{lib:{applyParentConstructor(){},add:(setup,construct)=>{upload={};construct.call(upload);}},core:{server:{sendGet:()=>{opened=true;throw new Error('Rejected file opened lightbox');},call:class {constructor(){this.data={};} send(){return {done:callback=>{callback({error:'Server rejected this file.'});return {fail(){}};}};}}}}},
 window:{addEventListener(){}},document:doc,location:{pathname:'/cms/pages/',search:'',origin:'https://fixture.test'},
 alert:text=>alerts.push(text),Blob,URL,FileReader:class {readAsArrayBuffer(){this.result=new ArrayBuffer(0);this.onload();}},console
});
upload.showError=text=>alerts.push(text);
assert.equal(upload.accepts({name:'a.JPEG'},'.jpg,.jpeg'),true);
assert.equal(upload.accepts({name:'movie.mp4'},'.png,.jpg'),false);
assert.match(alerts.pop(),/movie.mp4.*not allowed.*\.png/);
let opened=false;upload.openLightbox({accept:'.png'}, {name:'wrong.mp4'});
assert.equal(opened,false);
const event={target:{closest:()=>tile},preventDefault(){},stopPropagation(){}};
listeners.click[0](event);
assert.equal(picker.accept,'.png,.jpg,.jpeg');assert.equal(picker.multiple,true);assert.equal(picker.clicked,true);
let refreshed=false;upload.refreshLibrary=()=>refreshed=true;
const png=new Blob(['test'],{type:'image/png'});Object.defineProperty(png,'name',{value:'test.png'});
await assert.rejects(upload.sendFileDirect(png,{accept:'.png',group:'images'},true,true),/Server rejected/);
assert.equal(refreshed,false,'rejected final chunk cannot be treated as success');
assert.equal(upload.mimeOf({name:'sound.m4a',type:'video/mp4'}),'audio/mp4');
assert.equal(upload.mimeOf({name:'report.docx',type:'application/zip'}),'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
console.log('ok: group picker accept, visible rejection, final-chunk failure and container MIME aliases');

const sentFiles=[];upload.reportQueue=()=>{};
alerts.length=0;upload.refreshLibrary=(from,onReady)=>{alerts.length=0;onReady&&onReady();};
upload.sendFileDirect=(file)=>{sentFiles.push(file.name);return Promise.resolve();};
const blocked=new Blob(['x']);Object.defineProperty(blocked,'name',{value:'wrong.webm'});
upload.queueDirect([png,blocked,png],{accept:'.png'}, {classList:{add(){},remove(){}}});
await new Promise(resolve=>setTimeout(resolve,0));
assert.deepEqual(sentFiles,['test.png','test.png'],'mixed multi-file upload skips disallowed files and sends each allowed file');
assert.equal(alerts.length,1,'skipped-file feedback survives the library redraw');
assert.match(alerts[0],/wrong.webm/);
console.log('ok: multi-file upload validates every file; rejected-file feedback survives the redraw');
