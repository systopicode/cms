<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
class http {
 public static string $method='POST';
 public static string $siteUrl='/public/';
 public static array $post=['return_url'=>'/public/admin/pages/?id=10&lang=de'];
 public static function post($key,$default=null){return self::$post[$key]??$default;}
}
$panel=new class {
 public string $action='';public object $state;public object $parent;
 public function __construct(){$this->state=(object)[];$this->parent=(object)['parent'=>(object)['library'=>[],'href'=>'/public/admin/pages/']];}
 public function render():string{ob_start();include dirname(__DIR__).'/panels/admin/010_pages/editor/editLink/lightbox.tpl.php';return ob_get_clean();}
};
$html=$panel->render();
if (($argv[1]??'')==='--render') file_put_contents('/private/tmp/fluxo-link-dialog-fixture.html',$html);
check('search is independent of visible address',str_contains($html,'data-on_input="/public/admin/pages/editor/editLink/searchLinkPages"'));
check('transient link dialog closes without history entry',str_contains($html,'data-history="false"'));
check('close returns to original selection query',str_contains($html,'href="/public/admin/pages/?id=10&amp;lang=de"'));
http::$post['return_url']='https://other.test/public/admin/pages/';
$html=$panel->render();
check('return URL cannot leave project origin',!str_contains($html,'https://other.test'));
