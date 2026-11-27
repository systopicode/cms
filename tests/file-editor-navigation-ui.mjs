/** Production-rendered tab replacements, including the phone dialog portal. No project data. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
for(const file of ['../public/css/pages.css','../panels/admin/010_pages/panel.css'])css+='\n'+await readFile(new URL(file,import.meta.url),'utf8');
for(const name of new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1])))css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const mobile=await readFile(root+'panels/admin/mobile.js','utf8');
const frames={};for(const tab of ['master','crops','formats','files','details'])frames[tab]=execFileSync('php',['-d','zend.exception_ignore_args=1','tests/file-editor-tabs-fixture.php','image',tab],{encoding:'utf8'});
for(const kind of ['video','pdf'])for(const tab of ['preview','details'])frames[kind+'-'+tab]=execFileSync('php',['-d','zend.exception_ignore_args=1','tests/file-editor-tabs-fixture.php',kind,tab],{encoding:'utf8'});
const out='/private/tmp/fluxo-file-editor-navigation';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const [width,height] of [[390,844],[834,1194],[1366,1024],[1440,900]])for(const dark of [false,true]){
 if(process.env.FLUXO_VIEWPORT_WIDTH&&width!==Number(process.env.FLUXO_VIEWPORT_WIDTH))continue;
 const page=await browser.newPage({viewport:{width,height},isMobile:width<1440,hasTouch:width<1440}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});page.setDefaultTimeout(6000);
 await page.route('**/*',async r=>{const path=new URL(r.request().url()).pathname;try{await r.fulfill({body:await readFile(root+'public'+path),contentType:path.endsWith('.svg')?'image/svg+xml':'font/woff2'});}catch{await r.fulfill({body:'',contentType:'text/html'});}});
 await page.goto('https://fixture.test/admin/pages/');
 await page.setContent(`<base href="https://fixture.test/css/"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms pages"><header class="cmsMobileHeader"><button data-cms-window="menu"></button><strong data-cms-module>Pages</strong></header><main><div class="main cms"><nav><ul><li class="active"><a href="/admin/pages/">Pages</a></li></ul></nav></div><div class="content"><div id="editorHost"></div></div></main><button class="cmsMobileBackdrop" hidden></button></body>`);
 await page.evaluate(dark=>{if(dark)document.documentElement.dataset.mode='dark';window.sys={};window.cmsFeedback={hasChanges:()=>false};window.animations=[];document.addEventListener('animationstart',e=>{if(e.target.matches('.fileEditor > section'))animations.push(e.animationName);});},dark);
 await page.addScriptTag({path:root+'public/js/ext/jquery.js'});await page.addScriptTag({content:mobile});
 await page.evaluate(frames=>{
  window.framesHTML=frames;window.kind='image';window.currentTab='';
  window.replaceTab=tab=>{currentTab=tab;document.querySelector('#editorHost').innerHTML=framesHTML[kind==='image'?tab:kind+'-'+tab];history.replaceState(null,'','/admin/pages/editor/file/'+(kind==='image'?'image/':'')+(tab==='preview'?'':tab+'/')+'?target_id=42');window.cmsMobile.sync();};
  document.addEventListener('click',e=>{const tab=e.target.closest('.fileEditor [role=tab]');if(!tab)return;e.preventDefault();const path=new URL(tab.href).pathname.split('/').filter(Boolean);replaceTab(kind==='image'?path.at(-1):path.at(-1)==='details'?'details':'preview');});
  replaceTab('master');
 },frames);
 const painted=()=>page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 await page.waitForTimeout(550);
 const entrance=width===390||width===1366;
 assert.equal(await page.evaluate(()=>animations.includes('cmsPhoneSheetIn')),entrance,'Only sheet layouts animate the first opening');
 const check=async label=>{
  assert.equal(await page.locator('.fileEditor').count(),1,'One live editor after '+label);
  assert.equal(await page.locator('.ceTabs .cmsIcon').count(),0,'Editor tabs use text labels only');
  const mark=await page.locator('.ceTabs').evaluate(rail=>{const e=rail.querySelector('[aria-selected=true]'),s=getComputedStyle(e),a=e.getBoundingClientRect(),b=rail.getBoundingClientRect();return {border:s.borderBottomWidth,bottom:a.bottom,railBottom:b.bottom,top:a.top,railTop:b.top};});
  assert.equal(mark.border,'2px');assert.ok(mark.bottom<=mark.railBottom+.1&&mark.top>=mark.railTop-.1,'Entire active underline stays inside the scroll rail: '+JSON.stringify(mark));
 };
 await check('open');
 const input=width<1440?'tap':'click';
 for(const [tab,label] of [['crops','Edit crops'],['formats','Edit formats'],['files','Show files'],['details','File details'],['master','Edit master']]){
  const before=await page.evaluate(()=>animations.length);
  await page.getByRole('tab',{name:label,exact:true})[input]();await painted();
  assert.equal(await page.locator('.fileEditor').getAttribute('data-dialog-continued'),'','Same file keeps its window across replaced tab markup');
  const samples=await page.locator('.fileEditor > section').evaluate(async el=>{const values=[];for(let i=0;i<6;i++){await new Promise(requestAnimationFrame);values.push({animation:getComputedStyle(el).animationName,transform:getComputedStyle(el).transform,y:el.getBoundingClientRect().y});}return values;});
  assert.ok(samples.every(s=>s.animation==='none'&&s.transform==='none'),'No entrance motion on '+tab+': '+JSON.stringify(samples));
  assert.equal(await page.evaluate(()=>animations.length),before,'No animationstart on tab replacement');await check(tab);
  if(tab==='details')await page.screenshot({path:`${out}/tabs-${width}-${dark?'dark':'light'}.png`});
 }
 // Reexecuting panel assets must retain the same-window identity and expansion.
 await page.locator('.fileEditor > section').evaluate(el=>el.classList.add('is-expanded'));
 await page.evaluate(()=>replaceTab('crops'));await page.addScriptTag({content:mobile});await painted();
 assert.ok(await page.locator('.fileEditor > section').evaluate(el=>el.classList.contains('is-expanded')));
 assert.equal(await page.locator('.fileEditor > section').evaluate(el=>getComputedStyle(el).animationName),'none');
 // Closing clears identity. Opening the same file again gets its normal entrance.
 await page.evaluate(()=>{document.querySelector('#editorHost').replaceChildren();document.querySelectorAll('.fileEditor').forEach(el=>el.remove());cmsMobile.sync();animations=[];replaceTab('master');});
 await page.waitForTimeout(550);
 assert.equal(await page.locator('.fileEditor').getAttribute('data-dialog-continued'),null);
 assert.equal(await page.evaluate(()=>animations.includes('cmsPhoneSheetIn')),entrance,'A fresh opening still animates');
 // Other file kinds share the same header/window rules.
 for(const kind of ['video','pdf']){
  await page.evaluate(kind=>{document.querySelector('#editorHost').replaceChildren();document.querySelectorAll('.fileEditor').forEach(el=>el.remove());cmsMobile.sync();window.kind=kind;replaceTab('preview');},kind);
  await page.waitForTimeout(550);const before=await page.evaluate(()=>animations.length);
  await page.getByRole('tab',{name:'File details',exact:true})[input]();await painted();await check(kind+' details');
  assert.equal(await page.locator('.fileEditor > section').evaluate(el=>getComputedStyle(el).animationName),'none');assert.equal(await page.evaluate(()=>animations.length),before);
 }
 assert.deepEqual(errors,[]);console.log(`PASS ${width} ${dark?'dark':'light'}: actual tab DOM replacements, first-open/reopen animation, expanded state, asset reinitialisation, unclipped 2px underline, text tabs, image/video/PDF; no browser errors`);await page.close();
}}finally{await browser.close();}
