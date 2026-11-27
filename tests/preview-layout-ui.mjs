import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {mkdir} from 'node:fs/promises';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const data=JSON.parse(execFileSync('php',['tests/preview-layout-fixture.php'],{encoding:'utf8'}));
const out='/private/tmp/fluxo-media-fixes-20261126/preview-layout';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try {for(const width of [1440,834,768,390]){
 const page=await browser.newPage({viewport:{width,height:1000},hasTouch:width<1440}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 const base='body{margin:0;font:16px sans-serif}*{box-sizing:border-box}';
 await page.setContent(`<style>${base}${data.css}</style><main>${data.markup}</main>`);await page.locator('img').evaluate(i=>i.decode());
 const measure=()=>page.evaluate(()=>Object.fromEntries(['img','.caption','.text'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return [s,{x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom}]})));
 const expected=await measure();
 for(const main of [false,true])for(const file of ['responsive','site']){
  await page.setViewportSize({width:1600,height:1000});
  await page.setContent(`<style>${base}.cmsPreview{width:${width}px;container-type:inline-size;container-name:cms-preview}article{width:100%}${file==='responsive'?data.previewCss:''}</style>${file==='site'?data.scopedCss:''}<div class="cmsPreview"><article class="cmsContent">${main?'<main>':''}${data.markup}${main?'</main>':''}</article></div>`);
  await page.locator('img').evaluate(i=>i.decode());const actual=await measure();
  for(const selector of Object.keys(expected))for(const key of Object.keys(expected[selector]))assert.ok(Math.abs(expected[selector][key]-actual[selector][key])<1,JSON.stringify({width,main,selector,key,expected,actual}));
  assert.ok(actual.img.bottom<=actual['.caption'].y,'Caption never overlays the image');
  if(width===390||width===834||width===1440)await page.screenshot({path:out+`/preview-${width}-${main?'main':'canvas'}.png`});
  console.log(`PASS ${width}, ${file} CSS, ${main?'template main':'omitted layout main'}: native and CMS geometry agree, image and caption do not overlap`);
 }
 assert.deepEqual(errors,[]);await page.close();
}}finally{await browser.close()}
