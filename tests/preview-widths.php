<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/system/tests/bootstrap.php';
use Systopic\Cms\Pages\PreviewWidths;
$dir=sys_get_temp_dir().'/fluxo-preview-widths-'.bin2hex(random_bytes(5));mkdir($dir);
$write=static function(string $css)use($dir):void {file_put_contents($dir.'/x_responsive.css',$css);};
try {
 file_put_contents($dir.'/player.css','@media(max-width:600px){video{width:100%}}');
 $write('/* cms-preview: mobile */ @media only screen and (max-width: 768px) {body{margin:0}}');
 $data=PreviewWidths::read($dir);
 check('named smartphone breakpoint may be above 640px',$data['widths']===['desktop'=>769,'tablet'=>null,'mobile'=>768]);
 check('picture keeps the exact project media query',$data['queries']===['mobile'=>'only screen and (max-width: 768px)']);
 $write('/* cms-preview: mobile */ @media (width < 40rem) {} /* cms-preview: tablet 900px */ @media (40rem <= width <= 64rem) {}');
 $data=PreviewWidths::read($dir);
 check('range syntax, rem and explicit preview sizes work',$data['widths']===['desktop'=>1025,'tablet'=>900,'mobile'=>639]);
 check('mobile is the first matching picture source',array_keys($data['queries'])===['mobile','tablet']);
 check('preview evaluates the same queries using CSS',str_contains(PreviewWidths::previewCss($dir),'@media (width < 40rem)'));
 $write('@media(max-width:768px){body{margin:0}}');
 check('unlabelled single breakpoint stays mobile and ignores player CSS',PreviewWidths::of($dir)===['desktop'=>769,'tablet'=>null,'mobile'=>768]);
 $write('/* @media(max-width:100px){} */ @media(max-width:560px){} @media(max-width:960px){}');
 check('legacy two-device projects retain their widths',PreviewWidths::of($dir)===['desktop'=>961,'tablet'=>960,'mobile'=>560]);
 $write('@media(min-width:561px){} @media(min-width:961px){}');
 check('legacy mobile-first projects retain their device choices',PreviewWidths::of($dir)===['desktop'=>961,'tablet'=>960,'mobile'=>560]);
 $write('body{margin:0}');check('project without responsive rules has no invented devices',PreviewWidths::of($dir)===['desktop'=>null,'tablet'=>null,'mobile'=>null]);
}finally {foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
