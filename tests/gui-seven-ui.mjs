import fs from 'node:fs/promises';import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
const browser=await chromium.launch({channel:'chrome',headless:true});
try{for(const width of [390,1194,1440])for(const mode of ['light','dark']){
 const p=await browser.newPage({viewport:{width,height:834},hasTouch:width<1200});
 await p.setContent(`<html data-mode=${mode}><style>${css}.fixture{position:fixed;top:50px;left:32px;right:32px;display:flex;justify-content:space-between}</style><body class="cms backup"><div class=fixture><div class=splitApply><button class="button splitApplyMenu">Left menu</button><div class=splitApplyList hidden><button class=button>A rather wide menu action</button></div></div><div class=splitApply><button class="button splitApplyMenu">Right menu</button><div class=splitApplyList hidden><button class=button>A rather wide menu action</button></div></div></div><div class=backupProgress style="margin:150px 24px"><div class=bar><b>50%</b><div style="width:50%"><b>50%</b></div></div></div></body></html>`);
 await p.addScriptTag({path:'../system/public/js/gui/dropdownMenus.js'});
 for(const name of ['Left menu','Right menu']){await p.getByRole('button',{name,exact:true}).click();const q=await p.locator('.splitApplyList.is-open').evaluate(e=>{let a=e.getBoundingClientRect(),b=e.splitHome.getBoundingClientRect();return {left:a.left,right:a.right,bl:b.left,br:b.right};});assert.ok(Math.min(Math.abs(q.left-q.bl),Math.abs(q.right-q.br))<1,JSON.stringify(q));await p.getByRole('button',{name,exact:true}).click();}
 const bar=await p.locator('.bar').evaluate(e=>({radius:getComputedStyle(e).borderRadius,buttonRadius:getComputedStyle(document.querySelector('button')).borderRadius,fill:getComputedStyle(e.querySelector('div')).backgroundColor,main:getComputedStyle(e).getPropertyValue('--cmsMainColor')}));assert.equal(bar.radius,bar.buttonRadius);
 assert.ok(!bar.fill.includes('gradient'));await p.screenshot({path:`/private/tmp/fluxo-seven-${width}-${mode}.png`});await p.close();
}console.log('PASS dropdown left/right anchors, upload radius; desktop/tablet/narrow and light/dark');}finally{await browser.close();}
