/** Real public HTML + adoption/navigation, and the shared anonymous output check. */
import {readFile,mkdir,writeFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const out=process.env.FLUXO_SEARCH_QA_DIR || '/private/tmp/fluxo-search-output-implementation-20261127';
await mkdir(out,{recursive:true});
const base='https://dev.local/projects/galerie-lattemann.de/public/';
const panelSource=await readFile(new URL('../panels/admin/010_pages/panel.js',import.meta.url),'utf8');
const checkSource=panelSource.slice(panelSource.indexOf('async function cmsCheckPageOutput'));
const formsCSS=await readFile(new URL('../../system/public/css/50_forms.css',import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true}),report=[];
try {
 for(const width of [1440,834,390]) {
  const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width,height:1000},hasTouch:width<1440}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
  await page.addInitScript(()=>{new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes){if(!window.firstServerMain&&node.nodeType===1){const main=node.matches('main')?node:node.querySelector('main');if(main)window.firstServerMain=main}}}).observe(document,{childList:true,subtree:true});});
  const response=await page.goto(base+'galerie/',{waitUntil:'domcontentloaded'});
  assert.equal(response.status(),200);const raw=await response.text();assert.ok(raw.includes('<picture>')&&raw.includes('cms-layer:body'));
  await page.waitForFunction(()=>window.sys?.dom?.body);
  assert.equal(await page.evaluate(()=>firstServerMain===document.querySelector('main')),true,'First server nodes are adopted');
  assert.equal(await page.locator('main').count(),1);assert.equal(await page.locator('main picture').count(),3);
  assert.equal(await page.locator('link[rel=canonical]').count(),1);
  await page.screenshot({path:out+`/galerie-${width}.png`});
  const press=width<1440?'tap':'click';
  await page.locator('nav a').filter({hasText:/^Ausstellungen$/}).first()[press]();await page.waitForURL('**/ausstellungen/');
  await page.locator('.exhibition-gallery-link').first().waitFor();
  await page.waitForFunction(()=>document.querySelector('link[rel=canonical]')?.href===location.href);
  assert.equal(await page.locator('title').count(),1);
  const data=await page.locator('script[data-cms-meta][type="application/ld+json"]').textContent();assert.equal(JSON.parse(data)['@graph'][0].url,page.url());
  await page.locator('.exhibition-gallery-link').first()[press]();
  await page.locator('dialog.lightbox[open]').waitFor();
  const before=await page.locator('.lightbox-counter').textContent();
  if (width === 390) {
   const client=await context.newCDPSession(page),box=await page.locator('.lightbox-stage').boundingBox();
   for(const [type,fraction] of [['touchStart',.8],['touchMove',.5],['touchMove',.2],['touchEnd',.2]]) await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:19,x:box.x+box.width*fraction,y:box.y+box.height/2}]});
  } else await page.getByRole('button',{name:'Nächstes Bild',exact:true})[press]();
  assert.notEqual(await page.locator('.lightbox-counter').textContent(),before);
  await page.getByRole('button',{name:'Schließen',exact:true})[press]();assert.equal(await page.locator('dialog.lightbox[open]').count(),0);
  assert.deepEqual(errors,[]);
  // Same component as the sidebar; the public request is made with no session cookie.
  await context.addCookies([{name:'search-check-test',value:'must-not-be-sent',url:base}]);
  await page.setContent(`<style>:root{--cmsMainColor:#203c47;--cmsHighlightColor:#ffb900;--cmsFontColor:#192326}body{margin:0;font:16px Arial;background:#eef2f3}.cmsOutputCheck{padding:24px;background:white;max-width:560px;margin:24px}.cmsOutputCheckResult li{padding:6px 0} ${formsCSS}</style><div class="cms"><section class="cmsOutputCheck"><h2>Search and AI</h2><button type="button" class="button" data-output-url="${base}galerie/" onclick="cmsCheckPageOutput(this)">Check public output</button><div class="cmsOutputCheckResult" role="status" aria-live="polite"></div></section></div>`);
  await page.addScriptTag({content:checkSource});
  let sentCookies;page.on('request',request=>{if(request.url()===base+'galerie/')sentCookies=request.headers()['cookie']});
  await page.getByRole('button',{name:'Check public output'})[press]();
  await page.waitForFunction(()=>document.querySelector('.cmsOutputCheckResult li'));
  assert.equal(await page.locator('.cmsOutputCheckResult').getByText(/^✕/).count(),0);assert.equal(sentCookies,undefined);
  assert.equal(await page.getByRole('button',{name:'Check public output'}).isEnabled(),true);
  await page.screenshot({path:out+`/output-check-${width}.png`});
  await page.route('**/output-check-invalid-fixture',route=>route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><title>Empty</title><body></body>'}));
  await page.locator('[data-output-url]').evaluate((el,url)=>el.dataset.outputUrl=url,base+'output-check-invalid-fixture');
  await page.getByRole('button',{name:'Check public output'})[press]();
  await page.waitForFunction(()=>document.querySelector('.cmsOutputCheckResult').textContent.includes('✕ One canonical address'));
  assert.ok((await page.locator('.cmsOutputCheckResult').textContent()).includes('✕ Content is in the first HTML'));
  report.push({width,serverNodesAdopted:true,navigation:true,lightbox:true,anonymousOutputCheck:true,invalidOutputDetected:true});
  console.log(`PASS ${width}: server DOM preserved; metadata/navigation/lightbox; anonymous output check and failure detection`);
  await context.close();
 }
 for(const width of [1440,834,390]) {
  const context=await browser.newContext({ignoreHTTPSErrors:true,javaScriptEnabled:false,viewport:{width,height:1000}}),page=await context.newPage();
  await page.goto(base+'galerie/');assert.equal(await page.locator('main picture').count(),3);assert.ok((await page.locator('main').textContent()).length>300);
  assert.equal(await page.locator('main').evaluate(el=>getComputedStyle(el).visibility),'visible');
  await page.screenshot({path:out+`/no-script-${width}.png`});
  console.log(`PASS ${width}: content and pictures visible without JavaScript`);await context.close();
 }
 await writeFile(out+'/ui-report.json',JSON.stringify({status:'PASS',report},null,2));
} finally {await browser.close()}
