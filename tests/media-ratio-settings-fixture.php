<?php
require __DIR__.'/../../system/tests/bootstrap.php';
require_once __DIR__.'/../../system/src/Settings/ProjectFile.php';
$dir=sys_get_temp_dir().'/fluxo-ratio-'.bin2hex(random_bytes(5));mkdir($dir);$path=$dir.'/media.settings.json';
try {
 Systopic\System\Settings\ProjectJson::put($path,['formats'=>['free'=>['label'=>'Flexible background','megapixels'=>3],'ratio'=>['megapixels'=>2,'aspect_ratio'=>'16:9'],'pixels'=>['width'=>800,'height'=>600]]]);
 $groups=Systopic\System\Settings\ProjectJson::groups($path);
 $method=new ReflectionMethod(Systopic\System\Settings\ProjectForm::class,'recordTable');
 foreach($groups as $group)if($group->id==='call:mediaFormats::add')$method->invoke(null,'media',$group);
} finally { @unlink($path); @rmdir($dir); }
