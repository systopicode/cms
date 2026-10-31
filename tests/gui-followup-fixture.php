<?php
namespace Systopic\System\Config {
 class Config {static function current(){return new class {public function site(){return new class {public string $name='example.test';public function meta($key){return ($_GET['case']??'')==='site' ? (['title'=>'Example site','description'=>'Site description','titleSeparator'=>'—'][$key]??null) : null;}};}};}}
}
namespace Systopic\System\Settings { class ThemeAssets {static function projectHref($slot){return '/theme-share.jpg';}} }
namespace Systopic\Cms\Pages\Sites {class SiteRegistry {static function byName(...$args){return new class {public bool $isDefault=true;public function relationId($name){return null;}};}static function filesWithoutHostRules($name){return [];}}}
namespace {
class http {public static $root='/public/';public static $siteUrl='https://example.test/';}
class fs {public static $projectRoot='/tmp/';static function isDir($p){return true;}}
class message {static function flush(){return '';}}
define('META_DESCRIPTION','Global description');define('PUBLIC_PAGE_NAME','Global name');define('PAGE_TITLE_SEPERATOR','|');define('META_ROBOTS','index, follow');
$_GET['case']=$argv[1]??'site';
$panel=new class {
 public object $parent;public object $scope;public string $href='/public/cms/pages/';public object $state;
 function __construct(){$this->scope=(object)[];$this->parent=(object)['parent'=>$this];$this->state=(object)['editSite'=>'default'];}
 function currentViewHref(){return '/public/cms/pages/?id=10&lang=en';}function siteUrl($s){return 'https://example.test/';}function sitePageCount($s){return 4;}
 function render($path,$site=null){ob_start();include $path;return ob_get_clean();}
};
$root=dirname(__DIR__).'/';
if($_GET['case']==='editor'){
 $site=new class {public $id=3;public $name='example.test';public $metaData=['title'=>'Example'];public $aliasList=['alias.test'];function relationId($r){return null;}};
 echo $panel->render($root.'panels/cms/010_pages/site.tpl.php',$site);
}else echo $panel->render($root.'panels/cms/010_pages/sidebar/defaultMeta/lightbox.tpl.php');
}
