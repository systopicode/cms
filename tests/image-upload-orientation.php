<?php
namespace Systopic\System\Tables\Medias { #[\AllowDynamicProperties] class Model {public $id=123; public $filename='';} }
namespace {
class fs {static $siteRoot;static function toNative($path){return $path;}}
require __DIR__.'/../../system/src/Media/GroupPolicy.php';
require __DIR__.'/../../system/src/Tables/Medias/Files.php';
require __DIR__.'/image-orientation.php';
// The orientation-8 JPEG from the operator test now goes through the actual store/describe path.
$dir=sys_get_temp_dir().'/fluxo-upload-'.bin2hex(random_bytes(5));mkdir($dir);fs::$siteRoot=$dir.'/';
$tmp=$dir.'/upload.jpg';file_put_contents($tmp,$jpeg);$hash=hash_file('sha256',$tmp);$media=new \Systopic\System\Tables\Medias\Model();
try {
 \Systopic\System\Tables\Medias\Files::store($media,$tmp,'camera.jpg');
 $path=\Systopic\System\Tables\Medias\Files::originalPath($media);
 if([$media->width,$media->height]!==[40,80])throw new \RuntimeException('Stored display dimensions');
 if(hash_file('sha256',$path)!==$hash)throw new \RuntimeException('Upload original changed');
 echo "PASS actual upload storage: oriented dimensions, original bytes preserved\n";
}finally{\Systopic\System\Tables\Medias\Files::remove($media);foreach(['var/original/001','var/original','var',''] as $folder)@rmdir($dir.($folder?'/'.$folder:''));}
}
