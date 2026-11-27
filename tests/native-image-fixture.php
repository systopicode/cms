<?php
/** Native browser output, production variant server, disposable files; no project DB. */
declare(strict_types=1);
namespace Systopic\System\Queries\MediaCrops {
    final class MediaCrops {
        public static ?CropSet $set = null;
        public static function one(...$args): ?CropSet { return self::$set; }
    }
}
namespace {
require dirname(__DIR__, 2) . '/system/tests/bootstrap.php';
use Systopic\System\Media\{Variants, Ratio, CropBox, VariantServer};
use Systopic\System\Queries\MediaCrops\{CropSet, MediaCrops};
use Systopic\System\Tables\Medias\{Model, Picture, Files};
class fs { public static string $siteRoot = '/private/tmp/fluxo-native-image-fixture/'; public static function toNative($path) { return $path; } }
class http { public static string $root = '/'; }
\Systopic\Db\Scope::setDefault(new \Systopic\Db\Scope());
$row = new Model();
$row->id = 42; $row->filename = 'native.png'; $row->mimetype = 'image/png'; $row->label = 'Native responsive image';
$row->width = $row->masterWidth = 1600; $row->height = $row->masterHeight = 1000;
$crop = \Systopic\System\Tables\MediaCrops\Operator::put(null, $row, new CropBox(Ratio::of('9:16'), .3, .5, 1.2));
$set = new CropSet($row, [$crop]); MediaCrops::$set = $set;
$original = Files::originalPath($row);
if (!is_file($original)) {
    @mkdir(dirname($original), 0777, true);
    $image = new \Imagick(); $image->newImage(1600, 1000, new \ImagickPixel('#216568'), 'png');
    $draw = new \ImagickDraw(); $draw->setFillColor('#e1b351'); $draw->circle(650, 430, 650, 180);
    $draw->setFillColor('#173744'); $draw->polygon([['x'=>0,'y'=>1000],['x'=>1000,'y'=>450],['x'=>1600,'y'=>1000]]);
    $image->drawImage($draw); $image->writeImage($original);
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$folder = dirname(Files::variantPath(42, 'native.png', 'master', 'webp'));
if ($path === '/reset') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    foreach (glob($folder . '/native--*.webp') ?: [] as $file) unlink($file);
    Picture::forgetFiles(); header('Content-Type: application/json'); echo '{"cold":true}'; exit;
}
if ($path === '/files') {
    $files = [];
    foreach (glob($folder . '/native--*.webp') ?: [] as $file) $files[basename($file)] = ['bytes'=>filesize($file), 'pixels'=>array_slice(getimagesize($file), 0, 2)];
    header('Content-Type: application/json'); echo json_encode($files); exit;
}
if (str_starts_with($path, '/var/formats/') && VariantServer::handle(ltrim($path, '/'))) exit;
if ($path === '/favicon.ico') { http_response_code(204); exit; }
header('Content-Type: text/html; charset=utf-8');
$picture = new Picture($set, Ratio::of('16:9'));
$master = new Picture($set);
?>
<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Native Bilder ohne JavaScript</title>
<style>
body{margin:16px;font:16px system-ui;color:#173744;background:#edf2f3}main{max-width:960px;margin:auto}h1{font-size:24px}.hero img{display:block;width:100%;height:auto;border-radius:10px}a{display:inline-block;padding:12px;background:#fcb60c;color:#173744;border-radius:6px;margin-block:16px}.master{width:min(100%,320px);height:auto;display:block}.spacer{height:6500px}.below{width:min(100%,500px);height:auto;display:block}.containerFallback{width:min(100%,400px);height:200px;display:block}
</style>
<main><h1>Native Bilder ohne JavaScript</h1><p>Desktop 16:9 · Tablet 1:1 · Smartphone 9:16</p>
<div class="hero"><?= $picture->toPictureHtml(['(max-width:560px)'=>'9:16', '(max-width:960px)'=>'1:1'], ['id'=>'hero','loading'=>'eager','fetchpriority'=>'high','sizes'=>'(max-width:992px) calc(100vw - 32px), 960px']) ?></div>
<a href="#later">Zum späteren Bild</a><p>Master mit nativer Auflösungswahl</p>
<?= $master->toImgHtml(['id'=>'master', 'class'=>'master', 'sizes'=>'(max-width:352px) calc(100vw - 32px), 320px']) ?>
<p>Einheitliche Picture-Ausgabe ohne Gerätewechsel</p><?= $master->toPictureHtml([], ['id'=>'plainPicture','class'=>'master','sizes'=>'(max-width:352px) calc(100vw - 32px), 320px']) ?>
<?php if (isset($_GET['auto'])): ?><p>Container ohne Erweiterung: responsiver Master</p><?= $master->toAutoHtml(['id'=>'containerFallback','class'=>'containerFallback','sizes'=>'(max-width:432px) calc(100vw - 32px), 400px']) ?><?php endif; ?>
<div class="spacer"></div><section id="later"><h2>Erst beim Scrollen benötigt</h2>
<?= (new Picture($set, Ratio::of('4:3')))->toImgHtml(['id'=>'below','class'=>'below','sizes'=>'(max-width:532px) calc(100vw - 32px), 500px']) ?></section></main></html>
<?php }
