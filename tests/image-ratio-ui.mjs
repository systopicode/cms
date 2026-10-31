import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
let css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
// Inline local masks so setContent fixtures render the same icons without a web root.
const maskFiles=[...new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1]))];
const masks=new Map(await Promise.all(maskFiles.map(async name=>[name,'data:image/svg+xml;base64,'+(await fs.readFile('../system/public/icons/'+name)).toString('base64')])));
css=css.replace(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g,(_,name)=>'url("'+masks.get(name)+'")');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
for(const [width,height] of [[1920,1080],[1024,700],[390,844]]) for(const kind of ['free','ratio','fixed']) {
 const markup=execFileSync('php',['tests/crop-frame-fixture.php',kind],{encoding:'utf8'});
 const page=await browser.newPage({viewport:{width,height},hasTouch:width<1400}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(`<style>${css}</style><body class=cms><div class=frameView><img id=original></div></body>`);
 for(const file of ['ext/jquery.js','gui/dropdownMenus.js','gui/imageEditor.js'])await page.addScriptTag({path:'../system/public/js/'+file});
 await page.evaluate(html=>{window.settings={siteRoot:'/'};window.fetch=()=>Promise.resolve({json:()=>Promise.resolve({html})});},markup);
 await page.evaluate(()=>runImageEditor({unified:true,src:'/var/formats/test/001/23/a.jpg',display_width:360,display_height:240,offset_left:100,offset_top:100,domObj:jQuery('#original')}));
 const shape=()=>page.locator('.cropFrame').evaluate(e=>({w:parseFloat(e.style.width),h:parseFloat(e.style.height)}));
 const drag=async(selector,dx,dy)=>page.evaluate(({selector,dx,dy})=>{const e=document.querySelector(selector),r=e.getBoundingClientRect(),x=r.x+r.width/2,y=r.y+r.height/2;e.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,button:0,pointerId:30,clientX:x,clientY:y}));document.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,pointerId:30,clientX:x+dx,clientY:y+dy}));document.dispatchEvent(new PointerEvent('pointerup',{bubbles:true,pointerId:30}));},{selector,dx,dy});
 await page.evaluate(()=>{const box=document.createElement('div');box.className='cmslightbox';box.innerHTML='<i data-preview-loading class="cmsIcon cmsIcon-spinner cmsIcon-spin"></i>';document.body.append(box);});
 assert.equal(await page.locator('[data-preview-loading]').evaluate(e=>getComputedStyle(e).color),'rgb(252, 182, 12)','checkerboard loading uses highlight');await page.locator('[data-preview-loading]').evaluate(e=>e.parentElement.remove());
 const before=await page.locator('.imageEditor').boundingBox();await drag('.cropFrame',15,10);const after=await page.locator('.imageEditor').boundingBox();assert.equal(after.x,before.x,'fitted element cannot pan');
 // Whole-frame zoom and pan even on the empty area outside the source image.
 await page.evaluate(()=>{const e=document.querySelector('.cropFrame');for(let i=0;i<8;i++)e.dispatchEvent(new WheelEvent('wheel',{bubbles:true,cancelable:true,deltaY:-100,clientX:innerWidth/2,clientY:innerHeight/2}));});
 const zoom=await page.locator('.imageEditor').boundingBox();await drag('.cropFrame',15,10);const moved=await page.locator('.imageEditor').boundingBox();assert.ok(Math.abs(moved.x-zoom.x-15)<1,'zoomed frame accepts pan outside image');
 const prePinch=await shape();
 await page.evaluate(()=>{const e=document.querySelector('.cropFrame');const fire=(type,span)=>{const ev=new Event(type,{bubbles:true,cancelable:true});Object.defineProperty(ev,'touches',{value:[{clientX:100,clientY:200,pageX:100,pageY:200},{clientX:100+span,clientY:200,pageX:100+span,pageY:200}]});e.dispatchEvent(ev);};fire('touchstart',100);fire('touchmove',120);fire('touchend',120);});
 assert.ok((await shape()).w>prePinch.w*1.19,'whole-frame pinch zoom');
 await page.getByRole('button',{name:'Crop image',exact:true}).click();await page.waitForTimeout(200);
 const cropStyle=await page.getByRole('button',{name:'Crop image',exact:true}).evaluate(e=>({border:getComputedStyle(e).borderTopColor,shadow:getComputedStyle(e).boxShadow}));assert.equal(cropStyle.shadow,'none');assert.equal(cropStyle.border,'rgba(0, 0, 0, 0)');
 for(const edge of ['n','s','e','w','nw','ne','sw','se']){
  const old=await shape();await drag(`[data-crop-handle="${edge}"]`,edge.includes('w')?-15:15,edge.includes('n')?-25:25);await page.waitForTimeout(200);const next=await shape();
  if(kind!=='free')assert.ok(Math.abs(next.w/next.h-1.5)<.01,'fixed ratio on '+edge);
  else if(edge==='n')assert.notEqual(Math.round(next.w/next.h*100),Math.round(old.w/old.h*100),'free MP changes ratio');
 }
 if(kind==='free'){
  await page.getByRole('button',{name:'Original ratio',exact:true}).click();await page.waitForTimeout(200);assert.equal(await page.getByRole('button',{name:'Lock aspect ratio',exact:true}).getAttribute('aria-pressed'),'false','original ratio stays unlocked');
  await page.getByRole('button',{name:'Lock aspect ratio',exact:true}).click();const locked=await shape();await drag('[data-crop-handle="e"]',8,0);await page.waitForTimeout(200);const resized=await shape();assert.ok(Math.abs(resized.w/resized.h-locked.w/locked.h)<.01,'manual lock preserves ratio');
  await page.getByRole('button',{name:'Unlock aspect ratio',exact:true}).click();await drag('[data-crop-handle="e"]',8,0);await page.waitForTimeout(200);const free=await shape();assert.ok(Math.abs(free.w/free.h-locked.w/locked.h)>.005,'unlock restores free resize');
 }
 await page.getByRole('button',{name:'Grid',exact:true}).click();const grid=await page.locator('.cropGrid').evaluate(e=>getComputedStyle(e).backgroundImage);assert.ok(grid.includes('linear-gradient')&&grid.includes('255, 255, 255')&&grid.includes('252, 182, 12'),'dual colour grid');
 if(kind==='free')await page.screenshot({path:`/private/tmp/fluxo-ratio-${width}.png`});
 await page.evaluate(()=>dispatchEvent(new PopStateEvent('popstate')));assert.equal(await page.locator('.imageEditor').count(),0,'history navigation closes editor');assert.equal(await page.locator('.imageEditorScrim').count(),0);
 assert.deepEqual(errors,[]);await page.close();
}
console.log('PASS ratio controls, all eight grips, frame-wide zoom/pan, fitted pan guard, dual-colour grid: desktop/tablet/narrow');
} finally {await browser.close();}
