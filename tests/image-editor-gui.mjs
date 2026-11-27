/** New editor GUI, real controls and disposable responses; no project media are changed. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import assert from 'node:assert/strict';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const root=new URL('../../system/',import.meta.url).pathname;
const out=process.env.FLUXO_ARTIFACTS || '/private/tmp/fluxo-image-editor-gui';await mkdir(out,{recursive:true});
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
const maskFiles=[...new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1]))];
const masks=new Map(await Promise.all(maskFiles.map(async name=>[name,'data:image/svg+xml;base64,'+(await readFile(root+'public/icons/'+name)).toString('base64')])));
css=css.replace(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g,(_,name)=>'url("'+masks.get(name)+'")');
css+='\n'+await readFile(new URL('../public/css/pages.css',import.meta.url),'utf8');
const mobile=await readFile(root+'panels/admin/mobile.js','utf8');
const panelSource=await readFile(new URL('../panels/admin/010_pages/editor/file/image/panel.php',import.meta.url),'utf8');
const tabs=[...panelSource.matchAll(/'(master|crops|formats|files|details)'\s*=>\s*\['([^']+)'/g)].map(([,key,label])=>({key,label}));
assert.equal(tabs.length,5,'Use the production tab names');
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const [width,height] of [[1440,900],[1366,1024],[1024,768],[834,1194],[390,844]]){
 if(process.env.FLUXO_VIEWPORT_WIDTH&&width!==Number(process.env.FLUXO_VIEWPORT_WIDTH))continue;
 const p=await browser.newPage({viewport:{width,height},hasTouch:width<1440,isMobile:width<1440});let errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.route('**/*',async r=>{const path=new URL(r.request().url()).pathname;try{await r.fulfill({body:await readFile(root+'public'+path),contentType:path.endsWith('.svg')?'image/svg+xml':'font/woff2'});}catch{await r.fulfill({body:'',contentType:'text/html'});}});
 await p.goto('https://fixture.example.test/css/');
 await p.setContent(`<base href="https://fixture.example.test/css/"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms pages editor"><header class="cmsMobileHeader cms"><button type="button" data-cms-window="menu" aria-label="Open navigation">☰</button><strong data-cms-module>Pages</strong></header><main><div class="main cms"><nav><ul><li class="active"><a href="/admin/pages/">Pages</a></li></ul></nav></div><div class="content"></div></main><button class="cmsMobileBackdrop" hidden></button><div class="cmslightbox cms fileEditor" data-mobile-dialog="sheet" data-size="3"><section><header class="imageEditorHeader"><h1>Edit image</h1><nav class="ceTabs" role="tablist" aria-label="What to edit">${tabs.map(({key,label})=>`<a href="#${key}" role="tab" aria-selected="${key==='master'}"><span>${label}</span></a>`).join('')}</nav><ul class="barmenu"><li><a href="#" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li></ul></header><div class="layout"><main><div class="fileStage"><div class="fileFrame"></div></div></main></div></section></div></body>`);
 if(process.env.FLUXO_EXTERNAL){
  const frame=execFileSync('php',['tests/file-editor-tabs-fixture.php','image','master'],{encoding:'utf8'});
  await p.setContent(`<base href="https://fixture.example.test/css/"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms pages editor"><header class="cmsMobileHeader cms"><button type="button" data-cms-window="menu" aria-label="Open navigation">☰</button><strong data-cms-module>Pages</strong></header><main><div class="main cms"><nav><ul><li class="active"><a href="/admin/pages/">Pages</a></li></ul></nav></div><div class="content"></div></main><button class="cmsMobileBackdrop" hidden></button>${frame}</body>`);
  assert.equal(await p.locator('.fileStage.split').count(),0,'External posters use the full image editor stage');
  assert.equal(await p.locator('input[name=url]').count(),0,'Source URL stays in File details');
 }
 await p.evaluate(()=>window.sys={});
 await p.addScriptTag({path:root+'public/js/ext/jquery.js'});await p.addScriptTag({path:root+'public/js/gui/dropdownMenus.js'});await p.addScriptTag({content:mobile});
 await p.addScriptTag({path:root+'public/js/gui/cropMath.js'});await p.addScriptTag({path:root+'public/js/gui/cropEditor.js'});
 await p.evaluate(()=>{
 const math=sys.cropMath,names=['2:1','16:9','3:2','4:3','1:1','3:4','2:3','9:16','1:2'];
 const src='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"><rect width="1200" height="800" fill="#5896b3"/><circle cx="430" cy="330" r="220" fill="#fcb60c"/><path d="M0 800 650 300 1200 800" fill="#1f3a45"/></svg>');
 window.fixture={mediaId:42,version:'v1',cut:true,original:{width:1200,height:800,url:src},master:{manual:false,ratio:'3:2',ax:.5,ay:.5,scale:1,basis:'fill',angle:0,flip:false,look:{b:100,c:100,s:100},width:1200,height:800,sources:[{url:src,width:1200,height:800}]},ratios:names.map(name=>{const r=math.ratio(name);return {name,suffix:name.replace(':','by'),w:r.w,h:r.h,position:1-math.position(r,math.ratio('1:2'),math.ratio('2:1')),basis:'fill',ax:.5,ay:.5,scale:1,source:'centered'};}),pois:[],sizes:[.5,1,2]};
 fixture.formats=[{name:'shareImage',label:'Share image',w:40,h:21,width:1200,height:630,position:.15,basis:'fill',ax:.5,ay:.5,scale:1,source:'centered',auto:{basis:'fill',ax:.5,ay:.5,scale:1,source:'centered'}},{name:'square',label:'Square',w:1,h:1,width:800,height:800,position:.5,basis:'fill',ax:.5,ay:.5,scale:1,source:'centered',auto:{basis:'fill',ax:.5,ay:.5,scale:1,source:'centered'}}];
 window.requests=[];window.failSave=false;
 window.fetch=async url=>{requests.push(String(url));if(!String(url).includes('cropState'))await new Promise(resolve=>setTimeout(resolve,window.saveDelay||500));return {ok:!failSave,status:failSave?(window.failStatus||500):200,json:async()=>failSave?{error:window.failReason||'Fixture: could not apply changes.'}:structuredClone(fixture)};};
 sys.cropEditor.open({stage:document.querySelector('.fileStage'),url:'/file/',nodeId:169,mode:'master'});
 });
 await p.locator('.ceInner').waitFor({state:'visible'});await p.waitForFunction(()=>document.querySelector('.ceInner').naturalWidth>0);
 await p.waitForLoadState('networkidle');
 const sameBox=(a,b,label)=>{for(const key of ['x','y','width','height'])assert.ok(Math.abs(a[key]-b[key])<1,label+': '+JSON.stringify({a,b}));};
 const painted=()=>p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
 const save=p.locator('[data-ce-save]'),discard=p.locator('[data-ce-cancel]'),stage=p.locator('.ceStage');
 const choose=async name=>{await p.locator('[data-ce-ratio="'+name+'"]')[width<=720?'tap':'click']();};
 const rect=await stage.boundingBox();assert.ok(rect.width>=(width===834?400:width===390?280:600),JSON.stringify({width,rect}));
 await p.waitForFunction(()=>sys.cropEditor.current.work&&!sys.cropEditor.current.busy&&document.querySelector('[data-ce-save]').disabled);
 assert.equal(await save.isDisabled(),true);assert.equal(await discard.isVisible(),true);
 if(width<=720){
  assert.equal(await p.locator('[data-ce-more],.ceRatioSelect').count(),0,'No mobile menu opener or ratio dropdown');
  const tools=p.locator('.ceTools'),toolList=p.locator('.ceToolList');
  const tb=await tools.boundingBox();assert.ok(tb.y+tb.height<=rect.y&&tb.x>=rect.x&&tb.x+tb.width<=rect.x+rect.width,'Toolbar occupies its own row above the picture');
  const rows=await toolList.locator('button:visible').evaluateAll(es=>es.map(e=>e.getBoundingClientRect().y));assert.ok(rows.every(y=>Math.abs(y-rows[0])<1),'All tools stay in one row');
  assert.equal(await p.locator('.ceScrollBar').count(),0,'No separate visible scrollbar controls');
  assert.equal(await toolList.evaluate(e=>getComputedStyle(e).scrollbarWidth),'none');
  assert.equal(await p.locator('.ceRatioScroll').evaluate(e=>getComputedStyle(e).scrollbarWidth),'none');
  const compact=await p.locator('.ceRatios button').first().evaluate(e=>{const s=getComputedStyle(e);return {height:e.getBoundingClientRect().height,content:Math.max(...[...e.children].map(n=>n.getBoundingClientRect().height)),padding:[s.paddingTop,s.paddingRight,s.paddingBottom,s.paddingLeft].map(parseFloat)};});
  assert.ok(compact.padding.every(n=>n===compact.padding[0])&&compact.padding[0]<=5,'Bottom choices have equal compact padding: '+JSON.stringify(compact));
  assert.ok(Math.abs(compact.height-compact.content-compact.padding[0]*2)<1,'No fixed extra height around the choice contents: '+JSON.stringify(compact));
  const client=await p.context().newCDPSession(p),box=await toolList.boundingBox();
  for(const [type,x] of [['touchStart',box.x+box.width*.8],['touchMove',box.x+box.width*.5],['touchMove',box.x+box.width*.2],['touchEnd',box.x+box.width*.2]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:31,x,y:box.y+box.height/2}]});
  await p.waitForFunction(()=>document.querySelector('.ceToolList').scrollLeft>30);
  const choices=p.locator('.ceRatioScroll'),choiceBox=await choices.boundingBox(),before=await choices.evaluate(e=>e.scrollLeft),trackBefore=await p.locator('.ceTrack').boundingBox();
  for(const [type,x] of [['touchStart',choiceBox.x+choiceBox.width*.8],['touchMove',choiceBox.x+choiceBox.width*.5],['touchMove',choiceBox.x+choiceBox.width*.2],['touchEnd',choiceBox.x+choiceBox.width*.2]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:32,x,y:choiceBox.y+choiceBox.height/2}]});
  await p.waitForFunction(before=>document.querySelector('.ceRatioScroll').scrollLeft>before+30,before);await client.detach();
  sameBox(await p.locator('.ceTrack').boundingBox(),trackBefore,'Manual format scrolling leaves the slider fixed');
  const manual=await choices.evaluate(e=>e.scrollLeft);await p.evaluate(()=>sys.cropEditor.current.renderSide());assert.equal(await choices.evaluate(e=>e.scrollLeft),manual,'Unchanged ratio leaves manual scrolling alone');
  await toolList.evaluate(e=>e.scrollLeft=0);
  assert.equal(await save.isDisabled(),true,'Scrolling either toolbar does not change the image');
  await p.screenshot({path:`${out}/horizontal-tools-${width}.png`});
 }
 const title=await p.getByRole('heading',{name:'Edit image',exact:true}).boundingBox();assert.ok(title.width>75);
 const header=await p.locator('.imageEditorHeader').boundingBox();
 const modes=await p.locator('.ceTabs').boundingBox();
 const close=await p.getByRole('link',{name:'Close',exact:true}).boundingBox();
 assert.ok(modes.y>=header.y-1 && modes.y+modes.height<=header.y+header.height+1,'Modes fit in the title row');
 if(width>1400)assert.ok(modes.x>=title.x+title.width-1 && modes.x+modes.width<=close.x+1,'Title, modes and close have their own space');
 else {
  assert.ok(modes.y>=title.y+title.height && modes.y>=close.y+close.height-1,'Mobile tab row sits below the title and close button');
  const last=p.getByRole('tab',{name:'File details'});await last.scrollIntoViewIfNeeded();assert.ok((await last.boundingBox()).x>=modes.x-1);
  await p.getByRole('tab',{name:'Edit master'}).scrollIntoViewIfNeeded();
 }
 if(width<=720){
  const tabsRail=p.locator('.ceTabs');
  assert.equal(await tabsRail.evaluate(e=>getComputedStyle(e).overflowY),'hidden');
  await tabsRail.evaluate(nav=>nav.addEventListener('click',e=>{const tab=e.target.closest('[role=tab]');if(!tab)return;e.preventDefault();nav.querySelectorAll('[role=tab]').forEach(t=>t.setAttribute('aria-selected',String(t===tab)));}));
  await p.getByRole('tab',{name:'Edit formats',exact:true}).tap();
  await p.waitForFunction(()=>{const n=document.querySelector('.ceTabs'),t=n.querySelector('[aria-selected=true]'),a=n.getBoundingClientRect(),b=t.getBoundingClientRect(),expected=Math.max(0,Math.min(n.scrollWidth-n.clientWidth,n.scrollLeft+b.x+b.width/2-a.x-a.width/2));return Math.abs(n.scrollLeft-expected)<1;});
  const manually=await tabsRail.evaluate(e=>{e.scrollLeft=Math.min(e.scrollWidth-e.clientWidth,e.scrollLeft+25);return e.scrollLeft;});
  await p.evaluate(()=>document.dispatchEvent(new Event('cms:rendered')));await painted();
  assert.equal(await tabsRail.evaluate(e=>e.scrollLeft),manually,'Unchanged selection leaves manual tab scrolling alone');
  const panelBefore=await p.locator('.fileEditor > section').boundingBox(),bar=await tabsRail.boundingBox(),client=await p.context().newCDPSession(p);
  for(const [type,dy] of [['touchStart',0],['touchMove',30],['touchMove',60],['touchEnd',60]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:41,x:bar.x+bar.width/2,y:bar.y+bar.height/2+dy}]});
  await painted();assert.equal(await tabsRail.evaluate(e=>e.scrollTop),0,'Tab rail cannot scroll vertically');
  sameBox(await p.locator('.fileEditor > section').boundingBox(),panelBefore,'Vertical swiping on tabs does not move the editor');
  await tabsRail.evaluate(e=>e.scrollLeft=0);
  for(const [type,x] of [['touchStart',bar.x+bar.width*.8],['touchMove',bar.x+bar.width*.5],['touchMove',bar.x+bar.width*.2],['touchEnd',bar.x+bar.width*.2]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:42,x,y:bar.y+bar.height/2}]});
  await p.waitForFunction(()=>document.querySelector('.ceTabs').scrollLeft>30);await client.detach();
  assert.equal(await p.getByRole('tab',{name:'Edit formats',exact:true}).getAttribute('aria-selected'),'true','Swiping does not switch tabs');
  await p.screenshot({path:`${out}/horizontal-tabs-${width}.png`});
  await p.getByRole('tab',{name:'Edit master',exact:true}).tap();
  await p.waitForFunction(()=>document.querySelector('.ceTabs').scrollLeft<1);
 }
 const commit=await save.boundingBox();
 const standard=await p.evaluate(()=>{const b=document.createElement('button');b.className='button';b.textContent='Standard';b.style.cssText='position:fixed;left:-1000px';document.querySelector('.fileEditor').append(b);const height=b.getBoundingClientRect().height;b.remove();return height;});
 assert.ok(Math.abs(commit.height-standard)<1,'Apply matches the shared button height: '+JSON.stringify({actual:commit.height,standard}));
 assert.ok(Math.abs((await discard.boundingBox()).height-standard)<1,'Discard matches the shared button height');
 await p.evaluate(()=>sys.cropEditor.current.setRatio(sys.cropMath.ratio(192,247),true));
 await p.waitForFunction(()=>document.querySelector('.ceStatus').textContent.includes('Master 7:9'));
 assert.equal(await p.locator('.ceStatus').getAttribute('title'),'Exact aspect ratio: 192:247');
 assert.equal(await p.evaluate(()=>sys.cropEditor.current.work.ratio.name),'192:247','Short display leaves the exact geometry unchanged');
 await p.screenshot({path:`${out}/ratio-label-${width}.png`});
 const original=p.locator('[data-ce-original]');
 const selectOriginal=async()=>choose(await original.getAttribute('data-ce-ratio'));
 assert.equal(await original.locator('..').evaluate(el=>el.parentElement.className),'ceRatios','Original is part of the same list');
 await selectOriginal();await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='3:2'&&document.querySelector('[data-ce-save]').disabled);
 if(width>720){
  await p.locator('[data-ce-ratio="1:1"] .ceRatioName').click();await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='1:1');
  await original.locator('.ceRatioBox').click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 }
 const rail=p.locator('.ceTrack'),knob=p.locator('.ceKnob');
 if(width<=720)await p.locator('.ceRatioScroll').evaluate(e=>{e.scrollLeft=e.scrollWidth/2-e.clientWidth/2;});
 const railBox=await rail.boundingBox();
 const knobSize=await knob.boundingBox();assert.ok(knobSize.width>knobSize.height,'Ratio grip uses the same capsule shape as colour sliders');
 assert.equal(await knob.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(252, 182, 12)','Ratio grip retains the highlight colour');
 if(width>720){const choice=await original.boundingBox();assert.ok(knobSize.x-choice.x-choice.width>=12,'Capsule and choice row have a clear gap');}
 const railInput=width<1440?await p.context().newCDPSession(p):null;
 const railPoint=position=>railBox.width>railBox.height?{x:railBox.x+position*railBox.width,y:railBox.y+railBox.height/2}:{x:railBox.x+railBox.width/2,y:railBox.y+position*railBox.height};
 const railEvent=async(type,position)=>{const {x,y}=railPoint(position);if(railInput)await railInput.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:25,x,y,radiusX:4,radiusY:4,force:1}]});else if(type==='touchStart'){await p.mouse.move(x,y);await p.mouse.down();}else if(type==='touchMove')await p.mouse.move(x,y);else await p.mouse.up();};
 const railLength=Math.max(railBox.width,railBox.height);
 await railEvent('touchStart',.4);
 for(let step=1;step<=6;step++)await railEvent('touchMove',.4+(.5+3/railLength-.4)*step/6);
 await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='1:1');
 if(width<=720){
  assert.ok(await rail.evaluate(e=>e.held),'Follow is checked during the active touch drag');
  const coupled=await p.evaluate(()=>{const box=document.querySelector('.ceRatioScroll'),knob=document.querySelector('.ceKnob'),button=document.querySelector('.ceRatios .is-selected'),a=button.getBoundingClientRect(),b=knob.getBoundingClientRect(),position=Number(knob.style.getPropertyValue('--p'));return {actual:box.scrollLeft,expected:(box.scrollWidth-box.clientWidth)*position,label:a.x+a.width/2,knob:b.x+b.width/2,gap:document.querySelector('.ceTrack').getBoundingClientRect().y-a.bottom};});
  assert.ok(Math.abs(coupled.actual-coupled.expected)<1,'Format strip follows the slider while dragging: '+JSON.stringify(coupled));
  assert.ok(Math.abs(coupled.label-coupled.knob)<1,'Selected format stays above the slider thumb: '+JSON.stringify(coupled));
  assert.ok(coupled.gap>=0&&coupled.gap<5,'Formats sit close to the slider');
  await p.screenshot({path:`${out}/slider-follow-${width}.png`});
 }
 for(let step=1;step<=15;step++)await railEvent('touchMove',.5+(3+2*step)/railLength);
 await painted();assert.notEqual(await p.evaluate(()=>sys.cropEditor.current.work.ratio.name),'1:1','Absolute pointer movement can leave a ratio stop');
 await railEvent('touchEnd',.5+33/railLength);
 if(railInput)await railInput.detach();
 await selectOriginal();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 await p.screenshot({path:`${out}/ratio-rail-light-${width}.png`});
 const lightLabel=await p.locator('.ceRatioName').first().evaluate(el=>getComputedStyle(el).color);
 await p.evaluate(()=>document.documentElement.dataset.mode='dark');await painted();
 assert.notEqual(await p.locator('.ceRatioName').first().evaluate(el=>getComputedStyle(el).color),lightLabel,'Ratio labels follow the system dark theme');
 await p.waitForFunction(()=>getComputedStyle(document.querySelector('.ceRatios button:not(.selected)')).color===getComputedStyle(document.querySelector('.ceStatus')).color);
 const statusColours=await p.locator('.ceStatus').evaluate(el=>{const s=getComputedStyle(el),c=document.createElement('canvas'),ctx=c.getContext('2d');const rgb=value=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=value;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};return {foreground:rgb(s.color),background:rgb(s.backgroundColor)};});
 assert.ok(statusColours.foreground[0]>statusColours.background[0]+100,'Status text remains readable against its dark surface');
 assert.equal(await p.locator('.ceRatios button').first().evaluate(el=>getComputedStyle(el).filter),'none','Ratio choices have no white-circle drop shadows');
 await p.screenshot({path:`${out}/ratio-rail-dark-${width}.png`});
 await p.evaluate(()=>delete document.documentElement.dataset.mode);await painted();
 if(width<=720){
  await choose('1:1');
  await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='1:1'&&!document.querySelector('[data-ce-save]').disabled);
  const requestCount=await p.evaluate(()=>requests.length);
  await p.locator('[data-ce-ratio="1:1"]').press('Tab');await p.keyboard.press('Escape');
  assert.equal(await p.evaluate(()=>requests.length),requestCount,'Enter on the ratio choice must not save');
  await discard.click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 }

 const openTools=async()=>{};
 await openTools();
 const groups=p.locator('.ceToolGroup:visible');
 assert.equal(await groups.count(),3,'Framing, transforms and colour adjustments have their own groups');
 const previewOrder=await p.locator('[aria-label="Framing and navigation"] > button').evaluateAll(bs=>bs.filter(b=>!b.hidden).map(b=>b.hasAttribute('data-ce-basis')?'fit':b.dataset.ceTool|| (b.hasAttribute('data-ce-grid')?'grid':'checker')));
 assert.deepEqual(previewOrder,['fit','pan','grid','checker']);
 if(width>720){
  const rail=await p.locator('.ceRight').boundingBox(),outer=await p.locator('.fileStage').boundingBox();
  const inset=await p.locator('.fileStage').evaluate(el=>parseFloat(getComputedStyle(el).paddingRight));
  assert.ok(Math.abs(outer.x+outer.width-inset-rail.x-rail.width)<1,'Tool rail meets the standard right inset');
  assert.ok(rail.width<100,'Tool rail stays compact');
  const pairs=await p.locator('.ceToolGroup:not([aria-label="Framing and navigation"]):visible').evaluateAll(gs=>gs.map(g=>Array.from(g.querySelectorAll('button')).filter(b=>b.getClientRects().length).map(b=>{const r=b.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};})));
  for(const pair of pairs){assert.equal(pair[0].y,pair[1].y);assert.ok(pair[1].x>pair[0].x);if(width<1440)assert.ok(pair.every(b=>b.width>=44&&b.height>=44));}
 }
 assert.equal(await p.locator('[data-ce-tool="pan"] .cmsIcon-up-down-left-right').count(),1);
 assert.equal(await p.locator('[data-ce-quarter] .cmsIcon-file-orientation').count(),0);
 assert.equal(await p.locator('[data-ce-quarter]').textContent(),'90°');
 assert.equal(await p.locator('[data-ce-grid] .cmsIcon-table').count(),1,'Use the grid icon of the previous editor');
 assert.equal(await p.locator('[data-ce-tool="zoom"] .cmsIcon-zoom-scan').count(),1);
 if(width===1440){
  await p.evaluate(()=>document.documentElement.dataset.pointer='touch');
  assert.equal(await p.locator('[data-ce-tool=zoom]').isVisible(),false,'A wide hybrid screen also hides the mouse-only zoom tool after touch');
  await p.evaluate(()=>delete document.documentElement.dataset.pointer);
  assert.equal(await p.locator('[data-ce-tool=zoom]').isVisible(),true,'Mouse zoom remains available on desktop');
 }
 assert.equal(await p.locator('[data-ce-turn] .cmsIcon-rotate-rectangle').count(),1);
 assert.equal(await p.locator('[data-ce-reset] .cmsIcon-eraser').count(),3,'Reset uses the shared eraser symbol');
 assert.ok((await p.locator('[data-ce-reset]').allTextContents()).every(text=>text===''),'Reset buttons have accessible names without visible labels');
 const colourButtons=await p.locator('[aria-label="Colour adjustments"] > button').evaluateAll(bs=>bs.map(b=>{const r=b.getBoundingClientRect();return [r.x,r.y];}));
 assert.equal(colourButtons[2][1],colourButtons[3][1],'Colour reset completes the four-button block');
 assert.ok(colourButtons[3][0]>colourButtons[2][0]);
 assert.notEqual(await p.locator('[data-ce-reset="look"]').evaluate(el=>getComputedStyle(el).backgroundColor),'rgba(0, 0, 0, 0)','Toolbar reset keeps its grey surface');
 const checker=p.getByRole('button',{name:'Dark checkerboard',exact:true});
 const themeBefore=await p.evaluate(()=>({mode:document.documentElement.dataset.mode,theme:document.documentElement.getAttribute('style'),look:JSON.stringify(sys.cropEditor.current.work.look)}));
 const previewRequests=await p.evaluate(()=>requests.length);
 if(width<=720)await checker.tap();else await checker.click();
 assert.equal(await checker.getAttribute('aria-pressed'),'true');
 assert.equal(await stage.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(42, 46, 50)');
 assert.equal(await p.locator('.ceFrame').evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(42, 46, 50)');
 assert.deepEqual(await p.evaluate(()=>({mode:document.documentElement.dataset.mode,theme:document.documentElement.getAttribute('style'),look:JSON.stringify(sys.cropEditor.current.work.look)})),themeBefore,'Preview contrast leaves the system theme and image adjustments alone');
 assert.equal(await p.evaluate(()=>requests.length),previewRequests);
 assert.equal(await save.isDisabled(),true,'Checkerboard is only a viewing aid');
 await p.screenshot({path:`${out}/checker-dark-${width}.png`});
 if(width<=720)await checker.tap();else await checker.click();
 assert.equal(await stage.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(220, 220, 220)');
 const grid=p.locator('.cropGrid'),gridButton=p.getByRole('button',{name:'Show grid',exact:true});
 assert.equal(await grid.isVisible(),false);
 assert.equal(await p.locator('.ceFrame').evaluate(el=>getComputedStyle(el).outlineStyle),'none');
 const gridRequests=await p.evaluate(()=>requests.length);
 if(width<=720)await gridButton.tap();else await gridButton.click();
 assert.equal(await grid.isVisible(),true);assert.equal(await gridButton.getAttribute('aria-pressed'),'true');
 assert.equal(await p.locator('.ceFrame').evaluate(el=>getComputedStyle(el).outlineStyle),'solid','Outline is part of the grid aid');
 sameBox(await grid.boundingBox(),await p.locator('.ceFrame').boundingBox(),'Grid follows the crop frame');
 assert.equal(await save.isDisabled(),true,'Grid is only a viewing aid');
 assert.equal(await p.evaluate(()=>requests.length),gridRequests);
 await p.screenshot({path:`${out}/tools-grid-${width}.png`});
 if(width<=720)await gridButton.tap();else await gridButton.click();
 assert.equal(await grid.isVisible(),false);
 // Fill keeps the frame covered. Fit permits a margin around a smaller image.
 await p.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2);await p.mouse.wheel(0,150);await painted();
 assert.ok(await p.evaluate(()=>sys.cropEditor.current.work.scale>=.99),'Fill stops at its pixel-rounded covering scale');
 await openTools();await p.locator('[data-ce-basis]').click();
 await p.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2);await p.mouse.wheel(0,150);
 await p.waitForFunction(()=>sys.cropEditor.current.work.basis==='fit'&&sys.cropEditor.current.work.scale<1);
 await painted();
 const smaller=await p.locator('.ceInner').boundingBox(),frame=await p.locator('.ceFrame').boundingBox();
 assert.ok(smaller.width<frame.width&&smaller.height<frame.height,'Fit can leave a margin on all sides');
 const input=width<1440?await p.context().newCDPSession(p):null;
 const pointer=async(type,x,y)=>{
  if(input)await input.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:19,x,y,radiusX:4,radiusY:4,force:1}]});
  else if(type==='touchStart'){await p.mouse.move(x,y);await p.mouse.down();}else if(type==='touchMove')await p.mouse.move(x,y);else await p.mouse.up();
 };
 const snapDrag=async({gridOn,fraction,both=false,release=false})=>{
  await openTools();
  if((await gridButton.getAttribute('aria-pressed'))!==String(gridOn))await gridButton.click();
  await p.getByRole('button',{name:'Move the picture',exact:true}).click();
  await p.evaluate(()=>{const s=sys.cropEditor.current;s.work={...s.work,basis:'fit',scale:.45,ax:.5,ay:.5,angle:0,flip:false,auto:false};s.render();});
  await painted();
  const img=await p.locator('.ceInner').boundingBox(),f=await p.locator('.ceFrame').boundingBox();
  const x=img.x+img.width/2,y=img.y+img.height/2,tx=f.x+f.width*fraction,ty=f.y+f.height*fraction;
  const offset=fraction===0?4:1;
  const dx=tx+offset-img.x,dy=both?ty+offset-img.y:0;
  await pointer('touchStart',x,y);
  for(let step=1;step<=6;step++)await pointer('touchMove',x+dx*step/6,y+dy*step/6);
  await painted();
  const snapped=await p.locator('.ceInner').boundingBox();
  if(fraction===0||gridOn){assert.ok(Math.abs(snapped.x-tx)<1,`${width}: image snaps to ${fraction===0?'edge':'third'}`);if(both)assert.ok(Math.abs(snapped.y-ty)<1,'Vertical third snaps too');}
  else assert.ok(Math.abs(snapped.x-tx-offset)<.2&&Math.abs(snapped.x-tx)>.5,'Hidden grid does not attract the image to a third');
  if(release){for(let step=1;step<=15;step++)await pointer('touchMove',x+dx+step*2,y+dy);await painted();assert.ok((await p.locator('.ceInner').boundingBox()).x-tx>25,'Small moves can leave a snapped edge');}
  await pointer('touchEnd',x+dx,y+dy);
  if(gridOn)await p.screenshot({path:`${out}/snap-grid-${width}.png`});
 };
 await snapDrag({gridOn:false,fraction:0,release:true});
 await snapDrag({gridOn:true,fraction:1/3,both:true});
 await snapDrag({gridOn:false,fraction:1/3});
 await discard.click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 await openTools();await p.getByRole('button',{name:'Turn to the next right angle',exact:true}).click();
 await p.waitForFunction(()=>sys.cropEditor.current.work.angle===90);
 await p.getByRole('button',{name:'Reset geometry',exact:true}).click();
 await p.waitForFunction(()=>sys.cropEditor.current.work.angle===0&&document.querySelector('[data-ce-save]').disabled);
 await p.getByRole('button',{name:'Turn freely',exact:true}).click();
 await p.waitForFunction(()=>document.querySelector('.cropEditor').dataset.tool==='turn');
 assert.match(await stage.evaluate(el=>getComputedStyle(el).cursor),/data:image\/svg\+xml/,JSON.stringify(await stage.evaluate(el=>({tool:el.parentElement.dataset.tool,classes:el.parentElement.className,cursor:el.style.getPropertyValue('--ceTurnCursor')}))));
 const pivotButton=p.getByRole('button',{name:'Fix point',exact:true});
 assert.equal(await pivotButton.evaluate(el=>getComputedStyle(el).cursor),'move');
 const startPivot=await p.evaluate(()=>({...sys.cropEditor.current.pivot}));
 const pivotBox=await pivotButton.boundingBox(),px=pivotBox.x+pivotBox.width/2,py=pivotBox.y+pivotBox.height/2;
 const pdx=rect.width*.08,pdy=-rect.height*.06;
 await pointer('touchStart',px,py);
 for(let step=1;step<=6;step++)await pointer('touchMove',px+pdx*step/6,py+pdy*step/6);
 await pointer('touchEnd',px+pdx,py+pdy);await painted();
 const movedPivot=await p.evaluate(()=>({...sys.cropEditor.current.pivot}));
 assert.ok(Math.hypot(movedPivot.x-startPivot.x,movedPivot.y-startPivot.y)>20,'Rotation point can be dragged freely');
 assert.equal(await save.isDisabled(),true,'Moving the rotation point changes no image data');
 const arm=Math.min(90,rect.width*.16,rect.height*.16),ax=rect.x+movedPivot.x,ay=rect.y+movedPivot.y;
 await pointer('touchStart',ax+arm,ay);
 for(let step=1;step<=6;step++){const a=(20*step/6)*Math.PI/180;await pointer('touchMove',ax+arm*Math.cos(a),ay+arm*Math.sin(a));}
 await pointer('touchEnd',ax+arm*Math.cos(Math.PI/9),ay+arm*Math.sin(Math.PI/9));await painted();
 assert.ok(Math.abs(await p.evaluate(()=>sys.cropEditor.current.work.angle)-20)<.1,'Image turns around the chosen point');
 assert.equal(await p.evaluate(()=>sys.cropEditor.current.work.auto),false,'Custom pivot also works on an untouched master');
 assert.deepEqual(await p.evaluate(()=>({...sys.cropEditor.current.pivot})),movedPivot,'Drawing and turning preserve the moved pivot');
 await p.screenshot({path:`${out}/turn-pivot-${width}.png`});
 await discard.click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 if(input)await input.detach();
 await openTools();
 sameBox(await stage.boundingBox(),rect,'Opening tools must not resize the stage');
 if(width<1440){
  assert.equal(await p.locator('[data-ce-tool=zoom]').isVisible(),false,'Touch devices use pinch zoom without the focus-point tool');
  await p.getByRole('button',{name:'Move the picture',exact:true}).click();
 }else{
  await p.getByRole('button',{name:'Zoom around a fix point',exact:true}).click();
  await p.waitForFunction(()=>document.querySelector('[data-ce-tool=zoom]').getAttribute('aria-pressed')==='true');
 }
 if(width<=720)assert.equal(await p.locator('.ceToolList').isVisible(),true,'Tools stay available above the picture');
 // Exercise the canvas using actual mouse/touch input, then discard the disposable changes.
 const gesture=await stage.boundingBox(),cx=gesture.x+gesture.width/2,cy=gesture.y+gesture.height/2;
 if(width<1440){
  const input=await p.context().newCDPSession(p);
  const touch=async(type,points)=>input.send('Input.dispatchTouchEvent',{type,touchPoints:points.map(([id,x,y])=>({id,x,y,radiusX:4,radiusY:4,force:1}))});
  await p.touchscreen.tap(cx,cy);assert.equal(await p.locator('.ceStatus').isVisible(),true,'A tap reveals the image hint');
  await touch('touchStart',[[1,cx-30,cy],[2,cx+30,cy]]);
  assert.equal(await p.locator('.ceStatus').isVisible(),false,'The hint gets out of the way of a pinch gesture');
  await touch('touchMove',[[1,cx-60,cy],[2,cx+60,cy]]);await touch('touchEnd',[]);
  await p.waitForFunction(()=>sys.cropEditor.current.work.scale>1.5);
  assert.equal(await p.locator('.ceStatus').isVisible(),false,'The hint stays hidden after pinching');
  const zoomed=await p.evaluate(()=>JSON.stringify(sys.cropEditor.current.work));
  await p.touchscreen.tap(cx,cy);assert.equal(await p.locator('.ceStatus').isVisible(),true);
  assert.equal(await p.evaluate(()=>JSON.stringify(sys.cropEditor.current.work)),zoomed,'Revealing the hint changes no crop data');
  await openTools();await p.getByRole('button',{name:'Move the picture',exact:true}).click();
  const before=await p.evaluate(()=>sys.cropEditor.current.work.ax);
  await touch('touchStart',[[1,cx,cy]]);await touch('touchMove',[[1,cx+30,cy]]);await touch('touchEnd',[]);
  await p.waitForFunction(before=>Math.abs(sys.cropEditor.current.work.ax-before)>.01,before);
  assert.equal(await p.locator('.ceStatus').isVisible(),false,'Dragging also keeps the image hint out of the way');
  await p.screenshot({path:`${out}/gesture-hint-hidden-${width}.png`});
  await p.touchscreen.tap(cx,cy);assert.equal(await p.locator('.ceStatus').isVisible(),true);
  await p.screenshot({path:`${out}/gesture-hint-restored-${width}.png`});
  if(width<=720){
   await p.evaluate(()=>document.documentElement.dataset.mode='dark');await painted();
   await p.screenshot({path:`${out}/gesture-hint-restored-dark-${width}.png`});
   assert.equal(await p.locator('[data-ce-tool=zoom]').isVisible(),false);
   await p.evaluate(()=>delete document.documentElement.dataset.mode);
  }
  await input.detach();
 }else{
  await p.mouse.move(cx+30,cy);await p.mouse.down();await p.mouse.move(cx+60,cy,{steps:5});await p.mouse.up();
  await p.waitForFunction(()=>sys.cropEditor.current.work.scale>1.5);
  await p.getByRole('button',{name:'Move the picture',exact:true}).click();
  const before=await p.evaluate(()=>sys.cropEditor.current.work.ax);
  await p.mouse.move(cx,cy);await p.mouse.down();await p.mouse.move(cx+30,cy,{steps:5});await p.mouse.up();
  await p.waitForFunction(before=>Math.abs(sys.cropEditor.current.work.ax-before)>.01,before);
 }
 assert.equal(await p.evaluate(()=>sys.cropEditor.current.pointers.size),0,'Release removes every active pointer');
 await discard.click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 await openTools();await p.getByRole('button',{name:'Brightness',exact:true}).click();
 const range=p.locator('.ceLevel input');assert.equal(await range.isVisible(),true);
 if(width<=720){
  assert.equal(await p.locator('.ceTools > .ceLevel').count(),1,'Colour slider belongs to the same toolbar');
  const picture=await stage.boundingBox(),level=await p.locator('.ceLevel').boundingBox();
  assert.ok(level.x>picture.x&&level.y>picture.y&&level.x+level.width<picture.x+picture.width&&level.y+level.height<picture.y+picture.height,'Colour slider floats inside the checkerboard');
  sameBox(picture,rect,'Opening the colour slider does not resize the picture');
  const style=await p.locator('.ceLevel').evaluate(e=>{const s=getComputedStyle(e);return {radius:parseFloat(s.borderTopLeftRadius),border:s.borderTopWidth,shadow:s.boxShadow};});
  assert.ok(style.radius>0&&style.border==='1px'&&style.shadow!=='none','Colour slider retains the rounded desktop surface');
  await p.evaluate(()=>document.documentElement.dataset.mode='dark');await painted();await p.screenshot({path:`${out}/brightness-dark-${width}.png`});await p.evaluate(()=>delete document.documentElement.dataset.mode);
 }
 const centres=await p.locator('.ceLevel').evaluate(el=>Array.from(el.children).map(c=>{const r=c.getBoundingClientRect();return r.y+r.height/2;}));
 assert.ok(centres.every(v=>Math.abs(v-centres[0])<1),'Value, slider and reset share a centre line');
 assert.equal(await p.locator('[data-ce-level-reset]').evaluate(el=>getComputedStyle(el).backgroundColor),'rgba(0, 0, 0, 0)');
 await p.screenshot({path:`${out}/brightness-${width}.png`});
 const rb=await range.boundingBox();assert.ok(rb.x>=0&&rb.x+rb.width<=width);
 if(width<=720)await p.touchscreen.tap(rb.x+rb.width*.65,rb.y+rb.height/2);else await p.mouse.click(rb.x+rb.width*.65,rb.y+rb.height/2);
 assert.ok(Number(await range.inputValue())>100);
 await p.waitForFunction(()=>document.querySelector('.ceLevel output').textContent===String(Number(document.querySelector('.ceLevel input').value)-100));
 await painted();assert.equal(await save.isEnabled(),true);sameBox(await save.boundingBox(),commit,'Editing must not move Apply');
 await p.getByRole('button',{name:'Back to unchanged',exact:true}).click();
 assert.equal(await range.inputValue(),'100');await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 for(const name of ['Contrast','Saturation']){
  await p.getByRole('button',{name,exact:true}).click();
  assert.equal(await range.isVisible(),true);
  const track=await range.boundingBox();
  if(width<=720)await p.touchscreen.tap(track.x+track.width*.65,track.y+track.height/2);else await p.mouse.click(track.x+track.width*.65,track.y+track.height/2);
  assert.ok(Number(await range.inputValue())>100,`${width}: ${name} receives the pointer input`);
  await painted();assert.equal(await save.isEnabled(),true);
  await p.getByRole('button',{name:'Back to unchanged',exact:true}).click();
  assert.equal(await range.inputValue(),'100');await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 }
 await p.keyboard.press('Escape');assert.equal(await p.locator('.ceLevel').isVisible(),false);
 if(width<=720)assert.equal(await p.locator('.ceToolList').isVisible(),true);
 sameBox(await stage.boundingBox(),rect,'Resetting adjustments must not resize the stage');
 await openTools();await p.getByRole('button',{name:'Mirror',exact:true}).click();
 await p.evaluate(()=>{window.failSave=true;window.saveDelay=2200;});
 const applyBefore=await save.boundingBox(),applyLabel=await save.innerText();
 await save[width<1440?'tap':'click']();assert.equal(await save.isDisabled(),true,'Apply is disabled while saving');assert.equal(await range.isDisabled(),true,'Adjustments are disabled while saving');const busyBox=await stage.boundingBox();assert.ok(Math.abs(busyBox.width-rect.width)<1&&Math.abs(busyBox.height-rect.height)<1,'Saving must not resize the stage');
 assert.equal(await save.getAttribute('aria-busy'),'true');
 await p.waitForFunction(()=>getComputedStyle(document.querySelector('[data-ce-save]')).color==='rgba(0, 0, 0, 0)');
 assert.equal(await save.innerText(),applyLabel,'Busy state keeps the accessible label and its width');
 sameBox(await save.boundingBox(),applyBefore,'System spinner does not move or resize Apply');
 for(const mode of ['light','dark']){
  await p.evaluate(mode=>document.documentElement.dataset.mode=mode,mode);
  const spinner=await save.evaluate(el=>{const s=getComputedStyle(el,'::before');return {loading:el.classList.contains('loading'),animation:s.animationName,ink:s.borderRightColor,top:s.borderTopColor,text:getComputedStyle(el).color};});
  assert.equal(spinner.loading,true);assert.equal(spinner.animation,'spinner','Existing system spinner is painted');assert.notEqual(spinner.ink,spinner.top);assert.equal(spinner.text,'rgba(0, 0, 0, 0)','Label is visually replaced by the spinner');
  await p.screenshot({path:`${out}/apply-loading-${mode}-${width}.png`});
 }
 await p.evaluate(()=>delete document.documentElement.dataset.mode);
 await p.getByText('Fixture: could not apply changes.',{exact:true}).waitFor();
 assert.equal(await save.isEnabled(),true,'A failed save can be retried');
 assert.equal(await save.getAttribute('aria-busy'),'false');assert.equal(await save.evaluate(el=>el.classList.contains('loading')),false,'A failed save clears the spinner');
 await p.evaluate(()=>{window.failStatus=409;window.failReason='This image was changed elsewhere. Reopen the editor to use the saved version.';window.saveDelay=100;window.conflictDraft=JSON.stringify(sys.cropEditor.current.work);});
 await save[width<1440?'tap':'click']();await p.getByText('This image was changed elsewhere. Reopen the editor to use the saved version.',{exact:true}).waitFor();
 assert.equal(await p.evaluate(()=>JSON.stringify(sys.cropEditor.current.work)===conflictDraft&&sys.cropEditor.current.dirty()),true,'A conflicting save keeps the local changes');
 assert.equal(await p.evaluate(()=>new URL(requests.at(-1),'https://fixture.test').searchParams.get('version')===fixture.version),true,'Save carries the loaded media version');
 await p.evaluate(()=>{window.failStatus=0;window.failReason='';});
 await p.evaluate(()=>{window.failSave=false;window.saveDelay=500;});
 await save[width<1440?'tap':'click']();await p.waitForFunction(()=>!sys.cropEditor.current.dirty()&&document.querySelector('[data-ce-save]').disabled);
 assert.equal(await save.getAttribute('aria-busy'),'false');assert.equal(await save.evaluate(el=>el.classList.contains('loading')),false,'Successful retry clears the spinner');
 assert.equal(await p.locator('.ceStatus').evaluate(el=>el.classList.contains('is-error')),false,'Successful retry clears the old error status');
 assert.notEqual(await p.locator('.ceStatus').textContent(),'Not applied.');
 assert.ok(await p.evaluate(()=>requests.some(url=>url.includes('saveMasterCrop'))));
 await p.screenshot({path:`${out}/master-${width}.png`});
 for(const mode of ['crops','formats']){
  await p.evaluate(mode=>{document.querySelectorAll('.ceTabs [role=tab]').forEach(a=>a.setAttribute('aria-selected',String(a.getAttribute('href')==='#'+mode)));sys.cropEditor.open({stage:document.querySelector('.fileStage'),url:'/file/',nodeId:169,mode});},mode);
  await p.waitForFunction(()=>!!sys.cropEditor.current.work&&document.querySelector('.cropEditor').dataset.mode==='crops');
  if(mode==='formats')await p.waitForFunction(()=>document.querySelector('[data-ce-ratio=shareImage]').getAttribute('aria-pressed')==='true');
  if(width<=720){
   await choose(mode==='formats'?'square':'16:9');
   await p.waitForFunction(name=>sys.cropEditor.current.ratioName===name&&!sys.cropEditor.current.sliding,mode==='formats'?'square':'16:9');
   if(mode==='formats')await p.waitForFunction(()=>document.querySelector('[data-ce-ratio=square]').getAttribute('aria-pressed')==='true');

  }
  assert.equal(await p.getByRole('button',{name:'Mirror',exact:true}).isVisible(),false);
  assert.equal(await original.count(),0,'Original reference is offered only in Edit master');
  assert.equal(await p.locator('.ceGhosts').count(),0,'No grey crop comparisons are mixed into the format list');
  const expectedChoices=await p.evaluate(mode=>(mode==='formats'?fixture.formats:fixture.ratios).map(r=>r.name),mode);
  assert.deepEqual(await p.locator('[data-ce-ratio]').evaluateAll(bs=>bs.map(b=>b.dataset.ceRatio)),expectedChoices,'Each mode lists only its own choices');
  assert.equal(await p.locator('.ceRatioSelect').count(),0,'The mobile dropdown is removed');
  const pin=p.getByRole('button',{name:'Keep crop fixed',exact:true});
  assert.equal(await pin.locator('..').getAttribute('aria-label'),'Crop behaviour');
  assert.equal(await p.locator('.ceStage [data-ce-pin],.ceStage [data-ce-auto]').count(),0,'Crop actions no longer float over the image');
  assert.equal(await p.locator('.ceTools .cmsIcon-eraser:visible').count(),1,'One clear eraser action remains in crop mode');
  const currentCrop=await p.evaluate(()=>sys.cropEditor.current.ratioName);
  await pin.click();await p.waitForFunction(()=>document.querySelector('[data-ce-pin]').getAttribute('aria-pressed')==='true'&&!document.querySelector('[data-ce-save]').disabled);
  await pin.click();await p.waitForFunction(()=>document.querySelector('[data-ce-pin]').getAttribute('aria-pressed')==='false'&&document.querySelector('[data-ce-save]').disabled);
  assert.equal(await p.evaluate(()=>sys.cropEditor.current.ratioName),currentCrop,'Pin toggle preserves the selected crop');
  if(mode==='crops'){
   await pin.click();await p.waitForFunction(()=>sys.cropEditor.current.pending.size===1);
   await choose('4:3');
   await p.waitForFunction(()=>sys.cropEditor.current.ratioName==='4:3'&&!sys.cropEditor.current.sliding);
   await openTools();await pin.click();await p.waitForFunction(()=>sys.cropEditor.current.pending.size===2);
   await p.getByRole('button',{name:'Reset all crops',exact:true}).click();await p.waitForFunction(()=>sys.cropEditor.current.pending.size===0&&document.querySelector('[data-ce-save]').disabled);
  }
  await p.screenshot({path:`${out}/${mode}-actions-${width}.png`});
  await p.locator('[data-ce-basis]').click();await painted();
  await save.click();assert.equal(await save.evaluate(el=>el.classList.contains('loading')),true,'Crop and format saves use the same system spinner');await p.waitForFunction(()=>!sys.cropEditor.current.dirty());
  assert.equal(await save.evaluate(el=>el.classList.contains('loading')),false,'Finished crop and format saves clear the spinner');
  if(width<=720){
   const preview=await stage.boundingBox();assert.ok(preview.height>250&&preview.y>=0&&preview.y+preview.height<=height,'Switching ratio modes keeps the picture visible');
   const selected=await p.locator('.ceRatios .is-selected').boundingBox(),thumb=await knob.boundingBox();assert.ok(Math.abs(selected.x+selected.width/2-thumb.x-thumb.width/2)<1,'Crop and format choices follow the same slider position');
  }
  assert.ok(await p.evaluate(action=>requests.some(url=>url.includes(action)),mode==='formats'?'saveFormatCrops':'saveRatioCrops'));
  await p.screenshot({path:`${out}/${mode}-${width}.png`});
 }
 if(width<1440){
  const box=p.locator('.fileEditor > section'),grip=p.locator('.imageEditorHeader > .cmsMobileGrip');
  assert.equal(await grip.isVisible(),true,'Touch editor exposes the sheet grip');
  const bounds=await box.boundingBox();assert.ok(Math.abs(bounds.x)<1&&bounds.y>0&&bounds.y+bounds.height>=height-1,'Sheet meets the sides and bottom');
  await p.evaluate(()=>document.querySelector('[aria-label="Close"]').addEventListener('click',event=>{event.preventDefault();window.fixtureClosed=true;}));
  const client=await p.context().newCDPSession(p);
  const swipe=async delta=>{
   const handle=await grip.boundingBox(),x=handle.x+handle.width/2,y=handle.y+handle.height/2;
   for(const [type,dy] of [['touchStart',0],['touchMove',delta/2],['touchMove',delta],['touchEnd',delta]]){
    await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:1,x,y:y+dy}]});
   }
  };
  await swipe(-48);await p.waitForFunction(()=>document.querySelector('.fileEditor > section').classList.contains('is-expanded'));
  await p.evaluate(()=>Promise.all(document.getAnimations().filter(a=>Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a=>a.finished.catch(()=>{}))));
  const expanded=await box.boundingBox();
  const heading=await p.getByRole('heading',{name:'Edit image',exact:true}).boundingBox();
  for(const [type,dy] of [['touchStart',0],['touchMove',60],['touchMove',115],['touchEnd',115]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:1,x:heading.x+heading.width/2,y:heading.y+heading.height/2+dy}]});
  await painted();sameBox(await box.boundingBox(),expanded,'Dragging the headline does not move the sheet');
  assert.equal(await p.evaluate(()=>!!window.fixtureClosed),false);
  await p.evaluate(()=>{
   const s=sys.cropEditor.current;s.zoom(1.2,s.size.w/2,s.size.h/2);
   window.cropBeforeClose=JSON.stringify(s.work);window.closePrompts=0;const nativeConfirm=window.confirm;
   window.confirm=text=>{closePrompts++;window.promptSheetY=document.querySelector('.fileEditor > section').getBoundingClientRect().y;return nativeConfirm(text);};
  });
  p.once('dialog',dialog=>dialog.dismiss());await swipe(115);await p.waitForFunction(()=>window.closePrompts===1);await painted();
  sameBox(await box.boundingBox(),expanded,'Cancelling discard restores the complete sheet');
  assert.ok(await p.evaluate(()=>promptSheetY<innerHeight/2),'Discard prompt appears while the sheet is still on screen');
  assert.equal(await p.evaluate(()=>JSON.stringify(sys.cropEditor.current.work)===cropBeforeClose),true);
  assert.equal(await p.evaluate(()=>!!window.fixtureClosed),false);
  assert.equal(await p.locator('.fileEditor').evaluate(e=>e.style.getPropertyValue('--cmsGlassFade')),'');
  await p.screenshot({path:`${out}/cancel-close-${width}.png`});
  p.once('dialog',dialog=>dialog.accept());
  await swipe(115);await p.waitForFunction(()=>window.fixtureClosed===true);
  assert.equal(await p.evaluate(()=>closePrompts),2,'One discard prompt for each close attempt');
  await client.detach();
 }
 assert.deepEqual(errors,[]);
 if(width===1440){
  await p.evaluate(()=>{document.documentElement.dataset.mode='dark';delete sys.cropEditor.checkerDark;sys.cropEditor.open({stage:document.querySelector('.fileStage'),url:'/file/',nodeId:169,mode:'master'});});
  await p.waitForFunction(()=>sys.cropEditor.current.work&&!sys.cropEditor.current.busy);
  assert.equal(await checker.getAttribute('aria-pressed'),'true','Preview starts dark under the existing dark theme');
  await checker.click();
  assert.equal(await p.evaluate(()=>document.documentElement.dataset.mode),'dark');
  assert.equal(await stage.evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(220, 220, 220)','Preview can be light while the system theme stays dark');
 }
 await p.evaluate(()=>{
  delete document.documentElement.dataset.mode;
  fixture.formats=fixture.formats.filter(f=>f.name==='shareImage');
  document.querySelectorAll('.ceTabs [role=tab]').forEach(a=>a.setAttribute('aria-selected',String(a.getAttribute('href')==='#formats')));
  sys.cropEditor.open({stage:document.querySelector('.fileStage'),url:'/file/',nodeId:169,mode:'formats'});
 });
 await p.waitForFunction(()=>sys.cropEditor.current.fixed&&document.querySelector('[data-ce-ratio=shareImage]')?.getAttribute('aria-pressed')==='true');
 assert.deepEqual(await p.locator('[data-ce-ratio]').evaluateAll(bs=>bs.map(b=>b.dataset.ceRatio)),['shareImage'],'A project with one fixed format shows exactly that one');
 await p.screenshot({path:`${out}/single-format-${width}.png`});
 await p.evaluate(()=>{
  delete document.documentElement.dataset.mode;
  document.querySelectorAll('.ceTabs [role=tab]').forEach(a=>a.setAttribute('aria-selected',String(a.getAttribute('href')==='#master')));
  const src='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="1393" height="1438"><rect width="1393" height="1438" fill="#5896b3"/><circle cx="430" cy="530" r="320" fill="#fcb60c"/></svg>');
  Object.assign(fixture.original,{width:1393,height:1438,url:src});
  Object.assign(fixture.master,{ratio:'1393:1438',width:1393,height:1438,sources:[{url:src,width:1393,height:1438}]});
  sys.cropEditor.open({stage:document.querySelector('.fileStage'),url:'/file/',nodeId:169,mode:'master'});
 });
 await p.waitForFunction(()=>sys.cropEditor.current.work?.ratio.name==='1393:1438'&&document.querySelector('.ceInner').naturalHeight===1438);
 assert.equal(await original.locator('.ceRatioName').textContent(),'0.97:1','Original displays only its short aspect ratio');
 if(width>720){
  const choices=await p.locator('.ceRatios button').evaluateAll(bs=>bs.map(b=>b.getBoundingClientRect().toJSON()));
  for(let i=1;i<choices.length;i++)assert.ok(choices[i].top>=choices[i-1].bottom+3,'Nearby ratio buttons remain separately clickable');
  assert.ok((await p.locator('.ceSide').boundingBox()).width<140,'Rail takes only the width of its labels, preview and slider');
  const contentFit=await original.evaluate(b=>{const s=getComputedStyle(b),n=b.querySelector('.ceRatioName'),icon=b.querySelector('.ceRatioBox');return {blank:b.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight)-n.offsetWidth-icon.offsetWidth-parseFloat(s.gap),labelFits:n.scrollWidth<=n.clientWidth+1,left:parseFloat(s.paddingLeft),right:parseFloat(s.paddingRight)};});
  assert.ok(contentFit.labelFits&&contentFit.blank<2&&contentFit.left===contentFit.right,'Selected original is compact, untruncated and evenly padded');
  await p.locator('[data-ce-ratio="1:1"] .ceRatioName').click();await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='1:1');
  await painted();
  const squareBox=await p.locator('[data-ce-ratio="1:1"]').boundingBox(),squareKnob=await knob.boundingBox();
  assert.ok(Math.abs(squareBox.y+squareBox.height/2-squareKnob.y-squareKnob.height/2)<1,'Square button and slider thumb stay on the same line');
  await original.locator('.ceRatioBox').click();await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
  await painted();
  const originalBox=await original.boundingBox(),originalKnob=await knob.boundingBox();
  assert.ok(Math.abs(originalBox.y+originalBox.height/2-originalKnob.y-originalKnob.height/2)<1,'Original button and slider thumb stay on the same line');
  const originalStop=await p.evaluate(()=>sys.cropEditor.current.trackPosition(sys.cropEditor.current.positionOf(sys.cropEditor.current.originalRatio())));
  const nowRail=await rail.boundingBox(),x=nowRail.x+nowRail.width/2;
  const client=width<1440?await p.context().newCDPSession(p):null;
  const moveRatio=async(type,position)=>{const y=nowRail.y+position*nowRail.height;if(client)await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:29,x,y,radiusX:4,radiusY:4,force:1}]});else if(type==='touchStart'){await p.mouse.move(x,y);await p.mouse.down();}else if(type==='touchMove')await p.mouse.move(x,y);else await p.mouse.up();};
  await moveRatio('touchStart',originalStop-.08);
  await moveRatio('touchMove',originalStop+3/nowRail.height);
  await p.waitForFunction(()=>sys.cropEditor.current.work.ratio.name==='1393:1438');
  await moveRatio('touchEnd',originalStop);
  if(client)await client.detach();
  await p.waitForFunction(()=>document.querySelector('[data-ce-save]').disabled);
 }
 await p.mouse.move(width/2,20);
 await p.locator('.ceRatios').evaluate(el=>Promise.all(el.getAnimations({subtree:true}).map(animation=>animation.finished.catch(()=>{}))));
 await p.screenshot({path:`${out}/compact-original-light-${width}.png`});
 await p.evaluate(()=>document.documentElement.dataset.mode='dark');
 await p.waitForFunction(()=>getComputedStyle(document.querySelector('.ceRatios button:not(.selected)')).color===getComputedStyle(document.querySelector('.ceStatus')).color);
 await p.screenshot({path:`${out}/compact-original-dark-${width}.png`});
 assert.deepEqual(errors,[]);
 console.log(`PASS ${width}: compact inline ratio choices, whole-row clicks, magnetic ratio stops, dark theme, crop pin/reset, editor gestures, save retry and master/crops/formats`);
 await p.close();
}}finally{await browser.close();}
