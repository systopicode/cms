<?php
/** Local-only HTTP harness: real file permission views/actions on a disposable database. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\Db\{Connection, Scope};
use Systopic\System\Auth\Session;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Nodes\AclInheritance;
use Systopic\System\Tables\Medias\Media;
use Systopic\Cms\Refs\Resolver;
use Systopic\Cms\Queries\Params;

class http {
    public static string $root='/'; public static string $siteRoot='/';
    static function sessionStartOnce():void {}
    static function values($key){return $_POST[$key]??null;}
    static function dataset($key,$default=null){return $_POST[$key]??$default;}
}
class fs {public static string $siteRoot='/private/tmp/fluxo-file-permissions-output/';}
class message {public static array $errors=[];static function error($s){self::$errors[]=$s;}}
class client {public static array $commands=[];static function replaceInner($selector,$html){self::$commands[]=['selector'=>$selector,'html'=>$html];}}
define('PAGE_ACL',true);
try {
    $db=json_decode(file_get_contents(getenv('FLUXO_ACL_TEST_CONFIG')),true,512,JSON_THROW_ON_ERROR);
    if(!str_starts_with($db['name']??'','fluxo_test_file_permissions_'))throw new RuntimeException('Non-test database refused');
    $ids=json_decode(file_get_contents('/private/tmp/fluxo-file-permissions-fixture-ids.json'),true,512,JSON_THROW_ON_ERROR);
    $scope=new Scope(Connection::open($db)); Scope::setDefault($scope);Scope::addObserver(new AclInheritance());
    $_SESSION=['systopic_user_id'=>$ids[$_GET['viewer']??'admin']??null]; Session::reset();
    $row=MediaNodes::byNodeIds($scope,[$ids['node']])[$ids['node']];
    $panel=new class($scope,$row,$ids['node']) {
        public string $action='';
        public object $parent;
        function __construct(public Scope $scope,object $row,public int $targetId){}
        public object $mediaRow {get => MediaNodes::byNodeIds($this->scope,[$this->targetId])[$this->targetId];}
        function pages(){return $this;}
        function updateView($v){}
        function run($action){
            $this->action=$action;
            $renderStyle=null;
            if(isset($_GET['page'])){
                $this->parent=(object)['page'=>(object)['node'=>$this->mediaRow->node->parentNode],'scope'=>$this->scope];
                include dirname(__DIR__).'/panels/admin/010_pages/sidebar/actions.permissions.php';
            } else include dirname(__DIR__).'/panels/admin/010_pages/editor/file/actions.php';
            return $renderStyle;
        }
        function render(){include dirname(__DIR__).'/panels/admin/010_pages/editor/file/permissions.tpl.php';}
        function renderPage(){
            $permissionNode=$this->mediaRow->node->parentNode;
            include dirname(__DIR__).'/panels/admin/010_pages/sidebar/010_content/040_permissions/main.tpl.php';
        }
    };
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=$_GET['action']??'';
        if(!in_array($action,['aclInherit','aclToggle'],true))throw new RuntimeException('Unknown action');
        $style=$panel->run($action);
        if(isset($_GET['page'])){ob_start();$panel->renderPage();client::replaceInner('aside .sidebarItem > main',(string)ob_get_clean());}
        header('Content-Type:application/json');echo json_encode(['errors'=>message::$errors,'commands'=>client::$commands,'quiet'=>$style==='none']);return;
    }
    if(isset($_GET['output'])){
        header('Content-Type:text/html');
        $media=new Media($row);
        echo '<main data-readable="'.($media->readable?'yes':'no').'">';
        echo (new Resolver($scope,new Params(lang:'de')))->render('<a href="media:'.$ids['node'].'">Confidential download</a>');
        echo '</main>';return;
    }
    header('Content-Type:text/html');
    ?>
<!doctype html><html data-mode="<?= ($_GET['dark']??'0')==='1'?'dark':'light' ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/fixture.css"><body class="cms pages">
<header class="cmsMobileHeader"><button data-cms-window="menu">Menu</button><strong data-cms-module></strong></header><main><div class="main cms"><nav><ul><li class="active"><a href="/">Pages</a></li></ul></nav></div><div class="content"></div></main><button class="cmsMobileBackdrop" hidden></button>
<?php if(isset($_GET['page'])) { ?>
<aside class="cms" style="position:static;display:block;width:min(22rem,100%);height:auto;transform:none"><div class="sidebarItem"><h2>Page permissions</h2><main><?php $panel->renderPage(); ?></main></div></aside>
<?php } else { ?>
<div class="cms cmslightbox fileEditor" data-mobile-dialog="sheet"><section><header class="imageEditorHeader"><h1>File permissions</h1><button data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></header><main class="mediaDetails" data-node_id="<?= (int)$panel->targetId ?>"><label>Local note<input id="localNote" value="Local text"></label><section class="widget is-list mediaDetailPermissions"><header><h2>Permissions</h2></header><main><?php $panel->render(); ?></main></section></main></section></div>
<?php } ?>
<script>
// The CMS event transport is replaced here; permission logic and rendering are production code.
async function send(el,action,values){
 const token=window.cmsFeedback?.begin({event:{target:el,type:el.matches('input')?'change':'click'}},'put');
 const url=new URL(location.href);url.searchParams.set('action',action.split('?')[0].split('/').pop());
 const result=await fetch(url,{method:'POST',body:new URLSearchParams(values)}).then(r=>r.json());
 if(result.errors.length)throw new Error(result.errors.join('; '));
 for(const command of result.commands)document.querySelector(command.selector).innerHTML=command.html;
 document.dispatchEvent(new Event('cms:rendered'));
 window.cmsFeedback?.finish(token,true);
 window.fixtureSaves=(window.fixtureSaves||0)+1;
 window.fixtureQuiet=result.quiet;
}
document.addEventListener('change',e=>{if(e.target.matches('[data-on_change]'))send(e.target,e.target.dataset.on_change,{acl_inherit:e.target.checked?'1':'0'});});
document.addEventListener('click',e=>{const chip=e.target.closest('[data-on_click]');if(chip)send(chip,chip.dataset.on_click,{group_id:chip.dataset.group_id});});
</script></body></html>
<?php
} catch(Throwable $e) {http_response_code(500);echo 'Fixture request failed: '.htmlspecialchars($e->getMessage(),ENT_QUOTES);}
