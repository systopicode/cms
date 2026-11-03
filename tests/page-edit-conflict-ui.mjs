import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();
 await page.setContent('<div class=editor data-node_id=9 data-language_iso=en data-field=text01 data-field_version=old contenteditable=true>My unsaved text</div>');
 const source=await readFile('panels/admin/010_pages/pages.js','utf8');
 await page.addScriptTag({content:source.slice(source.indexOf('// Optimistic field versions:'))});
 await page.evaluate(()=>{window.saves=[];document.querySelector('.editor').addEventListener('blur', e=>saves.push([e.target.textContent,e.target.dataset.field_version]));pageEditConflict(9,'en','text01','newer',{author:'Test editor',at:'2026-10-30 12:00:00'});});
 assert.equal(await page.locator('#pageEditConflict').count(),1);
 assert.match(await page.locator('#pageEditConflict').innerText(),/Test editor.*2026-10-30 12:00:00/);
 assert.equal(await page.locator('.editor').textContent(),'My unsaved text');
 await page.locator('[data-choice=keep]').click();
 assert.deepEqual(await page.evaluate(()=>saves),[['My unsaved text','newer']]);
 assert.equal(await page.locator('#pageEditConflict').count(),0);
 await page.evaluate(()=>{pageEditConflict(9,'en','text01','third');pageEditConflict(9,'en','text01','fourth');});
 assert.equal(await page.locator('#pageEditConflict').count(),1,'no stacked conflict dialogs');
 await page.locator('[aria-label=Close]').click();
 assert.equal(await page.locator('.editor').getAttribute('data-field_version'),'newer','closing does not acknowledge another version');
 await page.evaluate(()=>{
  document.querySelector('.editor').insertAdjacentHTML('afterend','<aside><select data-field="meta_robots" data-node_id="9" data-language_iso="en" data-field_version="old" data-on_change="sidebar/updateParam"><option value="noindex">No index</option></select></aside>');
  document.querySelector('select').addEventListener('change',e=>saves.push(['select',e.target.dataset.field_version]));
  pageEditConflict(9,'en','meta_robots','changed-robots');
 });
 await page.locator('[data-choice=keep]').click();
 assert.deepEqual((await page.evaluate(()=>saves)).at(-1),['select','changed-robots'],'sidebar overwrite uses its change event');
 await page.evaluate(()=>pageEditConflict(42,'de','text01','late'));
 assert.equal(await page.locator('#pageEditConflict').count(),0,'late response for a previous page does not open a dialog here');
 console.log('ok: conflict preserves text, explicit overwrite uses exact version, close keeps protection');
} finally {await browser.close();}
