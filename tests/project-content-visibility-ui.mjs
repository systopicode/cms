/** Regression: project scroll animations must reveal tall WYSIWYG blocks. */
import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const project=new URL('../../../projects/stefanpohlfilm.de/',import.meta.url).pathname;
const source=await readFile(project+'public/js/funcs.js','utf8');
const start=source.indexOf('function initWowObserver(options)');assert.ok(start>=0);
const css=await readFile(project+'public/css/templates.css','utf8');
const imageCss=css.match(/main\.project \.project__text img\s*\{[^}]+\}/)?.[0];assert.ok(imageCss);
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1024,390]){
 const page=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent(`<style>body{margin:0}.wow:not(.visible){visibility:hidden}.spacer{height:1100px}.project__text{width:80%;height:24000px}#card{height:100px;background:teal}${imageCss}</style><div class="spacer"></div><main class="project"><div class="project__text wow" id="tall"><p>Visible text</p><img width="3000" height="2000" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='3000' height='2000'/%3E"></div><div class="spacer"></div><div class="wow" id="card">Normal card</div></main><div class="spacer"></div>`);
 await page.evaluate(()=>{const Native=IntersectionObserver;window.entries=[];window.IntersectionObserver=class extends Native{constructor(cb,options){super((entries,obs)=>{window.entries.push(...entries.map(e=>({id:e.target.id,ratio:e.intersectionRatio,intersects:e.isIntersecting,rect:e.boundingClientRect.toJSON(),root:e.rootBounds?.toJSON()})));cb(entries,obs)},options)}};});
 await page.addScriptTag({content:source.slice(start)});await page.evaluate(()=>initWowObserver());
 assert.equal(await page.locator('#tall').evaluate(el=>getComputedStyle(el).visibility),'hidden');
 await page.waitForFunction(()=>entries.length>=2);
 await page.evaluate(()=>scrollTo(0,document.querySelector('#tall').offsetTop-entries[0].root.bottom+10));
 await page.waitForFunction(()=>document.querySelector('#tall').classList.contains('visible'));
 assert.ok(await page.locator('#tall img').evaluate(el=>el.getBoundingClientRect().width<=el.parentElement.getBoundingClientRect().width+1));
 // A normal card keeps the 8% threshold: one pixel does not start it.
 await page.evaluate(()=>scrollTo(0,document.querySelector('#card').offsetTop-entries[0].root.bottom+1));
 await page.waitForTimeout(100);
 assert.equal(await page.locator('#card').evaluate(el=>getComputedStyle(el).visibility),'hidden');
 await page.evaluate(()=>scrollBy(0,20));await page.waitForFunction(()=>document.querySelector('#card').classList.contains('visible'));
 await page.emulateMedia({reducedMotion:'reduce'});await page.evaluate(()=>{const el=document.createElement('div');el.id='reduced';el.className='wow';document.body.append(el);initWowObserver();});
 assert.equal(await page.locator('#reduced').evaluate(el=>getComputedStyle(el).visibility),'visible');
 assert.deepEqual(errors,[]);console.log(`PASS ${width}: tall content reveals, image fits, card threshold and reduced motion preserved`);await page.close();
}}finally{await browser.close()}
