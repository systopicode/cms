<?php
declare(strict_types=1);
// Reuse real refs, crop rows and rendered picture files; all data are disposable.
$profile=$argv[1]??'wide';$argv[1]='--profile';ob_start();require __DIR__.'/image-device-fixture.php';ob_end_clean();
use Systopic\Cms\Pages\PreviewWidths;
$dir=sys_get_temp_dir().'/fluxo-project-queries-'.$profile.'/';@mkdir($dir.'css',0777,true);
$css=match($profile){
 'wide'=>"/* cms-preview: mobile */\n@media only screen and (max-width: 768px) { :root { --layout-device: mobile; } }",
 'range'=>"/* cms-preview: tablet 900px */\n@media screen and (40rem <= width <= 64rem) { :root { --layout-device: tablet; } }\n/* cms-preview: mobile */\n@media (width < 40rem) { :root { --layout-device: mobile; } }",
 default=>throw new RuntimeException('Unknown test profile')};
file_put_contents($dir.'css/x_responsive.css',$css);
file_put_contents($dir.'css/player.css','@media(max-width:600px){video{width:100%}}');
fs::$siteRoot=$dir;
$ref='media:169?format=master&desktop=16by9&tablet=1by1&mobile=9by16';
$image='<img class="garden" id="profile-image" src="'.htmlspecialchars($ref,ENT_QUOTES).'">';
$data=PreviewWidths::read($dir.'css');
$data['frontend']=$resolver->render($image);
$data['editor']=$resolver->renderForEditor($image);
$data['previewCss']=fsLoader::previewResponsiveCss(':root { --layout-device: desktop; font-size:24px; }'.$css.PreviewWidths::previewCss($dir.'css'));
echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
