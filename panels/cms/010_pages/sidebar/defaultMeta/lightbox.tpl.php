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
$title = $meta('title', 'PUBLIC_PAGE_NAME', 'fluxo');
$separator = $meta('titleSeparator', 'PAGE_TITLE_SEPERATOR', '|');
$robots = $meta('robots', 'META_ROBOTS', 'index, follow');
$image = $meta('shareImage', 'META_SHARE_IMAGE');
$themeImage = $image === '' ? \Systopic\System\Settings\ThemeAssets::projectHref('share') : null;
if ($image === '') $image = 'img/share_default.jpg';
$imageUrl = $themeImage ?? (preg_match('~^https?://~i', $image) ? $image : rtrim((string) http::$siteUrl, '/') . '/' . ltrim($image, '/'));
$pages = $this->parent->parent;
$settings = http::$root . 'cms/settings/pagesettings/';
$configSite = \Systopic\System\Config\Config::current()?->site();
$siteRow = $configSite ? \Systopic\Cms\Pages\Sites\SiteRegistry::byName($pages->scope, $configSite->name) : null;
$siteEdit = $siteRow === null ? null : $pages->href . ($siteRow->isDefault ? '?site=default' : '?id=' . $siteRow->relationId('node'));
$source = static function (string $key, string $constant) use ($configSite, $siteEdit, $settings, $e): void {
    $own = $configSite?->meta($key);
    $label = ['title'=>'Site name', 'titleSeparator'=>'Title separator', 'description'=>'Description', 'shareImage'=>'Share image', 'robots'=>'Robots'][$key] ?? $key;
    if ($key === 'shareImage' && (!is_string($own) || $own === '') && (!defined($constant) || constant($constant) === '')) {
        echo '<p class="metaHint">Share image: Theme · <a class="ajax cmsTextLink" href="' . $e(http::$root . 'cms/settings/theme/') . '">Edit theme share image</a></p>';
        return;
    }
    if (is_string($own) && $own !== '') {
        echo '<p class="metaHint">' . $e($label) . ' from site: ' . $e($configSite->name);
        if ($siteEdit !== null) echo ' · <a class="ajax cmsTextLink" href="' . $e($siteEdit) . '">Edit site defaults</a>';
        else echo ' · configured outside the site editor';
    } elseif (defined($constant)) {
        echo '<p class="metaHint">' . $e($label) . ' from global Page Settings · <a class="ajax cmsTextLink" href="' . $e($settings) . '">Edit global defaults</a>';
    } else {
        echo '<p class="metaHint">' . $e($label) . ': built-in fallback · <a class="ajax cmsTextLink" href="' . $e($settings) . '">Set global defaults</a>';
    }
    echo '</p>';
};
?>
<div class="cmslightbox cms metaDefaults" data-transient-info>
	<section>
		<header>
			<h1>Site defaults</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<div class="metaDefault">
				<h2>Description <span><?= mb_strlen($description) ?> / 160</span></h2><?php $source('description', 'META_DESCRIPTION'); ?>
				<p class="metaHint">Used when the field is empty and the page has no sentence yet.</p>
				<p class="metaPreview"><?= $description !== '' ? $e($description) : '—' ?></p>
			</div>
			<div class="metaDefault">
				<h2>Share title</h2><?php $source('title', 'PUBLIC_PAGE_NAME'); $source('titleSeparator', 'PAGE_TITLE_SEPERATOR'); ?>
				<p class="metaHint">The page title comes first.</p>
				<p class="metaPreview"><span class="metaVar">{page title}</span> <?= $e($separator . ' ' . $title) ?></p>
			</div>
			<div class="metaDefault">
				<h2>Share image</h2><?php $source('shareImage', 'META_SHARE_IMAGE'); ?>
				<p class="metaHint">Used when this page has none. Best size 1200×630.</p>
				<figure class="metaPreview">
					<img src="<?= $e($imageUrl) ?>" alt="">
					<figcaption><?= $e($image) ?></figcaption>
				</figure>
			</div>
			<div class="metaDefault">
				<h2>Robots</h2><?php $source('robots', 'META_ROBOTS'); ?>
				<p class="metaPreview"><?= $e($robots) ?></p>
			</div>
		</main>
	</section>
</div>
