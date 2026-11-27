/** Real native srcset/picture + cold production renderer; scripts disabled and enabled. */
import assert from 'node:assert/strict';
import {mkdir,writeFile} from 'node:fs/promises';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const base='http://127.0.0.1:18797',out='/private/tmp/fluxo-native-image-qa';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true}),report=[];
try{for(const javaScriptEnabled of [false,true])for(const width of [1440,834,390])for(const dpr of [1,2]){
 const context=await browser.newContext({javaScriptEnabled,viewport:{width,height:1000},deviceScaleFactor:dpr,hasTouch:width<1440});const page=await context.newPage(),errors=[],requests=[];
 page.setDefaultTimeout(25000);page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400)errors.push(r.status()+' '+r.url())});page.on('request',r=>requests.push({method:r.method(),url:r.url(),type:r.resourceType()}));
 // Page timers are disabled as well when scripting is off: poll from the test process.
 const loaded=async(selector,crop='')=>{const end=Date.now()+25000;while(Date.now()<end){if(await page.locator(selector).evaluateAll((images,crop)=>images.length>0&&images.every(i=>i.complete&&i.naturalWidth>0&&i.currentSrc.includes(crop)),crop))return;await new Promise(resolve=>setTimeout(resolve,50));}throw new Error('Image did not load: '+selector+' '+crop);};
 await page.request.post(base+'/reset');assert.equal(Object.keys(await (await page.request.get(base+'/files')).json()).length,0);
 await page.goto(base+'/?auto=1',{waitUntil:'domcontentloaded'});
 const expected=width<=560?'9by16':width<=960?'1by1':'16by9';
 await loaded('#hero',expected);
 await loaded('#master,#plainPicture,#containerFallback');
 assert.equal(await page.locator('picture picture').count(),0,'No nested picture wrappers');
 for(const selector of ['#hero','#master','#plainPicture','#containerFallback','#below']){
  assert.equal(await page.locator(selector).evaluate(i=>i.parentElement.tagName),'PICTURE','Every output uses picture, including legacy and container calls');
 }
 const before=await page.locator('#hero').evaluate(i=>({w:i.clientWidth,h:i.clientHeight,src:i.currentSrc,hasLoader:!!document.querySelector('img[data-media-src],img[data-media-srcset]'),sys:typeof window.sys}));
 const ratio=width<=560?9/16:width<=960?1:16/9;assert.ok(Math.abs(before.w/before.h-ratio)<.01,'Correct crop and intrinsic ratio without scripts');assert.equal(before.hasLoader,false);assert.equal(before.sys,'undefined');
 const files=await (await page.request.get(base+'/files')).json();assert.ok(files[new URL(before.src).pathname.split('/').pop()],'The browser request actually rendered a missing variant');
 if(javaScriptEnabled){assert.equal(Object.keys(files).some(name=>name.includes('4by3')),false,'Native lazy loading does not request the distant image');assert.equal(requests.some(r=>r.url.includes('4by3')),false);}
 assert.equal(requests.some(r=>r.method==='HEAD'||r.type==='script'),false,'Native loading needs no JS assets or preflight HEAD');
 await page.screenshot({path:`${out}/native-${width}-${dpr}x-${javaScriptEnabled?'enabled':'disabled'}.png`});
 await page.getByRole('link',{name:'Zum späteren Bild'})[width<1440?'tap':'click']();
 await loaded('#below','4by3');
 assert.deepEqual(errors,[]);report.push({javaScriptEnabled,width,dpr,before,files,requests,errors});console.log(`PASS ${width} ${dpr}x JS ${javaScriptEnabled?'enabled':'disabled'}: cold native picture, Master and container fallback, selected crop, scroll/touch; no HEAD, script or browser/request errors${javaScriptEnabled?'; lazy image deferred until scrolling':''}`);await context.close();
}
for(const width of [1440,834,390]){const one=report.find(r=>r.width===width&&r.dpr===1&&!r.javaScriptEnabled),two=report.find(r=>r.width===width&&r.dpr===2&&!r.javaScriptEnabled);const pixels=r=>r.files[new URL(r.before.src).pathname.split('/').pop()].pixels[0];assert.ok(pixels(two)>=pixels(one),'Native density selection supplies sufficient pixels');}
}finally{await writeFile(out+'/report.json',JSON.stringify(report,null,2));await browser.close()}
