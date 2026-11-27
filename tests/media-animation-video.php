<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\System\Media\{VariantRenderer,Presentation,Ratio};
use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\Medias\{Files,Model,Media,Picture};
// Actual file operators, isolated files; no application or database is started.
spl_autoload_register(static function($class) {
    static $map;
    if($map===null)foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__,2).'/system/lib/class')) as $e){
        if($e->isFile()&&$e->getExtension()==='php')$map[$e->getBasename('.php')]=$e->getPathname();
    }
    if(isset($map[$class]))require_once $map[$class];
});
function p($message):void {fwrite(STDERR,(string)$message."\n");}
define('OS',PHP_OS_FAMILY==='Windows'?'windows':'linux');
class http {public static string $root='https://fixture.test/';public static string $siteRoot='https://fixture.test/';}
$root=sys_get_temp_dir().'/fluxo-animation-video-'.bin2hex(random_bytes(4));mkdir($root.'/public',0777,true);
fs::setRootAndSitePath('', $root);
try {
 $animation=new Imagick();
 foreach(['red','blue','green'] as $color){$frame=new Imagick();$frame->newImage(48,32,new ImagickPixel($color),'gif');$frame->setImageDelay(12);$frame->setImageDispose(2);$animation->addImage($frame);}
 $animation->setImageIterations(0);$animation->writeImages($root.'/upload.gif',true);$hash=hash_file('sha256',$root.'/upload.gif');
 $gif=new Model();$gif->id=42;$gif->label='Animated';Files::store($gif,$root.'/upload.gif','animated.gif');
 check('upload preserves the exact animated original',hash_file('sha256',Files::originalPath($gif))===$hash);
 $read=new Imagick(Files::originalPath($gif));check('all three GIF frames survive the upload',$read->getNumberImages()===3);
 check('GIF dimensions and MIME are read',[$gif->width,$gif->height,$gif->mimetype]===[48,32,'image/gif']);
 check('GIF editor offers preview without crop tools',Presentation::editorKind('image/gif')==='gif'&&!str_contains(Presentation::editorPath(42,'image/gif'),'image/'));
 check('aliases and fixed formats keep the animated original',(new Media($gif))->setFormat('shareImage')->src==='https://fixture.test/var/original/000/42/animated.gif?v='.filemtime(Files::originalPath($gif)));
 $picture=new Picture(new CropSet($gif,[]),Ratio::of('1:1'));
 check('responsive output never replaces GIF with a still',!$picture->cut&&!str_contains($picture->toAutoHtml(),'data-media-auto')&&str_contains($picture->getUrl(2),'animated.gif'));
 check('GIF has no generated still candidates',$picture->sources()===[]);
 $source=file_get_contents(dirname(__DIR__).'/panels/admin/010_pages/panel.php');$start=strpos($source,'public function thumbOf(');$end=strpos($source,'/**', $start);eval('class FixtureThumb {'.substr($source,$start,$end-$start).'}');
 $thumb=(new FixtureThumb())->thumbOf($gif);
 check('library requests a separate static thumbnail, with replacement version',str_contains($thumb,'/var/formats/mmlThumb/000/42/animated.gif.webp?v=')&&$thumb!==(new Media($gif))->setFormat('original')->src);
 $method=new ReflectionMethod(mediaImage::class,'renderAlias');
 $alias=$method->invoke(null,Files::originalPath($gif),.5,'gif',false,'GIF thumbnail test');
 $still=new Imagick();$still->readImageBlob($alias->getContents());$alias->cleanup();$alias->destroy();
 check('actual thumbnail rasterizer produces one still frame',$still->getNumberImages()===1);
 $still->setImageFormat('webp');$still->thumbnailImage(200,200,true);
 $thumbPath=$root.'/thumb.webp';$still->writeImage($thumbPath);$still->clear();
 check('static thumbnail is a readable WebP',getimagesize($thumbPath)['mime']==='image/webp');
 check('making the library still leaves the original animation untouched',hash_file('sha256',Files::originalPath($gif))===$hash&&(new Imagick(Files::originalPath($gif)))->getNumberImages()===3);
 // The shared operator must work even in a web-worker PATH with no Homebrew.
 putenv('PATH=/usr/bin:/bin');
 $video=new Model();$video->id=43;$video->filename='sample.mp4';$video->mimetype='video/mp4';
 $dir=Files::originalDir($video);mkdir($dir,0777,true);$cmd=[shellBinary::find('ffmpeg'),'-v','error','-f','lavfi','-i','color=c=teal:s=320x180:r=12','-t','1','-c:v','libx264','-pix_fmt','yuv420p','-y',Files::originalPath($video)];
 $process=proc_open($cmd,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($error);
 $renderer=new VariantRenderer(new CropSet($video,[]),Files::originalPath($video));
 $path=$renderer->make(['ratio'=>null,'megapixels'=>.5,'original'=>false],'webp');
 $size=getimagesize($path);check('video thumbnail renders through real FFmpeg',$size!==false&&$size['mime']==='image/webp');
 check('small video is not upscaled',[$size[0],$size[1]]===[320,180]);
 check('row keeps actual video dimensions',[$video->width,$video->height]===[320,180]);
} finally {
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $e)$e->isDir()?rmdir($e->getPathname()):unlink($e->getPathname());rmdir($root);
}
