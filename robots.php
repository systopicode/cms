<?php
use Systopic\Cms\Search\PublicPages;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
if (PublicPages::noIndexEnvironment() || !PublicPages::siteIsPublic()) {
    header('X-Robots-Tag: noindex, nofollow');
    echo "User-agent: *\nDisallow: /\n";
} else {
    // Keep deliberate project rules, including separate search/training policies.
    $source = class_exists('fs') ? fs::toNative((string) fs::$siteRoot . 'robots.txt') : '';
    $rules = $source !== '' && is_file($source) ? (string) file_get_contents($source) : '';
    $rules = preg_replace('/^\s*Sitemap\s*:.*$/mi', '', $rules) ?? '';
    if (trim($rules) !== '') echo trim($rules) . "\n\n";
    echo "User-agent: *\n";
    echo 'Disallow: ' . http::$root . "admin/\n";
    echo 'Disallow: ' . http::$root . "auth/\n";
    echo 'Disallow: ' . http::$root . "cgi-bin/\n";
    echo 'Sitemap: ' . rtrim((string) http::$siteUrl, '/') . "/sitemap.xml\n";
}
