<?php

/**
 * A site without a single page yet (Frontend::notFound()) — a fresh project.
 * The first page is made in the cms: <root>/cms/pages/.
 */

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
$cmsUrl = htmlspecialchars(http::$root . 'cms/pages/', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex">
	<title>404 - this site has no pages yet</title>
	<style>
		body { margin: 0; font: 16px/1.5 system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; background: #f4f4f4; color: #333; }
		main { text-align: center; }
		h1 { font-size: 20px; font-weight: 600; }
		a { color: #4c6873; }
	</style>
</head>
<body>
	<main>
		<h1>404 - this site has no pages yet</h1>
		<p><a href="<?= $cmsUrl ?>">create the first page in the cms</a></p>
	</main>
</body>
</html>
