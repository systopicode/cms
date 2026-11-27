<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/lib/class/fs/loader/fsLoader.php';
// Minimal file-discovery adapter; parsing/scoping uses the production loader.
class fsFiles extends ArrayIterator {
 static function create():self { return new self(); }
 function import($path,$recursive=false):self { $this[]=(object)['name'=>'site.css','href'=>'/css/site.css','filepath'=>$path,'ext'=>'css','exists'=>true,'contents'=>file_get_contents($path)];return $this; }
 function filter($pattern):self { return $this; }
 function sort($compare):self { return $this; }
}
// The public layout owns <main>; the CMS renders just its page template.
$css='main { padding:20px; } main img { width:100%; height:auto; }
.first { display:flex; gap:20px; } .visual { width:40%; flex:none; }
.visual a { display:block; position:relative; padding-bottom:66.666667%; }
.visual img { position:absolute; top:0; left:0; }
/* cms-preview: mobile */ @media only screen and (max-width:768px) {
 .first { flex-direction:column; } .visual { width:100%; }
}';
$img='data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"><rect width="1200" height="800" fill="#39878a"/></svg>');
$markup='<div class="first"><div class="visual"><a><picture><img src="'.$img.'" width="1200" height="800" alt="Test image"></picture></a><p class="caption">Caption below the picture</p></div><div class="text"><h1>Exhibition</h1><p>Text must stay separate from the picture.</p></div></div>';
$file=tempnam(sys_get_temp_dir(),'fluxo-preview-css-');file_put_contents($file,$css);
try { $scoped=fsLoader::includeCSSFilesScoped($file,'article.cmsContent',preview:true); }
finally { unlink($file); }
echo json_encode(['css'=>$css,'previewCss'=>fsLoader::previewResponsiveCss($css),'scopedCss'=>$scoped,'markup'=>$markup],JSON_THROW_ON_ERROR);
