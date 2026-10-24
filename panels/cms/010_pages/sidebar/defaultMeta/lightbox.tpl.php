<?php

/**
 * The values an empty meta field falls back to. Site config first, then the
 * constants from settings, then the built-in default — the same order the
 * public page uses.
 */

$meta = static function (string $key, string $constant, string $default = ''): string {
	$value = \Systopic\System\Config\Config::current()?->site()?->meta($key);
	if (is_string($value) && $value !== '') {
		return $value;
	}
	return defined($constant) ? (string) constant($constant) : $default;
};
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$description = $meta('description', 'META_DESCRIPTION');
$title = $meta('title', 'PUBLIC_PAGE_NAME', 'fluxo cms');
$separator = $meta('titleSeparator', 'PAGE_TITLE_SEPERATOR', '|');
$robots = $meta('robots', 'META_ROBOTS', 'index, follow');
$image = $meta('shareImage', 'META_SHARE_IMAGE', 'img/share_default.jpg');
$imageUrl = preg_match('~^https?://~i', $image) ? $image : rtrim((string) http::$siteUrl, '/') . '/' . ltrim($image, '/');
$settings = http::$root . 'cms/settings/pagesettings/';
?>
<div class="cmslightbox cms metaDefaults">
	<section>
		<header>
			<h1>Default values</h1>
			<ul class="barmenu">
				<li><a class="ajax" href="../../"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<div class="metaDefault">
				<h2>Description <span><?= mb_strlen($description) ?> / 160</span></h2>
				<p class="hint">Used when the field is empty and the page has no sentence yet.</p>
				<p><?= $description !== '' ? $e($description) : '—' ?></p>
			</div>
			<div class="metaDefault">
				<h2>Share title</h2>
				<p class="hint">The page title comes first.</p>
				<p class="metaSample"><?= $e('Page title ' . $separator . ' ' . $title) ?></p>
			</div>
			<div class="metaDefault">
				<h2>Share image</h2>
				<p class="hint">Used when this page has none. Best size 1200×630.</p>
				<figure>
					<img src="<?= $e($imageUrl) ?>" alt="">
					<figcaption><?= $e($image) ?></figcaption>
				</figure>
			</div>
			<div class="metaDefault">
				<h2>Robots</h2>
				<p><?= $e($robots) ?></p>
			</div>
		</main>
		<footer>
			<a class="button submit ajax" href="<?= $e($settings) ?>">Edit defaults</a>
		</footer>
	</section>
</div>
