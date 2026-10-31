<?php
/** Replays actual upload actions against disposable files; no database writes. */
declare(strict_types=1);
require __DIR__.'/media-upload-policy.php';
$fixture=sys_get_temp_dir().'/fluxo-upload-receipts-'.bin2hex(random_bytes(4));mkdir($fixture);fs::$siteRoot=$fixture.'/';
$receiptPanel=new class($pages){public string $action='upload';public object $state;function __construct(public object $parent){$this->state=(object)[];}function run(){include dirname(__DIR__).'/panels/cms/010_pages/upload/actions.php';}};
$key='a1b2c3d4-1234-4321-abcd-123456789abc';
try{
 http::$get=['node_parent_node_id'=>10,'group'=>'images'];
 http::$put=['name'=>'first.png','size'=>9999999,'spanIndex'=>0,'spanStart'=>0,'lastSpan'=>false,'label'=>'First','uploadKey'=>$key];
 $receiptPanel->run();$file=$fixture.'/var/tmp/upload/'.$key.'-first.png';
 check('new upload isolates its temporary file by receipt',is_file($file));
 $receiptPanel->run();check('replayed first chunk overwrites instead of appending',file_get_contents($file)==='not a PNG');
 $offset=3*1024*1024;$handle=fopen($file,'c+b');ftruncate($handle,$offset);fclose($handle);
 http::$put['spanIndex']=1;http::$put['spanStart']=$offset;$receiptPanel->run();clearstatcache();$once=filesize($file);
 $receiptPanel->run();clearstatcache();check('replayed later chunk has stable size and contents',filesize($file)===$once&&file_get_contents($file,false,null,$offset)==='not a PNG');
 http::$put['spanStart']=42;$receiptPanel->run();check('wrong offset is rejected',str_contains(end(client::$done)['error']??'','Invalid upload chunk'));
 http::$put['spanStart']=$offset;
 $fingerprint=hash('sha256',json_encode(['upload',http::put('name'),http::put('size'),http::put('url'),http::get('node_id'),http::get('node_parent_node_id'),http::get('group')]));
 $receiptPanel->state->uploadReceipts=[$key=>$fingerprint];
 $before=hash_file('sha256',$file);$receiptPanel->run();check('completed receipt returns success before writing or storing',end(client::$done)===['uploadComplete'=>true]&&hash_file('sha256',$file)===$before);
 http::$get['node_parent_node_id']=20;$receiptPanel->run();check('receipt cannot be reused for another page',str_contains(end(client::$done)['error']??'','identity changed'));
}finally{foreach(glob($fixture.'/var/tmp/upload/*')?:[]as$f)unlink($f);foreach(['/var/tmp/upload','/var/tmp','/var','']as$sub)if(is_dir($fixture.$sub))rmdir($fixture.$sub);}
