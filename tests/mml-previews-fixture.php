<?php
/** The production library and preview helpers, with disposable files and no database. */
$argv=['fixture','image','library'];
ob_start();require __DIR__.'/file-editor-tabs-fixture.php';ob_end_clean();
require_once dirname(__DIR__,2).'/system/panels/admin/panel.php';
require_once dirname(__DIR__).'/panels/admin/010_pages/panel.php';
$panel=(new ReflectionClass(\Systopic\Cms\Panels\Root\Admin\Pages\Panel::class))->newInstanceWithoutConstructor();
class PreviewFixture extends Fixture {
 public object $previewPanel;
 function thumbOf($media){return $this->previewPanel->thumbOf($media);}
 function tileFace($media){return $this->previewPanel->tileFace($media);}
 function brokenIcon($media){return $this->previewPanel->brokenIcon($media);}
 function renderPreviews(){include dirname(__DIR__).'/panels/admin/010_pages/mml.tpl.php';}
}
fs::$siteRoot='/private/tmp/fluxo-mml-previews/';
$gif=clone $fixture->mediaRow;$gif->id=8;$gif->filename='animated.gif';$gif->mimetype='image/gif';$gif->type='image';$gif->label='Animated GIF';$gif->node=clone $gif->node;$gif->node->id=43;
$dir=fs::$siteRoot.'var/original/000/08/';@mkdir($dir,0777,true);
$animation=new Imagick();foreach(['red','blue','green'] as $color){$frame=new Imagick();$frame->newImage(120,80,new ImagickPixel($color),'gif');$frame->setImageDelay(12);$animation->addImage($frame);}$animation->setImageIterations(0);$animation->writeImages($dir.$gif->filename,true);
$still=new Imagick($dir.$gif->filename.'[0]');$still->setImageFormat('webp');$still->thumbnailImage(200,200,true);
$thumbDir=fs::$siteRoot.'var/formats/mmlThumb/000/08/';@mkdir($thumbDir,0777,true);$still->writeImage($thumbDir.$gif->filename.'.webp');
$external=clone $fixture->mediaRow;$external->label='External media';
$preview=new PreviewFixture('image','library',$media,'image/png','png');$preview->previewPanel=$panel;$preview->href='/admin/pages/';$preview->state=(object)['id'=>10];$preview->mediaGroups=['main'=>['label'=>'Main']];
$preview->library=['main'=>[['media'=>$external,'node'=>$external->node,'alias'=>false],['media'=>$gif,'node'=>$gif->node,'alias'=>false]]];
$preview->renderPreviews();
