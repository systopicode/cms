/** Real library markup, compact action handler and shared mobile dialog controller. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
for(const file of ['../public/css/pages.css','../panels/admin/010_pages/pages.mml.css','../panels/admin/010_pages/panel.css'])css+='\n'+await readFile(new URL(file,import.meta.url),'utf8');
for(const name of new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1])))css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const shell=await readFile(root+'panels/admin/body.tpl.php','utf8');
const chrome=shell.match(/<header class="cmsMobileHeader[\s\S]*?<\/header>/)[0].replace(/<\?(?:php|=)[\s\S]*?\?>/g,'');
const library=execFileSync('php',['tests/file-editor-tabs-fixture.php','image','library'],{encoding:'utf8'});
const frame=execFileSync('php',['tests/file-editor-tabs-fixture.php','image','master'],{encoding:'utf8'});
const panel=await readFile(new URL('../panels/admin/010_pages/panel.js',import.meta.url),'utf8');
const touch=panel.slice(panel.indexOf('// The clipboard contains a media reference'),panel.indexOf('function changeSrc'));
const ui=await readFile(root+'public/js/gui/ui.js','utf8');
const dialogs=ui.slice(ui.indexOf('/** Shared focus and keyboard behavior;'),ui.indexOf('// Empty tables keep'));
const out='/private/tmp/fluxo-library-editor';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1024,390])for(const pointer of ['mouse','touch']){
 const p=await browser.newPage({viewport:{width,height:900},hasTouch:pointer==='touch'}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 p.setDefaultTimeout(5000);
 await p.route('https://fixture.test/**',r=>r.request().url().includes('sample.png')?r.fulfill({body:'<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#628d99"/></svg>',contentType:'image/svg+xml'}):r.fulfill({body:'<html></html>',contentType:'text/html'}));
 await p.goto('https://fixture.test/public/admin/pages/?id=10');
 await p.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms pages">${chrome}<main><div class="main cms"><nav><ul><li class="active"><a href="/public/admin/pages/">Pages</a></li></ul></nav></div><div class="pylon cms"><nav><div class="tree"></div></nav><footer><div class="mml"><div class="mmlContent"><div>${library}</div></div></div></footer></div><div class="content"><header class="cms"><h1>Test page</h1><button data-cms-window="media">Media Library</button></header><div class="flexRow"><div class="flexColumn">Test page preview</div><aside class="cms"></aside></div></div></main><button class="cmsMobileBackdrop" data-cms-close aria-label="Close window"></button></body>`);
 await p.addScriptTag({path:root+'public/js/ext/jquery.js'});
 await p.evaluate(()=>{window.obj={extend:(...args)=>Object.assign({},...args)};window.sys={core:{}};for(const n of ['l','p','t','c','f'])window[n]=()=>({open(){}});window.cmsFeedback={hasChanges:()=>false,refresh(){},notice(){}};});
 await p.addScriptTag({path:root+'public/js/core/event/event.js'});await p.addScriptTag({path:root+'public/js/core/event/event.drag.js'});
 await p.evaluate(()=>{sys.processEvent=e=>new sys.core.event.drag({e},{});document.querySelectorAll('.mmlContent [data-drag_type="media"]').forEach(e=>$(e).on('pointerdown',sys.processPointerDown));});
 await p.addScriptTag({path:root+'panels/admin/mobile.js'});await p.addScriptTag({content:dialogs});await p.addScriptTag({content:touch});
 await p.evaluate(({pointer,frame})=>{
  document.documentElement.dataset.pointer=pointer;document.body.dataset.cmsWindow='edit';window.opened=[];
  document.addEventListener('click',event=>{
   const edit=event.target.closest('.thumbButtons a.ajax');
   if(edit){event.preventDefault();opened.push(edit.href);history.replaceState(null,'',edit.href);const template=document.createElement('template');template.innerHTML=frame;document.querySelector('.pageToolBox').before(template.content);document.dispatchEvent(new Event('cms:rendered'));}
   const close=event.target.closest('.fileEditor [aria-label=Close]');if(close){event.preventDefault();close.closest('.fileEditor').remove();history.replaceState(null,'','/public/admin/pages/?id=10');document.dispatchEvent(new Event('cms:rendered'));}
  });
  document.dispatchEvent(new Event('cms:rendered'));
 },{pointer,frame});
 const input=pointer==='touch'?'tap':'click';await p.getByRole('button',{name:'Media Library',exact:true})[input]();
 const box=p.locator('.pageToolBox[data-page-tool=media]');await box.waitFor();
 await box.locator('li[data-drag_type=media]').first()[input]({trial:true});
 await p.screenshot({path:`${out}/library-${width}-${pointer}.png`});
 if(pointer==='touch'){
  const tile=box.locator('li[data-drag_type=media]').first();await tile.tap();
  assert.equal(await p.evaluate(()=>sys.pagesMedia.hasClipboard()),false,'Short tap reveals actions without copying');
  assert.ok(await tile.evaluate(e=>e.classList.contains('touchOpen')));
  await box.getByRole('heading',{name:'Media Library',exact:true}).tap();
  assert.equal(await tile.evaluate(e=>e.classList.contains('touchOpen')),false,'Tap outside closes the actions');
  await tile.tap({trial:true});
  const r=await tile.boundingBox(),cdp=await p.context().newCDPSession(p);
  const contact={x:r.x+r.width/2,y:r.y+r.height/2,id:1};
  await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[contact]});
  await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{...contact,y:contact.y-30}]});
  await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  await p.waitForTimeout(450);
  assert.equal(await p.evaluate(()=>sys.pagesMedia.hasClipboard()),false,'Moving before the hold threshold does not copy');
  await tile.tap({trial:true});
  await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[contact]});
  await p.waitForFunction(()=>sys.pagesMedia.hasClipboard());
  assert.ok(await tile.evaluate(e=>e.hasAttribute('data-media-copied')),'Long press copies the media reference');
  await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
  assert.equal(await tile.evaluate(e=>e.classList.contains('touchOpen')),false,'Releasing a hold does not also reveal short-tap actions');
  assert.equal(await p.locator('.fileEditor').count(),0,'Long press never opens the editor');
  await p.waitForTimeout(450);
 }
 for(let i=0;i<2;i++){
  const tile=box.locator('li[data-drag_type=media]').first();await tile.locator('img').first()[input]();
  const edit=tile.getByRole('link',{name:'Edit: Sample',exact:true});await edit.waitFor({state:'visible'});
  await p.screenshot({path:`${out}/actions-${width}-${pointer}.png`});
  assert.match(await edit.getAttribute('href'),/^\/public\/admin\/pages\/editor\/file\/image\/\?target_id=42$/);
  await edit[input]();await p.locator('.fileEditor').waitFor({state:'visible'});
  await p.waitForFunction(()=>document.querySelector('.fileEditor')?.getAttribute('aria-modal')==='true');
  assert.equal(await p.locator('.fileEditor').getAttribute('aria-hidden'),null,'Editor is the active front dialog');
  assert.equal(await p.locator('.fileEditor').evaluate(e=>e.style.getPropertyValue('--cmsDialogLevel')),'1','Editor stacks above the existing library');
  await p.screenshot({path:`${out}/editor-${width}-${pointer}.png`});
  await p.locator('.fileEditor [aria-label=Close]')[input]();await p.waitForFunction(()=>!document.querySelector('.fileEditor'));
  assert.ok(await box.isVisible(),'Closing the editor returns to the existing library');
 }
 assert.equal(await p.evaluate(()=>opened.length),2);assert.deepEqual(errors,[]);console.log(`PASS library ${width} ${pointer}: short tap reveals Edit; long press keeps Clipboard on touch; correct absolute address; active editor dialog; return and reopen; no browser errors`);await p.close();
}}finally{await browser.close();}
