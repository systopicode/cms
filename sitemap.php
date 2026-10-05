<?php

use Systopic\System\Config\Config;
use Systopic\Db\Scope;
use Systopic\Cms\Pages\Route\Route;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Tables\Pages\Page;

p()->erase();
header("Content-type: text/xml");

if (defined('DEFAULT_LANG')) {
	$lang = DEFAULT_LANG;
} else if (isset($_GET['lang'])) {
	$lang = $_GET['lang'];
} else {
	$lang = 'de';
}

/**
 * One page and, below it, its children — the pages a search engine should
 * know: published, in the navigation, not redirecting, no alias, not
 * `noindex`, and visited on LIVE at least once (`livecount`). Children are
 * walked whether the page itself is listed or not.
 */
function printXML(Page $page, float $prio = 1.0): void {
	$path = $page->rewritepaths->primary;
	$use = $page->row !== null
		&& $page->node->type === 'page'
		&& $page->node->relationId('redirectNode') === null
		&& !$page->isAlias
		&& !str_contains((string) $page->meta->robots, 'noindex')
		&& $page->meta->published
		&& $page->meta->isNaviElement
		&& $path?->livecount !== 0;

	//PERMISSOIONS ABFRAGEN

	if ($use) {
		$modified = $page->row->modifiedDate;
		$w3c_date = date(DATE_W3C, $modified === null ? 0 : $modified->getTimestamp());
		$xml = '<url>';
		$xml .= "<loc>{$page->href}</loc>";
		$xml .= "<lastmod>$w3c_date</lastmod>";
		$xml .= "<priority>$prio</priority>";
		$xml .= '</url>';
		echo $xml;
	}

	foreach ($page->children as $child) {
		printXML($child, $prio - 0.1);
	}
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
// One sitemap per site (multi-site): a branch site lists the pages below its
// node, the default site the topmost level — the children of the tree root,
// which is a node without a record.
$scope = Scope::default();
$params = Route::paramsFor($lang);
$siteRoot = Config::current()?->site()?->root;
foreach (PageTreeLevel::under($scope, $params, [$siteRoot ?? 0])->nodes() as $node) {
	printXML(Page::of($scope, $params, $node));
}
echo '</urlset>';
