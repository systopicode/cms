/** Actual PHP resolver/action + real image files, isolated from project data. */
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const base='http://127.0.0.1:18796',out='/private/tmp/fluxo-image-device-qa';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
for(const width of [1440,1024,390])for(const dark of [false,true]){
 const page=await browser.newPage({viewport:{width,height:1000},hasTouch:width<1440}),errors=[];
 page.setDefaultTimeout(10000);page.setDefaultNavigationTimeout(15000);
 page.on('console',m=>{if(m.type()==='error')console.log(m.text())});
 page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)errors.push(r.status()+' '+r.url())});
 await page.request.post(base+'/reset');await page.goto(base);await page.waitForFunction(()=>typeof mediaPreviewDevice==='function');
 if(dark)await page.evaluate(()=>{document.body.dataset.theme='dark';document.documentElement.dataset.mode='dark'});
 const img=page.locator('#editable-image');const press=width<1440?'tap':'click';
 const choose=async(device,label)=>{
  await page.locator(`button[data-cms-device="${device}"]`)[press]();
  await img.scrollIntoViewIfNeeded();
  await img[press]({position:{x:30,y:30}});
  const title='Aspect ratio · '+{desktop:'Desktop',tablet:'Tablet',mobile:'Smartphone'}[device];
  await page.getByRole('button',{name:title,exact:true})[press]();
  await page.getByRole('button',{name:label,exact:true}).waitFor();
  await page.screenshot({path:`${out}/menu-${width}-${dark?'dark':'light'}-${device}.png`});
  await page.getByRole('button',{name:label,exact:true})[press]();
  await page.waitForFunction(()=>pendingRequests.length>0);await page.evaluate(()=>Promise.all(pendingRequests).then(()=>pendingSave));
  await page.waitForFunction(([device,label])=>{const img=document.querySelector('#editable-image');return editorMediaRef(img).params.get(device)===label},[device,{'16:9':'16by9','1:1':'1by1','9:16':'9by16','Master':'master'}[label]]);
  await page.waitForFunction(()=>document.querySelector('#editable-image').complete&&document.querySelector('#editable-image').naturalWidth>0);
 };
 await choose('desktop','16:9');await choose('tablet','1:1');await choose('mobile','9:16');
 const ref=await img.getAttribute('data-ref-src');assert.equal(ref,'media:169?format=master&desktop=16by9&tablet=1by1&mobile=9by16');
 assert.equal(await page.locator('#other-image').getAttribute('data-ref-src'),'media:169?format=master');
 const saved=(await (await page.request.get(base+'/content')).json()).stored;
 assert.match(saved,/desktop=16by9&amp;tablet=1by1&amp;mobile=9by16/);assert.ok(!saved.includes('data-ref-'),'No transient preview metadata saved');
 assert.ok(!saved.match(/id="editable-image"[^>]*(width|height)=/),'No generated dimensions persisted');
 assert.match(saved,/id="other-image"[^>]*width="320" height="200"/,'Other image dimensions preserved');
 const saves=await page.evaluate(()=>saves);
 for(const [device,ratio] of [['desktop',16/9],['tablet',1],['mobile',9/16]]){
  await page.locator(`button[data-cms-device="${device}"]`)[press]();
  await page.waitForFunction(device=>document.querySelector('#editable-image').getAttribute('src')===JSON.parse(document.querySelector('#editable-image').dataset.refImageViews).views[device].src,device);
  await page.waitForFunction(()=>document.querySelector('#editable-image').complete&&document.querySelector('#editable-image').naturalWidth>0);
  const dims=await img.evaluate(el=>({w:el.width,h:el.height,nw:el.naturalWidth,nh:el.naturalHeight}));
  assert.ok(Math.abs(dims.w/dims.h-ratio)<.015,`${device} displays actual crop ratio`);
  await img.scrollIntoViewIfNeeded();await page.screenshot({path:`${out}/preview-${width}-${dark?'dark':'light'}-${device}.png`});
 }
 assert.equal(await page.evaluate(()=>saves),saves,'Changing preview does not save');
 // Fit follows the article container even in a wide browser.
 await page.locator('button[data-cms-device="fit"]')[press]();
 for(const [size,expected] of [[450,'9by16'],[750,'1by1'],[1100,'16by9']]){
  await page.locator('.cmsPreview').evaluate((el,size)=>el.style.width=size+'px',size);
  await page.waitForFunction(expected=>mediaFormatOf(document.querySelector('#editable-image'))===expected,expected);
  await page.waitForFunction(expected=>document.querySelector('#editable-image').getAttribute('src').includes(expected),expected);
 }
 assert.equal(await page.evaluate(()=>saves),saves,'Resizing Fit does not save');
 await page.reload();await page.waitForFunction(()=>typeof mediaPreviewDevice==='function');
 await page.locator('button[data-cms-device="mobile"]')[press]();
 await page.waitForFunction(()=>mediaFormatOf(document.querySelector('#editable-image'))==='9by16');
 await page.waitForFunction(()=>document.querySelector('#editable-image').getAttribute('src').includes('9by16'));
 await img.scrollIntoViewIfNeeded();await img[press]({position:{x:30,y:30}});
 await page.getByRole('button',{name:'Aspect ratio · Smartphone',exact:true})[press]();
 const selected=page.getByRole('button',{name:'9:16',exact:true});assert.ok(await selected.evaluate(el=>el.classList.contains('selected')||el.closest('li').classList.contains('selected')),'Menu marks the active crop');
 await page.evaluate(()=>closeAllMenus());
 await img[press]({position:{x:30,y:30}});await page.getByRole('button',{name:'Crop and adjust',exact:true})[press]();
 await page.waitForFunction(()=>navigations.length>0);assert.match(await page.evaluate(()=>navigations.at(-1)),/crops\/\?target_id=169&format=9by16$/);
 await page.evaluate(()=>closeAllMenus());await page.locator('#fixed').scrollIntoViewIfNeeded();await page.locator('#fixed')[press]();
 assert.equal(await page.getByRole('button',{name:/Aspect ratio/}).count(),0,'Fixed template image has no per-device setting');
 // Reset only Smartphone to master: other choices stay.
 await choose('mobile','Master');assert.match(await img.getAttribute('data-ref-src'),/desktop=16by9&tablet=1by1&mobile=master$/);
 // Restore crop for the frontend switch test.
 await choose('mobile','9:16');
 if(width===1440&&!dark){
  // Newer selections win even when an earlier resolution arrives last.
  await page.evaluate(()=>closeAllMenus());
  let delayed=false;
  await page.route('**/resolveEditorLink',async route=>{const ref=route.request().postDataJSON().ref;if(ref.includes('mobile=4by3')){delayed=true;await new Promise(r=>setTimeout(r,220));}await route.continue();});
  await page.evaluate(()=>{const img=document.querySelector('#editable-image');changeMediaFormat({data:{target:img,format:'4by3'}});changeMediaFormat({data:{target:img,format:'1by1'}});});
  await page.waitForFunction(()=>document.querySelector('#editable-image').getAttribute('src').includes('1by1'));
  await page.evaluate(()=>Promise.all(pendingRequests).then(()=>pendingSave));
  assert.ok(delayed);assert.match(await img.getAttribute('data-ref-src'),/mobile=1by1$/);
  assert.equal((await (await page.request.get(base+'/content')).json()).stored.includes('mobile=1by1'),true,'Late result does not replace the newer saved choice');
  await page.unroute('**/resolveEditorLink');
  const before=await img.getAttribute('data-ref-src'),beforeSaves=await page.evaluate(()=>saves);
  await page.route('**/resolveEditorLink',r=>r.fulfill({contentType:'application/json',body:'{"error":true}'}));
  await page.evaluate(()=>changeMediaFormat({data:{target:document.querySelector('#editable-image'),format:'4by3'}}));
  await page.waitForFunction(ref=>document.querySelector('#editable-image').getAttribute('data-ref-src')===ref,before);
  assert.equal(await page.evaluate(()=>saves),beforeSaves,'Failed resolution leaves no changed reference or save');
  await page.unroute('**/resolveEditorLink');await choose('mobile','9:16');
 }
 assert.deepEqual(errors,[]);
 await page.goto(base+'/frontend');
 assert.equal(await page.locator('picture').count(),2,'Both per-device and plain Master images use picture');
 assert.equal(await page.locator('picture picture').count(),0,'No nested picture wrappers');
 assert.equal(await page.locator('#other-image').evaluate(i=>i.parentElement.querySelectorAll('source').length),0,'Plain Master uses one img without redundant device sources');
 for(const [vw,suffix,ratio] of [[1440,'16by9',16/9],[800,'1by1',1],[390,'9by16',9/16]]){
  await page.setViewportSize({width:vw,height:1000});
  await page.waitForFunction(suffix=>{const el=document.querySelector('#editable-image');return el.currentSrc.includes(suffix)&&el.complete&&el.naturalWidth>0},suffix);
  const size=await page.locator('#editable-image').boundingBox();assert.ok(Math.abs(size.width/size.height-ratio)<.02,'Frontend uses matching source dimensions');
  await page.screenshot({path:`${out}/frontend-${width}-${dark?'dark':'light'}-${vw}.png`});
 }
 assert.deepEqual(errors,[]);console.log(`PASS ${width} ${dark?'dark':'light'}: device menus, touch/click, per-occurrence save/reload, Fit container, correct crop editor, frontend picture`);await page.close();
}
}finally{await browser.close()}
