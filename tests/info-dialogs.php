<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class fs {public static string $siteRoot='/private/tmp/fluxo-no-theme-override/';public static string $sysRoot;public static function toNative($path){return $path;}}
fs::$sysRoot=dirname(__DIR__,2).'/system/';
class http {
 public static string $root='/public/';public static string $siteUrl='https://fixture.test/public/';
 public static function get($key){return $key==='group'?'images':null;}
}
$pages=new class {
 public array $mediaGroups=['images'=>['label'=>'Images','file_extension'=>'jpg,png']];
 public function currentViewHref():string{return '/public/admin/pages/?id=10&lang=de';}
};
$panel=new class($pages) {
 public object $parent;
 public function __construct($pages){$this->parent=(object)['parent'=>$pages];}
 public function render($path):string{ob_start();include $path;return ob_get_clean();}
};
$all='';
foreach(['mml/groupInfo','sidebar/relationsHelp','sidebar/defaultMeta'] as $name) {
 $html=$panel->render(dirname(__DIR__).'/panels/admin/010_pages/'.$name.'/lightbox.tpl.php');
 check($name.' is marked as transient info',str_contains($html,'data-transient-info'));
 check($name.' closes to same page/language without history',str_contains($html,'data-history="false"')&&str_contains($html,'href="/public/admin/pages/?id=10&amp;lang=de"'));
 $all.=$html;
}
check('global defaults remain real navigation',str_contains($all,'class="ajax cmsTextLink" href="/public/admin/settings/pagesettings/"'));
check('share image points to Theme',str_contains($all,'href="/public/admin/settings/theme/"'));
check('default sources are explained',str_contains($all,'built-in fallback'));
file_put_contents('/private/tmp/fluxo-info-dialogs.html',$all);
