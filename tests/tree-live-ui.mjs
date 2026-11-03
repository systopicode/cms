/** Local fixtures: remote tree updates preserve selection/scroll; real drop metadata. */
import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const read=p=>readFile(new URL(p,import.meta.url),'utf8');
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1180,height:820},hasTouch:true});
 await page.setContent('<body class="cms"><ul class="tree"><li class="active" data-node_id="11"><label>First</label></li></ul></body>');
 await page.evaluate(()=>{window.sys={lib:{add:(s,c)=>window.panelConstructor=c,applyParentConstructor:()=>{}}};window.scrolls=[];window.scrollTree=(...args)=>scrolls.push(args);});
 await page.addScriptTag({content:await read('../panels/cms/010_pages/panel.js')});
 await page.evaluate(()=>{window.panel={};panelConstructor.call(panel);panel.onUpdateTree();panel.onUpdateTree();});
 assert.equal(await page.evaluate(()=>scrolls.length),0,'unchanged remote selection does not scroll');
 await page.evaluate(()=>{window.menuCloses=0;window.closeAllMenus=()=>menuCloses++;panel.onUpdateTree();});
 assert.equal(await page.evaluate(()=>menuCloses),0,'background tree refresh keeps editor toolbar open');
 await page.evaluate(()=>{document.querySelector('.tree li').classList.add('menuTarget');panel.onUpdateTree();document.querySelector('.tree li').classList.remove('menuTarget');});
 assert.equal(await page.evaluate(()=>menuCloses),0,'tree refresh leaves menu dismissal to the shared opener lifecycle');
 await page.evaluate(()=>{document.querySelector('li').dataset.node_id='12';panel.onUpdateTree();window.rebuilt={};panelConstructor.call(rebuilt);rebuilt.onUpdateTree();});
 assert.deepEqual(await page.evaluate(()=>scrolls),[['smooth','12',true]],'only actual local selection change scrolls, not module rebuild');
 await page.evaluate(()=>{
  const active=document.querySelector('li.active');
  const before=document.createElement('li');before.dataset.node_id='10';active.before(before);
  rebuilt.onUpdateTree();rebuilt.onUpdateTree();
 });
 assert.equal(await page.evaluate(()=>scrolls.length),1,'moving the same selected row does not scroll');
 await page.addScriptTag({content:await read('../../system/public/js/ext/jquery.js')});
 await page.addScriptTag({content:await read('../../system/public/js/core/lib/obj.js')});
 await page.evaluate(()=>{sys.core={};for(const name of ['l','p','t','c','f'])window[name]=()=>({open(){}});});
 await page.addScriptTag({content:await read('../../system/public/js/core/event/event.js')});
 await page.addScriptTag({content:await read('../../system/public/js/core/event/event.pathItem.js')});
 await page.addScriptTag({content:await read('../../system/public/js/core/event/event.drag.js')});
 await page.setContent(`<style>label{display:block;position:relative;height:40px;width:250px}.sort{position:absolute;top:0;left:0;width:250px;height:8px}.draghelper{position:fixed;pointer-events:none}</style><ul class="tree">${[11,12,13].map(id=>`<li data-node_id="${id}"><label data-drag_type="page"><div class="sort v insertBefore" data-drop_accept="page" data-on_drop="insertBefore"></div><i>Grip</i>Page ${id}</label></li>`).join('')}</ul>`);
 await page.evaluate(()=>{
  const Real=sys.core.event;window.RealEvent=Real;window.drops=[];
  function Capture(e){const event=new Real(e);event.dispatch=()=>{event.resumeDrag();drops.push({source:event.data.dragdata.dataset.node_id,target:event.data.dataset.node_id,action:event.data.dataset.on_drop});};return event;}
  Object.assign(Capture,Real);sys.core.event=Capture;
  const label=document.querySelector('[data-node_id="13"] label'),grip=label.querySelector('i'),r=label.getBoundingClientRect();
  const event=new Real($.Event('pointerdown',{currentTarget:label,target:grip,clientX:r.left+10,clientY:r.top+20,originalEvent:{target:grip,pointerType:'touch',preventDefault(){},stopPropagation(){}}}));
  event.collectElementData();event.getTriggers();event.createDrag();
  const zone=document.querySelector('[data-node_id="11"] .sort'),z=zone.getBoundingClientRect();
  $(document).trigger($.Event('pointermove',{target:zone,clientX:z.left+10,clientY:z.top+1}));
  $(document).trigger($.Event('pointerup',{target:grip,clientX:z.left+10,clientY:z.top+1}));
 });
 assert.deepEqual(await page.evaluate(()=>drops),[{source:'13',target:'11',action:'insertBefore'}]);
 await page.evaluate(()=>{
  const Real=window.RealEvent;
  function Capture(e){const event=new Real(e);if(e.type==='pointerup')event.dispatch=()=>{event.resumeDrag();drops.push({source:event.data.dragdata.dataset.node_id,target:event.data.dataset.node_id,action:event.data.dataset.on_drop});};return event;}
  Object.assign(Capture,Real);sys.core.event=Capture;
  window.drops=[];
  $('label[data-drag_type]').on('pointerdown',sys.processPointerDown);
  const grip=document.querySelector('[data-node_id="13"] i'),r=grip.getBoundingClientRect();
  grip.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,cancelable:true,pointerId:1,pointerType:'touch',clientX:r.left+5,clientY:r.top+5}));
 });
 await page.waitForTimeout(450);
 await page.evaluate(()=>{
  const grip=document.querySelector('[data-node_id="13"] i'),zone=document.querySelector('[data-node_id="11"] .sort'),r=zone.getBoundingClientRect();
  grip.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerId:1,pointerType:'touch',clientX:r.left+10,clientY:r.top+1}));
  grip.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:1,pointerType:'touch',clientX:r.left+10,clientY:r.top+1}));
 });
 assert.deepEqual(await page.evaluate(()=>drops),[{source:'13',target:'11',action:'insertBefore'}],'native long press keeps metadata');
 // Local history navigation explicitly reveals the row; unchanged live views do not.
 const navigation=await browser.newPage();
 await navigation.route('https://tree.test/**',route=>route.fulfill({contentType:'text/html',body:'<body class="cms"><div class="tree" style="height:60px;overflow:auto"><ul class="tree">'+Array.from({length:20},(_,i)=>`<li data-node_id="${i+1}"><label style="display:block;height:30px">Page ${i+1}</label></li>`).join('')+'</ul></div></body>'}));
 await navigation.goto('https://tree.test/?id=1');
 await navigation.evaluate(()=>{window.sys={onDocumentReady(){},core:{server:{revealScroll(el,top){el.scrollTop=top;}}}};});
 await navigation.addScriptTag({content:await read('../panels/cms/010_pages/pages.js')});
 await navigation.evaluate(()=>{history.pushState({},'', '?id=20');onAfterHisotryPush();});await navigation.waitForTimeout(100);
 assert.ok(await navigation.locator('div.tree').evaluate(e=>e.scrollTop)>0,'local selection reaches actual selected row');
 await navigation.locator('div.tree').evaluate(e=>e.scrollTop=0);await navigation.evaluate(()=>onAfterHisotryPush());await navigation.waitForTimeout(100);
 assert.equal(await navigation.locator('div.tree').evaluate(e=>e.scrollTop),0,'same selection keeps independent scroll position');
 await navigation.evaluate(()=>{history.pushState({},'', '?id=1');onAfterHisotryPush();});await navigation.waitForTimeout(100);
 assert.equal(await navigation.locator('div.tree').evaluate(e=>e.scrollTop),0,'a visible selection does not scroll');
 await navigation.close();
 console.log('ok: remote updates keep local tree state; touch drop carries distinct source and target');
} finally {await browser.close();}
