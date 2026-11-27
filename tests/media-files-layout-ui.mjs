/** Production Show files markup with the shared mobile table controller. */
import {readFile,readdir,mkdir} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const root=new URL('../../system/',import.meta.url).pathname;
let css=(await Promise.all((await readdir(root+'public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>readFile(root+'public/css/'+n,'utf8')))).join('\n');
css+='\n'+await readFile(new URL('../public/css/pages.css',import.meta.url),'utf8');
const html=execFileSync('php',['tests/file-editor-tabs-fixture.php','image','files'],{encoding:'utf8'});
const out='/private/tmp/fluxo-media-files-layout';await mkdir(out,{recursive:true});
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [1440,1024,390])for(const dark of [false,true]){
 const p=await browser.newPage({viewport:{width,height:900},hasTouch:width<1440});const errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.setContent(`<html data-mode="${dark?'dark':'light'}"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="cms">${html}</body></html>`);
 await p.evaluate(()=>window.sys={});await p.addScriptTag({path:root+'public/js/ext/jquery.js'});await p.addScriptTag({path:root+'public/js/gui/tableFit.js'});
 await p.evaluate(()=>sys.fitTables($(document)));
 const row=p.locator('.mediaFiles tbody > tr').nth(1);
 if(width<768){await row.locator('[data-table-fold]').tap();assert.ok(await row.evaluate(e=>e.classList.contains('is-open')));}
 const values=row.locator('.variantLink');
 const boxes=await values.evaluateAll(es=>es.map(e=>({x:e.getBoundingClientRect().x,align:getComputedStyle(e.parentElement).textAlign})));
 assert.ok(boxes.every(b=>['left','start'].includes(b.align)),JSON.stringify({width,boxes}));
 if(width<768)assert.ok(boxes.every(b=>Math.abs(b.x-boxes[0].x)<1),'All generated values share one left edge');
 await p.screenshot({path:`${out}/files-${width}-${dark?'dark':'light'}.png`});assert.deepEqual(errors,[]);
 console.log(`PASS Show files ${width} ${dark?'dark':'light'}: left-aligned format names and dimensions, shared accordion/table, no browser errors`);await p.close();
}}finally{await browser.close();}
