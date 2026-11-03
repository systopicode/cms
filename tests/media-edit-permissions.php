<?php
declare(strict_types=1);
namespace Systopic\System\Auth { final class Session {public static function isSuperuser(): bool {return false;}} }
namespace {
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class http {public static string $type='video';public static function values($key){return self::$type;}}
class message {public static array $errors=[];public static function error($error){self::$errors[]=$error;}}
$pages=new class {public function uow(){throw new RuntimeException('Forbidden type change reached persistence.');}};
$panel=new class($pages) {
 public string $action='updateMediaType';
 public object $mediaRow;
 public function __construct(public object $owner){$this->mediaRow=(object)['node'=>null,'type'=>'image','url'=>''];}
 public function pages(){return $this->owner;}
 public function run(){include dirname(__DIR__).'/panels/admin/010_pages/editor/file/actions.php';}
};
$panel->run();
check('real edit action rejects a forged native type change',$panel->mediaRow->type==='image'&&count(message::$errors)===1);
$panel->action='updateMediaUrl';$panel->run();
check('ordinary local file cannot acquire external URL through forged action',$panel->mediaRow->url==='');
}
