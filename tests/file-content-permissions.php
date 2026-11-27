<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\Db\{Connection,Scope};
use Systopic\Db\Schema\{Audit,Schema};
use Systopic\System\Auth\Session;
use Systopic\System\Tables\Nodes\{Model as Node,Operator as NodeOperator,AclInheritance};
use Systopic\System\Tables\Medias\{Model as MediaRow,Media,Picture};
use Systopic\System\Tables\Acls\{Model as Acl,Operator as AclOperator};
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\RolesOnGroups\Model as Grant;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\UsersAtRoles\Model as Assignment;
use Systopic\Cms\Refs\{MediaRefs,Ref,Resolver};
use Systopic\Cms\Queries\Params;

class http {
 public static string $root='https://fixture.test/'; public static string $siteRoot='https://fixture.test/'; public static string $siteUrl='https://fixture.test/';
 public static array $values=[];public static array $dataset=[];
 static function sessionStartOnce():void{}
 static function values($key){return self::$values[$key]??null;}
 static function dataset($key,$default=null){return self::$dataset[$key]??$default;}
}
class fs {static string $siteRoot='/private/tmp/fluxo-file-permissions-output/';static function toNative($path){return $path;}}
class message {static array $errors=[];static function error($s){self::$errors[]=$s;}}
class client {static array $commands=[];static function replaceInner($selector,$html){self::$commands[]=[$selector,$html];}}
$_SESSION=[];define('PAGE_ACL',true);
$configFile=getenv('FLUXO_ACL_TEST_CONFIG')?:throw new RuntimeException('Set FLUXO_ACL_TEST_CONFIG to isolated test connection JSON.');
$db=json_decode(file_get_contents($configFile),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with($db['name']??'','fluxo_test_file_permissions_'))throw new RuntimeException('Refusing a non-test database.');
$connection=Connection::open($db);$database=$db['name'];
$connection->exec("DROP DATABASE `$database`");$connection->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");$connection->exec("USE `$database`");
Schema::setDefaultTablePrefix('cms');$scope=new Scope($connection);Scope::setDefault($scope);Scope::addObserver(new AclInheritance());
$audit=new Audit($connection);$audit->apply($audit->run([User::class,Group::class,Role::class,Grant::class,Assignment::class,Acl::class,Systopic\System\Tables\GroupsInAcls\Model::class,Node::class,MediaRow::class,Systopic\System\Tables\MediaCrops\Model::class]));
check('isolated permission tables created',$audit->failures()===[]);
$scope->save(new Group(),new Role(),new User(),new Acl(),new Node(),new MediaRow());
$public=new Group();$public->name='Public';$public->color='#1f3a45';$restricted=new Group();$restricted->name='Group X';$restricted->color='#66aacc';$other=new Group();$other->name='Group Y';$scope->save($public,$restricted,$other);
$builtins=Systopic\System\Queries\UserRights\UserRights::keyedRoles($scope);
$everyone=$builtins['everyone'];
$writer=new Role();$writer->key='fileWriter';$writer->name='File writer';$reader=new Role();$reader->key='fileReader';$reader->name='File reader';$outsider=new Role();$outsider->key='otherReader';$outsider->name='Other reader';$scope->save($writer,$reader,$outsider);
foreach([[$everyone,$public,true,false],[$writer,$restricted,true,true],[$reader,$restricted,true,false],[$outsider,$other,true,true]] as [$r,$g,$read,$write]){$grant=new Grant();$grant->role=$r;$grant->group=$g;$grant->read=$read;$grant->write=$write;$scope->save($grant);}
$makeUser=static function($name,$role=null,$super=false)use($scope){$u=new User();$u->name=$name;$u->isActive=true;$u->isSuperuser=$super;$scope->save($u);if($role){$a=new Assignment();$a->user=$u;$a->role=$role;$scope->save($a);}return $u;};
$admin=$makeUser('Test admin',null,true);$alice=$makeUser('X writer',$writer);$bob=$makeUser('X reader',$reader);$eve=$makeUser('Y reader',$outsider);
$acl=static function($groups)use($scope){[$a,$rows]=AclOperator::forGroups($scope,$groups);if($rows)$scope->save(...$rows);return $a;};
$root=new Node();$root->type='page';$scope->save(...NodeOperator::withAcl($root,$acl([$public])));
$node=new Node();$node->type='media';$node->parentNode=$root;$scope->save($node);
$row=new MediaRow();$row->node=$node;$row->filename='private.png';$row->mimetype='image/png';$row->type='image';$row->label='Secret image';$row->width=640;$row->height=360;$scope->save($row);
$as=static function(?User $user):void{$_SESSION['systopic_user_id']=$user?->id;Session::reset();};
$as($admin);
$panel=new class($scope,$row) {
 public string $action='';public array $updated=[];public int $targetId;
 function __construct(public Scope $scope,public MediaRow $mediaRow){$this->targetId=(int)$mediaRow->node->id;}
 function pages(){return new class($this->scope){function __construct(public Scope $scope){}function updateView($v){} };}
 function updateView($view){$this->updated[]=$view;}
 function run(string $action,array $values=[],array $dataset=[]){$this->action=$action;http::$values=$values;http::$dataset=$dataset;$renderStyle=null;include dirname(__DIR__).'/panels/admin/010_pages/editor/file/actions.php';check('permission action skips a full dialog render',$renderStyle==='none');}
};
$request=static function(string $action,array $values=[],array $dataset=[])use($connection,&$scope,&$row,&$node,$panel):void {
 // Each HTTP action gets a fresh ORM scope, as the running application does.
 $scope=new Scope($connection);Scope::setDefault($scope);Session::reset();
 $row=Systopic\System\Queries\MediaNodes\MediaNodes::byNodeIds($scope,[(int)$node->id])[(int)$node->id];$node=$row->node;
 $panel->scope=$scope;$panel->mediaRow=$row;$panel->run($action,$values,$dataset);
};
check('a new file inherits the public parent',$node->aclInherit&&$node->relationId('acl')===$root->relationId('acl'));
$request('aclInherit',['acl_inherit'=>false]);check('real file action disables inheritance',!$node->aclInherit);
$request('aclToggle',[],['group_id'=>$restricted->id]);$request('aclToggle',[],['group_id'=>$public->id]);
check('real file actions store Group X on this file',AclOperator::groupIds($node->acl)===[(int)$restricted->id]&&$panel->updated===['permissions','permissions','permissions']);
check('permission responses replace only the current file card',count(client::$commands)===3&&array_reduce(client::$commands,static fn($ok,$cmd)=>$ok&&$cmd[0]==='.fileEditor .mediaDetails[data-node_id="'.(int)$node->id.'"] .mediaDetailPermissions > main'&&str_contains($cmd[1],'data-feedback="none"'),true));
check('changing file groups does not change its parent',AclOperator::groupIds($root->acl)===[(int)$public->id]);
$media=new Media($row);$picture=new Picture(new Systopic\System\Queries\MediaCrops\CropSet($row,[]));
foreach([[$alice,true],[$bob,true],[$eve,false],[null,false],[$admin,true]] as [$viewer,$allowed]){
 $as($viewer);$label=$viewer?->name??'Guest';
 check($label.': visibility matches groups',$media->readable===$allowed);
 check($label.': direct load respects groups',(Media::byNodeId($scope,(int)$node->id)!==null)===$allowed);
 check($label.': ordinary media output',((string)$media!=='')===$allowed);
 check($label.': responsive image output',($picture->toAutoHtml()!=='')===$allowed);
 check($label.': legacy image helper respects file groups',($picture->toImgHtml()!=='')===$allowed);
 check($label.': picture output',($picture->toPictureHtml(['(max-width:600px)'=>'1:1'])!=='')===$allowed);
 check($label.': original URL',($media->setFormat('original')->src!=='')===$allowed);
 check($label.': generated URL',($picture->variantUrl('shareImage','jpg')!=='')===$allowed);
 $page=new Systopic\Cms\Tables\Pages\Page($scope,new Params(lang:'de'),$root);
 $page->placeIn(new Systopic\Cms\Queries\PageTree\PageTreeLevel($scope,new Params(lang:'de')));
 check($label.': page media collection filters restricted files',($page->medias->all()!==[])===$allowed);
 $refs=new MediaRefs($scope);$ref=Ref::fromAttribute('media:'.$node->id);check($label.': references omit unavailable media',($refs->load([$ref])!==[])===$allowed);
 $html=(new Resolver($scope,new Params(lang:'de')))->render('<img src="media:'.$node->id.'" alt="Secret image">');check($label.': WYSIWYG image output',str_contains($html,'<img')===$allowed);
 $html=(new Resolver($scope,new Params(lang:'de')))->render('<img src="media:'.$node->id.'?format=master&amp;desktop=16by9&amp;tablet=1by1&amp;mobile=9by16" alt="Secret crop">');check($label.': device picture respects file groups',str_contains($html,'<picture>')===$allowed);
 $html=(new Resolver($scope,new Params(lang:'de')))->render('<a href="media:'.$node->id.'">Confidential download</a>');check($label.': WYSIWYG download label is omitted with its file',str_contains($html,'Confidential download')===$allowed);
 foreach(['video/mp4','image/gif','audio/mpeg','application/pdf','application/zip'] as $mime){
  $row->mimetype=$mime;
  check($label.': '.$mime.' output follows file groups',((string)new Media($row)!=='')===$allowed);
 }
 $row->mimetype='image/png';
}
$as($bob);message::$errors=[];$before=$node->relationId('acl');$request('aclInherit',['acl_inherit'=>true]);check('a reader cannot alter file permissions',$node->relationId('acl')===$before&&!$node->aclInherit&&message::$errors!==[]);
$as($alice);message::$errors=[];$request('aclToggle',[],['group_id'=>$other->id]);check('a writer cannot add groups they do not control',AclOperator::groupIds($node->acl)===[(int)$restricted->id]&&message::$errors!==[]);
$as($admin);$request('aclInherit',['acl_inherit'=>true]);check('returning to inheritance restores parent groups',$node->aclInherit&&$node->relationId('acl')===$root->relationId('acl'));
check('inherited groups are readable in the same response',AclOperator::groupIds($node->acl)===[(int)$public->id]);
$as(null);$media=new Media($row);$picture=new Picture(new Systopic\System\Queries\MediaCrops\CropSet($row,[]));check('public inheritance restores visitor output',$media->readable&&$picture->toImgHtml()!=='');
// Save fixture IDs for a browser round trip against this isolated database.
file_put_contents('/private/tmp/fluxo-file-permissions-fixture-ids.json',json_encode(['media'=>$row->id,'node'=>$node->id,'root'=>$root->id,'admin'=>$admin->id,'writer'=>$alice->id,'reader'=>$bob->id,'other'=>$eve->id,'public'=>$public->id,'restricted'=>$restricted->id,'otherGroup'=>$other->id]));
echo "File content permissions: all checks passed.\n";
