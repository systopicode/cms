<?php
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\System\Ui\Icons;
require_once dirname(__DIR__,2).'/system/panels/cms/panel.php';
require_once dirname(__DIR__).'/panels/cms/010_pages/panel.php';
$panel=(new ReflectionClass(\Systopic\Cms\Panels\Root\Cms\Pages\Panel::class))->newInstanceWithoutConstructor();
foreach (['image','video','application'] as $type) {
    $media=new \Systopic\System\Tables\Medias\Model(); $media->type=$type;
    check('actual broken media producer '.$type, isset(Icons::registry()['outline'][$panel->brokenIcon($media)]));
}

foreach ([['image','image/png','fixture.png'],['image','image/gif','fixture.gif'],['image','image/svg+xml','fixture.svg'],['video','video/mp4','fixture.mp4'],['audio','audio/mpeg','fixture.mp3'],['sketchup','application/vnd.sketchup.skp','fixture.skp'],['application','application/pdf','fixture.pdf'],['application','application/zip','fixture.zip'],['application','application/vnd.openxmlformats-officedocument.wordprocessingml.document','fixture.docx'],['youtube','video/mp4','fixture.mp4']] as [$type,$mime,$filename]) {
    $media=new \Systopic\System\Tables\Medias\Model();$media->type=$type;$media->mimetype=$mime;$media->filename=$filename;
    $face=$panel->tileFace($media);
    check('actual tile face '.$mime,isset(Icons::registry()['outline'][$face['icon']]));
}
