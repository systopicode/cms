/** Persist permissions through production HTTP actions, then read frontend output as different roles. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
css+='\n'+await readFile(new URL('../public/css/pages.css',import.meta.url),'utf8')+'\n'+await readFile(new URL('../panels/admin/010_pages/panel.css',import.meta.url),'utf8');
for(const name of new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1])))css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const base=process.env.FLUXO_ACL_TEST_URL||'http://127.0.0.1:8769/';
const browser=await chromium.launch({channel:'chrome',headless:true});
const out='/private/tmp/fluxo-file-permissions-ui';await mkdir(out,{recursive:true});
try {for(const width of [1440,1024,390])for(const dark of [false,true]){
 const p=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440});const errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.route('**/fixture.css',r=>r.fulfill({contentType:'text/css',body:css}));
 const open=async url=>{
  await p.goto(url);await p.addScriptTag({path:root+'public/js/ext/jquery.js'});
  await p.evaluate(()=>{window.sys={core:{}};for(const name of ['l','p','t','c','f'])window[name]=()=>{};});
  await p.addScriptTag({path:root+'public/js/gui/ui.js'});await p.addScriptTag({path:root+'panels/admin/mobile.js'});
  await p.evaluate(()=>Promise.all(document.getAnimations().filter(a=>Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a=>a.finished.catch(()=>{}))));
 };
 const checkSelected=async()=>{
  const styles=await p.locator('.aclGroups .tag.active').evaluateAll(es=>es.map(e=>({color:getComputedStyle(e).color,opacity:getComputedStyle(e).opacity})));
  assert.ok(styles.length>0);for(const style of styles){assert.equal(style.color,'rgb(255, 255, 255)');assert.equal(style.opacity,'1');}
 };
 await open(`${base}?viewer=admin&dark=${dark?1:0}`);
 const inherit=p.locator('input[name=acl_inherit]');
 assert.ok(await inherit.isChecked());
 assert.equal(await inherit.locator('..').innerText(),'Use page permissions');
 assert.ok((await p.locator('.sidebarPermissions').innerText()).includes('page this file belongs to'));
 assert.equal((await p.locator('.sidebarPermissions').innerText()).includes('parent page'),false);
 await checkSelected();
 const act=async f=>{
  const saves=await p.evaluate(()=>window.fixtureSaves||0),file=await p.locator('.fileEditor').count();
  if(file)await p.evaluate(()=>{window.keptSection=document.querySelector('.fileEditor > section');document.querySelector('#localNote').value='Keep my local note';});
  await f();await p.waitForFunction(before=>(window.fixtureSaves||0)>before,saves);
  if(file){
   assert.equal(await p.evaluate(()=>keptSection===document.querySelector('.fileEditor > section')),true,'Permission updates keep the same sheet');
   assert.equal(await p.locator('#localNote').inputValue(),'Keep my local note');
   assert.equal(await p.evaluate(()=>fixtureQuiet),true,'Response suppresses a full render');
   assert.equal(await p.locator('.cmsSaveState:visible').count(),0,'No Saved banner for file permissions');
   assert.equal(await p.locator('.fileEditor > section').evaluate(e=>e.getAnimations().filter(a=>a.playState==='running').length),0,'No repeated sheet entrance');
  }
 };
 const click=async locator=>{await locator.scrollIntoViewIfNeeded();await locator[width<1440?'tap':'click']();};
 await act(()=>click(inherit.locator('..')));assert.equal(await inherit.isChecked(),false);
 await act(()=>click(p.locator('.aclGroups .tag').filter({hasText:'Group X'})));
 await act(()=>click(p.locator('.aclGroups .tag').filter({hasText:'Public'})));
 assert.deepEqual(await p.locator('.aclGroups .active').allTextContents(),['Group X']);
 await open(p.url());assert.deepEqual(await p.locator('.aclGroups .active').allTextContents(),['Group X']);
 await checkSelected();
 if(width===1440){await p.locator('.aclGroups .active').hover();await checkSelected();}
 assert.ok((await p.locator('.aclResolution').innerText()).includes('File reader'));
 assert.ok((await p.locator('.aclResolution').innerText()).includes('File writer'));
 await p.screenshot({path:`${out}/permissions-${width}-${dark?'dark':'light'}.png`,fullPage:true});
 assert.ok(await p.locator('.aclResolution').evaluate(e=>e.getBoundingClientRect().right<=innerWidth));
 for(const [viewer,allowed] of [['writer',true],['reader',true],['other',false],['guest',false]]){
  await p.goto(`${base}?viewer=${viewer}&output=1`);
  assert.equal(await p.locator('main').getAttribute('data-readable'),allowed?'yes':'no');
  assert.equal((await p.locator('main').innerText()).includes('Confidential download'),allowed);
 }
 await open(`${base}?viewer=admin&dark=${dark?1:0}`);await act(()=>click(inherit.locator('..')));
 assert.ok(await inherit.isChecked());assert.deepEqual(await p.locator('.aclGroups .active').allTextContents(),['Public']);
 await p.goto(`${base}?viewer=guest&output=1`);assert.equal(await p.locator('main').getAttribute('data-readable'),'yes');
 await open(`${base}?viewer=admin&page=1&dark=${dark?1:0}`);assert.equal(await p.getByRole('heading',{name:'Page permissions'}).count(),1);assert.equal(await p.locator('.aclResolution').count(),1);
 assert.equal(await p.locator('aside.cms .sidebarPermissions').count(),1);await checkSelected();
 await act(()=>click(p.locator('.aclGroups .tag').filter({hasText:'Group X'})));
 assert.deepEqual(await p.locator('.aclGroups .active').allTextContents(),['Public','Group X']);
 await checkSelected();
 await p.screenshot({path:`${out}/sidebar-${width}-${dark?'dark':'light'}.png`,fullPage:true});
 await act(()=>click(p.locator('.aclGroups .tag').filter({hasText:'Group X'})));
 assert.deepEqual(await p.locator('.aclGroups .active').allTextContents(),['Public']);
 assert.deepEqual(errors,[]);console.log(`PASS ${width} ${dark?'dark':'light'}: save/reload, inheritance, group/role resolution, frontend role output, shared Pages view, no browser errors`);await p.close();
}}finally{await browser.close();}
