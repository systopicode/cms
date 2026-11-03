import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n')+'\n'+await fs.readFile('panels/admin/010_pages/pages.mml.css','utf8');
const markup=execFileSync('php',['tests/block2-media-fixture.php'],{encoding:'utf8'}),cropMarkup=execFileSync('php',['tests/crop-frame-fixture.php'],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1194,1024,390]){
 const page=await browser.newPage({viewport:{width,height:800},hasTouch:width<1400}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fixture.test/**',route=>route.fulfill({contentType:'image/svg+xml',body:route.request().url().includes('rebuild=')?'<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630"><rect width="1200" height="630" fill="teal"/></svg>':'<svg xmlns="http://www.w3.org/2000/svg" width="480" height="800"><rect width="480" height="800" fill="teal"/></svg>'}));
 await page.addInitScript(()=>{});
 await page.setContent(`<style>${css}</style><body class=cms><div class="cms cmslightbox fileEditor"><section><header>Edit image</header><div class=layout><main><div class=fileStage>${markup.replace(/<script>[\s\S]*?<\/script>/,'')}</div></main><aside class=cms>Metadata</aside></div></section></div></body>`);
 await page.addScriptTag({path:'../system/public/js/ext/jquery.js'});await page.addScriptTag({path:'../system/public/js/gui/dropdownMenus.js'});await page.addScriptTag({path:'../system/public/js/gui/imageEditor.js'});
 await page.evaluate(html=>{window.settings={siteRoot:'/'};window.sent=[];window.fetch=url=>{sent.push(url);return Promise.resolve(url.includes('getImageEditor')?{json:()=>Promise.resolve({html})}:{ok:true,headers:new Headers({'X-Image-Edit':'saved'})});};},cropMarkup);
 await page.addScriptTag({content:(await fs.readFile('panels/admin/010_pages/panel.js','utf8')).match(/function changeSrc\(src\) \{[\s\S]*?\n\}/)[0]});
 await page.addScriptTag({content:markup.match(/<script>([\s\S]*?)<\/script>/)[1]});
 await page.locator('.srcToChange').evaluate(e=>e.src='https://fixture.test/var/formats/tile/1/2/test.jpg');await page.waitForFunction(()=>document.querySelector('.srcToChange').naturalWidth===480);
 await page.evaluate(()=>changeSrc('https://fixture.test/var/formats/tile/1/2/test.jpg'));assert.match(await page.locator('.srcToChange').getAttribute('src'),/_preview=\d+/,'format preview bypasses stale browser cache');await page.waitForFunction(()=>document.querySelector('.srcToChange').complete);
 assert.equal(await page.locator('.fileToolbar button').count(),1,'only Edit remains beside normal format choice');assert.equal(await page.locator('.fileAdjust').count(),0);
 assert.equal(await page.getByRole('button',{name:'Edit image',exact:true}).evaluate(e=>e.classList.contains('submit')),true);
 const normalSize=await page.locator('.frameView').evaluate(e=>e.getBoundingClientRect().width);
 const fitBox=await page.locator('.frameView').boundingBox();const panBefore=await page.locator('.frameView').evaluate(e=>e.style.transform);
 await page.mouse.move(fitBox.x+fitBox.width/2,fitBox.y+fitBox.height/2);await page.mouse.down();await page.mouse.move(fitBox.x+fitBox.width/2+30,fitBox.y+fitBox.height/2+20);await page.mouse.up();
 assert.equal(await page.locator('.frameView').evaluate(e=>e.style.transform),panBefore,'fitted preview cannot be dragged');
 await page.getByRole('button',{name:'Zoom in',exact:true}).click();assert.ok(await page.locator('.frameView').evaluate(e=>e.getBoundingClientRect().width)>normalSize*1.2);
 await page.getByRole('button',{name:'Fit image',exact:true}).click();assert.ok(Math.abs(await page.locator('.frameView').evaluate(e=>e.getBoundingClientRect().width)-normalSize)<1);
 if(width<1400){await page.locator('.fileFrame').evaluate(e=>{const fire=(type,span)=>{const touches=[new Touch({identifier:1,target:e,clientX:100,clientY:100}),new Touch({identifier:2,target:e,clientX:100+span,clientY:100})];e.dispatchEvent(new TouchEvent(type,{bubbles:true,cancelable:true,touches}));};fire('touchstart',100);fire('touchmove',150);e.dispatchEvent(new TouchEvent('touchend',{bubbles:true,touches:[]}));});assert.ok(await page.locator('.frameView').evaluate(e=>e.getBoundingClientRect().width)>normalSize*1.4,'preview supports view-only pinch');await page.getByRole('button',{name:'Fit image',exact:true}).click();}
 assert.equal(await page.locator('.imageEditor').count(),0);assert.equal(await page.evaluate(()=>sent.length),0,'preview zoom is view only');
 await page.getByRole('button',{name:'Edit image',exact:true}).click();await page.locator('.unifiedImageEditor').waitFor();
 await page.getByRole('button',{name:'Cancel',exact:true}).click();await page.locator('.imageEditor').waitFor({state:'detached'});
 const afterCancel=await page.evaluate(()=>{const i=document.querySelector('.srcToChange').getBoundingClientRect(),v=document.querySelector('.frameView').getBoundingClientRect();return [i.width-v.width,i.height-v.height];});assert.ok(afterCancel.every(n=>Math.abs(n)<1),'cancel restores aligned frame');
 await page.getByRole('button',{name:'Edit image',exact:true}).click();await page.locator('.unifiedImageEditor').waitFor();
 await page.getByRole('button',{name:'Rotate right',exact:true}).click();await page.getByRole('button',{name:'Apply',exact:true}).click();await page.locator('.imageEditor').waitFor({state:'detached'});
 const aligned=await page.evaluate(()=>{const i=document.querySelector('.srcToChange').getBoundingClientRect(),v=document.querySelector('.frameView').getBoundingClientRect();return [i.x-v.x,i.y-v.y,i.width-v.width,i.height-v.height];});assert.ok(aligned.every(n=>Math.abs(n)<1),'preview frame aligned after unified save');
 assert.ok(Math.abs(await page.locator('.frameView').evaluate(e=>e.getBoundingClientRect().width/e.getBoundingClientRect().height)-1200/630)<.02,'frame follows changed source aspect ratio');
 assert.match(await page.evaluate(()=>sent.find(u=>u.includes('saveImageEdit'))),/target_id=1/,'save identifies exact media panel');
 await page.screenshot({path:`/private/tmp/fluxo-editor-valid-${width}.png`});assert.deepEqual(errors,[]);await page.close();
}console.log('PASS normal media template: Format + Edit only, unified save, exact target, preview frame, no JS errors');}finally{await browser.close();}
