<?php
// Exercise the real action branch with in-memory repositories; no project DB or files.
namespace Systopic\System\Queries\MediaCrop { class MediaCrop { static function of($scope,$id,$name){ return [(object)['id'=>$id],null]; } } }
namespace Systopic\System\Tables\MediaCrops { class Operator { static function cropped($crop,$media,$format,$left,$top,$width,$height){return compact('format','left','top','width','height');} } }
namespace {
class http { static array $data=[]; static function get($name,$fallback=null){return self::$data[$name]??$fallback;} }
class MediaFiles { static array $saved=[],$dropped=[]; static function saveTune($id,$filename,$format,$tune){self::$saved[$format]=$tune;} static function dropFormatFile($id,$format,$filename){self::$dropped[]=$format;} }
$source=file_get_contents(__DIR__.'/../panels/admin/010_pages/editor/file/actions.php');$start=strpos($source,"case 'saveImageEdit':");$end=strpos($source,"case 'adjustImage':",$start);$branch=substr($source,$start,$end-$start);
$fixture=new class {public string $action='saveImageEdit';function run($branch){$row=(object)['id'=>7,'filename'=>'fixture.png'];$scope=new class{public array $saved=[];function save($crop){$this->saved[]=$crop;}};$pages=(object)['scope'=>$scope];$formatsOf=fn($all)=>$all?['tile','shareImage']:['tile'];eval('switch($this->action){'.$branch.'}');return $scope->saved;}};
http::$data=['left'=>.4,'top'=>.6,'width'=>.5,'height'=>.25,'brightness'=>125,'contrast'=>80,'saturation'=>75,'rotate'=>90,'flip'=>1,'apply_all'=>1];
$rows=$fixture->run($branch);if(count($rows)!==1 || $rows[0]['left']!==.4 || !isset(MediaFiles::$saved['shareImage']) || $rows[0]['format']!=='tile' || MediaFiles::$saved['tile']!==['b'=>125,'c'=>80,'s'=>75,'r'=>90,'f'=>1])throw new \RuntimeException('crop must stay on current format; only tune propagates');
http::$data['width']=-1;$before=MediaFiles::$saved;$rows=$fixture->run($branch);if($rows!==[] || MediaFiles::$saved!==$before || http_response_code()!==422)throw new \RuntimeException('invalid crop was written');
echo "PASS combined save action: geometry+tune/all formats, invalid crop rejected without writes\n";
}
