/** Run the public edit bar against hostile frontend CSS without project accounts or data. */
import {readFile,mkdir} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE ? pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href : 'playwright');
const script=await readFile(new URL('../public/site/editBar.js',import.meta.url),'utf8');
const frontend=process.env.FLUXO_FRONTEND_CSS ? await readFile(process.env.FLUXO_FRONTEND_CSS,'utf8') : '';
const output=process.env.FLUXO_ARTIFACTS;
if(output) await mkdir(output,{recursive:true});
const hostile=`*{font:300 27px/2 Georgia,serif!important;color:red!important;letter-spacing:8px;text-transform:uppercase;transition:all 2s;--main:lime;--high:red;--onMain:black}fluxo-edit-bar{position:relative!important;left:200px!important;bottom:100px!important;z-index:1!important;opacity:.1!important;transform:scale(.2)!important}`;
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 for(const width of [1440,834,390]) for(const polluted of [false,true]) {
  const page=await browser.newPage({viewport:{width,height:900},hasTouch:width<1025,isMobile:width<1025});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.context().route('**/*',route=>{
   const url=new URL(route.request().url());
   return route.fulfill({contentType:url.pathname.endsWith('editBarMenu')?'application/json':'text/html',body:url.pathname.endsWith('editBarMenu')?JSON.stringify([{href:'/admin/pages/',label:'Pages',icon:'cmsIcon cmsIcon-file'}]):'<html></html>'});
  });
  await page.goto('https://fixture.example.test/');
  await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><style>${polluted?frontend+hostile:''}</style><p id="website">Website content</p><fluxo-edit-bar></fluxo-edit-bar>`);
  const outsideBefore=await page.locator('#website').evaluate(el=>({font:getComputedStyle(el).font,color:getComputedStyle(el).color}));
  await page.evaluate(()=>{window.sys={singletons:{pages:{renderer:{__data:{page:{id:42,lang:'de'}}}}}};window.fluxoEditBar={cms:'https://fixture.example.test/admin/',page:{id:7,lang:'en'},name:'Test Editor',portrait:'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"/>',signet:'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"/>',icons:'data:text/css,',main:'#1f3a45',highlight:'#fcb60c',bg:'#f0f3f4',onMain:'#ffffff',onHighlight:'#1b1b1b'};});
  await page.addScriptTag({content:script});
  const style=await page.locator('fluxo-edit-bar').evaluate(host=>{
   const bar=host.shadowRoot.querySelector('.bar'),link=host.shadowRoot.querySelector('.edit'),text=link.querySelector('span');
   const hostStyle=getComputedStyle(host),barStyle=getComputedStyle(bar),textStyle=getComputedStyle(text);
   return {position:hostStyle.position,left:host.getBoundingClientRect().left,opacity:hostStyle.opacity,transform:hostStyle.transform,font:textStyle.fontFamily,size:textStyle.fontSize,spacing:textStyle.letterSpacing,casing:textStyle.textTransform,bg:barStyle.backgroundColor,linkBg:getComputedStyle(link).backgroundColor,color:textStyle.color};
  });
  assert.deepEqual(style,{position:'fixed',left:16,opacity:'1',transform:'none',font:'system-ui, -apple-system, "Segoe UI", sans-serif',size:'13px',spacing:'normal',casing:'none',bg:'rgb(31, 58, 69)',linkBg:'rgb(252, 182, 12)',color:'rgb(27, 27, 27)'});
  assert.deepEqual(await page.locator('#website').evaluate(el=>({font:getComputedStyle(el).font,color:getComputedStyle(el).color})),outsideBefore,'Edit bar must not change the website');
  const menu=page.locator('.menu');
  await page.getByRole('button',{name:'fluxo menu'}).click();
  await page.getByRole('link',{name:'Pages',exact:true}).waitFor();
  assert.equal(await menu.isVisible(),true);
  assert.equal(await menu.evaluate(el=>getComputedStyle(el).color),'rgb(27, 31, 34)');
  const bounds=await menu.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width);
  if(output&&polluted) await page.screenshot({path:`${output}/edit-menu-${width}.png`});
  await page.keyboard.press('Escape');assert.equal(await menu.isVisible(),false);
  const popupPromise=page.waitForEvent('popup');await page.getByRole('link',{name:'Edit page',exact:true}).click();
  const popup=await popupPromise;await popup.waitForLoadState('domcontentloaded');
  assert.equal(popup.url(),'https://fixture.example.test/admin/pages/?id=42&lang=de');await popup.close();
  await page.getByRole('button',{name:'Fold the bar'}).click();
  assert.equal(await page.getByRole('link',{name:'Edit page'}).isVisible(),false);
  assert.equal(await page.evaluate(()=>localStorage.getItem('fluxoEditBar')),'folded');
  await page.getByRole('button',{name:'fluxo menu'}).click();
  assert.equal(await page.getByRole('link',{name:'Edit page'}).isVisible(),true);
  assert.deepEqual(errors,[]);
  console.log(`PASS ${width}/${polluted?'frontend CSS':'baseline'}: isolated font, colors and position; menu, Escape, current page link, fold and unfold`);
  await page.close();
 }
}finally{await browser.close();}
