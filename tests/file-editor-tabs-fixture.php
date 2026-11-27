<?php
declare(strict_types=1);
namespace Systopic\Cms\Queries\MediaUsage { final class MediaUsage {static function of(...$args):array{return [];}} }
namespace Systopic\System\Queries\MediaCrops { final class MediaCrops {static function one(...$args):object{return new \stdClass();} static function announce(...$args):void{} static function lazy(...$args):?object{return null;}} }
namespace Systopic\System\Media { final class VariantList {static function of($set):array{
 $make=static fn($name,$w,$h)=>['file'=>$name.'.webp','name'=>$name,'width'=>$w,'height'=>$h,'made'=>false,'bytes'=>0,'note'=>'','url'=>'https://fixture.test/'.$name.'.webp'];
 $sizes=[.5,1,2,5,10];$grid=[];foreach(['2:1','16:9','3:2','4:3','1:1'] as $ratio){$cells=[];foreach(['0.5mp','1mp','2mp','5mp','10mp',''] as $size)$cells[]=$make($size.str_replace(':','by',$ratio),1393,784);$grid[]=['title'=>$ratio,'cells'=>$cells];}
 return ['original'=>$make('original',1393,1438),'made'=>0,'all'=>30,'sizes'=>$sizes,'grid'=>$grid,'fixed'=>[],'names'=>[],'others'=>[]];
}} }
namespace Systopic\System\Auth { final class Session {static function isSuperuser():bool{return false;} static function current():object{return (object)['writeGroupIds'=>[2]];}} }
namespace {
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
spl_autoload_register(static function($class) {
    static $map;
    if ($map === null) foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__,2).'/system/lib/class')) as $entry) {
        if ($entry->isFile() && $entry->getExtension() === 'php') $map[$entry->getBasename('.php')]=$entry->getPathname();
    }
    if(isset($map[$class])) require_once $map[$class];
});
require dirname(__DIR__).'/lib/media.video.php';
define('VIDEO_FORMATS', ['videoTile'=>['max_width'=>960], 'videoTileM'=>['max_width'=>1280], 'missing'=>['max_width'=>1920]]);

class http {static string $root='https://fixture.test/';static string $siteRoot='https://fixture.test/';}
class fs {static string $siteRoot='/private/tmp/fluxo-file-tabs/'; static function toNative($v){return $v;} static function toInternal($v){return $v;} static function filemtime($v){return filemtime($v);} static function isFile($v){return is_file($v);} static function filesize($v){return filesize($v);}}
class fsLoader {static function includeScriptFiles($v){return '';}}
class str {static function formatbytes($v){return '1 KB';}}
class message {static function error($v){throw new \RuntimeException($v);}}
$kind=$argv[1]??'pdf';$tab=$argv[2]??'preview';
$types=['gif'=>['image/gif','gif'],'image'=>['image/png','png'],'pdf'=>['application/pdf','pdf'],'svg'=>['image/svg+xml','svg'],'audio'=>['audio/mpeg','mp3'],'video'=>['video/mp4','mp4'],'webm'=>['video/webm','webm'],'file'=>['application/zip','zip']];
[$mime,$ext]=$types[$kind];$path=fs::$siteRoot.'sample.'.$ext;if(!is_dir(fs::$siteRoot))mkdir(fs::$siteRoot,0777,true);
if(!is_file($path))file_put_contents($path,$kind==='svg'?'<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250"><rect width="400" height="250" fill="teal"/></svg>':'fixture');
$media=new class($path,$ext) {public bool $readable=true;public string $src;public string $url; public string $filename;public int $id=7;public string $label='Sample';public string $folders='000/07/';public int $width=400;public int $height=250;function __construct(public string $path,string $ext){$this->src=$this->url='https://fixture.test/sample.'.$ext;$this->filename='sample.'.$ext;}function setFormat($v){return $this;}};
foreach(['videoTile','videoTileM'] as $f){$dir=fs::$siteRoot.'var/formats/'.$f.'/000/07/';if(!is_dir($dir))mkdir($dir,0777,true);copy(fs::$siteRoot.'sample.mp4',$dir.'sample.mp4');}
if(!is_file(fs::$siteRoot.'sample.webp')){$poster=new \Imagick();$poster->newImage(320,180,new \ImagickPixel('teal'),'webp');$poster->writeImage(fs::$siteRoot.'sample.webp');}
$source=file_get_contents(dirname(__DIR__).'/panels/admin/010_pages/editor/file/panel.php');$start=strpos($source,'public function tabs(): string {');$end=strpos($source,'/** Header of the lightbox. */',$start);
eval('class FixtureTabs {'.substr($source,$start,$end-$start).'}');
$imageSource=file_get_contents(dirname(__DIR__).'/panels/admin/010_pages/editor/file/image/panel.php');
$start=strpos($imageSource,'public const TABS =');$end=strpos($imageSource,'/** The tabs there are:',$start);
$imageConstants=substr($imageSource,$start,$end-$start);
$start=strpos($imageSource,'public function tabs(): string {');$end=strrpos($imageSource,'}');
eval('trait FixtureImageTabs {'.$imageConstants.' public function tabList(): array {return self::TABS;} '.str_replace('function tabs()', 'function imageTabs()',substr($imageSource,$start,$end-$start)).'}');
class Fixture extends FixtureTabs {
 use FixtureImageTabs;
 const TEXT_GROUP='text';
 public object $state;public array $mediaGroups=[];public array $library=[];
 public mixed $scope=null;public int $targetId=42;public string $href='/admin/pages/editor/file/';public string $lightboxTitle='Edit file';public object $mediaRow;public string $tab='master';public ?string $shownFirst=null;
 function __construct(public string $kind,public string $fileTab,public object $media,string $mime,string $ext){$this->mediaRow=new \Systopic\System\Tables\Medias\Model();$this->mediaRow->id=7;$n=new \Systopic\System\Tables\Nodes\Model();$n->id=42;$a=new \Systopic\System\Tables\Acls\Model();$a->id=2;$a->groupIds='2';$n->acl=$a;$n->aclInherit=true;$this->mediaRow->node=$n;$this->mediaRow->filename='sample.'.$ext;$this->mediaRow->label='Sample';$this->mediaRow->mimetype=$mime;$this->mediaRow->type=match($kind){'video','webm'=>'video','svg'=>'image',default=>$kind};$this->mediaRow->width=400;$this->mediaRow->height=250;}
 function file(){return $this;}
 function tabs():string{return $this->kind==='image'?$this->imageTabs():parent::tabs();}
 function pages(){return (object)['scope'=>null,'href'=>'/admin/pages/'];}
 function withView($v){
  if($v==='permissions'){
   $permissionAdmin=new class {
    public array $groups; public array $roles;
    function __construct(){
     $g=new \Systopic\System\Tables\Groups\Model();$g->id=2;$g->name='Public';$g->color='#66aacc';$this->groups=[2=>$g];
     $r=new \Systopic\System\Tables\Roles\Model();$r->id=2;$r->name='Everyone';$this->roles=[2=>$r];
    }
    function group($id){return $this->groups[$id]??null;}
    function permission($g,$r){return (object)['read'=>true,'write'=>false];}
   };
   include dirname(__DIR__).'/panels/admin/010_pages/editor/file/permissions.tpl.php';return;
  }
  $groups=[];
  foreach(['Category'=>['Film','Editorial','Commercial','Documentary','Music video','Longer tag label'],'Client'=>['ADAC','Studio','Personal']] as $name=>$names){
   $g=new \Systopic\System\Tables\TagGroups\Model();$g->id=count($groups)+2;$g->name=$name;$g->color='#dd8844';$g->tags=[];
   foreach($names as $label){$t=new \Systopic\System\Tables\Tags\Model();$t->id=count($g->tags)+2;$t->name=$label;$t->taggroup=$g;$g->tags[]=$t;}$groups[]=$g;
  }
  echo \Systopic\System\Queries\TagGroups\TagGroupsPanel::forPage($groups,42,[2,4]);
 }
 function kindPanel(){return in_array($this->kind,['image','svg','video'])?$this:null;}
 function image(){return $this;}
 function tileFace($media){return ['mark'=>false,'icon'=>'file-image'];}
 function thumbOf($media){return 'https://fixture.test/sample.png';}
 function brokenIcon($media){return '';}
 function renderLibrary(){
  $this->href='/public/admin/pages/';$this->state=(object)['id'=>10];$this->mediaGroups=['main'=>['label'=>'Main']];
  $this->library=['main'=>[['media'=>$this->mediaRow,'node'=>$this->mediaRow->node,'alias'=>false]]];
  include dirname(__DIR__).'/panels/admin/010_pages/mml.tpl.php';
 }
 function stage($media,$original){include dirname(__DIR__).'/panels/admin/010_pages/editor/file/'.$this->kind.'/stage.tpl.php';}
 function render(){include dirname(__DIR__).'/panels/admin/010_pages/editor/file/lightbox.tpl.php';}
}
$fixture=new Fixture($kind,$tab,$media,$mime,$ext);
if($kind==='image'){
 $fixture->lightboxTitle='Edit image';$fixture->tab=$tab;
 \Systopic\System\Tables\Medias\External::storeUrl($fixture->mediaRow,'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
 $fixture->mediaRow->type='youtube';
}
if($tab==='library')$fixture->renderLibrary();else $fixture->render();
}
