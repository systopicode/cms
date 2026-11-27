/** Displayed media crop and editor navigation. All responses and media are disposable. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
css+=await readFile(new URL('../panels/admin/010_pages/pages.mml.css',import.meta.url),'utf8');
const head=await readFile(root+'panels/admin/head.tpl.php','utf8');
css+=head.match(/\.cmsMediaFormat\{[\s\S]*?\}/)[0];
const icons=[...new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1]))];
for(const name of icons)css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const pickerSource=await readFile(new URL('../panels/admin/010_pages/sidebar/panel.php',import.meta.url),'utf8');
const picker=pickerSource.match(/\$picker = '([^']+)'/)[1];
const ratioChoices=JSON.parse(execFileSync('php',['tests/image-ratio-choices.php','--json'],{encoding:'utf8'}));
const out='/private/tmp/fluxo-media-entry';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1024,390])for(const dark of [false,true]){
 const page=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fixture.test/**',r=>r.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#297387"/></svg>'}));
 await page.setContent(`<base href="https://fixture.test/"><style>${css}</style><style>
 body{margin:0;padding:20px;font:16px sans-serif} .cmsPreview{width:65vw;height:360px;overflow:hidden;position:relative}article.cmsContent{width:1200px;height:650px}#fixed{width:1200px;height:600px;display:block} .editor{margin-top:20px;width:200px;height:120px} .editor img{width:160px;height:100px}aside{width:${Math.min(280,width-40)}px;position:absolute;right:20px;top:400px}.mediaDrop{box-sizing:border-box}.library{display:none}
 </style><body class="cms ${width<1440?'cmsMobile':''}" ${dark?'data-theme="dark"':''}>
 <div class="library"><li data-media_id="42" data-node_id="169"></li></div>
 <div class="cmsPreview"><article class="cmsContent"><a href="https://fixture.test/original.jpg"><img id="fixed" src="https://fixture.test/var/formats/000/42/picture--2mp16by9.webp"></a></article></div>
 <article class="cmsContent"><div class="editor" contenteditable="true"><img id="rich" src="https://fixture.test/var/formats/000/42/picture--shareImage.jpg" data-ref-src="media::169?format=shareImage"></div></article>
 <aside><div class="mediaDrop shareImage">${picker}</div></aside></body>`);
 await page.evaluate(dark=>document.documentElement.dataset.mode=dark?'dark':'light',dark);
 await page.addScriptTag({path:root+'public/js/ext/jquery.js'});
 await page.evaluate(()=>{window.settings={siteRoot:'/public/'};window.contextMenus=[];window.cmsMenuSequence=0;window.prepareMenuKeyboard=()=>{};window.closeAllMenus=()=>document.querySelectorAll('.HTMLeditorMenu').forEach(e=>e.parentElement.remove());window.editorFormats={image:{panorama:'Panorama'},video:{}};window.wysiwyg={canStep:()=>false,active:()=>false,inline:()=>false,blockFor:()=>null,changed:()=>{}};window.navigations=[];window.requests=[];window.sys={core:{server:{onIdle:fn=>fn(),sendCall:(data,url)=>navigations.push(url),call:class{constructor(data){this.data=data}send(url){requests.push({url,...this.data});return {done:fn=>{const format=new URLSearchParams(this.data.ref.split('?')[1]).get('format');fn({url:format==='master'||/^\d+by\d+$/.test(format)?'/var/formats/000/42/photo--1mp'+(format==='master'?'':format)+'.webp':'/var/original/000/42/photo.jpg',editorUrl:'/public/admin/pages/editor/file/image/'+(format==='shareImage'?'formats':format==='original'?'master':'crops')+'/?target_id=169&format='+encodeURIComponent(format)});return {fail:()=>{}}},fail:()=>{}}}}}}}});
 await page.addScriptTag({path:root+'public/js/gui/HTMLeditor.funcs.js'});
 await page.addScriptTag({path:root+'public/js/gui/HTMLeditor.contextmenu.js'});
 await page.addScriptTag({path:root+'public/js/gui/HTMLeditor.menus.js'});
 await page.waitForLoadState('networkidle');
 const image=page.locator('#fixed');
 if(width<1440)await image.tap({position:{x:60,y:80}});else await image.click({position:{x:60,y:80}});
 await page.getByRole('button',{name:'Crop and adjust',exact:true}).waitFor();
 const chip=page.locator('.cmsMediaFormat');
 if(width===1440)await image.hover({position:{x:100,y:140}});
 else await page.evaluate(()=>cmsMediaFormatShow(document.querySelector('#fixed')));
 assert.equal(await chip.innerText(),'16:9');
 const b=await chip.boundingBox(),v=await page.locator('.cmsPreview').boundingBox();
 assert.ok(b.x>=v.x&&b.x+b.width<=v.x+v.width&&b.y>=v.y&&b.y+b.height<=v.y+v.height,'Badge stays inside the visible clipped image');
 await page.screenshot({path:`${out}/hover-${width}-${dark?'dark':'light'}.png`});
 await page.getByRole('button',{name:'Crop and adjust',exact:true}).click();
 await page.waitForFunction(()=>navigations.length===1);
 assert.match(await page.evaluate(()=>navigations[0]),/image\/crops\/\?target_id=169&format=16by9$/);
 assert.equal(await page.evaluate(()=>fixedMediaNodeId(document.querySelector('#fixed'))),'169');
 const rich=page.locator('#rich');if(width<1440)await rich.tap();else await rich.click();
 await page.getByRole('button',{name:'Crop and adjust',exact:true}).click();
 await page.waitForFunction(()=>navigations.length===2);
 assert.match(await page.evaluate(()=>navigations[1]),/image\/formats\/\?target_id=169&format=shareImage$/);
 await page.evaluate(()=>{const img=document.querySelector('#rich');img.setAttribute('data-ref-src','media:169?format=16%3A9');window.encoded=editorMediaRef(img).format;});
 assert.equal(await page.evaluate(()=>encoded),'16:9');
 const icon=page.locator('.shareImagePicker .cmsIcon');assert.equal(await icon.isVisible(),true);
 assert.notEqual(await icon.evaluate(el=>getComputedStyle(el,'::before').maskImage),'none','Shared image icon has an SVG mask');
 assert.equal((await page.locator('.shareImagePicker').innerText()).toLowerCase(),width<1440?'tap to select from media library':'drop image from media library');
 const ib=await icon.boundingBox(),pb=await page.locator('.shareImagePicker').boundingBox();assert.ok(ib.y>=pb.y&&ib.y+ib.height<=pb.y+pb.height,'Empty-state icon fits');
 await page.evaluate(()=>changeMediaFormat({data:{target:document.querySelector('#fixed'),format:'panorama'}}));
 assert.equal(await image.getAttribute('src'),'/var/original/000/42/photo.jpg','Format selection uses resolved URL for new media paths');
 await rich.scrollIntoViewIfNeeded();
 await page.evaluate(choices=>{editorFormats.image=choices;const img=document.querySelector('#rich');img.dataset.refMimetype='image/jpeg';closeAllMenus();HTMLeditorRightClick('image',{target:img,anchorRect:img.getBoundingClientRect()},[img]);},ratioChoices);
 const input=width<1440?'tap':'click';
 await page.getByRole('button',{name:'Aspect ratio',exact:true})[input]();
 const menu=page.locator('.HTMLeditorMenu');
 await menu.getByRole('button',{name:'Master',exact:true}).waitFor({state:'visible',timeout:6000});
 assert.equal(await menu.getByRole('button',{name:'Master',exact:true}).count(),1);
 for(const label of ['background','projektCollection','new','mPaspect'])assert.equal(await menu.getByRole('button',{name:label,exact:true}).count(),0,'Legacy alias is not offered');
 await page.screenshot({path:`${out}/ratio-choices-${width}-${dark?'dark':'light'}.png`});
 await menu.getByRole('button',{name:'1:1',exact:true})[input]();
 await page.waitForFunction(()=>document.querySelector('#rich').getAttribute('data-ref-src')==='media:169?format=1by1');
 assert.match(await rich.getAttribute('src'),/photo--1mp1by1\.webp$/);
 assert.equal(await page.evaluate(()=>mediaFormatOf(document.querySelector('#rich'))),'1by1');
 await page.evaluate(()=>{closeAllMenus();const img=document.querySelector('#rich');HTMLeditorRightClick('image',{target:img,anchorRect:img.getBoundingClientRect()},[img]);});
 await page.getByRole('button',{name:'Aspect ratio',exact:true})[input]();
 await page.getByRole('button',{name:'Master',exact:true})[input]();
 await page.waitForFunction(()=>document.querySelector('#rich').getAttribute('data-ref-src')==='media:169?format=master');
 assert.match(await rich.getAttribute('src'),/photo--1mp\.webp$/);
 assert.deepEqual(errors,[]);
 console.log(`PASS ${width} ${dark?'dark':'light'}: clipped badge; mouse/touch media menus; correct crop and WYSIWYG target; empty share icon`);
 await page.close();
}}finally{await browser.close()}
