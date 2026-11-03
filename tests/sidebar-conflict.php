<?php
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
class http { static string $version=''; static function dataset($key,$fallback=''){return $key==='field_version'?self::$version:($key==='language_iso'?'en':$fallback);} }
class client { static array $calls=[]; static function callFunction($name,$args){self::$calls[]=[$name,$args];} }
class message { static function error($text){} }
class Work { public int $writes=0; function __construct(public object $connection){} function commit($guard){$guard($this->connection);$this->writes++;} }
class SidebarFixture {
 public object $parent;
 function action(): Closure {
  $source=file_get_contents(dirname(__DIR__).'/panels/admin/010_pages/sidebar/actions.settings.php');
  $a=strpos($source,'$settingGuard =');$b=strpos($source,'switch ($this->action)', $a);
  return eval(substr($source,$a,$b-$a).'return $commitSetting;');
 }
}
$connection=new class {
 public array $rows=[['junction_setting_node_id'=>10,'junction_varchar'=>'first','junction_int'=>null,'junction_decimal'=>null],['junction_setting_node_id'=>20,'junction_varchar'=>'other','junction_int'=>null,'junction_decimal'=>null]];
 function all($sql,$params){return $this->rows;}
 function value($sql,$params){check('setting changes lock the page node',str_contains($sql,'FOR UPDATE')&&$params===[9]);return 9;}
};
$fixture=new SidebarFixture();$fixture->parent=(object)['scope'=>(object)['connection'=>$connection]];
$write=$fixture->action();$work=new Work($connection);
http::$version=\Systopic\Cms\Pages\SettingVersion::hash($connection,9,[10]);
$write($work,9,10,[10]);check('unchanged setting may save',$work->writes===1);
$connection->rows[0]['junction_varchar']='second';
$write($work,9,10,[10]);check('stale setting is not committed',$work->writes===1);
check('stale setting opens conflict dialog',end(client::$calls)[0]==='pageEditConflict');
http::$version=\Systopic\Cms\Pages\SettingVersion::hash($connection,9,[10]);
$connection->rows[1]['junction_varchar']='unrelated change';
$write($work,9,10,[10]);check('unrelated setting does not conflict',$work->writes===2);
$connection->rows=[];$write($work,9,10,[10]);check('removed setting is detected as conflict',$work->writes===2);
