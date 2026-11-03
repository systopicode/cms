<?php
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
class http { public static string $version=''; static function post($key,$fallback=''){return $fallback;} static function dataset($key,$fallback=''){return $key==='field_version'?self::$version:$fallback;} }
class client { static array $calls=[];static function callFunction($name,$args){self::$calls[]=[$name,$args];} }
class message { static function error($text){} }
class TestOperator {
 static int $touches=0;
 static function propertyOf($field){return $field==='text01'?'text01':null;}
 static function assign($row,$field,$value){$row->$field=$value;}
 static function touch($row,$user){self::$touches++;}
}
class TestWork {
 public int $writes=0;
 function __construct(public object $connection){}
 function persist($row){return $this;}
 function commit($guard){$guard($this->connection);$this->writes++;}
}
$source=file_get_contents(dirname(__DIR__).'/panels/admin/010_pages/panel.php');
$start=strpos($source,'function updateField(');$end=strpos($source,"\n\t/**",$start);
$method=str_replace('PageOperator::','TestOperator::',substr($source,$start,$end-$start));
eval('class TestEditor {public object $scope;public object $shown;public TestWork $work;function pageIn($id,$lang){return $this->shown;}function uow(){return $this->work;}function userId(){return 1;}' . $method . '}');
$connection=new class {public string $value='original'; function value($sql,$params){check('locking read uses actual row id',str_contains($sql,'FOR UPDATE')&&$params===[7]);return $this->value;}};
$editor=new TestEditor();$editor->shown=(object)['row'=>(object)['id'=>7,'text01'=>'original'],'language'=>'en'];$editor->work=new TestWork($connection);$editor->scope=(object)['connection'=>new class {function all($sql,$args){return [];}}];
http::$version=hash('sha256','original');
check('unchanged baseline saves', $editor->updateField(9,'en','text01','mine'));
check('one committed write',$editor->work->writes===1);
$connection->value='someone else';
check('stale baseline rejected',!$editor->updateField(9,'en','text01','my second edit'));
check('conflict does not assign local ORM value',$editor->shown->row->text01==='mine'&&$editor->work->writes===1);
check('conflict offers current version',client::$calls[array_key_last(client::$calls)][1][3]===hash('sha256','someone else'));
http::$version=hash('sha256','someone else');
check('explicit overwrite accepts acknowledged version',$editor->updateField(9,'en','text01','my second edit'));
$connection->value='third edit';
check('third writer still protected',!$editor->updateField(9,'en','text01','my third edit'));
check('unknown fields rejected before SQL',!$editor->updateField(9,'en','bad`field','x'));
$connection->value='same content';
$editor->shown->row->text01='same content';
$before=TestOperator::$touches;
http::$version=hash('sha256','older version');
check('unchanged blur accepted even with an older baseline',$editor->updateField(9,'en','text01','same content'));
check('unchanged blur does not touch timestamp or broadcast a new revision',TestOperator::$touches===$before);
echo "Page conflict checks passed.\n";
