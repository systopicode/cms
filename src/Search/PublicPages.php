<?php
declare(strict_types=1);

namespace Systopic\Cms\Search;

use Systopic\Cms\Queries\Params;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Tables\Pages\{Page,Languages};
use Systopic\Db\Scope;
use Systopic\System\Auth\Identity;
use Systopic\System\Config\Config;

/** The public projection, independent of an editor's session and preview. */
final class PublicPages
{
    public static function params(string $lang): Params
    {
        return new Params(lang: $lang, root: (string) \http::$root, siteUrl: (string) \http::$siteUrl,
            nodeType: 'page', readAcls: defined('PAGE_ACL') && PAGE_ACL
                ? Identity::anonymous(Scope::default())->readAclIds : null);
    }

    public static function siteIsPublic(): bool { return !defined('PAGE_IS_PUBLIC') || (bool) PAGE_IS_PUBLIC; }

    public static function noIndexEnvironment(): bool
    {
        return defined('STAGE') && strtoupper((string) STAGE) !== 'LIVE';
    }

    /** A second place for the same content points search engines at its public original. */
    public static function canonical(Page $page): Page
    {
        if (!$page->isAlias) return $page;
        $original = Page::byId(Scope::default(), self::params($page->language), $page->contentNodeId);
        return $original?->meta->published && !str_contains((string) $original->href, '#')
            ? $original : $page;
    }

    /** @return list<Page> */
    public static function all(): array
    {
        if (!self::siteIsPublic()) return [];
        $scope = Scope::default();
        $root = Config::current()?->site()?->root ?? 0;
        $result = [];
        $languages = [Languages::default(), ...array_keys(Languages::activated())];
        foreach (array_unique($languages) as $lang) {
            $params = self::params($lang);
            $visit = function(Page $page) use (&$visit, &$result): void {
                $url = (string) $page->href;
                if ($page->meta->listed && self::canonical($page)->id === $page->id && $page->redirectsTo === null && !str_contains($url, '#')
                    && preg_match('~^https?://~i', $url)) $result[$url] ??= $page;
                foreach ($page->children as $child) $visit($child);
            };
            foreach (PageTreeLevel::under($scope, $params, [$root])->nodes() as $node) $visit(Page::of($scope, $params, $node));
        }
        return array_values($result);
    }
}
