import assert from 'node:assert/strict';
import {readFile,readdir,writeFile,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {tmpdir} from 'node:os';
import {createServer} from 'node:http';
import {resolve,extname} from 'node:path';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const cmsRoot=resolve(new URL('..',import.meta.url).pathname),sysRoot=resolve(cmsRoot,'../system');
const artifacts=process.env.FLUXO_GUI_ARTIFACTS||resolve(tmpdir(),'fluxo-gui-followup');await mkdir(artifacts,{recursive:true});
for(const scenario of ['site','global','editor']){const out=scenario==='editor'?'site-editor.html':'defaults-'+scenario+'.html';await writeFile(resolve(artifacts,out),execFileSync(process.env.FLUXO_PHP||'php',[cmsRoot+'/tests/gui-followup-fixture.php',scenario]));}
const sheets=(await readdir(sysRoot+'/public/css')).filter(n=>/^\d.*\.css$/.test(n)||n==='x_responsive.css');
const headers='<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CMS component review</title>'+sheets.map(n=>'<link rel="stylesheet" href="/public/css/'+n+'">').join('')+'<link rel="stylesheet" href="/cms/pages.mml.css"><link rel="stylesheet" href="/cms/module.css"><style>:root{--cmsMainColor:#203d48;--cmsTextColor:#172024;--cmsCanvas:#fff;--cmsLine:#dbe1e3;--cmsError:#b32630;--cmsConfirm:#17662b;--cmsSuccess:#17662b;--cmsCardPadding:1.5rem;--cmsRadius:8px}body{margin:0}.mmlContent{display:none}body.cms main{padding:1rem}.siteEditor{max-width:850px;margin:auto}.cmslightbox footer{display:flex} [hidden]{display:none!important}</style><body class="cms"><main><button id="opener">Open overview</button><div class="mmlContent"><div><ul><li id="tile">Upload tile</li></ul></div></div></main><footer class="cmsStatusbar"><span class="cmsJobs"></span></footer>';
const receipts=new Set(),attempts=[],created=[];let releaseHeld;
const server=createServer(async(req,res)=>{
 try{
 if(req.url==='/'){res.setHeader('Content-Type','text/html');res.end(headers);return;}
 if(req.url==='/upload'){
  let raw='';for await(const chunk of req)raw+=chunk;const d=JSON.parse(raw);attempts.push(d.name);
  if(d.name==='held.png')await new Promise(done=>releaseHeld=done);
  if(receipts.has(d.uploadKey)){res.setHeader('Content-Type','application/json');res.end('{"uploadComplete":true}');return;}
  if(d.name==='second.png'&&!created.includes(d.name)){created.push(d.name);receipts.add(d.uploadKey);res.writeHead(503);res.end('response unavailable');return;}
  receipts.add(d.uploadKey);created.push(d.name);res.setHeader('Content-Type','application/json');res.end('{}');return;
 }
 let path=req.url.startsWith('/public/')?sysRoot+req.url:cmsRoot+'/panels/cms/010_pages/'+req.url.replace('/cms/','');
 if(!path.startsWith(sysRoot+'/public/')&&!path.startsWith(cmsRoot+'/panels/cms/010_pages/'))throw Error();
 res.setHeader('Content-Type',({'.css':'text/css','.svg':'image/svg+xml','.js':'text/javascript','.woff2':'font/woff2'})[extname(path)]||'application/octet-stream');res.end(await readFile(path));
 }catch{res.writeHead(404);res.end();}
});
await new Promise(done=>server.listen(0,'127.0.0.1',done));
const origin='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({channel:'chrome',headless:true});
try{
 const page=await browser.newPage();page.setDefaultTimeout(6000);const errors=[];page.on('pageerror',e=>{errors.push(e.message);console.log('PAGEERROR',e.message);});
 await page.goto(origin);
 const prepare=()=>{
  window.$=()=>({keydown(){}});window.calls=[];
  window.sys={lib:{applyParentConstructor(){},add(setup,construct){if(setup.route==='panels.cms.pages.upload'){window.upload={};construct.call(upload);}else if(setup.route==='panels.cms.pages'){window.pages={};construct.call(pages);}}},core:{server:{}}};
  sys.core.server.call=class{constructor(data){this.data=data||{};}send(){const result={};const p=fetch('/upload',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(this.data)}).then(r=>{if(!r.ok)throw Error('Request failed');return r.json();});result.done=fn=>{result.doneFn=fn;return result;};result.fail=fn=>{p.then(d=>result.doneFn?.(d)).catch(fn);return result;};return result;}};
 };
 await page.evaluate(prepare);
 await page.addScriptTag({path:sysRoot+'/public/js/ext/jquery.js'});
 for(const name of ['ui','icons','jobs'])await page.addScriptTag({path:sysRoot+'/public/js/gui/'+name+'.js'});
 await page.addScriptTag({path:cmsRoot+'/panels/cms/010_pages/upload/panel.js'});
 await page.evaluate(()=>{upload.refreshLibrary=()=>{};document.querySelector('#opener').onclick=()=>upload.queueDirect(['first.png','second.png','third.png'].map(name=>new File(['fixture'],name,{type:'image/png'})),{accept:'.png',node_parent_node_id:10,group:'main'},document.querySelector('#tile'));});
 await page.locator('#opener').click();
 
 await page.waitForFunction(()=>document.querySelector('.uploadQueueFiles li[data-state="failed"]')).catch(async e=>{console.log('STATE',JSON.stringify(await page.evaluate(()=>upload.queues.map(b=>b.entries.map(e=>({state:e.state,error:e.error}))))),attempts,created);throw e;});
 assert.deepEqual(await page.locator('.uploadQueueFiles li').evaluateAll(es=>es.map(e=>e.dataset.state)),['success','failed','pending']);
 assert.match(await page.locator('.uploadQueueFiles li[data-state=failed]').innerText(),/connection|fetch|interrupted|Request failed/i);
 assert.equal(await page.locator('.uploadSummary').getAttribute('role'),'dialog');
 if(process.env.FLUXO_AXE_PATH){await page.addScriptTag({path:process.env.FLUXO_AXE_PATH});
 const axeResult=await page.evaluate(()=>axe.run('.uploadSummary',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}}));
 await writeFile(resolve(artifacts,'axe-upload.json'),JSON.stringify(axeResult.violations.map(v=>({id:v.id,impact:v.impact,description:v.description,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))})),null,2));
 console.log('AXE',axeResult.violations.map(v=>v.id));assert.deepEqual(axeResult.violations,[],'upload dialog has no automated WCAG A/AA violations');}
 for(const [width,height,touch] of [[1440,960,false],[768,1024,true],[390,844,true]]){
  await page.setViewportSize({width,height});
  assert.ok(await page.locator('.uploadQueueRetry').isVisible());
  assert.ok(await page.locator('.uploadSummary > section').evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1;}),'dialog fits viewport '+width);
  await page.screenshot({path:resolve(artifacts,'uploads-'+width+'.png')});
  if(width===1440)await page.locator('.uploadSummary > section').screenshot({path:resolve(artifacts,'upload-overview.png')});
 }
 await page.locator('.uploadQueueRetry').click();
 await page.waitForFunction(()=>document.querySelectorAll('.uploadQueueFiles li[data-state="success"]').length===3);
 assert.deepEqual(created,['first.png','second.png','third.png'],'lost final reply retry does not create duplicates');
 assert.deepEqual(attempts,['first.png','second.png','second.png','third.png'],'successful first entry never replays');
 await page.locator('[data-box-close]').press('Escape');
 assert.equal(await page.locator('.uploadSummary').count(),0);
 await page.locator('.uploadQueueOpen').click();assert.equal(await page.locator('.uploadQueueFiles li[data-state=success]').count(),3);
 await page.locator('[data-box-close]').click();

 await page.setViewportSize({width:1440,height:960});
 await page.evaluate(()=>upload.queueDirect(['held.png','held-next.png'].map(name=>new File(['x'],name,{type:'image/png'})),{accept:'.png',node_parent_node_id:10,group:'main'},document.querySelector('#tile')));
 await page.locator('.uploadSummary [data-box-close]').click();
 await page.locator('footer.cmsStatusbar .cmsJobs button').click();
 assert.equal(await page.locator('.uploadSummary').isVisible(),true,'active progress Details opens the same overview');
 while(!releaseHeld)await new Promise(done=>setTimeout(done,10));releaseHeld();
 await page.waitForFunction(()=>!upload.queues.at(-1).running);
 await page.locator('.uploadSummary [data-box-close]').click();
 // Real defaults templates: site values and global values can coexist.
 for(const scenario of ['site','global']){
  await page.evaluate(html=>document.body.insertAdjacentHTML('beforeend',html),await readFile(resolve(artifacts,'defaults-'+scenario+'.html'),'utf8'));
  await page.waitForFunction(()=>document.querySelector('.metaDefaults')?.getAttribute('role')==='dialog');
  assert.equal(await page.locator('a:has-text("Edit theme share image")').getAttribute('href'),'/public/cms/settings/theme/');
  if(scenario==='site')assert.equal(await page.locator('a:has-text("Edit site defaults")').first().getAttribute('href'),'/public/cms/pages/?site=default');
  else assert.ok(await page.locator('a:has-text("Edit global defaults")').count()>0);
  await page.screenshot({path:resolve(artifacts,'meta-'+scenario+'.png')});
  await page.evaluate(()=>document.querySelector('.metaDefaults').remove());
 }
 await page.evaluate(html=>document.querySelector('body > main').innerHTML=html,await readFile(resolve(artifacts,'site-editor.html'),'utf8'));
 await page.addScriptTag({path:cmsRoot+'/panels/cms/010_pages/panel.js'});
 await page.evaluate(()=>{window.renameCalls=[];document.querySelector('[data-on_click=reviewSiteRename]').onclick=function(){pages.reviewSiteRename({element:this,data:{dataset:{site_id:'3'}},executeAction(action){renameCalls.push(action);}});};});
 await page.locator('input[name=siteName]').fill('renamed.test');assert.equal(await page.evaluate(()=>renameCalls.length),0);
 let review='';page.once('dialog',async d=>{review=d.message();await d.dismiss();});
 await page.getByRole('button',{name:'Rename site…',exact:true}).click();assert.equal(await page.evaluate(()=>renameCalls.length),0);
 assert.match(review,/sites\/example.test\/.*sites\/renamed.test\//);assert.match(review,/DNS/);
 page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'Rename site…',exact:true}).click();assert.deepEqual(await page.evaluate(()=>renameCalls),['renameSite']);
 for(const width of [1440,768,390]){await page.setViewportSize({width,height:960});await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:resolve(artifacts,'site-'+width+'.png')});}

 await page.evaluate(()=>{
  const host=document.createElement('div');host.id='fold-test';host.style.cssText='position:fixed;top:0;left:0;z-index:9000;background:white;padding:1rem';
  host.innerHTML='<a class="fold" data-focus-id="test-fold" role="button" tabindex="0" title="Show child pages"><i class="cmsIcon cmsIcon-chevron-right"></i></a><input id="other-focus" aria-label="Another field">';document.body.append(host);
  host.querySelector('a').onclick=function(){this.replaceWith(this.cloneNode(true));pages.onUpdateTree();};
 });
 await page.locator('#fold-test a').press('Enter');
 assert.equal(await page.evaluate(()=>document.activeElement.dataset.focusId),'test-fold','keyboard focus returns to matching fold after AJAX replacement');
 await page.evaluate(()=>document.querySelector('#fold-test a').onclick=function(){document.querySelector('#other-focus').focus();this.replaceWith(this.cloneNode(true));pages.onUpdateTree();});
 await page.locator('#fold-test a').press('Space');
 assert.equal(await page.evaluate(()=>document.activeElement.id),'other-focus','a newer focus choice is not stolen by a tree response');
 await page.evaluate(()=>document.querySelector('#fold-test').remove());
 // Dynamic icon-only controls use their action's own wording.
 await page.evaluate(()=>document.body.insertAdjacentHTML('beforeend','<button id="named-icon" aria-label="Restore"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i></button><button id="titled-icon" title="Download"><i class="cmsIcon cmsIcon-download"></i></button>'));
 await page.waitForFunction(()=>document.querySelector('#named-icon').title==='Restore');
 assert.equal(await page.locator('#titled-icon').getAttribute('aria-label'),'Download');

 const touchContext=await browser.newContext({viewport:{width:768,height:1024},hasTouch:true,isMobile:true});
 const touch=await touchContext.newPage();touch.on('pageerror',e=>errors.push(e.message));await touch.goto(origin);await touch.evaluate(prepare);
 await touch.addScriptTag({path:sysRoot+'/public/js/ext/jquery.js'});
 for(const name of ['ui','icons','jobs'])await touch.addScriptTag({path:sysRoot+'/public/js/gui/'+name+'.js'});
 await touch.addScriptTag({path:cmsRoot+'/panels/cms/010_pages/upload/panel.js'});
 await touch.evaluate(()=>{
  upload.refreshLibrary=()=>{};
  const input=document.createElement('input');input.type='file';input.multiple=true;input.id='touch-picker';input.accept='.png';input.setAttribute('aria-label','Upload files');document.body.append(input);
  input.onchange=()=>upload.queueDirect(input.files,{accept:'.png',node_parent_node_id:10,group:'main'},document.querySelector('#tile'));
 });
 assert.equal(await touch.evaluate(()=>matchMedia('(pointer:coarse)').matches),true);
 await touch.locator('#touch-picker').setInputFiles(['touch-first.png','touch-second.png'].map(name=>({name,mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6OZ8AAAAASUVORK5CYII=','base64')})));
 await touch.waitForFunction(()=>document.querySelectorAll('.uploadQueueFiles li[data-state=success]').length===2);
 await touch.locator('[data-box-close]').tap();assert.equal(await touch.locator('.uploadSummary').count(),0);
 await touch.locator('.uploadQueueOpen').tap();assert.equal(await touch.locator('.uploadSummary').isVisible(),true);
 await touch.screenshot({path:resolve(artifacts,'uploads-touch.png')});
 await touchContext.close();
 assert.deepEqual(errors,[]);
 console.log('PASS: queue failure/pending/success, explicit retry without duplicate after lost reply, close/reopen/Escape, desktop/iPad/narrow layouts and real touch file-input/taps, real meta provenance links, rename review cancel/confirm without writes, dynamic accessible icon names; no JS errors.');
}finally{await browser.close();await new Promise(done=>server.close(done));}
