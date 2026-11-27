/** Production file templates, disposable media and touch navigation. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname;
const mediaDir='/private/tmp/fluxo-file-tabs';await mkdir(mediaDir,{recursive:true});
for(const [ext,codec] of [['mp4','libx264'],['webm','libvpx-vp9']]){
 try{await readFile(`${mediaDir}/sample.${ext}`)}catch{execFileSync('ffmpeg',['-v','error','-f','lavfi','-i','color=c=teal:s=320x180:r=12','-t','1','-c:v',codec,'-pix_fmt','yuv420p','-y',`${mediaDir}/sample.${ext}`]);}
}
try{await readFile(`${mediaDir}/sample.mp3`)}catch{execFileSync('ffmpeg',['-v','error','-f','lavfi','-i','sine=frequency=440','-t','1','-y',`${mediaDir}/sample.mp3`]);}
try{await readFile(`${mediaDir}/sample.gif`)}catch{execFileSync('php',['-r',`$a=new Imagick();foreach(['red','blue'] as $c){$f=new Imagick();$f->newImage(48,32,new ImagickPixel($c),'gif');$f->setImageDelay(15);$a->addImage($f);}$a->setImageIterations(0);$a->writeImages('${mediaDir}/sample.gif',true);`]);}

let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
css+='\n'+await readFile(new URL('../public/css/pages.css',import.meta.url),'utf8');
css+='\n'+await readFile(new URL('../panels/admin/010_pages/panel.css',import.meta.url),'utf8');
const icons=[...new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1]))];
for(const name of icons)css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const ui=await readFile(root+'public/js/gui/ui.js','utf8');
const previewReady=ui.slice(ui.lastIndexOf('(function(){\n    function previewReady'));
const mediaUrl=page=>page.locator('video').evaluate(v=>v.src);
const out='/private/tmp/fluxo-file-tabs-ui';await mkdir(out,{recursive:true});
const fixture=(kind,tab)=>execFileSync('php',['tests/file-editor-tabs-fixture.php',kind,tab],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of (process.env.FLUXO_WIDTH?[Number(process.env.FLUXO_WIDTH)]:[1440,1024,390]))for(const dark of [false,true]){
 const page=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const input=width<1440?'tap':'click';
 const settled=()=>page.evaluate(()=>Promise.all(document.getAnimations().filter(a=>Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a=>a.finished.catch(()=>{}))));
 let kind='pdf';
 const html=tab=>`<!doctype html><html data-mode="${dark?'dark':'light'}"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><body class="cms ${width<1440?'cmsMobile':''}">${fixture(kind,tab)}<script>${previewReady}</script></body></html>`;
 await page.route('https://fixture.test/**',async r=>{
  const url=new URL(r.request().url());
  if(url.pathname.endsWith('/createPoster')){
    const value=new URLSearchParams(r.request().postData()).get('value');assert.ok(value.startsWith('data:image/webp;base64,'));
    await import('node:fs/promises').then(fs=>fs.writeFile(`${out}/poster-${width}.webp`,Buffer.from(value.split(',')[1],'base64')));
    await new Promise(resolve=>setTimeout(resolve,350));
    return r.fulfill({body:''});
  }
  if(url.pathname.includes('/admin/pages/editor/file/'))return r.fulfill({contentType:'text/html',body:html(url.pathname.endsWith('/details/')?'details':'preview')});
  const ext=url.pathname.split('.').pop(),types={mp4:'video/mp4',webm:'video/webm',mp3:'audio/mpeg',svg:'image/svg+xml',pdf:'application/pdf',gif:'image/gif',png:'image/png',webp:'image/webp'};
  return r.fulfill({contentType:types[ext]||'application/octet-stream',body:await readFile('/private/tmp/fluxo-file-tabs/sample.'+ext).catch(()=>Buffer.from('fixture'))});
 });
 for(kind of ['pdf','svg','audio','video','webm','file','gif']){
  await page.goto('https://fixture.test/admin/pages/editor/file/?target_id=42');await settled();
  const preview=page.getByRole('tab',{name:kind==='video'?'Video & poster':'Preview',exact:true}),details=page.getByRole('tab',{name:'File details',exact:true});
  assert.equal(await preview.getAttribute('aria-selected'),'true');
  assert.equal(await page.locator('.fileEditor aside').count(),0,'No metadata sidebar for '+kind);
  assert.equal(await page.getByRole('link',{name:'Open original',exact:true}).count(),0);
  assert.equal(await page.getByRole('link',{name:'Download',exact:true}).count(),0);
  assert.equal(await page.locator('header [aria-label="Delete file"]').count(),0,'Destructive action stays in details');
  assert.equal(await page.locator('.fileEditor').getAttribute('data-mobile-dialog'),'sheet');
  if(kind==='pdf')assert.equal(await page.locator('iframe[title=PDF]').count(),1);
  if(kind==='svg'){await page.locator('#svgPreviewImage').waitFor();assert.equal(await page.locator('textarea[name=svgContent]').inputValue(),await readFile('/private/tmp/fluxo-file-tabs/sample.svg','utf8'));}
  if(kind==='gif'){await page.waitForFunction(()=>document.querySelector('.fileFrame img').naturalWidth>0);assert.equal(await page.locator('.cropEditor').count(),0);const frames=new Set();for(let t=0;t<8;t++){frames.add((await page.locator('.fileFrame img').screenshot()).toString('base64'));await page.waitForTimeout(100);}assert.ok(frames.size>1,'GIF visibly animates');}
  if(kind==='file')await page.getByText('A browser preview is not available for this file type.').waitFor();
  if(['audio','video','webm'].includes(kind)){
   if(width===390)await page.screenshot({path:`${out}/before-${kind}-${dark?'dark':'light'}.png`});
   const media=page.locator(kind==='audio'?'audio':'video');
   const mb=await media.boundingBox();assert.ok(mb.height>20&&mb.y>=0&&mb.y+Math.min(mb.height,100)<900,'Player is visible without scrolling '+kind+' '+JSON.stringify(mb));
   await page.waitForFunction(()=>document.querySelector('audio,video').readyState>=1);
   await media.evaluate(el=>el.play());await page.waitForFunction(()=>document.querySelector('audio,video').currentTime>.05);await media.evaluate(el=>el.pause());
  }
  if(kind==='video'){
   const checkVideoFit=async()=>{
    const box=await page.locator('#videosnapshot').boundingBox();const ratio=await page.locator('#videosnapshot').evaluate(v=>v.videoWidth/v.videoHeight);
    assert.ok(Math.abs(box.width/box.height-ratio)<.01,'Video element keeps the native ratio');
   };
   await checkVideoFit();
   assert.equal(await page.locator('#videoFormat select').count(),0);assert.equal(await page.locator('#videoPosterPreview').isVisible(),true,'Existing original poster stays visible');if(width<1440)assert.ok((await page.locator('#videoFormat button').first().boundingBox()).height>=44,'Touch choices are at least 44px high');
   if(width<768){
    const choices=page.locator('#videoFormat'),rail=await choices.boundingBox(),workspace=await page.locator('.videoWorkspace').boundingBox();
    assert.ok(rail.y>=workspace.y+workspace.height-1&&rail.y+rail.height<=900,'Mobile format choices stay below the video workspace');
    assert.equal(await choices.evaluate(e=>getComputedStyle(e).scrollbarWidth),'none','Video choices hide the scrollbar like image choices');
    const client=await page.context().newCDPSession(page),before=await mediaUrl(page);
    for(const [type,x] of [['touchStart',rail.x+rail.width*.8],['touchMove',rail.x+rail.width*.5],['touchMove',rail.x+rail.width*.2],['touchEnd',rail.x+rail.width*.2]])await client.send('Input.dispatchTouchEvent',{type,touchPoints:type==='touchEnd'?[]:[{id:17,x,y:rail.y+rail.height/2}]});
    await page.waitForFunction(()=>document.querySelector('#videoFormat').scrollLeft>30);await client.detach();
    assert.equal(await mediaUrl(page),before,'Swiping choices does not switch the video');
    await page.locator('[data-format="original"]').tap();
    await page.waitForFunction(()=>document.querySelector('video').readyState>=2&&document.querySelectorAll('.videoEditor [data-preview-loading]:not([hidden])').length===0);
    await page.screenshot({path:`${out}/video-formats-bottom-${width}-${dark?'dark':'light'}.png`});
   }else{
    const choices=await page.locator('#videoFormat').boundingBox(),workspace=await page.locator('.videoWorkspace').boundingBox();assert.ok(choices.x+choices.width<=workspace.x,'Wide layouts retain the format column beside the video');
   }
   const pick=page.locator('[data-format="videoTile"]');await pick[input]();
   await page.waitForFunction(()=>document.querySelector('[data-poster-empty]').hidden===false);
   assert.equal(await page.locator('.posterPane [data-preview-loading]:visible').count(),0,'No spinner for a poster that does not exist');assert.equal(await pick.getAttribute('aria-pressed'),'true');
   await page.waitForFunction(()=>document.querySelector('video').readyState>=2);assert.ok((await mediaUrl(page)).includes('/videoTile/'));await checkVideoFit();
   const snap=page.locator('#snap'),beforeSnap=await snap.boundingBox(),beforeVideo=await page.locator('#videosnapshot').boundingBox();
   const request=page.waitForRequest(r=>r.url().includes('/createPoster'));await snap[input]();const posted=await request;assert.ok(posted.url().includes('format=videoTile'));
   await page.locator('#snap.loading').waitFor();
   const still=async(locator,before,label)=>{const now=await locator.boundingBox();for(const key of ['x','y','width','height'])assert.ok(Math.abs(now[key]-before[key])<1,`${label} ${key} stays stable: ${before[key]} → ${now[key]}`);};
   await still(snap,beforeSnap,'Poster button while saving');await still(page.locator('#videosnapshot'),beforeVideo,'Video while saving');
   await page.screenshot({path:`${out}/poster-saving-${width}-${dark?'dark':'light'}.png`});
   await page.waitForFunction(()=>!document.querySelector('#snap').classList.contains('loading')&&document.querySelector('#videoPosterPreview').complete);
   await still(snap,beforeSnap,'Poster button after saving');await still(page.locator('#videosnapshot'),beforeVideo,'Video after saving');
   await page.locator('[data-format="missing"]')[input]();assert.equal(await page.locator('#snap').isDisabled(),true);assert.equal(await page.locator('.formatCreate').isVisible(),true);
   await page.waitForFunction(()=>document.querySelectorAll('.videoEditor [data-preview-loading]:not([hidden])').length===0);
   assert.equal(await page.locator('#videosnapshot').getAttribute('src'),null,'Missing format never requests a video');
   assert.equal(await page.locator('[data-video-empty]').isVisible(),true);assert.equal(await page.locator('[data-poster-empty]').isVisible(),true);
   await page.screenshot({path:`${out}/missing-video-${width}-${dark?'dark':'light'}.png`});
   await page.locator('[data-format="original"]')[input]();assert.equal(await page.locator('#snap').isEnabled(),true);
  }
  const header=await page.locator('.imageEditorHeader').boundingBox(),tabs=await page.locator('.ceTabs').boundingBox();assert.ok(tabs.x>=header.x&&tabs.x+tabs.width<=header.x+header.width+1,'Tabs fit '+kind);
  await details[input]();await page.waitForURL('**/details/?target_id=42');await page.waitForLoadState('load');await settled();
  assert.equal(await page.getByRole('tab',{name:'File details',exact:true}).getAttribute('aria-selected'),'true');
  assert.equal(await page.locator('.mediaDetailCards > .widget:first-child > footer').getByRole('link',{name:'Open original',exact:true}).count(),1);
  assert.equal(await page.locator('.mediaDetailCards > .widget:first-child > footer').getByRole('link',{name:'Download',exact:true}).count(),1);
  const groups=page.locator('.mediaDetails .taggroup:has(> .tags)');assert.equal(await groups.count(),2);
  for(const g of await groups.all()){
   const heading=g.locator('h6');assert.equal(await heading.evaluate(e=>getComputedStyle(e).borderBottomWidth),'1px');
   const titleBox=await heading.boundingBox(),tagsBox=await g.locator('.tags').boundingBox();
   assert.ok(Math.abs(titleBox.x-tagsBox.x)<1&&Math.abs(titleBox.width-tagsBox.width)<1,'Widget tag lines stay within the content inset');
   const boxes=await g.locator('.tags > .tag').evaluateAll(es=>es.map(e=>{let b=e.getBoundingClientRect();return {x:b.x,y:b.y,right:b.right,bottom:b.bottom};}));
   for(let i=1;i<boxes.length;i++)if(Math.abs(boxes[i].y-boxes[i-1].y)<2)assert.ok(boxes[i].x-boxes[i-1].right>=5,'Tag pills have space');
  }
  for(const name of ['File','Tags','Technical details','Permissions','Delete file'])assert.equal(await page.getByRole('heading',{name,exact:true}).count(),1,name+' uses the common card');
  assert.equal(await page.getByRole('button',{name:'Delete file',exact:true}).innerText(),'Delete file');
  const fields=await page.getByRole('textbox',{name:'Label',exact:true}).boundingBox();assert.ok(fields.width>100&&fields.x>=0&&fields.x+fields.width<=width,'Details fields fit '+kind);
  if(kind==='video'&&width>=768){
   const cards=await page.locator('.mediaDetailCards > .widget').evaluateAll(es=>es.slice(0,4).map(e=>{const r=e.getBoundingClientRect();return {height:r.height,top:r.top,bottom:r.bottom};}));
   assert.ok(Math.abs(cards[0].height-cards[1].height)<1);assert.ok(Math.abs(cards[2].height-cards[3].height)<1);
   assert.ok(Math.abs(cards[0].height-cards[2].height)>10,'Different rows keep their own content height');
   const footer=await page.locator('.mediaDetailCards > .widget:first-child > footer').boundingBox();assert.ok(Math.abs(footer.y+footer.height-cards[0].bottom)<2,'File footer sits at the bottom');
   const permissions=await page.locator('.mediaDetailPermissions').boundingBox(),remove=await page.locator('.mediaDetailDelete').boundingBox();
   assert.ok(Math.abs(permissions.y-remove.y)<1&&remove.x>permissions.x,'Permissions and delete share the last row');
  } else if(kind==='video') {
   const permissions=await page.locator('.mediaDetailPermissions').boundingBox(),remove=await page.locator('.mediaDetailDelete').boundingBox();
   assert.ok(remove.y>=permissions.y+permissions.height,'Narrow cards remain stacked');
  }
  if(kind==='video')await page.screenshot({path:`${out}/details-${width}-${dark?'dark':'light'}.png`});
  if(kind==='video'&&width===1440){
   const sidebar=await page.evaluate(()=>{
    const aside=document.createElement('aside');aside.className='cms';aside.style.cssText='position:fixed;left:0;top:100px;width:320px;display:block;z-index:10000';
    const item=document.createElement('div');item.className='sidebarItem';item.style.padding='1rem';item.append(document.querySelector('.mediaDetails .taggroup:has(> .tags)').cloneNode(true));aside.append(item);document.body.append(aside);
    const group=item.querySelector('.taggroup'),heading=group.querySelector('h6'),g=group.getBoundingClientRect(),h=heading.getBoundingClientRect();const result={extra:h.width-g.width,inset:g.x-h.x,rem:parseFloat(getComputedStyle(document.documentElement).fontSize)};aside.remove();return result;
   });
   assert.ok(Math.abs(sidebar.extra-2*sidebar.rem)<1&&Math.abs(sidebar.inset-sidebar.rem)<1,'Sidebar tag titles preserve their existing gutter bleed: '+JSON.stringify(sidebar));
  }
  await page.getByRole('tab',{name:kind==='video'?'Video & poster':'Preview',exact:true})[input]();await page.waitForURL('**/file/?target_id=42');await page.waitForLoadState('load');await settled();
  if(kind==='video')await page.waitForFunction(()=>document.querySelector('video').readyState>=2);
  if(kind==='video')await page.screenshot({path:`${out}/video-${width}-${dark?'dark':'light'}.png`});
 }
 assert.deepEqual(errors,[]);console.log(`PASS ${width} ${dark?'dark':'light'}: seven media types; video format buttons and poster capture; spaced tag groups; touch/mouse tabs; shared cards; video/audio playback; preview fallback; no browser errors`);await page.close();
}}finally{await browser.close()}
