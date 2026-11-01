import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
const browser=await chromium.launch({channel:'chrome',headless:true});
try { for(const width of [390,1194,1440]) for(const mode of ['light','dark']) {
 const p=await browser.newPage({viewport:{width,height:834},hasTouch:width<1200});
 await p.setContent(`<html data-mode="${mode}"><style>${css}body{overflow:auto!important}.sample{padding:24px}.sample aside{position:relative!important;width:300px!important;height:auto!important;transform:none!important}.cmslightbox.upload{position:relative!important;inset:auto!important;display:block!important;padding:0!important}.cmslightbox.upload footer{position:relative!important}.backupProgress{margin:20px}</style><body class="cms backup"><div class=sample><aside class=cms><details class=sidebarSection open><summary>Settings group</summary><section class=settingGroup><h4>Tile</h4><div><label>Short label<input value="A value"></label></div></section></details></aside><div class=backupProgress><div class=bar><b>50 / 100</b><div style="width:50%"><b>50 / 100</b></div></div></div><div class="cmslightbox upload cms"><section><footer><div class=bar><b>Uploading 50%</b><div style="width:50%"><b>Uploading 50%</b></div></div></footer></section></div></div></body></html>`);
 assert.equal(await p.locator('.settingGroup label').evaluate(e=>parseFloat(getComputedStyle(e).fontSize)/parseFloat(getComputedStyle(document.documentElement).fontSize)),.75);
 const summary=await p.locator('summary').boundingBox();assert.ok(summary.height>=35);
 await p.locator('summary').click();assert.equal(await p.locator('details').evaluate(e=>e.open),false);await p.locator('summary').click();
 for(const bar of await p.locator('.bar').all()) {
  const m=await bar.evaluate(e=>{let r=e.getBoundingClientRect(),t=e.querySelector(':scope>b').getBoundingClientRect();return {dx:Math.abs((r.left+r.right-t.left-t.right)/2),dy:Math.abs((r.top+r.bottom-t.top-t.bottom)/2),hidden:getComputedStyle(e.querySelector(':scope>div>b')).display}});
  assert.ok(m.dx<1&&m.dy<1,JSON.stringify(m));assert.equal(m.hidden,'none');
 }
 await p.screenshot({path:`/private/tmp/fluxo-ten-components-${width}-${mode}.png`});await p.close();
} console.log('PASS: sidebar summaries toggle; group labels .75rem; upload and backup counter centered once; 390/1194/1440, light/dark.'); }
finally { await browser.close(); }
