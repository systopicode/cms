<?php
/** Disposable HTTP/CLI fixture: production refs, image renderer and resolve action; no DB. */
declare(strict_types=1);
namespace Systopic\System\Queries\MediaCrops {
 final class MediaCrops {public static ?CropSet $set=null;static function announce(...$args):void{}static function lazy(...$args):?CropSet{return self::$set;}}
}
namespace Systopic\System\Queries\MediaNodes {
 final class MediaNodes {public static object $row;static function byNodeIds($scope,$ids):array{return in_array(169,$ids,true)?[169=>self::$row]:[];}}
}
namespace {
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
require_once dirname(__DIR__,2).'/system/lib/class/fs/loader/fsLoader.php';
use Systopic\Cms\Refs\{Resolver,MediaRefs,Ref,Attributes};
use Systopic\System\Tables\Medias\{Model,Media,Files,Picture};
use Systopic\System\Media\{VariantRenderer,Variants,Ratio,CropBox};
use Systopic\System\Queries\MediaCrops\{MediaCrops,CropSet};
class fs {public static string $siteRoot='/private/tmp/fluxo-image-device-fixture/';static function toNative($path){return $path;}}
class http {public static string $root='/';public static array $input=[];static function put($key){return self::$input[$key]??null;}}
class client {public static array $data=[];static function done($data){self::$data=$data;}}
class message {static function error($message){throw new \RuntimeException($message);}}
$system=dirname(__DIR__,2).'/system/';
@mkdir(fs::$siteRoot.'css',0777,true);
file_put_contents(fs::$siteRoot.'css/x_responsive.css',"/* cms-preview: mobile */\n@media (max-width: 560px) {\n body {}\n}\n/* cms-preview: tablet */\n@media (min-width: 561px) and (max-width: 960px) {\n body {}\n}\n");
$row=new Model();$row->id=42;$row->filename='garden.png';$row->mimetype='image/png';$row->type='image';$row->width=1600;$row->height=1000;$row->label='Garden';
$hand=\Systopic\System\Tables\MediaCrops\Operator::put(null,$row,new CropBox(Ratio::of('9:16'),.3,.5,1.2));
$set=new CropSet($row,[$hand]);MediaCrops::$set=$set;\Systopic\System\Queries\MediaNodes\MediaNodes::$row=$row;
$media=new Media($row);$resolver=new Resolver(new \Systopic\Db\Scope(),new \Systopic\Cms\Queries\Params('de'));
$original=Files::originalPath($row);
if(!is_file($original)){
 @mkdir(dirname($original),0777,true);$image=new \Imagick();$image->newImage(1600,1000,new \ImagickPixel('#236856'),'png');
 $draw=new \ImagickDraw();foreach([[0,533,'#cba754'],[533,1066,'#368e88'],[1066,1600,'#155a6b']] as [$x,$end,$color]){$draw->setFillColor($color);$draw->rectangle($x,0,$end,1000);}
 $draw->setFillColor('#f5efc4');$draw->circle(680,460,680,250);$image->drawImage($draw);$image->writeImage($original);
}
if(!is_file(Files::variantPath(42,'garden.png','1mp9by16','webp'))){
 $renderer=new VariantRenderer($set,$original);
 foreach([null,...Variants::ratios()] as $ratio)foreach([.5,1.,null] as $mp)$renderer->make(['ratio'=>$ratio,'megapixels'=>$mp,'original'=>false],'webp');
}
// Do not persist the renderer's derived row size between requests.
Picture::forgetFiles();
$stored=fs::$siteRoot.'text.html';
$initial='<p>One image, independent device crops</p><p><img id="editable-image" src="media:169?format=master" alt="Test garden" class="garden" data-caption="Keep me"></p><p>Same medium, another occurrence</p><p><img id="other-image" src="media:169?format=master" alt="Other occurrence" width="320" height="200"></p>';
if(!is_file($stored))file_put_contents($stored,$initial);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$mode=PHP_SAPI==='cli'?($argv[1]??'--check'):$path;
if($mode==='--check'){
 $ref=Ref::fromAttribute('media:169?format=master&desktop=16by9&tablet=1by1&mobile=9by16');$attrs=' id="test" src="media:169?format=master&amp;desktop=16by9&amp;tablet=1by1&amp;mobile=9by16" alt="A &amp; B" class="custom"';
 $editor=$resolver->renderForEditor('<img'.$attrs.'>');$front=$resolver->render('<img'.$attrs.'>');
 check('editor keeps one editable img, with all view metadata',!str_contains($editor,'<picture>')&&str_contains($editor,'data-ref-image-views='));
 check('frontend emits a real picture with full project media queries',str_starts_with($front,'<picture>')&&str_contains($front,'max-width: 560px')&&str_contains($front,'(min-width: 561px) and (max-width: 960px)'));
 check('each source uses its own ratio, with width/height',str_contains($front,'garden--0.5mp9by16.webp')&&str_contains($front,'garden--0.5mp1by1.webp')&&str_contains($front,'garden--1mp16by9.webp'));
 check('occurrence attributes and escaped alt survive',str_contains($front,'id="test"')&&str_contains($front,'alt="A &amp; B"')&&str_contains($front,'custom'));
 $changed=Attributes::set(Attributes::set(substr($editor,4,-1),'width','999'),'height','888');
 $normal=Resolver::normalize('<img'.$changed.'>');
 check('preview dimensions and transient metadata never get stored',!str_contains($normal,'width=')&&!str_contains($normal,'data-ref-')&&str_contains($normal,'mobile=9by16'));
 $sized=$resolver->renderForEditor('<img width="320" height="200"'.$attrs.'>');
 $sized=preg_replace('~ width="320" height="200"~',' width="999" height="888"',$sized);
 check('stored explicit dimensions survive preview changes',str_contains(Resolver::normalize($sized),'width="320" height="200"'));
 $plain=$resolver->render('<img src="media:169?format=master">');check('images without device choices use picture and stay on master',str_starts_with($plain,'<picture><img ')&&!str_contains($plain,'<source')&&str_contains($plain,'garden--1mp.webp'));
 check('a master without device overrides offers responsive pixel sizes',str_contains($plain,'srcset=')&&str_contains($plain,'sizes="auto, 100vw"'));
 $plain=$resolver->render('<img src="media:169?format=16by9" alt="Wide" data-caption="kept">');check('a single ratio uses picture, offers responsive sizes and keeps attributes',str_starts_with($plain,'<picture><img ')&&!str_contains($plain,'<source')&&str_contains($plain,'srcset=')&&str_contains($plain,'1mp16by9.webp')&&str_contains($plain,'data-caption="kept"'));
 $original=$resolver->render('<img src="media:169?format=original">');check('an explicitly requested original stays the uploaded file',!str_contains($original,'srcset=')&&str_contains($original,'/var/original/'));
 $short=$resolver->render('{media:169}');check('image shorthand uses the same responsive picture as an image tag',$short===$resolver->render('<img src="media:169?format=master">'));
 $short=$resolver->render('{media:169?format=master&desktop=16by9&tablet=1by1&mobile=9by16}');check('shorthand honours per-device crops',substr_count($short,'<source ')===2&&str_contains($short,'9by16.webp'));
 $original=$resolver->render('<img width="320" height="200" src="media:169?format=original">');check('original images retain their explicit display dimensions',str_contains($original,'width="320"')&&str_contains($original,'height="200"'));
 $html=MediaRefs::imageViewMarkup($media,$ref,' src="x"',false);check('normal picture is made and usable',str_contains($html,'srcset='));
 // A cold image also exposes native addresses, independent of a JavaScript loader.
 $row->filename='unmade.png';Picture::forgetFiles();$lazy=MediaRefs::imageViewMarkup($media,$ref,' src="x" data-caption="kept"',false);
 check('unmade fallback exposes native sources and lazy loading',!str_contains($lazy,'data-media-src')&&str_contains($lazy,'srcset=')&&str_contains($lazy,'loading="lazy"')&&str_contains($lazy,'unmade--')&&str_contains($lazy,'data-caption="kept"'));
 exit;
}
if(PHP_SAPI!=='cli')http::$input=json_decode(file_get_contents('php://input'),true)??[];
if($mode==='/reset'){file_put_contents($stored,$initial);header('Content-Type: application/json');echo '{}';exit;}
if($mode==='/save'){file_put_contents($stored,Resolver::normalize((string)http::put('html')));header('Content-Type: application/json');echo json_encode(['stored'=>file_get_contents($stored)]);exit;}
if($mode==='/resolveEditorLink'){
 $panel=new class($row){public string $action='resolveEditorLink';function __construct(public object $row){}function mediaOfNode($id){return $id===169?$this->row:null;}function run(){include dirname(__DIR__).'/panels/admin/010_pages/actions.php';}};
 $panel->run();header('Content-Type: application/json');echo json_encode(client::$data);exit;
}
if($mode==='/content'){header('Content-Type: application/json');echo json_encode(['editor'=>$resolver->renderForEditor(file_get_contents($stored)),'stored'=>file_get_contents($stored),'widths'=>\Systopic\Cms\Pages\PreviewWidths::of(fs::$siteRoot.'css')]);exit;}
if(str_starts_with($mode,'/var/')){
 $file=realpath(fs::$siteRoot.ltrim($mode,'/'));if(!$file||!str_starts_with($file,fs::$siteRoot.'var/')){http_response_code(404);exit;}
 header('Content-Type: '.(str_ends_with($file,'.png')?'image/png':'image/webp'));readfile($file);exit;
}
if(str_starts_with($mode,'/assets/')){
 $file=realpath($system.'public/'.substr($mode,8));if(!$file||!str_starts_with($file,$system.'public/')){http_response_code(404);exit;}
 header('Content-Type: '.(str_ends_with($file,'.js')?'text/javascript':(str_ends_with($file,'.svg')?'image/svg+xml':'text/css')));readfile($file);exit;
}
header('Content-Type: text/html; charset=utf-8');
if($mode==='/frontend'){
 echo '<style>body{margin:20px;font:16px sans-serif}.garden{display:block;width:100%;height:auto;max-width:1000px}#other-image{display:none}</style>'.$resolver->render(file_get_contents($stored));exit;
}
$styles='';foreach(glob($system.'public/css/*.css') as $file)$styles.='<link rel="stylesheet" href="/assets/css/'.basename($file).'">';
$styles.='<style>'.fsLoader::previewResponsiveCss(\Systopic\Cms\Pages\PreviewWidths::previewCss(fs::$siteRoot.'css')).'</style>';
$widths=\Systopic\Cms\Pages\PreviewWidths::of(fs::$siteRoot.'css');
$formats=array_column(Variants::ratios(),'name','suffix');
echo '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1"><title>Device image fixture</title>'.$styles;
?>
<style>
body.cms{margin:0;padding:20px;font:16px system-ui;background:#eef2f3;color:#182022}.fixture-head{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:20px}.fixture-head h1{font-size:22px;margin:0}.deviceSwitch{display:flex;gap:6px}.deviceSwitch button{padding:10px}.cmsPreview{overflow:auto;width:100%;padding-bottom:15px;container-type:inline-size;container-name:cms-preview}article.cmsContent{background:white;padding:20px;box-sizing:border-box;max-width:none;width:100%}body[data-cms-device=desktop] article{width:1100px}body[data-cms-device=tablet] article{width:800px}body[data-cms-device=mobile] article{width:360px}img.garden{display:block;width:100%;max-width:720px;height:auto}#other-image{max-width:120px;height:auto}#fixed{width:120px}.editor{outline:none}body[data-theme=dark]{background:#14252b;color:#eef2f3}body[data-theme=dark] article{background:#20353e;color:#eef2f3}body[data-theme=dark] article p{color:inherit}button[aria-pressed=true]{background:#ffbb00;color:#182022}
</style>
<body class="cms" data-cms-device="desktop"><header class="fixture-head"><h1>Image ratios by preview</h1><div class="deviceSwitch" role="group" aria-label="Preview width"><button data-cms-device="fit">Fit</button><button data-cms-device="desktop">Desktop</button><button data-cms-device="tablet">Tablet</button><button data-cms-device="mobile">Smartphone</button></div></header>
<div class="cmsPreview"><article class="cmsContent"><div class="editor" contenteditable="true"><?= $resolver->renderForEditor(file_get_contents($stored)) ?></div><img id="fixed" src="<?= MediaRefs::imageSource($media) ?>" alt="Template image"></article></div>
<script src="/assets/js/ext/jquery.js"></script>
<script>
window.editorImageWidths=<?= json_encode($widths) ?>;window.editorFormats={image:<?= json_encode($formats) ?>,video:{}};
window.settings={siteRoot:'/'};window.contextMenus=[];window.cmsMenuSequence=0;window.prepareMenuKeyboard=()=>{};window.navigations=[];window.saves=0;window.pendingSave=Promise.resolve();window.pendingRequests=[];
window.closeAllMenus=()=>document.querySelectorAll('.HTMLeditorMenu').forEach(e=>e.parentElement.remove());
window.wysiwyg={canStep:()=>false,active:()=>false,inline:()=>false,blockFor:()=>null,changed:editor=>{saves++;pendingSave=fetch('/save',{method:'POST',body:JSON.stringify({html:editor.innerHTML})}).then(r=>r.json());}};
window.sys={core:{server:{onIdle:fn=>pendingSave.then(fn),sendCall:(data,url)=>navigations.push(url),call:class{constructor(data){this.data=data}send(url){const task=fetch(url.replace('/admin/pages',''),{method:'POST',body:JSON.stringify(this.data)}).then(r=>r.json());pendingRequests.push(task);const chain={done:fn=>{task.then(fn);return chain},fail:fn=>{task.catch(fn);return chain}};return chain}}}}};
document.querySelectorAll('.deviceSwitch button').forEach(button=>button.onclick=()=>{closeAllMenus();document.body.dataset.cmsDevice=button.dataset.cmsDevice;document.querySelectorAll('.deviceSwitch button').forEach(b=>b.setAttribute('aria-pressed',b===button));});
</script>
<script src="/assets/js/gui/HTMLeditor.funcs.js"></script><script src="/assets/js/gui/HTMLeditor.contextmenu.js"></script><script src="/assets/js/gui/HTMLeditor.menus.js"></script>
</body>
<?php }
