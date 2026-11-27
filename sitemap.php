<?php
use Systopic\Cms\Search\PublicPages;

p()->erase();
header('Content-Type: application/xml; charset=utf-8');
if (PublicPages::noIndexEnvironment() || !PublicPages::siteIsPublic()) header('X-Robots-Tag: noindex, nofollow');
if (!PublicPages::siteIsPublic()) http_response_code(403);
$e = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
foreach (PublicPages::all() as $page) {
    echo '<url><loc>' . $e((string) $page->href) . '</loc>';
    $modified = $page->row?->modifiedDate;
    if ($modified instanceof DateTimeInterface) echo '<lastmod>' . $modified->format(DATE_W3C) . '</lastmod>';
    foreach (app::site()->alternates($page) as $language => $url) echo '<xhtml:link rel="alternate" hreflang="' . $e($language) . '" href="' . $e($url) . '"/>';
    echo '</url>';
}
echo '</urlset>';
