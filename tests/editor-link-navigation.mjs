import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const src=await readFile(new URL('../../system/public/js/gui/HTMLeditor.funcs.js',import.meta.url),'utf8');
let calls=[],opened=[],downloads=[],resolved={url:'/public/var/file.pdf',filename:'file.pdf',inline:true};
const context={settings:{siteRoot:'/public/'},location:{pathname:'/public/cms/pages/',search:'?id=10'},URLSearchParams,
 window:{open:(url)=>{const view={location:{href:url},close(){this.closed=true;}};opened.push(view);return view;},location:{assign:url=>calls.push(['assign',url])}},
 document:{body:{appendChild(){}},createElement:()=>({click(){downloads.push({href:this.href,name:this.download});},remove(){}})},
 sys:{core:{server:{sendGet:url=>calls.push(['get',url]),call:class{constructor(data){this.data=data;}send(url){calls.push(['resolve',url,this.data]);return {done:fn=>{fn(resolved);return {fail(){}};}};}}}}}
};
vm.createContext(context);vm.runInContext(src,context);
const anchor=attrs=>({nodeType:1,getAttribute:n=>attrs[n]??null,hasAttribute:n=>Object.hasOwn(attrs,n),get href(){return attrs.href;},closest(){return this;}});
context.openAnchor({data:{target:anchor({'data-ref-href':'page:42?lang=de',href:'/site/example/'})}});
assert.deepEqual(calls.pop(),['get','/public/cms/pages/?id=42&lang=de']);assert.equal(opened.length,0,'default page visit stays in CMS');
context.openAnchor({data:{target:anchor({href:'page:42',target:'_blank'})}});
assert.equal(opened.at(-1).location.href,'/public/cms/pages/?id=42');
context.openAnchor({data:{target:anchor({href:'media::42'})}});
assert.equal(calls.at(-1)[2].ref,'media:42');assert.equal(opened.at(-1).location.href,'/public/var/file.pdf');
resolved={url:'/public/var/file.zip',filename:'file.zip',inline:false};
context.openAnchor({data:{target:anchor({href:'media:43'})}});
assert.equal(downloads.at(-1).name,'file.zip');assert.equal(opened.at(-1).closed,true,'temporary preview window closes for downloads');
context.openAnchor({data:{target:anchor({href:'media:43',target:'_self'})}});
assert.deepEqual(calls.at(-1),['assign','/public/var/file.zip'],'explicit same-window overrides download default');
resolved={url:'/public/var/file.pdf',filename:'file.pdf',inline:true};
context.openAnchor({data:{target:anchor({href:'media:44',download:''})}});
assert.equal(downloads.at(-1).name,'file.pdf','download setting overrides inline preview');
context.openAnchor({data:{target:anchor({href:'https://example.org/'})}});
assert.equal(opened.at(-1).location.href,'https://example.org/');
for(const href of ['mailto:hello@example.org','tel:+4912345','sms:+4912345']) {
 const count=opened.length;
 context.openAnchor({data:{target:anchor({href})}});
 assert.equal(opened.length,count);assert.deepEqual(calls.at(-1),['assign',href]);
 context.openAnchor({data:{target:anchor({href,target:'_blank'})}});
 assert.equal(opened.length,count+1,'native link target remains overridable');
}
const before=opened.length;context.openAnchor({data:{target:anchor({href:'javascript:alert(1)'})}});assert.equal(opened.length,before);
const server=await readFile(new URL('../../system/public/js/core/server/server.js',import.meta.url),'utf8');
const start=server.indexOf("if (method !== 'put' && !call.redirecting)");
const end=server.indexOf('\n                    }\n                } else if (xhr.status === 500',start);
const run=new Function('method','call','sys','document','xhrRootHref',server.slice(start,end));
for(const [method,data,expected] of [['post',{history:false},0],['get',{dataset:{history:'false'}},0],['get',{},1]]) {
 let pushed=0,marked=0;
 run(method,{data,url:{pathChanged:false},responses:[{command:'updateDocument'}]},
 {http:{markRendered(){marked++;},historyPush(){pushed++;},url2string(){return '/dialog/';}},core:{server:{restoreScroll(){}}}},
 {querySelector(){return null;}},'/dialog/');
 assert.equal(pushed,expected);if(method==='post')assert.equal(marked,1,'transient dialog still updates the diff reference');
}
console.log('ok: page links stay in CMS; media refs resolve for previews/downloads; settings preserved; transient open/close skip history');
