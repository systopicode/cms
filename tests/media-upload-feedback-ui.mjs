import {readFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import assert from 'node:assert/strict';
const {chromium}=await import(pathToFileURL(process.env.FLUXO_PLAYWRIGHT_MODULE).href);
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<style>.mmlContent>div>ul{height:180px;overflow:auto}.spacer{height:900px}</style><div class="mmlContent"><div><ul><li class="spacer">Media</li></ul></div></div><input id="replace" type="file" accept=".png">');
 await page.evaluate(()=>{window.sent=[];window.sys={lib:{applyParentConstructor(){},add:(s,c)=>{window.upload={};c.call(upload);}},core:{server:{sendGet:(...args)=>{sent.push(args);return {done(){}};}}}};});
 await page.addScriptTag({content:await readFile(new URL('../panels/cms/010_pages/upload/panel.js',import.meta.url),'utf8')});
 await page.locator('#replace').setInputFiles({name:'wrong.webm',mimeType:'video/webm',buffer:Buffer.from('invalid fixture')});
 await page.evaluate(()=>{document.querySelector('.mmlContent ul').scrollTop=700;const input=document.querySelector('#replace');upload.open({sourceElement:input,element:input,data:{dataset:{node_id:42,media_group:'images'}}});});
 assert.equal(await page.evaluate(()=>sent.length),0,'invalid replacement must not open upload lightbox');
 assert.equal(await page.locator('.mmlContent ul').evaluate(el=>el.scrollTop),0,'MML scroll returns to message at top');
 assert.match(await page.locator('[data-system-messages] .error .message').innerText(),/wrong.webm.*not allowed/);
 assert.equal(await page.locator('[data-system-messages]').getAttribute('role'),'alert');
 await page.evaluate(()=>upload.showError('<script>unsafe name</script>'));
 assert.equal(await page.locator('[data-system-messages] script').count(),0,'file name is escaped through textContent');
 await page.locator('[data-system-messages] > .error').evaluateAll(elements=>elements.forEach(el=>el.dispatchEvent(new AnimationEvent('animationend',{animationName:'cmsMessageRetire',bubbles:true}))));
 assert.equal(await page.locator('.mmlUploadMessages').count(),0,'retired messages remove the entire holder, including its spacing');
 await page.setContent(`<div class="cmslightbox cms upload"><div class="uploader"><div data-doc="box"></div><input data-doc="input" accept=".zip,.png"><input data-doc="label"><input data-doc="name"><select data-doc="type"><option value="file">File</option></select><div data-doc="info"></div><div data-doc="footer"></div><div data-doc="fileFallback" data-icons='{"zip":"file-zipper"}' hidden><i data-doc="fileFallbackIcon"></i><strong data-doc="fileFallbackName"></strong></div></div></div>`);
 await page.evaluate(()=>{
  upload.doc=Object.fromEntries([...document.querySelectorAll('[data-doc]')].map(el=>[el.dataset.doc,el]));
  sys.classes={media:{info:{__data:{mimetypes:{'application/zip':'zip'}}}}};
  sys.singletons={str:{__data:{safeNameSettings:{search:[],replace:[],trim:' '}}}};
  window.printFileSize=()=> '1 KB';
  upload.fileInfo=new File(['zip fixture'],'Archive.zip',{type:'application/zip'});
  upload.preview();
 });
 assert.equal(await page.locator('[data-doc=fileFallback]').isVisible(),true,'download-only upload has a deliberate file preview');
 assert.match(await page.locator('[data-doc=fileFallbackIcon]').getAttribute('class'),/file-zipper/);
 assert.equal(await page.locator('[data-doc=fileFallbackName]').textContent(),'Archive.zip');
 assert.equal(await page.locator('[data-doc=box]').getAttribute('class'),'preview','download-only file remains ready to upload');
 await page.evaluate(()=>upload.resetPreviews());
 assert.equal(await page.locator('[data-doc=fileFallback]').isVisible(),false,'changing a file clears the previous fallback');
 assert.deepEqual(errors,[]);
 console.log('ok: rejected replacement aborts; messages scroll and retire cleanly; ZIP preview shows file icon/name and resets on file change');
} finally {await browser.close();}
