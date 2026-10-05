<?php

declare(strict_types=1);

namespace Systopic\Cms\Pages\Router;

use Systopic\System\Config\Config;
use Systopic\Db\Scope;
use Systopic\Cms\Pages\Route\Route;
use Systopic\Cms\Queries\RewritepathLookup\RewritepathLookup;
use Systopic\Cms\Queries\Sites\Sites;
use Systopic\Cms\Tables\Pages\Page;
use Systopic\Cms\Tables\Rewritepaths\Counter;

/**
 * Turns the request URL into a page id + language.
 *
 * This is the resolution step the legacy site.php did inline, lifted out so
 * both the dispatcher (which has to know whether a page exists before choosing
 * site.php or app.php) and site.php itself can use it. appRequest already
 * declares `$id` / `$lang` as "resolved by caller" — this is that caller.
 *
 * Multi-site (config/ `sites`): the HOST has already chosen the site
 * (Config::resolveSite() in sys::init). Paths are unique per site, so the
 * lookup is confined to it — every site has its own '' (home) and its own
 * 'kontakt'. A path that exists in another site sends the client to that
 * site's host with a 301; a site whose branch has no '' path yet answers its
 * empty path with the branch root.
 *
 * Order, unchanged from the legacy apart from the site:
 *
 *  1. An explicit ?id= wins over everything (CMS preview links).
 *  2. Otherwise the path is looked up in cms_rewritepaths, within the site.
 *     A non-primary hit redirects to the primary URL rather than serving a
 *     duplicate.
 *     2b. No hit, no trailing slash: the last segment is an action
 *     (/projekte/filter), the page is the path before it.
 *  3. A trailing numeric segment addresses an alias node (/kuenstler/123/).
 *  4. The path belongs to another site → 301 to that site's host.
 *  5. No hit at all: the empty path falls back to the site's root page, or
 *     to the first page on the root level.
 */
final class UrlResolver
{
    /**
     * Fills app::request()->id / ->lang / ->pageResolutionType.
     * Idempotent: a second call is a no-op once an id has been resolved.
     */
    public static function resolve(): void
    {
        $request = \app::request();

        if ($request->id !== null && $request->id !== '') {
            return;
        }

        $request->lang ??= \http::params('lang') ?: null;

        // 1. explicit id
        $paramId = \http::params('id', false);
        if ($paramId) {
            $request->id                 = $paramId;
            $request->pageResolutionType = 'id';
            return;
        }

        [$rewritePath, $alias] = self::splitPath($request->rawpath);

        // The site's node id, NULL for the default site. Without config/
        // there are no sites: ANY_SITE asks over the whole installation,
        // exactly as before.
        $config     = Config::current();
        $site       = $config?->site();
        $siteNodeId = $config === null ? RewritepathLookup::ANY_SITE : $site?->root;

        // 2. rewritepath lookup, within the site
        if (self::resolveRewritepath($request, $rewritePath, $alias, $siteNodeId)) {
            return;
        }

        // 2b. action suffix. A path without a trailing slash carries an action
        // in its last segment (appRequest::parsePathString()): /projekte/filter
        // is page "projekte" with action "filter" — the url tag::toSearchHtml()
        // builds, for one. The legacy router resolved the page from the path
        // without that segment; looking up the whole path finds nothing and
        // hands the request to the panel app instead.
        //
        // Only with a page path left over: '/cms' must not turn into the home
        // page (rewritepath '') with the action 'cms'.
        $action = (string) $request->action;
        if (
            $action !== ''
            && !str_ends_with($request->rawpath, '/')
            && str_ends_with($rewritePath, '/' . $action)
            && self::resolveRewritepath($request, substr($rewritePath, 0, -strlen($action) - 1), false, $siteNodeId)
        ) {
            return;
        }

        // 4. the path is known — in another site. One address per page: send
        // the client to the host that owns it.
        if ($config !== null && $rewritePath !== '' && self::redirectToOwningSite($request, $rewritePath, $alias)) {
            return;
        }

        // 5. fallback — ONLY for the empty path.
        //
        // The legacy applied this fallback to any unmatched path, which was
        // harmless because it ran inside site.php, after the dispatcher had
        // already decided. Here resolution runs BEFORE that decision, so an
        // unconditional fallback would resolve every panel URL (/cms/pages/,
        // /auth/, …) to some page and hand the request to site.php. A path
        // that matches no rewritepath must stay unresolved and let the panel
        // tree have it.
        if ($rewritePath !== '') {
            return;
        }

        // No '' path: the first page is home — of the site's branch (the
        // site's node is no page, it has no record to render), or of the
        // topmost level. The tree root above that is a node without a record
        // and no page's parent.
        $branch = $site?->root;
        $first = Sites::firstPageOf(Scope::default(), $branch);
        if ($first !== null) {
            $request->id                 = $first;
            $request->pageResolutionType = $branch !== null ? 'siteFirstPage' : 'rootFallback';
        }
    }

    /**
     * Splits the raw path into the rewritepath and an optional alias id.
     *
     * "/kuenstler/123/" → ['kuenstler', 123]
     * "/galerie/"       → ['galerie', false]
     * "/"               → ['', false]         (the home page's rewritepath is '')
     *
     * @return array{0: string, 1: int|false}
     */
    private static function splitPath(string $rawPath): array
    {
        $path  = trim($rawPath, '/');
        $alias = false;

        if ($path !== '' && preg_match('~(?:^|/)(\d+)$~', $path, $match)) {
            $alias = (int) $match[1];
            $path  = rtrim(substr($path, 0, -strlen($match[1])), '/');
        }

        return [$path, $alias];
    }

    /**
     * Looks $rewritePath up in cms_rewritepaths and fills the request from it.
     *
     * $siteNodeId confines the lookup to one site (its node id, NULL for the
     * default site); RewritepathLookup::ANY_SITE asks everywhere.
     *
     * True when the path is known — resolved, or redirected to its primary
     * url (2a) — false when there is no such rewritepath.
     */
    private static function resolveRewritepath(\appRequest $request, string $rewritePath, int|false $alias, ?int $siteNodeId): bool
    {
        $scope = Scope::default();
        $path = RewritepathLookup::resolve($scope, $rewritePath, $siteNodeId);
        $nodeId = $path?->page?->relationId('node');
        if ($nodeId === null) {
            return false;
        }
        $lang = (string) $path->page->languageIso;

        if (!$path->isPrimary) {
            // 2a. duplicate URL — send the client to the canonical one
            $canonical = Page::byId($scope, Route::paramsFor($lang), $nodeId);
            if ($canonical !== null) {
                \http::redirect($canonical->link);
                return true;
            }
        }

        Counter::hit($scope, $path, defined('STAGE') && STAGE === 'LIVE');

        $request->id                 = $alias !== false ? $alias : $nodeId;
        $request->lang               = $lang;
        $request->pageResolutionType = $alias !== false ? 'alias' : 'rewritepath';
        return true;
    }

    /**
     * The path exists, but in another site: 301 to that site's host.
     *
     * When the owning site has no host of its own on this instance there is
     * nowhere to send the client — then the page is served here and the
     * missing host entry is logged. Returns true when the request is dealt
     * with either way, false when no other site knows the path.
     */
    private static function redirectToOwningSite(\appRequest $request, string $rewritePath, int|false $alias): bool
    {
        $path = RewritepathLookup::resolve(Scope::default(), $rewritePath, RewritepathLookup::ANY_SITE);
        if ($path === null) {
            return false;
        }
        $config = Config::current();
        // 0 is the first spelling of the default site (before the column was nullable)
        $ownerId = $path->siteNodeId ?: null;
        if ($ownerId === ($config->site()?->root)) {
            return false; // same site — the lookup above already said no
        }
        $owner = $ownerId === null ? $config->sites->default() : ($config->sites->byRoot($ownerId) ?? $config->sites->default());

        $canonical = $owner->firstEntry();
        if ($canonical === null || $canonical === '*') {
            \logroute('UrlResolver: path belongs to site without a host on this instance — serving it here', [
                'path' => $rewritePath, 'site' => $owner->name, 'instance' => $config->instance->name,
            ]);
            return self::resolveRewritepath($request, $rewritePath, $alias, $ownerId);
        }

        $target = $config->baseUrlFor($owner) . $rewritePath . '/' . ($alias !== false ? $alias . '/' : '');
        \logroute('UrlResolver: 301 to owning site', ['path' => $rewritePath, 'site' => $owner->name, 'target' => $target]);
        if (\http::ajax()) {
            \http::redirect($target);
            return true;
        }
        if (PHP_SAPI === 'cli') {
            // the CLI harness cannot see headers - say where the client would go
            echo "Location: $target
";
        }
        header('Location: ' . $target, true, 301);
        exit;
    }
}
