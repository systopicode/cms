<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\SiteOfNode;

use Systopic\Db\Scope;
use Systopic\Cms\Pages\Sites\SiteRegistry;

/**
 * The site node a node belongs to — `SiteOfNode.sql`. NULL is the default
 * site. Uncached here: `Pages\Sites\NodeSite` keeps the answers per request
 * and forgets them when the tree changes.
 */
final class SiteOfNode
{
    /** @param bool $skipSelf start at the parent: the site a node would get under its parent */
    public static function of(Scope $scope, int $nodeId, bool $skipSelf = false): ?int
    {
        if ($nodeId <= 0) {
            return null;
        }
        $rows = $scope->rows(__DIR__ . '/SiteOfNode.sql', [
            'id' => $nodeId, 'type' => SiteRegistry::NODE_TYPE, 'skipSelf' => $skipSelf ? 1 : 0,
        ], cache: false);
        $id = $rows->looseValues()[0]['chain_site_node_id'] ?? null;
        return $id === null ? null : (int) $id;
    }
}
