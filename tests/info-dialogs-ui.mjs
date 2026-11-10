import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();await page.route('**/*',r=>r.abort());
 await page.setContent(await readFile('/private/tmp/fluxo-info-dialogs.html','utf8'));
 await page.addScriptTag({content:await readFile(new URL('../../system/public/js/ext/jquery.js',import.meta.url),'utf8')});
 await page.addScriptTag({content:await readFile(new URL('../../system/public/js/gui/ui.js',import.meta.url),'utf8')});
 await page.evaluate(()=>{window.infoClosures=[];document.addEventListener('click',e=>{const close=e.target.closest('header [data-box-close], header a[data-history="false"]');if(!close)return;e.preventDefault();infoClosures.push(close.hasAttribute('data-box-close')?'local':{href:close.getAttribute('href'),history:close.dataset.history});close.closest('.cmslightbox').remove();},true);});
 for(let i=0;i<3;i++)await page.keyboard.press('Escape');
 assert.equal(await page.locator('[data-transient-info]').count(),0);
 assert.deepEqual(await page.evaluate(()=>infoClosures),['local','local',{href:'/public/admin/pages/?id=10&lang=de',history:'false'}]);
 console.log('ok: Escape closes sidebar info locally and Media Library info without history');
}finally{await browser.close();}
