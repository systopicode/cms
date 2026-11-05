import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
let css=(await Promise.all((await fs.readdir('../system/public/css')).filter(n=>n.endsWith('.css')).sort().map(n=>fs.readFile('../system/public/css/'+n,'utf8')))).join('\n');
css+='\n'+await fs.readFile('../system/panels/auth/auth.css','utf8');
css+='\n'+await fs.readFile('../system/panels/admin/user/profile.css','utf8');
css+='\n'+await fs.readFile('panels/admin/010_pages/panel.css','utf8');
const form=execFileSync('php',['-r',`class http {public static $root='/';} class message {static function flush(...$args){return '';}} $authTitle='Reset password';$authLead='We email you a code.';$authForm='<form><input aria-label="Email"><button class="button highlight">Send code</button></form>';include '../system/src/Auth/form.card.tpl.php';`],{encoding:'utf8'});
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [390,1194,1440])for(const mode of ['light','dark']){
  const page=await browser.newPage({viewport:{width,height:900}});
  await page.setContent(`<style>${css}</style><html data-mode=${mode}><body class="cms auth"><div class=authCard>${form}</div></body></html>`);
  const button=page.locator('.authFoot .button');
  assert.equal(await button.evaluate(e=>getComputedStyle(e).backgroundColor),'rgba(0, 0, 0, 0)','footer uses transparent shared button');
  const edges=await page.evaluate(()=>{const a=document.querySelector('.authFoot .button').getBoundingClientRect(),b=document.querySelector('.authPane').getBoundingClientRect();return [a.x,b.x]});
  assert.ok(Math.abs(edges[0]-edges[1])<1,'footer button and content have matching inset');
  await page.screenshot({path:`/private/tmp/fluxo-auth-footer-${width}-${mode}.png`});
  await page.setContent(`<style>${css}</style><html data-mode=${mode}><body class=cms><aside class=cms><div class="sidebarItem pageRelations mediaUsage"><ul class=relationIncoming><li><a class="button relationLink" href="#"><i class="cmsIcon cmsIcon-file"></i><span class=relationLinkText>Long page title<br><small>Media library</small></span></a></li></ul></div></aside><div class="cms cmslightbox"><section><header><h1>Dialog</h1><button class="button transparent icononly close"><i class="cmsIcon cmsIcon-xmark"></i></button></header><main><div class=fileFrame><svg class=qr-code width=100 height=100><path d="M0 0h10v10H0z"/></svg></div><a class="button cmsExternalLink" href="#">Website<i class="cmsIcon cmsIcon-up-right-from-square"></i></a></main><footer><button class=button>Done</button></footer></section></div></body></html>`);
  const qr=page.locator('svg.qr-code');assert.equal(await qr.evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(255, 255, 255)','QR remains white in both modes');
  const pads=await page.evaluate(()=>['header','footer'].map(q=>parseFloat(getComputedStyle(document.querySelector('.cmslightbox section>'+q)).paddingRight)));
  assert.ok(Math.abs(pads[0]-pads[1])<1,'dialog header and footer use matching right padding '+pads);
  const order=await page.locator('.cmsExternalLink i').evaluate(e=>getComputedStyle(e).order);assert.equal(order,'-1','external icon precedes label');
  await page.locator('.cmslightbox').evaluate(e=>e.remove());
  await page.locator('.relationLink').hover();
  const border=await page.locator('.relationLink').evaluate(e=>({border:getComputedStyle(e).borderWidth,shadow:getComputedStyle(e).boxShadow}));assert.equal(border.border,'0px');assert.equal(border.shadow,'none');
  await page.close();
 }
 const portrait=await browser.newPage();await portrait.setContent(`<style>${css}</style><body class=cms><div class=profile><form class=portraitDrop style="width:100px;height:100px"><input type=file aria-label=Portrait></form></div></body>`);await portrait.locator('input').focus();assert.equal(await portrait.locator('.portraitDrop').evaluate(e=>getComputedStyle(e).borderRadius),'50%');assert.equal(await portrait.locator('.portraitDrop').evaluate(e=>getComputedStyle(e).outlineStyle),'solid');await portrait.close();
 const remote=execFileSync('php',['-r',`class http {public static $root='/';} class message {static function flush(...$args){return '';}} class qr {static function payload(...$a){return '';}static function svgMarkup(...$a){return '<svg></svg>';}} $authMethod='device';$authCodes=true;$authPairUrl='/fixture/';$authPair=['expires'=>time()-1,'code'=>'TEST-TEST'];include '../system/src/Auth/login.card.tpl.php';`],{encoding:'utf8'});
 const expiry=await browser.newPage();await expiry.setContent(`<style>${css}</style><body class="cms auth"><div class=authCard>${remote}</div></body>`);await expiry.addScriptTag({path:'../system/public/js/gui/code-until.js'});
 assert.equal(await expiry.locator('[data-code-live]').isVisible(),false);assert.equal(await expiry.locator('[data-code-expired] .warning').isVisible(),true,'expired remote code uses shared warning');assert.ok(!remote.includes('on the phone'),'remote instructions use device');await expiry.close();
 const standalone=execFileSync('php',['../system/panels/admin/090_tools/010_system/systemcheck/systemcheck.php'],{encoding:'utf8'});
 const standalonePage=await browser.newPage({viewport:{width:1194,height:834}});await standalonePage.setContent(standalone);
 const alignments=await standalonePage.locator('li').evaluateAll(es=>es.map(e=>{const a=e.children[0].getBoundingClientRect(),b=e.children[1].getBoundingClientRect();return Math.abs(a.y+a.height/2-b.y-b.height/2)}));assert.ok(alignments.every(n=>n<1),'standalone symbols vertically centered alongside wrapped text');await standalonePage.screenshot({path:'/private/tmp/fluxo-standalone-check.png'});await standalonePage.close();
 console.log('PASS final shared UI: auth footer insets, transparent buttons, white QR, dialog footer alignment, external icon order, used-on hover at 390/1194/1440 light/dark');
}finally{await browser.close()}
