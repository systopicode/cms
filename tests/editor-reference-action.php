<?php
declare(strict_types=1);
namespace Systopic\System\Queries\MediaCrops { final class MediaCrops {static function announce(...$args):void{} static function lazy(...$args):?object{return null;}} }
namespace {
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class http {public static string $root='/public/';public static string $ref='media:42';public static function put($key){return self::$ref;}}
class fs {public static string $siteRoot='/private/tmp/fluxo-unused-fixture/';static function toNative($path){return $path;}}
class client {public static array $data=[];public static function done($data){self::$data=$data;}}
class message {public static array $errors=[];public static function error($error){self::$errors[]=$error;}}
$row=new \Systopic\System\Tables\Medias\Model();$row->id=7;$row->filename='archive.zip';$row->mimetype='application/zip';$row->type='file';
$panel=new class($row) {
 public string $action='resolveEditorLink';
 public function __construct(public object $medium){}
 public function mediaOfNode($id){return $id===42?$this->medium:null;}
 public function run(){include dirname(__DIR__).'/panels/admin/010_pages/actions.php';}
};
$panel->run();
check('real reference action returns original file URL',client::$data['url']==='/public/var/original/000/07/archive.zip');
check('download classification comes from actual MIME',client::$data['inline']===false);
$row->filename='photo.png';$row->mimetype='image/png';$row->width=1600;$row->height=1200;http::$ref='media::42';$panel->run();
check('double-colon input resolves the image master',str_contains(client::$data['url'],'photo--1mp.webp')&&client::$data['inline']===true);
foreach(['16by9','4by3','1by1'] as $ratio){http::$ref='media:42?format='.$ratio;$panel->run();check('image choice resolves the actual crop '.$ratio,str_contains(client::$data['url'],'photo--1mp'.$ratio.'.webp'));}
http::$ref='media:42?format=original';$panel->run();check('an explicit original is still the uploaded file',str_ends_with(client::$data['url'],'photo.png'));
http::$ref='media:999';$panel->run();check('unavailable node rejected',client::$data['error']===true&&count(message::$errors)===1);
$path=\Systopic\System\Media\Presentation::editorPath(...[42,'image/png','photo.png','image','shareImage']);
check('share image opens its fixed-format tab',$path==='editor/file/image/formats/?target_id=42&format=shareImage');
foreach (['16:9','16by9','2mp16by9'] as $format) {
 $path=\Systopic\System\Media\Presentation::editorPath(42,'image/png','photo.png','image',$format);
 check('displayed crop opens directly: '.$format,str_contains($path,'/image/crops/?')&&str_ends_with($path,'format='.rawurlencode($format)));
}
$path=\Systopic\System\Media\Presentation::editorPath(42,'image/png','photo.png','image','original');
check('original opens master',str_contains($path,'/image/master/?'));
foreach (['video/mp4'=>'video/','image/svg+xml'=>'svg/','application/pdf'=>''] as $mime=>$kind) {
 $path=\Systopic\System\Media\Presentation::editorPath(42,$mime,'file','file','original');
 check('correct editor for '.$mime,$path==='editor/file/'.$kind.'?target_id=42&format=original');
}
}
