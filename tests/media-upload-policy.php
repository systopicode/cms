<?php
/** Exercise the real upload/replace and move actions before any DB/filesystem mutation. */
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class http {
 public static array $get=[], $put=[], $dataset=[], $drag=[];
 public static function get($key) {return self::$get[$key]??null;}
 public static function put($key=null,$default=null) {return $key===null?(object)self::$put:(self::$put[$key]??$default);}
 public static function dataset($key,$default=null) {return self::$dataset[$key]??$default;}
 public static function dragdata($key) {return self::$drag[$key]??null;}
 public static function binary() {return 'not a PNG';}
}
class fs {
 public static string $siteRoot;
 public static function toNative($path) {return $path;}
 public static function isDir($path) {return is_dir($path);}
 public static function fsMkdir($path,...$args) {mkdir($path,0777,true);}
}
class str {public static function getSafeName($name){return $name;}}
class message {public static array $errors=[]; public static function error($error){self::$errors[]=$error;}}
class client {public static array $done=[];public static function done($data){self::$done[]=$data;}}
$dir=sys_get_temp_dir().'/fluxo-upload-preflight-'.bin2hex(random_bytes(5));mkdir($dir);fs::$siteRoot=$dir.'/';
$media=(object)['group'=>'images','filename'=>'current.png','label'=>'Keep me','width'=>100,'height'=>100];
$pages=new class($media) {
 public array $mediaGroups=['images'=>['label'=>'Images','file_extension'=>'png']];
 public object $state;
 public function __construct(public object $media){$this->state=(object)['id'=>10];}
 public function mediaOfNode($id){return $id===42?$this->media:null;}
 public function uow(){throw new RuntimeException('Rejected file reached persistence!');}
 public function updateView(...$args){}
};
$panel=new class($pages) {
 public string $action='upload';
 public function __construct(public object $parent){}
 public function run(){include dirname(__DIR__).'/panels/cms/010_pages/upload/actions.php';}
 public function move(){include dirname(__DIR__).'/panels/cms/010_pages/mml/actions.php';}
};
try {
 http::$get=['node_id'=>42,'group'=>'all']; // Cannot bypass the real replacement group.
 http::$put=['name'=>'blocked.pdf','spanIndex'=>0,'lastSpan'=>true,'label'=>'Changed'];$panel->run();
 check('replacement restriction reports an error',str_contains(client::$done[0]['error']??'','not allowed in Images'));
 check('rejected replacement retains metadata and file reference',$media->filename==='current.png'&&$media->label==='Keep me'&&$media->width===100);
 check('rejected temporary file is removed',!is_file($dir.'/var/tmp/upload/blocked.pdf'));
 http::$put['name']='fake.png';$panel->run();
 check('matching extension with mismatched content rejected',str_contains(end(client::$done)['error'],'contents do not match'));
 http::$get=['node_parent_node_id'=>10,'group'=>'images'];http::$put['name']='blocked.mp4';$panel->run();
 check('new upload rejected before media rows are created',str_contains(end(client::$done)['error'],'not allowed'));
 $media->filename='blocked.mp4';$panel->action='updateMediaGroup';http::$drag=['node_id'=>42];http::$dataset=['node_parent_node_id'=>10,'media_group'=>'images'];$panel->move();
 check('moving into disallowed group rejected before persistence',str_contains(end(message::$errors),'not allowed'));
} finally {
 foreach(glob($dir.'/var/tmp/upload/*')?:[] as $f)unlink($f);
 foreach(['/var/tmp/upload','/var/tmp','/var',''] as $sub)if(is_dir($dir.$sub))rmdir($dir.$sub);
}
echo "All real media-action preflight checks passed.\n";
