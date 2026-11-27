<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
require dirname(__DIR__,2).'/system/lib/class/media/format/mediaFormats.php';
use Systopic\System\Media\Variants;
mediaFormats::add(['background'=>['megapixels'=>3], 'projektCollection'=>['width'=>800,'height'=>600], 'new'=>['width'=>1600]]);
$choices=mediaFormats::editorImages();
if(in_array('--json',$argv,true)){echo json_encode($choices);return;}
check('page editor choices are exactly the configured ratios',array_values($choices)===array_keys(Variants::ratios()));
check('legacy template aliases are not choices',!array_intersect(['background','projektCollection','new'],array_keys($choices)));
check('choices have no fixed-pixel or megapixel labels',!preg_match('/MP|px/',implode(' ',$choices)));
Variants::configure(['16:9','1:1'],null);
check('project ratio settings control the choices',mediaFormats::editorImages()===['16by9'=>'16:9','1by1'=>'1:1']);
