<?php
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\Cms\Queries\MediaUsage\MediaUsage;
$nodes = [
 ['id'=>1,'parent'=>0,'original'=>0,'type'=>'page','trashed'=>0],
 ['id'=>2,'parent'=>1,'original'=>0,'type'=>'media','trashed'=>0],
 ['id'=>3,'parent'=>4,'original'=>2,'type'=>'media','trashed'=>0],
 ['id'=>4,'parent'=>0,'original'=>0,'type'=>'page','trashed'=>0],
];
$pages = [
 ['node'=>1,'lang'=>'en','label'=>'First','share'=>0,'content'=>''],
 ['node'=>4,'lang'=>'en','label'=>'Second','share'=>3,'content'=>'<a href="media:3">Download</a>'],
 ['node'=>5,'lang'=>'de','label'=>'Third','share'=>0,'content'=>'<img src="media:20">'],
 ['node'=>6,'lang'=>'en','label'=>'Legacy','share'=>0,'content'=>'/var/original/000/42/file.pdf'],
];
$uses = MediaUsage::collect($nodes,$pages,2,42);
check('library, aliases, share and legacy links detected',count($uses) === 3);
check('combined reasons deduplicated', $uses[1]['reasons'] === ['Media library','Share image','Content']);
check('similar media id excluded', !in_array(5,array_column($uses,'node'),true));
check('querying alias resolves same uses', MediaUsage::collect($nodes,$pages,3,42) === $uses);
echo "Media usage checks passed.\n";
