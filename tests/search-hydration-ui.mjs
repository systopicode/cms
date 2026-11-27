import {readFile} from 'node:fs/promises';
import assert from 'node:assert/strict';
const {chromium}=await import(process.env.FLUXO_PLAYWRIGHT_MODULE);
const layerSource=await readFile(new URL('../../system/public/js/core/domLayer.js',import.meta.url),'utf8');
const rendererSource=await readFile(new URL('../public/site/pagesRenderer.js',import.meta.url),'utf8');
const browser=await chromium.launch({channel:'chrome',headless:true});
try {
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.setContent('<table><!--cms-layer:body:start--><!--cms-layer:body.0:start--><tr><td>Server row</td></tr><!--cms-layer:body.0:end--><!--cms-layer:body:end--></table><div><!--cms-layer:empty:start--><!--cms-layer:empty.0:start--><!--cms-layer:empty.0:end--><!--cms-layer:empty:end--></div><script>window.executions=(window.executions||0)+1</script>');
 await page.evaluate(()=>{
  window.l=()=>{};window.f=message=>{throw new Error(message)};
  window.sys={core:{},dom:{},lib:{add(setup,build){const path=setup.route.split('.');let node=sys;for(const key of path)node=node[key]??={};node.__class=function(){build.call(this)};if(setup.shorthand)sys[setup.shorthand]=new node.__class();}}};
 });
 await page.addScriptTag({content:layerSource});await page.addScriptTag({content:rendererSource});
 const result=await page.evaluate(()=>{
  const make=(html='',children=[],type='new')=>({data:{source:'page',viewName:'test',type,result:true,moduleName:'test'},html,childLayers:children});
  const tr=document.querySelector('tr');
  const table=sys.siteRenderer.adopt(make('',[make()]),'body',document.body);
  const empty=sys.siteRenderer.adopt(make('',[make()]),'empty',document.body);
  return {sameRow:table.children[0].rootNodes.includes(tr),emptyAnchor:empty.children[0].ownPlaceholder.isConnected,executions:window.executions,rows:document.querySelectorAll('tr').length};
 });
 assert.deepEqual(result,{sameRow:true,emptyAnchor:true,executions:1,rows:1});assert.deepEqual(errors,[]);
 console.log('PASS server nodes, implicit tbody, empty layer anchor and one inline-script execution');
} finally {await browser.close()}
