<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\RewritepathLookup;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;

/**
 * Who owns a path.
 *
 * A path is unique within a SITE, across languages: two pages of one site
 * cannot answer the same URL. "Unique" therefore means "no *other* page of
 * this site has it"; the page itself may have it any number of times, and a
 * page of another site — another host — may have it too.
 *
 * The site is its node id, NULL for the default site. ANY_SITE asks over the
 * whole installation.
 */
final class RewritepathLookup
{
    /** "In every site" — NULL is a site (the default one), so it cannot say that. */
    public const ANY_SITE = -1;

    public static function isUnique(Scope $scope, string $path, ?int $exceptPageId = null, ?int $siteNodeId = self::ANY_SITE): bool
    {
        return self::blocker($scope, $path, $exceptPageId, $siteNodeId) === null;
    }

    /**
     * The path of another page that holds this string — the "barrier".
     *
     * `rewritepath_barrier_id` stores its id, so the sidebar can say which
     * page stands in the way of the ideal URL.
     */
    public static function blocker(Scope $scope, string $path, ?int $exceptPageId = null, ?int $siteNodeId = self::ANY_SITE): ?Rewritepath
    {
        Registry::register(Rewritepath::class);
        $any = $siteNodeId === self::ANY_SITE;
        $rows = $scope->rows(__DIR__ . '/RewritepathLookup.byString.sql', [
            'path'    => $path,
            'pageId'  => $exceptPageId ?? 0,
            'anySite' => $any ? 1 : 0,
            'site'    => $any ? null : $siteNodeId,
        ], provisioner: new Provisioner($scope->connection));
        return $rows->first(Rewritepath::class);
    }

    /**
     * The path a request asks for, with its page (`->page`: node, language).
     *
     * Within one site, or everywhere with ANY_SITE — then
     * `->siteNodeId` says whose path it is.
     */
    public static function resolve(Scope $scope, string $path, ?int $siteNodeId = self::ANY_SITE): ?Rewritepath
    {
        Registry::register(Rewritepath::class);
        Registry::register(PageRow::class);
        $any = $siteNodeId === self::ANY_SITE;
        return $scope->rows(__DIR__ . '/RewritepathLookup.resolve.sql', [
            'path'    => trim($path, '/'),
            'anySite' => $any ? 1 : 0,
            'site'    => $any ? null : $siteNodeId,
        ], provisioner: new Provisioner($scope->connection))->first(Rewritepath::class);
    }

    /**
     * The page a path belongs to — label, node, language, for the link in a
     * "not unique" message.
     */
    public static function pageOf(Scope $scope, int $rewritepathId): ?PageRow
    {
        Registry::register(PageRow::class);
        $rows = $scope->rows(__DIR__ . '/RewritepathLookup.pageOf.sql', ['id' => $rewritepathId]);
        return $rows->first(PageRow::class);
    }
}
