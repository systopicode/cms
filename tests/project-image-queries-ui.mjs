import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile,mkdir} from 'node:fs/promises';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname,out='/private/tmp/fluxo-media-fixes-20261126/project-queries';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const profile of ['wide','range']){
 const data=JSON.parse(execFileSync('php',['-d','zend.exception_ignore_args=1','tests/project-image-queries-fixture.php',profile],{encoding:'utf8'}));
 const context=await browser.newContext({viewport:{width:1440,height:1000},hasTouch:true}),p=await context.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.route('https://queries.test/**',async r=>{try{await r.fulfill({body:await readFile('/private/tmp/fluxo-image-device-fixture/'+new URL(r.request().url()).pathname.slice(1)),contentType:'image/webp'})}catch{await r.fulfill({status:404,body:''})}});
 const widths=profile==='wide'?[[769,'desktop'],[768,'mobile'],[700,'mobile'],[390,'mobile']]:[[1025,'desktop'],[1024,'tablet'],[900,'tablet'],[640,'tablet'],[639,'mobile'],[390,'mobile']];
 for(const [width,device] of widths){
  // Native frontend: the project media query itself selects the source.
  await p.setViewportSize({width,height:1000});
  await p.setContent('<base href="https://queries.test/"><meta name="viewport" content="width=device-width"><style>body{margin:0}img{display:block;width:100%;height:auto}</style>'+data.frontend);
  const suffix={desktop:'16by9',tablet:'1by1',mobile:'9by16'}[device];
  await p.waitForFunction(s=>{const i=document.querySelector('img');return i.complete&&i.naturalWidth>0&&i.currentSrc.includes(s)},suffix);
  // Admin Fit: an inner preview can be narrower than the browser window.
  await p.setViewportSize({width:1440,height:1000});
  await p.setContent(`<base href="https://queries.test/"><style>body{margin:0}.cmsPreview{container-type:inline-size;container-name:cms-preview;width:${width}px}article{width:100%}img{width:100%;height:auto}${data.previewCss}</style><body data-cms-device="fit"><div class="cmsPreview"><article class="cmsContent"><div class="editor" contenteditable="true">${data.editor}</div></article></div>`);
  await p.evaluate(d=>window.editorImageWidths=d,data.widths);await p.addScriptTag({path:root+'public/js/ext/jquery.js'});await p.addScriptTag({path:root+'public/js/gui/HTMLeditor.funcs.js'});
  assert.equal(await p.evaluate(()=>mediaPreviewDevice(document.querySelector('img'))),device,'Fit follows project CSS at '+width);
  assert.equal(await p.locator('article').evaluate(a=>getComputedStyle(a).getPropertyValue('--layout-device').trim()),device,'Real project layout query also matches');
  await p.evaluate(()=>applyMediaImageView(document.querySelector('img')));
  await p.waitForFunction(s=>{const i=document.querySelector('img');return i.complete&&i.naturalWidth>0&&i.src.includes(s)},suffix);
  if(width===390)await p.screenshot({path:out+'/'+profile+'-touch.png'});
  console.log(`PASS ${profile} ${width}: native source, project CSS and admin Fit agree on ${device}`);
 }
 assert.deepEqual(errors,[]);await context.close();
}}finally{await browser.close()}
