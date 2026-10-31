import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const system='../system/';
const css=(await Promise.all((await fs.readdir(system+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile(system+'public/css/'+n,'utf8')))).join('\n');
const serverSource=await fs.readFile(system+'public/js/core/server/server.js','utf8');
const navStart=serverSource.indexOf('const mainItems =');
const navEnd=serverSource.indexOf('if (backupStep && !backupContinues)',navStart);
const navSync='{'+serverSource.slice(navStart,navEnd)+'}';
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [390,1194,1440]) {
  const page=await browser.newPage({viewport:{width,height:560},hasTouch:width<1400});const errors=[];page.on('pageerror',e=>errors.push(e.stack));
  await page.setContent(`<style>:root{--cmsStatusBar:26px;--cmsFrame:#203c48;--cmsCanvas:#fff;--cmsTextColor:#182024;--cmsRadius:6px;--cmsRadiusLg:12px;--cmsCardPadding:20px;--cmsBar:48px;--cmsLine:#ddd;--cmsMainColor:#203c48;--cmsActive:#c6dbe2;--cmsInteractive:#12627d;--cmsFontFamily:sans-serif}${css}</style><body class=cms><div id=nprogress><div class=bar></div></div><div class="pylon cms"><nav><ul><li><a><i></i><span data-nav-label="Longer navigation label">Longer navigation label</span></a></li></ul></nav></div><form class=ajax><input id=edit value=before data-on_blur=save></form><div class="cms message cmsActionMessages" data-system-messages><div class=error>Example error</div></div><footer class=cmsStatusbar><span class=cmsSaveState hidden></span></footer></body>`);
  await page.evaluate(()=>{window.$=()=>({keydown(){}});window.confirm=()=>{throw Error('Unexpected discard prompt')};window.sys={};});
  await page.addScriptTag({path:system+'public/js/gui/ui.js'});
  await page.evaluate(()=>document.querySelector('main')?.remove());
  await page.evaluate(()=>{document.body.insertAdjacentHTML('afterbegin','<main><div class=main><nav><ul><li data-name=pages class=active><a>Pages</a></li><li data-name=settings><a>Settings</a></li></ul></nav></div></main>');document.body.classList.add('settings')});
  await page.addScriptTag({content:navSync});
  assert.equal(await page.locator('div.main li.active').getAttribute('data-name'),'settings');
  assert.equal(await page.locator('div.main li.active a').getAttribute('aria-current'),'page');
  assert.equal(await page.locator('.cmsActionMessages').evaluate(e=>getComputedStyle(e).padding),'0px');
  assert.equal(await page.locator('.cmsActionMessages > div').evaluate(e=>getComputedStyle(e).marginBottom),'0px');
  if(width===1194) await page.screenshot({path:'/private/tmp/fluxo-block3-message.png'});
  const before=await page.locator('.pylon a').boundingBox();await page.locator('.pylon li').evaluate(e=>e.classList.add('active'));const after=await page.locator('.pylon a').boundingBox();assert.equal(before.width,after.width);assert.equal(before.height,after.height);
  const line=await page.locator('#nprogress').boundingBox();assert.equal(line.x,0);assert.equal(line.width,width);
  await page.locator('#edit').fill('draft');
  await page.evaluate(()=>{const box=document.createElement('dialog');box.id='remote';box.className='cms cmslightbox';box.setAttribute('data-feedback','parameters');box.innerHTML='<section><header><h1>Remote login</h1><button data-box-close onclick="this.closest(\'dialog\').close()">Close</button></header><main><form method=post><input id=code></form></main></section>';document.body.append(box);box.showModal()});
  await page.locator('#code').fill('A');await page.locator('[data-box-close]').click();await page.waitForFunction(()=>!document.querySelector('#remote').open);assert.ok(await page.evaluate(()=>cmsFeedback.hasChanges()),'page draft survives auth dialog close');
  await page.evaluate(()=>{document.querySelector('#remote').remove();cmsFeedback.cancel(document.querySelector('#edit'));});
  await page.locator('#edit').click();
  await page.evaluate(()=>document.dispatchEvent(new CustomEvent('cms:rendered')));assert.equal(await page.evaluate(()=>document.activeElement.id),'edit');
  await page.evaluate(()=>{const d=document.createElement('dialog');d.className='cmslightbox';d.innerHTML='<section style="height:100%"><header><h1>Outer</h1><button>Close</button></header><main>Content</main></section>';document.body.append(d);d.showModal();const n=d.cloneNode(true);n.id='nested';n.removeAttribute('open');document.body.append(n);n.showModal()});
  await page.waitForFunction(()=>document.querySelector('#nested > footer.cmsStatusbar'));
  const gap=await page.evaluate(()=>{const bar=document.querySelector('footer.cmsStatusbar').getBoundingClientRect();const box=document.querySelector('#nested > section').getBoundingClientRect();return bar.top-box.bottom});if(width>1024) assert.ok(gap>=8,`safety gap ${width}: ${gap}`); else assert.equal(await page.locator('footer.cmsStatusbar').isVisible(),false,'narrow layout retains mobile feedback instead of desktop bar');
  await page.screenshot({path:`/private/tmp/fluxo-block3-${width}.png`});assert.deepEqual(errors,[]);await page.close();
 }
 console.log('ok: flat desktop/touch/narrow: full loading width, stable bold navigation, scoped auth dirty state, retained focus, nested statusbar safety gap');
}finally{await browser.close()}
