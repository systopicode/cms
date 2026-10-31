<?php
require_once __DIR__ . '/../../system/lib/class/media/format/mediaFormats.php';
// Render the production markup fragment without database or image processing.
$source = file_get_contents(__DIR__ . '/../../system/lib/class/media/editor/mediaEditor.php');
$start = strpos($source, '$fixedRatio =');
$end = strpos($source, '\n\t\treturn array(', $start);
if ($end === false) $end = strpos($source, 'return array(', $start);
$image_format = match ($argv[1] ?? 'fixed') {
 'free' => ['megapixels'=>3,'aspect_ratios'=>[]],
 'ratio' => ['megapixels'=>3,'aspect_ratio'=>'3:2','aspect_ratios'=>[]],
 default => ['width'=>1200,'height'=>800,'aspect_ratios'=>[]],
};
$tuneJson='{}';$src_width=900;$src_height=600;
$cnv_width=360; $cnv_height=240;
$alias_width=2160; $alias_height=1440; $margin_left=-900; $margin_top=-600;
$svg='<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><defs><pattern id="p" width="90" height="90" patternUnits="userSpaceOnUse"><rect width="90" height="90" fill="#365e70"/><rect width="45" height="45" fill="#f6bb40"/><circle cx="65" cy="65" r="18" fill="#e7eaef"/></pattern></defs><rect width="900" height="600" fill="url(#p)"/></svg>';
$src_public_filename='data:image/svg+xml;base64,'.base64_encode($svg);
eval(substr($source,$start,$end-$start));
echo $html;
