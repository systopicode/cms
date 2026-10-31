import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1194,390]){
 const page=await browser.newPage({viewport:{width,height:700},hasTouch:width<1400});
 await page.setContent(`<style>${css}</style><body class=cms><div class=cms><div class="HTMLeditorMenu is-touch" style="left:20px;top:20px"><ul><li class="hasChildren open"><button data-cms-action=menu>Formats</button><ul><li><button data-cms-action=format>Original</button></li><li class=selected><button data-cms-action=format>Background (3 MP)</button></li><li><button data-cms-action=format>Longer format label (800 × 600 px)</button></li></ul></li></ul></div></div></body>`);
 const rows=page.locator('.HTMLeditorMenu li>ul>li');
 for(const row of await rows.all()){
  const item=await row.boundingBox(),button=await row.locator('button').boundingBox();assert.ok(Math.abs(item.width-button.width)<1,'entire option width is interactive');
 }
 await page.screenshot({path:`/private/tmp/fluxo-format-options-${width}.png`});await page.close();
}console.log('PASS full-width WYSIWYG options, including selected row, at desktop/tablet/phone widths');}finally{await browser.close();}
