<?php
// The real upload working-copy operator, with disposable EXIF-tagged camera JPEGs.
require __DIR__.'/../../system/lib/class/fs/file/operator/fsFileOperator.php';
require __DIR__.'/../../system/lib/class/fs/file/operator/fsFileOperator_imagick.php';
$raw=new Imagick();$raw->newImage(80,40,'red','jpeg');$draw=new ImagickDraw();$draw->setFillColor('blue');$draw->rectangle(40,0,79,39);$raw->drawImage($draw);$blob=$raw->getImageBlob();$raw->clear();
foreach(range(1,8) as $orientation){
 $exif="Exif\0\0II".pack('vV',42,8).pack('v',1).pack('vvVv',0x112,3,1,$orientation)."\0\0".pack('V',0);
 $jpeg=substr($blob,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif.substr($blob,2);
 $operator=(new ReflectionClass(fsFileOperator_imagick::class))->newInstanceWithoutConstructor();
 $operator->file=(object)['mimeext'=>'jpg','standardizedExtension'=>'jpg','contents'=>$jpeg];$operator->read();
 $image=$operator->imagick;$expected=$orientation>=5?[40,80]:[80,40];
 if([$image->getImageWidth(),$image->getImageHeight()]!==$expected)throw new RuntimeException('Orientation dimensions '.$orientation);
 $first=$image->getImagePixelColor(5,5)->getColor();$blue=in_array($orientation,[2,3,7,8],true);
 if(($first['b']>$first['r'])!==$blue)throw new RuntimeException('Mirroring/rotation '.$orientation);
 if($image->getImageOrientation()!==Imagick::ORIENTATION_TOPLEFT && $orientation!==1)throw new RuntimeException('Orientation not normalized');
 $image->clear();echo "PASS camera EXIF orientation $orientation\n";
}
