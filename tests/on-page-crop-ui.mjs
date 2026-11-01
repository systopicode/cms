import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
const html=execFileSync('php',['tests/crop-frame-fixture.php','fixed'],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 for(const [width,height,touch] of [[1440,1000,false],[1194,834,true],[390,844,true]]){
  const page=await browser.newPage({viewport:{width,height},hasTouch:touch});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://fixture.test/**',r=>r.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="teal"/></svg>'}));
  await page.setContent(`<base href="https://fixture.test/"><style>${css}</style><body class=cms><button id=start>Crop on page</button><div style="margin:180px 40px"><img id=photo style="width:280px;height:186px" src="/var/formats/tile/1/2/photo.jpg"><img id=second style="width:100px;height:66px" src="/var/formats/tile/1/3/second.jpg"><p>Neighbouring content stays in place</p></div></body>`);
  await page.addScriptTag({path:'../system/public/js/ext/jquery.js'});await page.addScriptTag({path:'../system/public/js/gui/imageEditor.js'});await page.addScriptTag({path:'../system/public/js/gui/HTMLeditor.funcs.js'});
  await page.evaluate(html=>{window.settings={siteRoot:'/'};window.calls=[];window.fetch=url=>{calls.push(url);return url.includes('getImageEditor')?Promise.resolve({json:()=>Promise.resolve({html})}):Promise.resolve({ok:true});};document.querySelector('#start').onclick=()=>cropImageOnPage({data:{target:document.querySelector('#photo')}});},html);
  const source=await page.locator('#photo').boundingBox();await page.locator('#start').click();await page.locator('.inlineImageCrop').waitFor();
  page.once('dialog',d=>d.accept());
  await page.evaluate(()=>cropImageOnPage({data:{target:document.querySelector('#second')}}));
  await page.waitForFunction(()=>document.querySelector('#photo').style.visibility==='visible' && document.querySelectorAll('.imageEditor').length===1);
  page.once('dialog',d=>d.accept());
  await page.evaluate(()=>cropImageOnPage({data:{target:document.querySelector('#photo')}}));
  await page.waitForFunction(()=>document.querySelector('#second').style.visibility==='visible' && document.querySelector('#photo').style.visibility==='hidden');
  await page.locator('#photo').evaluate(e=>e.style.width='240px');
  await page.waitForTimeout(100);
  assert.equal(await page.locator('.inlineImageCrop').count(),1,'layout resize preserves active crop');
  await page.locator('#photo').evaluate(e=>e.style.width='280px');
  await page.waitForTimeout(100);
  const frame=await page.locator('.cropFrame').boundingBox();assert.ok(Math.abs(frame.x-source.x)<2&&Math.abs(frame.y-source.y)<2,'crop stays at page position');
  assert.equal(await page.locator('.inlineImageCropScrim').evaluate(e=>getComputedStyle(e).backgroundColor),'rgba(0, 0, 0, 0)');
  assert.equal(await page.locator('.cropBar .submit').isEnabled(),false);
  const close=page.getByRole('button',{name:'Cancel',exact:true});
  const geometry=await close.evaluate(e=>{const bar=e.closest('.cropBar'),apply=bar.querySelector('.submit'),r=e.getBoundingClientRect(),a=apply.getBoundingClientRect();return {right:r.x>=a.right,background:getComputedStyle(e).backgroundColor,width:bar.getBoundingClientRect().width};});
  assert.ok(geometry.right,'Close follows Apply');
  assert.equal(geometry.background,'rgba(0, 0, 0, 0)','Close rests transparently');
  assert.ok(geometry.width<=width-32,'inline toolbar stays within viewport');
  assert.equal(await close.evaluate(e=>parseFloat(getComputedStyle(e.closest('.cropBar')).paddingLeft)/parseFloat(getComputedStyle(document.documentElement).fontSize)),.5,'compact inline padding');
  if(width>=1194){const widths=await page.locator('.cropBar > span').evaluate(el=>{const bar=el.closest('.cropBar'),original=el.textContent;const first=bar.getBoundingClientRect().width;el.textContent='Move image';const second=bar.getBoundingClientRect().width;el.textContent=original;return [first,second];});assert.ok(Math.abs(widths[0]-widths[1])<1,'hint changes keep toolbar width stable');}
  await close.hover();
  assert.notEqual(await close.evaluate(e=>getComputedStyle(e).backgroundColor),'rgba(0, 0, 0, 0)','Close has hover feedback');
  const img=page.locator('.cropimage'),b=await img.boundingBox();
  await img.dispatchEvent('pointerdown',{pointerId:8,pointerType:touch?'touch':'mouse',button:0,clientX:frame.x+100,clientY:frame.y+80});
  await page.locator('.imageEditor').dispatchEvent('pointermove',{pointerId:8,pointerType:touch?'touch':'mouse',clientX:frame.x+120,clientY:frame.y+85});
  await page.locator('.imageEditor').dispatchEvent('pointerup',{pointerId:8,pointerType:touch?'touch':'mouse'});
  assert.equal(await page.locator('.cropBar .submit').isEnabled(),true);
  page.once('dialog',d=>d.dismiss());
  await page.evaluate(()=>cropImageOnPage({data:{target:document.querySelector('#second')}}));
  assert.equal(await page.locator('#photo').evaluate(e=>e.style.visibility),'hidden','cancel keeps current edited crop');
  assert.equal(await page.locator('.imageEditor').count(),1);
  await page.screenshot({path:`/private/tmp/fluxo-on-page-crop-${width}.png`});
  await page.locator('.cropBar .submit').click();await page.locator('.imageEditor').waitFor({state:'detached'});
  assert.ok(await page.evaluate(()=>calls.some(u=>u.includes('updateMediaCrop'))),'shared crop save endpoint');assert.equal(await page.locator('#photo').evaluate(e=>getComputedStyle(e).visibility),'visible');assert.deepEqual(errors,[]);await page.close();
 }
 console.log('PASS on-page crop: desktop/touch/narrow, source position, shared save, preview restored, no JS errors');
}finally{await browser.close();}
