<?php
// Exercise the real Imagick renderer with disposable files and a fixed in-memory record.
namespace Systopic\Db { class Scope {static function default(){return new self;}} }
namespace Systopic\System\Tables\Medias {class Files {static function idPath($id){return '001/23/';}static function tune(...$args){} }}
namespace Systopic\System\Queries\MediaCrop {class MediaCrop {static function record(...$args){return (object)[
 'media_crop_id'=>1,'media_crop_top'=>.5,'media_crop_left'=>.5,'media_crop_width'=>1,'media_crop_height'=>1,'media_crop_auto'=>0,
 'media_mimetype'=>'image/png','media_width'=>400,'media_height'=>200,'media_background_type'=>'image',
 'media_livearea_width'=>1,'media_livearea_height'=>1,'media_livearea_left'=>.5,'media_livearea_top'=>.5,
];}}}
namespace {
require __DIR__.'/../../system/lib/class/media/format/mediaFormats.php';require __DIR__.'/../../system/lib/class/media/image/mediaImage.php';
class fs {static $siteRoot;static function toNative($p){return $p;}static function fileExists($p){return is_file($p);}}
if(!class_exists('Imagick'))throw new RuntimeException('Run php -d extension=imagick tests/image-ratio-render.php');
$dir=sys_get_temp_dir().'/fluxo-render-'.bin2hex(random_bytes(5));mkdir($dir.'/var/formats/alias/001/23/',0777,true);fs::$siteRoot=$dir.'/';
$source=new Imagick();$source->newImage(400,200,new ImagickPixel('#365e70'),'png');$path=$dir.'/var/formats/alias/001/23/test.png';$source->writeImage($path);$source->clear();
$GLOBALS['image_formats']=['free'=>['megapixels'=>.04],'ratio'=>['megapixels'=>.04,'aspect_ratio'=>'1:1'],'pixels'=>['width'=>120,'height'=>80]];
try {foreach(['free'=>[283,141],'ratio'=>[200,200],'pixels'=>[120,80]] as $format=>$expected){$image=mediaImage::createImageFormat(123,$format,$path,'test.png');$size=[$image->getImageWidth(),$image->getImageHeight()];if($size!==$expected)throw new RuntimeException($format.': '.json_encode($size));$image->clear();echo 'PASS real rendered pixels: '.$format.' '.implode('x',$size)."\n";}}
finally {unlink($path);foreach(['var/formats/alias/001/23','var/formats/alias/001','var/formats/alias','var/formats','var',''] as $folder)rmdir($dir.($folder?'/'.$folder:''));}
}
