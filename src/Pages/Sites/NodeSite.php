<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Sites;

use Systopic\System\Config\Config;
use Systopic\System\Config\Site;
use Systopic\Db\Scope;
use Systopic\Cms\Queries\SiteOfNode\SiteOfNode;
use Systopic\Cms\Tables\Rewritepaths\SiteAssignment;

/**
 * Which site a node belongs to — and the addresses that follow from it.
 *
 * A site is a node of type 'site' on the topmost level (see SiteRegistry). A
 * page belongs to the nearest such node above it; a page with none above it
 * belongs to the default site. The answer is a node id or NULL:
 *
 *   NULL   the default site
 *   <id>   the node of type 'site' the page hangs under
 *
 * `cms_rewritepaths.rewritepath_site_node_id` carries a copy per path, so a
 * path is unique per site and every site has its own '' (home). The copy is
 * never edited by hand: it is written with the path, rewritten for a branch
 * when it moves (rebuildBranch), and for everything by the maintenance panel
 * (rebuildAll).
 */
final class NodeSite
{
    /** @var array<int, ?int> node id => site node id, per request */
    private static array $siteNodeIds = [];

    /** The site node id a path of this node has to carry — NULL = default site. */
    public static function siteNodeIdOf(int $nodeId): ?int
    {
        if ($nodeId <= 0) {
            return null;
        }
        if (array_key_exists($nodeId, self::$siteNodeIds)) {
            return self::$siteNodeIds[$nodeId];
        }
        return self::$siteNodeIds[$nodeId] = SiteOfNode::of(Scope::default(), $nodeId);
    }

    /** The site of a node — null without config/. */
    public static function siteOf(int $nodeId): ?Site
    {
        return self::site(self::siteNodeIdOf($nodeId));
    }

    /** The site of a site node id (NULL = default site) — null without config/. */
    public static function site(?int $siteNodeId): ?Site
    {
        $sites = Config::current()?->sites;
        if ($sites === null) {
            return null;
        }
        return $siteNodeId === null ? $sites->default() : ($sites->byRoot($siteNodeId) ?? $sites->default());
    }

    /** The site node id of the site the request came in for — NULL = default site. */
    public static function currentSiteNodeId(): ?int
    {
        return Config::current()?->site()?->root;
    }

    /** True when the node lies in the site the request came in for. */
    public static function isCurrent(int $nodeId): bool
    {
        return Config::current() === null || self::siteNodeIdOf($nodeId) === self::currentSiteNodeId();
    }

    // ---------------------------------------------------------- addresses
    //
    // By site node id: a path carries the id of its site (written with it,
    // rewritten when its branch moves), so whoever holds the path need not
    // ask the tree — `Rewritepaths::$link` / `$href` pass it in. One tree
    // lookup per page was what a list of links cost before.

    /**
     * What goes in front of a rewritepath in a link: `http::$root` inside the
     * current site, the absolute root of the other site otherwise.
     */
    public static function linkRoot(?int $siteNodeId): string
    {
        $config = Config::current();
        if ($config === null || $siteNodeId === self::currentSiteNodeId()) {
            return (string) \http::$root;
        }
        $site = self::site($siteNodeId);
        return $site === null ? (string) \http::$root : $config->baseUrlFor($site);
    }

    /** What goes in front of a rewritepath in an absolute address (href, canonical, sitemap). */
    public static function hrefRoot(?int $siteNodeId): string
    {
        $config = Config::current();
        if ($config === null || $siteNodeId === self::currentSiteNodeId()) {
            return (string) \http::$siteUrl;
        }
        $site = self::site($siteNodeId);
        return $site === null ? (string) \http::$siteUrl : $config->baseUrlFor($site);
    }

    /** linkRoot() for a node — its site looked up in the tree. */
    public static function linkRootOf(int $nodeId): string
    {
        return self::linkRoot(Config::current() === null ? null : self::siteNodeIdOf($nodeId));
    }

    /** hrefRoot() for a node — its site looked up in the tree. */
    public static function hrefRootOf(int $nodeId): string
    {
        return self::hrefRoot(Config::current() === null ? null : self::siteNodeIdOf($nodeId));
    }

    // -------------------------------------------------------------- writes

    /**
     * After a branch moved: every path in it gets the site of its new place —
     * copied from the new parent, NULL for the default site.
     *
     * @return int the number of paths that changed
     */
    public static function rebuildBranch(int $nodeId): int
    {
        self::$siteNodeIds = [];
        $changed = SiteAssignment::branch(Scope::default(), $nodeId);
        self::$siteNodeIds = [];
        return $changed;
    }

    /**
     * Every path of the installation onto its site — the maintenance button.
     *
     * @return int the number of paths that changed
     */
    public static function rebuildAll(): int
    {
        self::$siteNodeIds = [];
        return SiteAssignment::all(Scope::default());
    }

    /** Forget what was computed — after the tree changed in this request. */
    public static function reset(): void
    {
        self::$siteNodeIds = [];
    }
}
