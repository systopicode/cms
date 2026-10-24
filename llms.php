<?php

use Systopic\System\Config\Config;
use Systopic\Db\Scope;
use Systopic\Cms\Pages\Route\Route;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Tables\Pages\Page;

/**
 * llms.txt for every website this system serves.
 *
 * The name, the description and the page list come from the site and from
 * the same pages the sitemap lists. A project does not write this file.
 */

p()->erase();
header('Content-Type: text/plain; charset=utf-8');

$lang = defined('DEFAULT_LANG') ? DEFAULT_LANG : (isset($_GET['lang']) ? (string) $_GET['lang'] : 'de');
$site = Config::current()?->site();

$title = siteText($site?->meta('title'), defined('PUBLIC_PAGE_NAME') ? (string) PUBLIC_PAGE_NAME : (string) ($site?->name ?? ''));
$description = siteText($site?->meta('description'), defined('META_DESCRIPTION') ? (string) META_DESCRIPTION : '');

$lines = [];
collectPages($lang, $site?->root, $lines);

echo '# ' . ($title !== '' ? $title : 'Website') . "\n\n";
if ($description !== '') {
	echo '> ' . oneLine($description) . "\n\n";
}
echo "Written by fluxo cms from the published pages of this website.\n\n";
echo "## Pages\n\n";
echo $lines === [] ? '' : implode("\n", $lines) . "\n";
echo "\n## Optional\n\n";
echo '- [Sitemap](' . rtrim((string) http::$siteUrl, '/') . "/sitemap.xml)\n";

/** A configured string, or the fallback when the site has none. */
function siteText(mixed $value, string $fallback): string {
	return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
}

function oneLine(string $text): string {
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
	return str_replace(['[', ']'], '', $text);
}

/** @param list<string> $lines */
function collectPages(string $lang, ?int $siteRoot, array &$lines): void {
	$scope = Scope::default();
	$params = Route::paramsFor($lang);
	foreach (PageTreeLevel::under($scope, $params, [$siteRoot ?? 0])->nodes() as $node) {
		collectPage(Page::of($scope, $params, $node), $lines);
	}
}

/** @param list<string> $lines */
function collectPage(Page $page, array &$lines): void {
	if ($page->meta->listed) {
		$name = oneLine((string) ($page->meta->shareTitle ?: $page->label->value));
		$href = (string) $page->href;
		if ($name !== '' && $href !== '') {
			$line = '- [' . $name . '](' . $href . ')';
			$note = oneLine((string) $page->meta->description);
			if ($note !== '') {
				$line .= ': ' . $note;
			}
			$lines[] = $line;
		}
	}
	foreach ($page->children as $child) {
		collectPage($child, $lines);
	}
}
