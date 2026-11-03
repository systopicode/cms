import fs from 'node:fs/promises';import assert from 'node:assert/strict';import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
let css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');css+='\n'+await fs.readFile('../system/panels/admin/030_settings/project.css','utf8');
const html=execFileSync('php',['tests/media-ratio-settings-fixture.php'],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});try{
for(const width of [1440,1194,1024,390])for(const dark of [false,true]){
 const page=await browser.newPage({viewport:{width,height:800},hasTouch:width<1200});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(`<html data-mode="${dark?'dark':'light'}"><style>${css}</style><body class="cms system settings"><main style="height:100vh;overflow:auto"><div class=content><div class=projectFiles style="max-width:800px"><section class=widget>${html}</section></div><label>Range <input type=range min=0 max=200 value=100></label></div></main></body></html>`);
 await page.evaluate(()=>window.sys={});await page.addScriptTag({path:'../system/public/js/gui/tableFit.js'});
 await page.evaluate(()=>document.querySelectorAll('.tableScroll').forEach(box=>{sys.fitTables.fit(box);}));
 assert.equal(await page.locator('thead th').nth(1).evaluate(e=>getComputedStyle(e).textAlign),'left','settings table headers align left');
 assert.equal(await page.locator('tbody tr').first().evaluate(e=>getComputedStyle(e).transform),'none','idle rows avoid transformed-input caret rendering');
 const stacked=await page.locator('.tableScroll').evaluate(e=>e.classList.contains('stacked'));
 assert.equal(stacked,width<=767,'only smartphones use accordion cards');
 if(!stacked){
  const box=page.locator('.tableScroll');assert.equal(await box.evaluate(e=>e.classList.contains('canScrollX')),true,'tablet table scrolls instead of squeezing controls');
  assert.equal(await box.locator('td.projectRemove').first().evaluate(e=>getComputedStyle(e).position),'sticky','actions stay fixed');
 }
 if(stacked){
  const row=page.locator('tbody>tr').first(),detail=row.locator('td').nth(2);
  assert.equal(await detail.isVisible(),false,'stacked row starts folded');
  await row.locator('.tableFoldToggle').click();
  assert.equal(await detail.isVisible(),true,'tap opens settings card');
  await page.evaluate(()=>document.querySelectorAll('tbody>tr').forEach(e=>e.classList.add('is-open')));
 }
 assert.equal(await page.locator('[data-field=aspect_ratio]').count(),2,'ratio field only for MP');
 const input=page.locator('[data-field=aspect_ratio]').first();await input.fill('16:9');assert.equal(await input.evaluate(e=>e.checkValidity()),true);await input.fill('bad');assert.equal(await input.evaluate(e=>e.checkValidity()),false);await input.fill('');assert.equal(await input.evaluate(e=>e.checkValidity()),true,'free ratio permitted');
 const style=await page.locator('input[type=range]').evaluate(e=>{const t=getComputedStyle(e,'::-webkit-slider-thumb');return {appearance:getComputedStyle(e).appearance,w:t.width,h:t.height,bg:t.backgroundColor};});assert.equal(style.appearance,'none');
 await page.locator('input[type=range]').scrollIntoViewIfNeeded();const r=await page.locator('input[type=range]').boundingBox();await page.locator('input[type=range]').press('End');assert.ok(Number(await page.locator('input[type=range]').inputValue())>100);
 const ground=await page.evaluate(()=>({root:getComputedStyle(document.documentElement).backgroundColor,body:getComputedStyle(document.body).backgroundColor,main:getComputedStyle(document.body).getPropertyValue('--cmsMainColor').trim(),overflow:document.documentElement.scrollWidth>innerWidth}));assert.equal(ground.root,ground.body);assert.equal(ground.overflow,false,'no viewport overflow');
 assert.deepEqual(errors,[]);await page.evaluate(()=>{document.querySelector('main').scrollTop=0;scrollTo(0,0);});await page.screenshot({path:`/private/tmp/fluxo-ratio-settings-${width}-${dark?'dark':'light'}.png`});await page.close();
}
console.log('PASS production settings markup: MP-only ratio fields, validity, sliders, root/body background, no viewport overflow; light/dark at 1440/1024/390');
}finally{await browser.close();}
