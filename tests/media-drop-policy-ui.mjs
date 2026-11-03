import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const read=path=>readFile(new URL(path,import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1100,height:700}});
 await page.setContent(`<style>:root{--cmsError:rgb(210,20,40);--cmsRadius:6px}${await read('../panels/admin/010_pages/pages.mml.css')}#source{position:absolute;left:20px;top:90px;width:80px;height:80px}.mmlContent{position:static!important}.mmlContent #group{position:absolute;left:300px;top:100px;width:240px;height:180px}#share{position:absolute;left:700px;top:100px;width:240px;height:180px}</style><body class="cms"><div class="cms"><div id="source" data-drag_type="media" data-media-extension="jpg" data-node_id="12">JPG</div><div class="mmlContent"><ul><li id="group" class="title" data-drop_accept="media" data-media-accept=".png">PNG only</li></ul></div><div id="share" class="mediaDrop shareImage" data-drop_accept="media" data-media-accept=".jpg,.png,.gif,.webp"></div></div></body>`);
 await page.addScriptTag({content:await read('../../system/public/js/ext/jquery.js')});
 await page.evaluate(()=>{window.sys={core:{event:{}}};window.obj={extend:(...args)=>Object.assign({},...args)};});
 await page.addScriptTag({content:await read('../../system/public/js/core/event/event.drag.js')});
 for (const [ext,target,blocked] of [['jpg','group',true],['svg','share',true],['png','share',false]]) {
  await page.evaluate(({ext,target})=>{
   const source=document.querySelector('#source'),drop=document.getElementById(target);source.setAttribute('data-media-extension',ext);const r=source.getBoundingClientRect(),d=drop.getBoundingClientRect();window.accepted=0;
   window.drag=new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:source,target:source,clientX:r.x,clientY:r.y})},{accept:()=>accepted++});
   $(document).trigger($.Event('mousemove',{target:drop,clientX:d.x+60,clientY:d.y+50}));
  },{ext,target});
  assert.equal(await page.locator('#'+target).evaluate(el=>el.classList.contains('dropRejected')),blocked);
  if(blocked) assert.equal(await page.locator('#'+target).evaluate(el=>getComputedStyle(el,el.id==='group'?'::after':null).borderTopColor),'rgb(210, 20, 40)');
  await page.evaluate(target=>{const el=document.getElementById(target),r=el.getBoundingClientRect();$(document).trigger($.Event('mouseup',{target:el,clientX:r.x+60,clientY:r.y+50}));},target);
  if(blocked)assert.equal(await page.evaluate(()=>accepted),0,'invalid drop never dispatches accept hook');
  assert.equal(await page.locator('.dropRejected').count(),0,'cue cleaned on release');
 }
 await page.evaluate(()=>{
  const source=document.querySelector('#source');source.dataset.drag_type='page';
  const editor=document.createElement('div');editor.id='editor';editor.className='editor';editor.contentEditable='true';editor.setAttribute('data-drop_accept','media page');editor.style.cssText='position:absolute;left:300px;top:400px;width:400px;height:100px';editor.textContent='Drop a page here';document.body.appendChild(editor);
  const r=source.getBoundingClientRect(),d=editor.getBoundingClientRect();window.accepted=0;
  new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:source,target:source,clientX:r.x,clientY:r.y})},{accept:()=>accepted++});
  $(document).trigger($.Event('mousemove',{target:editor,clientX:d.x+70,clientY:d.y+20}));
  $(document).trigger($.Event('mouseup',{target:editor,clientX:d.x+70,clientY:d.y+20}));
 });
 assert.equal(await page.evaluate(()=>accepted),1,'page tree item can be dropped into editor');
 assert.equal(await page.locator('#editor').evaluate(el=>el.contains(getSelection().anchorNode)),true,'page drop places caret at drop position');
 await page.evaluate(()=>{const e=document.createElement('div');e.id='plain';e.className='editor plaintext';e.dataset.drop_accept='media';e.style.cssText='position:absolute;left:750px;top:400px;width:250px;height:100px';e.textContent='Text only';document.body.append(e);});
 for(const ext of ['jpg','mp4','mp3','pdf','zip']){
  await page.evaluate(ext=>{const s=document.querySelector('#source'),e=document.querySelector('#plain'),r=s.getBoundingClientRect(),d=e.getBoundingClientRect();s.dataset.drag_type='media';s.dataset.mediaExtension=ext;window.accepted=0;new sys.core.event.drag({e:$.Event('mousedown',{currentTarget:s,target:s,clientX:r.x,clientY:r.y})},{accept:()=>accepted++});$(document).trigger($.Event('mousemove',{target:e,clientX:d.x+60,clientY:d.y+30}));},ext);
  assert.equal(await page.locator('#plain').evaluate(e=>e.classList.contains('dropRejected')),true,'MML plain-text rejection '+ext);
  await page.evaluate(()=>{const e=document.querySelector('#plain'),d=e.getBoundingClientRect();$(document).trigger($.Event('mouseup',{target:e,clientX:d.x+60,clientY:d.y+30}));});assert.equal(await page.evaluate(()=>accepted),0,'MML never accepts media in plain text '+ext);
 }
 console.log('ok: invalid media targets turn red and reject; tree pages drop into WYSIWYG with the caret at the drop point');
} finally {await browser.close();}
