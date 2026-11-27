<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
require dirname(__DIR__).'/lib/class/app/site/appSite.php';
use Systopic\Db\{Connection,Scope};
use Systopic\Db\Schema\{Audit,Schema};
use Systopic\System\Auth\Session;
use Systopic\System\Tables\Nodes\{Model as Node,Operator as NodeOperator};
use Systopic\System\Tables\Acls\{Model as Acl,Operator as AclOperator};
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\RolesOnGroups\Model as Grant;
use Systopic\System\Tables\Users\Model as User;
use Systopic\Cms\Tables\Pages\{Model as PageRow,Page};
use Systopic\Cms\Tables\Rewritepaths\Model as Path;
use Systopic\Cms\Search\PublicPages;

class http {public static string $root='/project/',$siteUrl='https://fixture.test/project/'; static function sessionStartOnce():void{}}
class app {static function request(){static $request;return $request??=(object)['preview'=>false,'showeditableareas'=>false];} static function site(){static $site;return $site??=new appSite();}}
class fs {static string $siteRoot='/private/tmp/fluxo-search-output-implementation-20261127/robots-fixture/';static function toNative($v){return $v;}}
function p(){return new class{function erase(){return $this;}};}
define('PAGE_ACL',true);define('PAGE_IS_PUBLIC',!in_array('--private',$argv,true));
define('DEFAULT_LANG','it');define('LANGUAGES',['de'=>['code'=>'de','activated'=>true],'it'=>['code'=>'it','activated'=>true],'en'=>['code'=>'en','activated'=>false]]);
define('PUBLIC_PAGE_NAME','Fixture');define('META_SHARE_IMAGE','https://fixture.test/share.jpg');define('STAGE','LIVE');
$_SESSION=[];
$file=getenv('FLUXO_SEARCH_TEST_CONFIG')?:throw new RuntimeException('Set FLUXO_SEARCH_TEST_CONFIG to an isolated test connection.');
$db=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with($db['name']??'','fluxo_test_search_output_'))throw new RuntimeException('Refusing a non-test database.');
$connection=Connection::open($db);Schema::setDefaultTablePrefix('cms');$scope=new Scope($connection);Scope::setDefault($scope);
if(!PAGE_IS_PUBLIC){
 check('a private website exports no pages',PublicPages::all()===[]);
 ob_start();include dirname(__DIR__).'/llms.php';$privateText=ob_get_clean();
 check('private llms response is refused without page details',http_response_code()===403&&$privateText==="Not public.\n");
 ob_start();include dirname(__DIR__).'/sitemap.php';$privateXml=ob_get_clean();
 check('private sitemap response is refused without URLs',http_response_code()===403&&!str_contains($privateXml,'<url>'));
 ob_start();include dirname(__DIR__).'/robots.php';$privateRobots=ob_get_clean();
 check('private website robots blocks all crawling',str_contains($privateRobots,'Disallow: /')&&!str_contains($privateRobots,'Sitemap:'));
 exit;
}
$database=$db['name'];
$connection->exec("DROP DATABASE `$database`");
$connection->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
$connection->exec("USE `$database`");
$audit=new Audit($connection);
$audit->apply($audit->run([User::class,Group::class,Systopic\System\Tables\Roles\Model::class,Grant::class,Acl::class,Systopic\System\Tables\GroupsInAcls\Model::class,Node::class,PageRow::class,Path::class]));
check('isolated page and permission tables created',$audit->failures()===[]);
$scope->save(new Group(),new User(),new Node(),new PageRow());
$group=new Group();$group->name='Public';$secret=new Group();$secret->name='Secret';$scope->save($group,$secret);
$roles=Systopic\System\Queries\UserRights\UserRights::keyedRoles($scope);
$grant=new Grant();$grant->role=$roles['everyone'];$grant->group=$group;$grant->read=true;$scope->save($grant);
$acl=static function($g)use($scope){[$a,$save]=AclOperator::forGroups($scope,[$g]);if($save)$scope->save(...$save);return $a;};
$public=$acl($group);$private=$acl($secret);
$make=static function(string $label,string $path,?Acl $acl,string $lang='it',bool $published=true,?Node $parent=null)use($scope):array{
 $node=new Node();$node->type='page';$node->parentNode=$parent;$scope->save(...NodeOperator::withAcl($node,$acl));
 $row=new PageRow();$row->node=$node;$row->languageIso=$lang;$row->label=$label;$row->templateName='fixture';$row->metaDescription='Description "with quotes" & details';$row->isPublished=$published;$row->isNaviElement=true;$scope->save($row);
 $rewrite=new Path();$rewrite->page=$row;$rewrite->string=$path;$rewrite->isPrimary=true;$scope->save($rewrite);
 return [$node,$row];
};
[$home,$homeRow]=$make('Italian home','home',$public);
$de=new PageRow();$de->node=$home;$de->languageIso='de';$de->label='German home';$de->templateName='fixture';$de->isPublished=true;$de->isNaviElement=true;$scope->save($de);
$rewrite=new Path();$rewrite->page=$de;$rewrite->string='start';$rewrite->isPrimary=true;$scope->save($rewrite);
[$duplicate]=$make('Same URL','home',$public);
[$secretNode]=$make('Secret page','secret',$private);
$make('Public child under a private page','secret-child',$public,parent:$secretNode);
$make('Not published','offline',$public,published:false);
[$n,$r]=$make('No index','no-index',$public);$r->metaRobots='noindex, follow';$scope->save($r);
[$n,$r]=$make('Future','future',$public);$r->startPublishing=new DateTimeImmutable('+1 day');$scope->save($r);
[$n,$r]=$make('Expired','expired',$public);$r->endPublishing=new DateTimeImmutable('-1 day');$scope->save($r);
[$n,$r]=$make('Trash','trash',$public);$n->isTrashed=true;$scope->save($n);
$make('Fragment','#part',$public);
$make('Disabled English','english',$public,lang:'en');
[$folder,$folderRow]=$make('Organizational folder','folder',$public);$folderRow->templateName='';$scope->save($folderRow);
$alias=new Node();$alias->type='page';$alias->originalNode=$home;$scope->save(...NodeOperator::withAcl($alias,$public));
$admin=new User();$admin->name='Disposable admin';$admin->isActive=true;$admin->isSuperuser=true;$scope->save($admin);
$_SESSION['systopic_user_id']=$admin->id;Session::reset();
$urls=array_map(static fn(Page $p)=>(string)$p->href,PublicPages::all());
check('an editor session exports only public, released, canonical URLs',$urls===['https://fixture.test/project/home/','https://fixture.test/project/start/']);
$page=Page::byId($scope,PublicPages::params('it'),(int)$home->id);
$links=app::site()->alternates($page);
check('default language controls x-default even when not first',$links===['de'=>'https://fixture.test/project/start/','it'=>'https://fixture.test/project/home/','x-default'=>'https://fixture.test/project/home/']);
$html=app::site()->headTags($page);
check('central metadata escapes values',str_contains($html,'Description &quot;with quotes&quot; &amp; details'));
check('public LIVE metadata stays indexable',str_contains($html,'name="robots" content="index, follow"'));
app::request()->preview=true;
check('authenticated previews stay noindex even on LIVE',app::site()->robotsFor($page)==='noindex, nofollow');
app::request()->preview=false;
$doc=Dom\HTMLDocument::createFromString('<!doctype html><html><head>'.$html.'</head><body></body></html>',LIBXML_NOERROR);
$graph=json_decode($doc->querySelector('script[type="application/ld+json"]')->textContent,true)['@graph'];
check('structured data and canonical address describe the same page',$graph[0]['url']===$doc->querySelector('link[rel=canonical]')->getAttribute('href'));
$aliasPage=Page::byId($scope,PublicPages::params('it'),(int)$alias->id);
check('a public alias uses its original canonical URL',app::site()->record($aliasPage)['url']==='https://fixture.test/project/home/'&&!$aliasPage->meta->listed);
check('a template-less folder stays out of indexing',app::site()->robotsFor(Page::byId($scope,PublicPages::params('it'),(int)$folder->id))==='noindex, nofollow');
check('a private translation is absent from public alternatives',Page::byId($scope,PublicPages::params('it'),(int)$secretNode->id)===null);
ob_start();include dirname(__DIR__).'/sitemap.php';$xml=ob_get_clean();
$parsed=new DOMDocument();check('public sitemap is valid XML with escaped values',$parsed->loadXML($xml));
check('public sitemap has exactly the two canonical language URLs',$parsed->getElementsByTagName('url')->length===2&&str_contains($xml,'hreflang="it"'));
ob_start();include dirname(__DIR__).'/llms.php';$text=ob_get_clean();
check('AI list shares the same public pages',substr_count($text,'- [')===3&&!str_contains($text,'Secret page')&&!str_contains($text,'Not published'));
ob_start();include dirname(__DIR__).'/robots.php';$robots=ob_get_clean();
check('LIVE robots uses the full site sitemap address',str_contains($robots,'Sitemap: https://fixture.test/project/sitemap.xml')&&str_contains($robots,'Disallow: /project/admin/'));
if(!is_dir(fs::$siteRoot))mkdir(fs::$siteRoot,0700,true);
file_put_contents(fs::$siteRoot.'robots.txt',"User-agent: GPTBot\nDisallow: /training-private/\nSitemap: /wrong-sitemap.xml\n");
ob_start();include dirname(__DIR__).'/robots.php';$robots=ob_get_clean();
check('existing project crawler policies survive the generated robots output',str_contains($robots,'User-agent: GPTBot')&&str_contains($robots,'Disallow: /training-private/')&&!str_contains($robots,'wrong-sitemap')&&substr_count($robots,'Sitemap:')===1);
require dirname(__DIR__,2).'/system/panels/admin/panel.php';
require dirname(__DIR__).'/panels/admin/010_pages/sidebar/panel.php';
$host=(new ReflectionClass(Systopic\System\Panels\PanelNode::class))->newInstanceWithoutConstructor();$host->page=Page::byId($scope,PublicPages::params('it'),(int)$home->id);
$sidebar=new class($host) extends Systopic\Cms\Panels\Root\Admin\Pages\Sidebar\Panel {
 public function __construct(private Systopic\System\Panels\PanelNode $fixturePages) {}
 public function pages(): Systopic\System\Panels\PanelNode {return $this->fixturePages;}
};
$preview=$sidebar->seoPreview();
check('existing sidebar shows configured default and the anonymous output-check control',str_contains($preview,'Default language</dt><dd>it</dd>')&&str_contains($preview,'onclick="cmsCheckPageOutput(this)"')&&str_contains($preview,'data-output-url="https://fixture.test/project/home/"'));
file_put_contents('/private/tmp/fluxo-search-output-implementation-20261127/sidebar-fragment.html',$preview);
echo "Public search output: all checks passed.\n";
