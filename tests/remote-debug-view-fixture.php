<?php
namespace Systopic\Relay { class RemoteDebug { const COOKIE='fixture'; const LIST_SECONDS=1800; static function available(){return true;} static function label($ua){return 'Tablet · Safari';} } }
namespace Systopic\System\Settings { class CentrifugoHub { static function status(){return ['running'=>true];} } }
namespace Systopic\Db { class Scope { static function default(){return null;} } }
namespace Systopic\System\Queries\UserAdmin { class UserAdmin { static function of($s){return new self;} function user($id){return (object)['fullname'=>'Fixture User','name'=>'fixture','email'=>''];} } }
namespace { class http { static $root='/'; } $panel=new class { public $parent=null;public $name='debug';function clients(){return [['id'=>'fixture','ua'=>'fixture','ip'=>'127.0.0.1','user'=>3,'url'=>'/admin/','seen'=>time(),'session'=>null]];}function windowHref($id){return '/fixture/debug';}function render(){include __DIR__.'/../../relay/panels/admin/090_tools/040_debug/main.tpl.php';}}; $panel->render(); }
