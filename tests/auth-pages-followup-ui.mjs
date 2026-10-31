import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
const source=await fs.readFile('panels/cms/010_pages/pages.js','utf8');
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(x=>x.endsWith('.css')).sort().map(x=>fs.readFile('../system/public/css/'+x,'utf8')))).join('\n')+await fs.readFile('../system/panels/cms/auth/auth.css','utf8')+await fs.readFile('panels/cms/010_pages/pages.mml.css','utf8');
const card=execFileSync('php',['-r',`class http {public static $root='/';} class message {static function flush(...$args){return '';}} $authMethod='code';$authCodes=true;$authCode='ZZZZ-ZZZZ';$authCodeAttempted=true;include '../system/src/Auth/login.card.tpl.php';`],{encoding:'utf8'});
try {
 for(const width of [390,1194,1440]){
  const page=await browser.newPage({viewport:{width,height:850},hasTouch:width<1400});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(`<style>${css}</style><body class=cms><div class=authCard>${card.replace(/value="[^"]*"/g,'value=""')}</div></body>`);
  assert.equal(await page.locator('[data-qr-host]').getAttribute('data-qr-boot'),null,'even an empty failed attempt does not boot camera '+width);
  await page.setContent(`<style>${css}</style><body class=cms><div class=authCard>${card}</div></body>`);
  assert.equal(await page.locator('[data-code-form]').isVisible(),true,'failed code stays in manual entry '+width);
  assert.equal(await page.locator('[data-qr-host]').isVisible(),false,'camera remains hidden '+width);
  assert.equal(await page.locator('[name=code]').inputValue(),'ZZZZ-ZZZZ');
  await page.addScriptTag({content:(await fs.readFile('../system/public/js/gui/qr-scan.js','utf8')).replace('window.QrScan = { start: start, stop: stop };','window.QrScan = { start: start, stop: stop, testTake:take };')});
  await page.evaluate(()=>{window.settings={siteRoot:'/'};window.validQr=true;window.sys={core:{server:{call:function(){this.send=()=>({done:fn=>{queueMicrotask(()=>fn({valid:validQr}));return {fail:()=>{}};}});}}}};});
  await page.evaluate(()=>{window.submitted=0;document.querySelector('[data-code-form]').addEventListener('submit',e=>{e.preventDefault();submitted++})});
  assert.equal(await page.evaluate(()=>QrScan.testTake(document.querySelector('[data-qr-host]'),'https://example.org/foreign')),false);
  assert.equal(await page.evaluate(()=>QrScan.testTake(document.querySelector('[data-qr-host]'),'https://dev.local/?code=ZZZZ-ZZZZ')),true);
  // Validation resolves asynchronously before submitting the typed code.
  await page.waitForFunction(()=>submitted===1);
  await page.evaluate(()=>{window.validQr=false;QrScan.testTake(document.querySelector('[data-qr-host]'),'https://other.local/?screen=ZZZZ-ZZZZ')});
  await page.waitForFunction(()=>document.querySelector('[data-qr-msg]').textContent.includes('expired'));
  assert.equal(await page.evaluate(()=>submitted),1,'expired or other-instance QR never navigates');
  await page.setContent(`<style>${css}</style><body class=cms><div class="cms pylon"><footer class="resizeMML is-empty" style="height:300px;max-height:300px;background:red"><div class=mml>Library</div></footer></div><aside class="cms collapsed" style="flex:0 0 0px"><a class=sidebarFlag><i>›</i></a></aside><div class=authCard><div class=authSuccess><p class=authLead data-success-name>Welcome</p><p class=authWait>Timer</p></div></div></body>`);
  await page.addScriptTag({content:'/* MML reveal intentionally removed. */'});
  await page.evaluate(()=>{document.querySelector('.resizeMML').classList.remove('is-empty');document.dispatchEvent(new Event('cms:rendered'))});
  assert.equal(await page.locator('.resizeMML').evaluate(e=>e.getAnimations().length),0,'library appears without slide animation');
  await page.waitForTimeout(300);
  await page.evaluate(()=>document.dispatchEvent(new Event('cms:rendered')));
  assert.equal(await page.locator('.resizeMML').evaluate(e=>e.getAnimations().length),0,'page switches do not replay');
  assert.equal(await page.locator('aside').evaluate(e=>e.getBoundingClientRect().width),0,'closed sidebar leaves no line');
  assert.notEqual(await page.locator('[data-success-name]').evaluate(e=>getComputedStyle(e).color),await page.locator('.authWait').evaluate(e=>getComputedStyle(e).color),'welcome and timer have distinct text tones');
  await page.emulateMedia({reducedMotion:'reduce'});
  await page.evaluate(()=>{document.querySelector('.resizeMML').classList.add('is-empty');document.dispatchEvent(new Event('cms:rendered'));document.querySelector('.resizeMML').classList.remove('is-empty');document.dispatchEvent(new Event('cms:rendered'))});
  assert.equal(await page.locator('.resizeMML').evaluate(e=>e.getAnimations().length),0);
  assert.deepEqual(errors,[]);
  await page.screenshot({path:`/private/tmp/fluxo-round-${width}.png`});await page.close();
  const loaded=await browser.newPage({viewport:{width,height:850}});
  await loaded.setContent('<div class="cms pylon"><footer class="resizeMML">Already selected page</footer></div>');
  await loaded.addScriptTag({content:'/* MML reveal intentionally removed. */'});
  assert.equal(await loaded.locator('.resizeMML').evaluate(e=>e.getAnimations().length),0,'reload with selected page never replays reveal');
  await loaded.close();

 }
 console.log('ok: 390/1194/1440 manual code, MML without slide animation, stable page switch, reduced motion, sidebar and success text');
} finally {await browser.close()}
