/** Actual sidebar PHP markup and CMS styles; no account or project data is edited. */
import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const out='/private/tmp/fluxo-search-output-implementation-20261127',base='https://dev.local/projects/galerie-lattemann.de/public/';
const fragment=(await readFile(out+'/sidebar-fragment.html','utf8')).replaceAll('https://fixture.test/project/home/',base);
const source=await readFile(new URL('../panels/admin/010_pages/panel.js',import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [1440,834,390]) {
  const context=await browser.newContext({ignoreHTTPSErrors:true,viewport:{width,height:1000},hasTouch:width<1440}),page=await context.newPage();
  await page.goto(base+'auth/',{waitUntil:'networkidle'});
  await page.evaluate(html=>{document.body.className='cms';document.body.innerHTML='<aside class="cms" style="position:static;display:block;transform:none;width:auto;max-width:560px;margin:24px;padding:24px;background:var(--cmsBgColor,white);overflow:auto"><h2>Search and AI</h2>'+html+'</aside>';},fragment);
  await page.addScriptTag({content:source.slice(source.indexOf('async function cmsCheckPageOutput'))});
  await page.getByRole('button',{name:'Check public output'})[width<1440?'tap':'click']();
  await page.waitForFunction(()=>document.querySelector('.cmsOutputCheckResult li'));
  assert.equal(await page.locator('.cmsOutputCheckResult').getByText(/^✕/).count(),0);
  assert.ok(await page.getByText('it',{exact:true}).count());
  await page.screenshot({path:out+`/sidebar-${width}.png`});
  console.log(`PASS ${width}: actual sidebar markup, CMS styles, default language and public output check`);await context.close();
 }
} finally {await browser.close()}
