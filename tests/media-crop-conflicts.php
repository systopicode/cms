<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\Db\{Connection,Scope};
use Systopic\System\Media\CropEditorEndpoint;
use Systopic\System\Tables\Medias\{Model,Files};
class fs {public static string $siteRoot;static function toNative($p){return $p;}}
class http {public static string $root='/';}
$config=getenv('FLUXO_ACL_TEST_CONFIG')?:throw new RuntimeException('An isolated ACL test DB is required');
$db=json_decode(file_get_contents($config),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with($db['name']??'','fluxo_test_file_permissions_'))throw new RuntimeException('Test DB only');
$connection=Connection::open($db);fs::$siteRoot='/private/tmp/fluxo-media-crop-conflicts/';
$request=static fn(int $id,array $params):array=>CropEditorEndpoint::answer($params['action']??'saveRatioCrops',new Scope($connection),$id,static fn($k,$default=null)=>$params[$k]??$default);
if(($argv[1]??'')==='--child'){
 $params=json_decode($argv[2],true,512,JSON_THROW_ON_ERROR);$id=$params['id'];unset($params['id']);$answer=$request($id,$params);echo json_encode(['status'=>$answer['status']]);exit;
}
$scope=new Scope($connection);$row=new Model();$row->filename='concurrency.png';$row->type='image';$row->mimetype='image/png';$row->width=600;$row->height=400;$scope->save($row);$id=(int)$row->id;
@mkdir(dirname(Files::originalPath($row)),0777,true);$image=new Imagick();$image->newImage(600,400,new ImagickPixel('#357485'),'png');$image->writeImage(Files::originalPath($row));$image->clear();
$state=static fn()=> $request($id,['action'=>'cropState']);
$version=$state()['body']['version'];
$params=['version'=>$version,'crops'=>json_encode([['ratio'=>'1:1','ax'=>.3,'ay'=>.5,'scale'=>1.2]])];
check('first editor may apply the version it loaded',$request($id,$params)['status']===200);
$after=$state()['body'];check('a successful edit changes the saved version',$after['version']!==$version);
check('second editor cannot overwrite with an older version',$request($id,$params)['status']===409&&$state()['body']['version']===$after['version']);
check('a missing version requires a reload',$request($id,['crops'=>'[]'])['status']===409);
check('old master edits are rejected too',$request($id,['action'=>'saveMasterCrop','version'=>$version,'auto'=>1])['status']===409);
check('old point-of-interest edits are rejected too',$request($id,['action'=>'savePois','version'=>$version,'pois'=>'[]'])['status']===409);
$params['version']=$after['version'];check('reopening on the current version allows saving',$request($id,$params)['status']===200);
// Two genuinely separate PHP/DB connections start from the same saved version.
$params['version']=$state()['body']['version'];$params['id']=$id;$params['crops']=json_encode([['ratio'=>'1:1','ax'=>.7,'ay'=>.5,'scale'=>1.5]]);
$children=[];
for($i=0;$i<2;$i++){
 $process=proc_open([PHP_BINARY,'-d','zend.exception_ignore_args=1',__FILE__,'--child',json_encode($params)], [['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 if(!is_resource($process))throw new RuntimeException('Cannot start concurrent test');fclose($pipes[0]);$children[]=[$process,$pipes];
}
$statuses=[];foreach($children as[$process,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException($error);$statuses[]=json_decode($output,true,512,JSON_THROW_ON_ERROR)['status'];}
sort($statuses);check('concurrent saves produce exactly one success and one conflict',$statuses===[200,409]);
Files::removeVariants($id);@unlink(Files::originalPath($row));$scope->remove($row);
echo "media crop conflicts: all checks passed\n";
