import {readFile,readdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const system=new URL('../../system/',import.meta.url);
const css=(await Promise.all((await readdir(new URL('public/css/',system))).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(new URL('public/css/'+n,system),'utf8')))).join('\n');
const projectCSS=await readFile(new URL('panels/admin/030_settings/project.css',system),'utf8');
const mmlCSS=await readFile(new URL('../panels/admin/010_pages/pages.mml.css',import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 await page.setContent(`<style>${css}\n${projectCSS}</style><body class="cms">${await readFile('/private/tmp/fluxo-media-table.html','utf8')}</body>`);
 const rows=await page.locator('tr.imageFormatRow').evaluateAll(rows=>rows.map(row=>{
  const fields=[...row.querySelectorAll('input:not([type="checkbox"]),select')].map(el=>el.getBoundingClientRect().top);
  return {fields,top:row.getBoundingClientRect().top,labels:[...row.querySelectorAll('label>span')].map(el=>el.getBoundingClientRect().top)};
 }));
 assert.equal(rows.length,2);
 for(const row of rows){assert.ok(Math.max(...row.fields)-Math.min(...row.fields)<1,'key, mode and size align');assert.ok(row.labels.every(y=>y>=row.top),'labels stay within their table row');}
 await page.locator('tr.imageFormatRow').first().scrollIntoViewIfNeeded();
 await page.screenshot({path:'/private/tmp/fluxo-media-fields-aligned.png'});
 await page.setContent(`<style>${css}\n${mmlCSS}</style><body class="cms"><div class="mmlContent"><div><ul><li class="mmlUploadMessages"><div class="cms message"><div class="error"><p class="message">Invalid file type.</p></div></div></li><li class="title"><h4><span>MAIN</span><a class="button transparent icononly small mediaGroupInfo"><i>i</i></a></h4><ul><li class="upload">+</li></ul></li></ul></div></div></body>`);
 const positions=await page.locator('h4').evaluate(el=>({right:el.getBoundingClientRect().right-parseFloat(getComputedStyle(el).paddingRight),icon:el.querySelector('a').getBoundingClientRect().right}));
 assert.ok(Math.abs(positions.right-positions.icon)<1,'group info sits at right content edge');
 const widths=await page.locator('.mmlUploadMessages').evaluate(el=>({message:el.querySelector('.message').getBoundingClientRect().width,content:el.clientWidth-parseFloat(getComputedStyle(el).paddingLeft)-parseFloat(getComputedStyle(el).paddingRight)}));
 assert.ok(Math.abs(widths.message-widths.content)<1,'message fills content width without nested margins');
 console.log('ok: real rendered image-format labels remain inside rows; inputs align; MML icon sits right and message fills content width');
} finally {await browser.close();}
