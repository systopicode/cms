<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class http {public static string $root='/public/';public static string $ref='media:42';public static function put($key){return self::$ref;}}
class fs {public static string $siteRoot='/private/tmp/fluxo-unused-fixture/';}
class client {public static array $data=[];public static function done($data){self::$data=$data;}}
class message {public static array $errors=[];public static function error($error){self::$errors[]=$error;}}
$row=new \Systopic\System\Tables\Medias\Model();$row->id=7;$row->filename='archive.zip';$row->mimetype='application/zip';$row->type='file';
$panel=new class($row) {
 public string $action='resolveEditorLink';
 public function __construct(public object $medium){}
 public function mediaOfNode($id){return $id===42?$this->medium:null;}
 public function run(){include dirname(__DIR__).'/panels/cms/010_pages/actions.php';}
};
$panel->run();
check('real reference action returns original file URL',client::$data['url']==='/public/var/original/000/07/archive.zip');
check('download classification comes from actual MIME',client::$data['inline']===false);
$row->filename='photo.png';$row->mimetype='image/png';http::$ref='media::42';$panel->run();
check('double-colon input also resolves',str_ends_with(client::$data['url'],'photo.png')&&client::$data['inline']===true);
http::$ref='media:999';$panel->run();check('unavailable node rejected',client::$data['error']===true&&count(message::$errors)===1);
