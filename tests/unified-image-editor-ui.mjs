import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
let css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
// Inline local masks so setContent fixtures render the same icons without a web root.
const maskFiles=[...new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1]))];
const masks=new Map(await Promise.all(maskFiles.map(async name=>[name,'data:image/svg+xml;base64,'+(await fs.readFile('../system/public/icons/'+name)).toString('base64')])));
css=css.replace(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g,(_,name)=>'url("'+masks.get(name)+'")');
const holdSource=(await fs.readFile('../system/public/js/core/event/event.js','utf8')).split('sys.longPress = ')[1].split('sys.processEventDelayed = ')[0];
const markup=execFileSync('php',['tests/crop-frame-fixture.php','fixed'],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 for(const [width,height] of [[1920,1080],[1194,700],[1024,600],[390,844]]){
  const page=await browser.newPage({viewport:{width,height},hasTouch:width<1400}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(`<base href="https://fixture.test/"><style>${css}</style><body class=cms><select name=imageEditorFormat><option value=tile>Tile</option><option value=other>Other</option></select><div class=frameView><img id=original></div></body>`);
  await page.evaluate(()=>window.sys={});await page.addScriptTag({content:'sys.longPress = '+holdSource});await page.addScriptTag({path:'../system/public/js/ext/jquery.js'});await page.addScriptTag({path:'../system/public/js/gui/dropdownMenus.js'});await page.addScriptTag({path:'../system/public/js/gui/imageEditor.js'});
  await page.evaluate(html=>{window.settings={siteRoot:'/'};window.requests=[];window.fetch=url=>{requests.push(url);return url.includes('getImageEditor')?Promise.resolve({json:()=>Promise.resolve({html})}):Promise.resolve({ok:true,headers:new Headers({'X-Image-Edit':'saved'})});};},markup);
  const open=()=>page.evaluate(()=>runImageEditor({unified:true,saveUrl:'/admin/pages/editor/file/',formats:[{key:'tile',label:'Tile'},{key:'other',label:'Other'}],src:'/var/formats/tile/1/2/test.jpg',display_width:360,display_height:240,offset_left:40,offset_top:140,domObj:$('#original')}));
  await open();await page.locator('.unifiedImageEditor').waitFor();await page.waitForTimeout(220);
  assert.equal(await page.locator('.cropHandles').isVisible(),false,'crop is initially inactive');
  const holdBox=await page.locator('.unifiedImageEditor img').first().boundingBox();
  await page.mouse.move(holdBox.x+holdBox.width/2,holdBox.y+holdBox.height/2);await page.mouse.down();await page.mouse.move(holdBox.x+holdBox.width/2+16,holdBox.y+holdBox.height/2);await page.waitForTimeout(450);await page.mouse.up();assert.equal(await page.locator('.cropHandles').isVisible(),false,'movement cancels long press');
  if(width<1400){await page.locator('.unifiedImageEditor img').first().dispatchEvent('pointerdown',{pointerType:'touch',pointerId:91,button:0,clientX:holdBox.x+holdBox.width/2,clientY:holdBox.y+holdBox.height/2});await page.waitForTimeout(460);assert.equal(await page.locator('.unifiedImageEditor').evaluate(e=>getComputedStyle(e).position),'absolute');await page.locator('.unifiedImageEditor').dispatchEvent('pointerup',{pointerType:'touch',pointerId:91,button:0});}
  else{await page.mouse.move(holdBox.x+holdBox.width/2,holdBox.y+holdBox.height/2);await page.mouse.down();await page.waitForTimeout(460);assert.equal(await page.locator('.cropHandles').isVisible(),true,'crop is ready before release');assert.equal(await page.locator('.unifiedImageEditor').evaluate(e=>getComputedStyle(e).position),'absolute');await page.mouse.up();}
  assert.equal(await page.locator('.cropHandles').isVisible(),true,'shared mouse/touch long press starts crop');await page.getByRole('button',{name:'Crop image',exact:true}).click();
  const alignment=await page.evaluate(()=>{const a=document.querySelector('.editorFormat button').getBoundingClientRect(),b=document.querySelector('[aria-label="Crop image"]').getBoundingClientRect();return [a.y-b.y,a.height-b.height];});assert.ok(alignment.every(n=>Math.abs(n)<1),'format button aligned with image tools');
  assert.equal(await page.getByRole('button',{name:'Grid',exact:true}).isVisible(),false,'crop grid hidden outside crop');
  assert.ok(await page.getByRole('button',{name:'Discard unsaved edits',exact:true}).locator('.cmsIcon-eraser').count(),'discard edits uses Eraser');
  assert.ok(await page.getByRole('button',{name:'Reset saved adjustments and crop',exact:true}).locator('.cmsIcon-arrow-rotate-left').count(),'reset saved adjustments uses Restore');
  assert.ok(await page.getByRole('button',{name:'Rotate right',exact:true}).locator('.cmsIcon-rotate-right').count(),'actual image rotation retains clockwise icon');
  const buttonColors=await page.evaluate(()=>{const b=n=>document.querySelector(`[aria-label="${n}"]`);return {edit:getComputedStyle(b('Rotate right')).backgroundColor,utility:getComputedStyle(b('Close')).backgroundColor,reset:getComputedStyle(b('Reset saved adjustments and crop')).backgroundColor,apply:document.querySelector('.cropBar .splitApply > .submit:not(.splitApplyMenu)').textContent};});
  assert.notEqual(buttonColors.edit,buttonColors.utility,'image tools and utilities have separate surfaces');assert.notEqual(buttonColors.utility,buttonColors.reset,'Close is transparent while reset retains its utility surface');assert.match(buttonColors.apply,/Apply/);
  const cancel=page.getByRole('button',{name:'Close',exact:true});
  const exitBox=await cancel.boundingBox(),applyBox=await page.getByRole('button',{name:'Apply',exact:true}).boundingBox();assert.ok(Math.abs(exitBox.y-applyBox.y)<1,'Cancel and Apply share a row');assert.ok(exitBox.x>applyBox.x,'Cancel directly follows Apply');
  for(const state of ['hover','focus']){
   if(state==='hover')await cancel.hover();else{await page.mouse.move(1,1);await cancel.evaluate(e=>e.focus());}
   assert.ok(await cancel.evaluate(e=>{const c=document.createElement('canvas');c.width=c.height=1;const ctx=c.getContext('2d');ctx.fillStyle=getComputedStyle(e).backgroundColor;ctx.fillRect(0,0,1,1);const alpha=ctx.getImageData(0,0,1,1).data[3];return alpha>0&&alpha<128;}),'Close retains shared transparent feedback on '+state);
  }


  assert.equal(await page.getByRole('button',{name:'Apply',exact:true}).isEnabled(),false);
  assert.equal(await page.getByRole('button',{name:'Apply options',exact:true}).isEnabled(),false);
  assert.equal(await page.locator('.imageEditor').evaluate(e=>e.getAnimations().length),0,'no opening animation');
  assert.notEqual(await page.locator('.cropFrame').evaluate(e=>getComputedStyle(e).outlineStyle),'none','frame visible without crop');
  await page.getByRole('button',{name:'Image format',exact:true}).click();assert.equal(await page.getByRole('button',{name:'Other',exact:true}).isVisible(),true);
  await page.getByRole('button',{name:'Saturation',exact:true}).click();assert.equal(await page.getByRole('button',{name:'Other',exact:true}).isVisible(),false,'tool closes format menu');
  const range=page.locator('.unifiedAdjustSlider input');const rr=await range.boundingBox();const oldRange=await range.inputValue();
  await page.mouse.click(rr.x+rr.width*.7,rr.y+rr.height/2);assert.notEqual(await range.inputValue(),oldRange,'real pointer changes adjustment');
  assert.equal(await page.locator('.imageEditor').evaluate(e=>e.classList.contains('is-interacting')),false,'slider never starts image drag');
  assert.equal(await page.getByRole('button',{name:'Saturation',exact:true}).getAttribute('aria-pressed'),'true');
  assert.ok(rr.width>180,'usable slider width on every viewport');await page.screenshot({path:`/private/tmp/fluxo-adjustment-${width}.png`});
  for(const name of ['Brightness','Contrast','Saturation','Brightness','Saturation','Contrast']){
   await page.getByRole('button',{name,exact:true}).click();await page.waitForTimeout(30);
   assert.equal(await range.isVisible(),true,'slider survives focus transfer: '+name);
   const box=await range.boundingBox(),before=await range.inputValue();
   await page.mouse.click(box.x+box.width*(Number(before)>100?.25:.75),box.y+box.height/2);
   assert.notEqual(await range.inputValue(),before,'pointer changes shared slider: '+name);
  }
  await page.mouse.click(4,height-4);assert.equal(await page.locator('.unifiedAdjustSlider').isVisible(),false,'outside click closes adjustment');
  await page.getByRole('button',{name:'Discard unsaved edits',exact:true}).click();
  await page.getByRole('button',{name:'Image format',exact:true}).click();assert.equal(await page.locator('.unifiedAdjustSlider').isVisible(),false,'menu closes slider');
  await page.keyboard.press('Escape');assert.equal(await page.locator('.imageEditor').count(),1,'Escape closes only menu');

  const norm=()=>page.evaluate(()=>{const f=document.querySelector('.cropFrame'),i=document.querySelector('.dragimage');return [f.offsetWidth/parseFloat(i.style.width),f.offsetHeight/parseFloat(i.style.height)];});
  const initial=await norm();const r=await page.locator('.cropFrame').boundingBox();await page.mouse.move(r.x+r.width/2,r.y+r.height/2);await page.mouse.wheel(0,-100);await page.waitForTimeout(50);
  assert.ok((await norm()).every((n,i)=>Math.abs(n-initial[i])<.002),'view zoom does not change crop');assert.equal(await page.getByRole('button',{name:'Apply',exact:true}).isEnabled(),false,'view zoom is not an edit');
  await page.getByRole('button',{name:'Crop image',exact:true}).click();await page.waitForTimeout(220);
  const align=await page.evaluate(()=>{const f=document.querySelector('.cropFrame').getBoundingClientRect(),i=document.querySelector('.cropimage').getBoundingClientRect();return {gap:f.left-i.left,x:f.left+f.width/2,y:f.top+f.height/2};});
  await page.evaluate(a=>{const img=document.querySelector('.cropimage');img.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,button:0,pointerId:20,pageX:a.x,clientX:a.x,pageY:a.y,clientY:a.y}));document.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerId:20,clientX:a.x+a.gap+3,clientY:a.y}));},align);
  const edgeGap=()=>page.evaluate(()=>document.querySelector('.cropimage').getBoundingClientRect().left-document.querySelector('.cropFrame').getBoundingClientRect().left);
  assert.ok(Math.abs(await edgeGap())<.1,'pan snaps exactly to crop edge');
  await page.evaluate(a=>{document.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerId:20,clientX:a.x+a.gap+30,clientY:a.y}));document.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:20}));},align);
  assert.ok(await edgeGap()>20,'pan continues beyond snap');
  const centre=await page.locator('.cropFrame').boundingBox();await page.mouse.move(centre.x+centre.width/2,centre.y+centre.height/2);
  for(let n=0;n<12;n++){const ratio=await page.evaluate(()=>document.querySelector('.cropFrame').offsetWidth/parseFloat(document.querySelector('.dragimage').style.width));const delta=-Math.log(ratio)/.002;if(Math.abs(delta)<95){await page.mouse.wheel(0,delta);break;}await page.mouse.wheel(0,Math.sign(delta)*90);}
  assert.ok(Math.abs((await norm())[0]-1)<.002,'zoom snaps image width to crop width');
  await page.getByRole('button',{name:'Grid',exact:true}).click();
  const gridAlign=await page.evaluate(()=>{const f=document.querySelector('.cropFrame').getBoundingClientRect(),i=document.querySelector('.cropimage').getBoundingClientRect();return {gap:f.left+f.width/3-i.left,x:f.left+f.width/2,y:f.top+f.height/2,target:f.width/3};});
  await page.evaluate(a=>{document.querySelector('.cropimage').dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,button:0,pointerId:21,clientX:a.x,clientY:a.y}));document.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerId:21,clientX:a.x+a.gap+6,clientY:a.y}));document.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:21}));},gridAlign);
  assert.ok(Math.abs((await edgeGap())-gridAlign.target)<1,'image edge snaps to active grid third');
  await page.getByRole('button',{name:'Grid',exact:true}).click();
  await page.mouse.move(centre.x+centre.width/2,centre.y+centre.height/2);await page.mouse.wheel(0,-50);assert.ok((await norm())[0]<.95,'zoom continues beyond snap '+width+' '+JSON.stringify(await norm()));
  assert.equal(await page.getByRole('button',{name:'Apply options',exact:true}).isEnabled(),false,'crop alone cannot apply to all');
  const rejectedDialog=page.waitForEvent('dialog');
  const toggle=page.getByRole('button',{name:'Crop image',exact:true}).click();
  const dialog=await rejectedDialog;assert.equal(dialog.message(),'Keep this crop?');await dialog.dismiss();await toggle;
  assert.equal(await page.locator('.cropHandles').isVisible(),false,'discarded crop leaves crop mode');
  await page.getByRole('button',{name:'Crop image',exact:true}).click();
  await page.getByRole('button',{name:'Reset saved adjustments and crop',exact:true}).click();
  const resetRatios=await norm();assert.ok(resetRatios.every(n=>n<=1.002)&&resetRatios.some(n=>Math.abs(n-1)<.002),'reset restores crop covering original without empty frame');
  await page.keyboard.press('Escape');assert.equal(await page.locator('.cropHandles').isVisible(),false,'Escape reverts crop before closing editor');
  assert.equal(await page.locator('.unifiedImageEditor').count(),1);
  await page.getByRole('button',{name:'Crop image',exact:true}).click();


  if(await page.getByRole('button',{name:'Discard unsaved edits',exact:true}).isEnabled())await page.getByRole('button',{name:'Discard unsaved edits',exact:true}).click();
  await page.getByRole('button',{name:'Crop image',exact:true}).click();
  await page.getByRole('button',{name:'Rotate right',exact:true}).click();await page.getByRole('button',{name:'Flip horizontally',exact:true}).click();
  await page.getByRole('button',{name:'Brightness',exact:true}).click();await page.locator('.unifiedAdjustSlider input').fill('125');
  assert.match(await page.locator('.cropimage').evaluate(e=>e.style.transform),/rotate\(90deg\) scaleX\(-1\)/);
  await page.getByRole('button',{name:'Crop image',exact:true}).click();await page.waitForTimeout(220);
  assert.equal(await page.locator('.cropHandles').isVisible(),true);assert.equal(await page.getByRole('button',{name:'Grid',exact:true}).isVisible(),true);assert.equal(await page.getByRole('button',{name:'Original ratio',exact:true}).isVisible(),false,'fixed-ratio format never adds original-ratio action');assert.match(await page.locator('.cropimage').evaluate(e=>e.style.filter),/125%/,'adjustments remain visible in crop');
  assert.equal(await page.locator('.imageEditorHint').evaluate(e=>e.getBoundingClientRect().bottom<innerHeight),true,'hint sits at bottom');
  await page.screenshot({path:`/private/tmp/fluxo-unified-editor-${width}.png`});
  await page.getByRole('button',{name:'Apply options',exact:true}).click();assert.equal(await page.locator('.unifiedAdjustSlider').isVisible(),false);
  await page.getByRole('button',{name:'Saturation',exact:true}).click();assert.equal(await page.getByRole('button',{name:'Apply to all formats',exact:true}).isVisible(),false,'slider closes Apply menu');
  await page.getByRole('button',{name:'Apply options',exact:true}).click();await page.getByRole('button',{name:'Apply to all formats',exact:true}).click();
  await page.waitForFunction(()=>requests.some(u=>u.includes('saveImageEdit')));
  const saved=new URL(await page.evaluate(()=>requests.at(-1)),'https://fixture');assert.equal(saved.searchParams.get('rotate'),'90');assert.equal(saved.searchParams.get('flip'),'1');assert.equal(saved.searchParams.get('brightness'),'125');assert.equal(saved.searchParams.get('apply_all'),'1');
  await page.evaluate(()=>document.querySelector('#original').dispatchEvent(new Event('load')));await page.locator('.imageEditor').waitFor({state:'detached'});
  await open();await page.locator('.imageEditor').waitFor();await page.evaluate(()=>{window.firstEditor=document.querySelector('.imageEditor');const originalFetch=fetch;window.fetch=url=>url.includes('getImageEditor')?new Promise(resolve=>setTimeout(()=>resolve(originalFetch(url)),150)):originalFetch(url);});await page.getByRole('button',{name:'Image format',exact:true}).click();await page.getByRole('button',{name:'Other',exact:true}).click();
  assert.equal(await page.evaluate(()=>firstEditor.isConnected),true,'format loading keeps editor visible');await page.waitForFunction(()=>requests.at(-1).includes('image_format=other'));await page.waitForFunction(()=>document.querySelector('.imageEditor')!==firstEditor);assert.equal(await page.locator('.imageEditor').count(),1);
  await page.getByRole('button',{name:'Image format',exact:true}).click();await page.getByRole('button',{name:'Tile',exact:true}).click();await page.getByRole('button',{name:'Close',exact:true}).click();await page.waitForTimeout(200);assert.equal(await page.locator('.imageEditor').count(),0,'cancel during format load never reopens editor');assert.deepEqual(errors,[]);await page.close();
 }
 console.log('PASS unified editor: shared transforms/crop, view-only zoom, bottom hint, format switching, Apply all, no JS errors');
}finally{await browser.close();}
