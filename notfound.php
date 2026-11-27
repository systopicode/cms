<?php
/** Unknown and unavailable pages share one response; no content or internal details leak. */
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
$language = \Systopic\Cms\Tables\Pages\Languages::default();
$german = str_starts_with($language, 'de');
$heading = $german ? 'Seite nicht gefunden' : 'Page not found';
$home = htmlspecialchars((string) http::$root, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Systopic\Cms\Tables\Pages\Languages::tag($language), ENT_QUOTES, 'UTF-8') ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>404 – <?= $heading ?></title></head>
<body><main><h1><?= $heading ?></h1><p><a href="<?= $home ?>"><?= $german ? 'Zur Startseite' : 'Go to the home page' ?></a></p></main></body>
</html>
