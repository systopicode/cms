<?php
use Systopic\Cms\Search\PublicPages;
use Systopic\System\Config\Config;

p()->erase();
header('Content-Type: text/plain; charset=utf-8');
if (PublicPages::noIndexEnvironment() || !PublicPages::siteIsPublic()) header('X-Robots-Tag: noindex, nofollow');
if (!PublicPages::siteIsPublic()) { http_response_code(403); echo "Not public.\n"; return; }
$oneLine = static function(string $s): string {
    return str_replace(['[',']'], '', trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? ''));
};
$site = Config::current()?->site();
$title = (string) ($site?->meta('title') ?: (defined('PUBLIC_PAGE_NAME') ? PUBLIC_PAGE_NAME : $site?->name));
$description = (string) ($site?->meta('description') ?: (defined('META_DESCRIPTION') ? META_DESCRIPTION : ''));
echo '# ' . ($oneLine($title) ?: 'Website') . "\n\n";
if ($description !== '') echo '> ' . $oneLine($description) . "\n\n";
echo "Written by fluxo from the published pages of this website.\n\n## Pages\n\n";
foreach (PublicPages::all() as $page) {
    $record = app::site()->record($page);
    if ($record['name'] === '') continue;
    echo '- [' . $oneLine($record['name']) . '](' . str_replace(['(',')'], ['%28','%29'], $record['url']) . ')';
    if ($record['description'] !== '') echo ': ' . $oneLine($record['description']);
    echo "\n";
}
echo "\n## Optional\n\n- [Sitemap](" . rtrim((string) http::$siteUrl, '/') . "/sitemap.xml)\n";
