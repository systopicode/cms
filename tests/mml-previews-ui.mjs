/** Production MML previews: a still in the library, animated original elsewhere. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
for(const path of ['../public/css/pages.css','../panels/admin/010_pages/pages.mml.css','../panels/admin/010_pages/panel.css'])css+='\n'+await readFile(new URL(path,import.meta.url),'utf8');
for(const name of new Set([...css.matchAll(/url\("\.\.\/icons\/([^"?]+)(?:\?[^\"]*)?"\)/g)].map(m=>m[1])))css=css.replaceAll(`url("../icons/${name}")`,`url("data:image/svg+xml;base64,${(await readFile(root+'public/icons/'+name)).toString('base64')}")`);
const library=execFileSync('php',['-d','zend.exception_ignore_args=1','tests/mml-previews-fixture.php'],{encoding:'utf8'});
const out='/private/tmp/fluxo-mml-previews';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,834,390])for(const dark of [false,true]){
 const p=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.route('https://fixture.test/**',async r=>{const path=new URL(r.request().url()).pathname;
  if(path.endsWith('sample.png')||path.includes('sample--'))return r.fulfill({body:'<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="teal"/></svg>',contentType:'image/svg+xml'});
  try{await r.fulfill({body:await readFile(out+path),contentType:path.endsWith('.gif')?'image/gif':path.endsWith('.webp')?'image/webp':'font/woff2'});}catch{await r.fulfill({body:'',contentType:'text/html'});}
 });
 await p.goto('https://fixture.test/admin/pages/');
 await p.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms pages"><aside class="cms" style="position:relative;display:block;inset:auto;width:min(100%,360px);height:500px"><div class="mml"><div class="mmlContent">${library}</div></div></aside><div style="position:absolute;left:0;top:550px"><p>Animated original</p><img id="original" src="https://fixture.test/var/original/000/08/animated.gif"></div></body>`);
 await p.evaluate(dark=>{if(dark)document.documentElement.dataset.mode='dark';},dark);
 await p.waitForFunction(()=>[...document.querySelectorAll('img')].every(i=>i.complete&&i.naturalWidth>0));
 assert.match(await p.locator('.mml img[alt="External media"]').getAttribute('src'),/\/formats\/000\/07\/sample--0\.5mp\.webp\?v=/,'Raster MML previews use the modern master');
 const gif=p.locator('.mml img[alt="Animated GIF"]');assert.match(await gif.getAttribute('src'),/\/formats\/mmlThumb\/.*\.gif\.webp\?v=/);
 const icon=p.locator('.mml [data-type=youtube] > .thumbType');assert.equal(await icon.count(),1);assert.ok(await icon.evaluate(e=>e.classList.contains('cmsIcon-arrow-up-right-from-square')));
 assert.notEqual(await icon.evaluate(e=>getComputedStyle(e,'::before').maskImage),'none','External-link glyph is rendered');
 const stills=new Set(),animated=new Set();for(let i=0;i<8;i++){stills.add((await gif.screenshot()).toString('base64'));animated.add((await p.locator('#original').screenshot()).toString('base64'));await p.waitForTimeout(100);}
 assert.equal(stills.size,1,'GIF thumbnail remains still');assert.ok(animated.size>1,'Original continues animating');
 await p.screenshot({path:`${out}/library-${width}-${dark?'dark':'light'}.png`});assert.deepEqual(errors,[]);
 console.log(`PASS MML ${width} ${dark?'dark':'light'}: real preview helpers and markup, visibly static GIF thumbnail, animated original, rendered external-link icon; no browser errors`);await p.close();
}}finally{await browser.close();}
