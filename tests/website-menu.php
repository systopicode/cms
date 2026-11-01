<?php
require __DIR__.'/../../system/tests/bootstrap.php';
require __DIR__.'/../../system/panels/cms/090_tools/035_website/panel.php';
$type=new ReflectionClass(Systopic\System\Panels\Root\Cms\Tools\Website\Panel::class);
$panel=$type->newInstanceWithoutConstructor();
$panel->onLoad();
check('website tools without CMS-supplied children are hidden', $panel->firstChild===null && !$panel->inNavi);
