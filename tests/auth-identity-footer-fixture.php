<?php
namespace Systopic\System\Auth {
 class SignInRights { const PERSONS='persons';const SCREENS='screens';static function allows(...$args){return false;}static function screens(...$args){return [];} }
}
namespace {
 class http {static function get($key,$default=''){return $default;}}
 class message {static function flush(...$args){return '';}}
 $context=new class {public $scope;public $href='/auth/';function pendingAccount(){return (object)['fullname'=>'Fixture User','name'=>'Fixture'];}function render(){include __DIR__.'/../../system/panels/auth/identify.tpl.php';}};
 $context->render();
}
