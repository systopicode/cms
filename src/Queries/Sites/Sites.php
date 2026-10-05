<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Sites;

use Systopic\Db\Result\Completion;
use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Sites\Model as Site;

/**
 * Every site of the installation — `Sites.sql`. The lookups by id, node and
 * name and the boot into the config live in `Pages\Sites\SiteRegistry`.
 */
final class Sites
{
    /** @return list<Site> the default site first, the others in tree order */
    public static function all(Scope $scope): array
    {
        Registry::register(Site::class);
        return $scope->rows(
            __DIR__ . '/Sites.sql', [], Completion::Group,
            // the table is new: created on first use in an installation
            provisioner: new Provisioner($scope->connection),
        )->all(Site::class);
    }

    /** The node id of a site's first page (NULL = the default site's topmost level), NULL when it has none. */
    public static function firstPageOf(Scope $scope, ?int $siteNodeId): ?int
    {
        Registry::register(Node::class);
        return $scope->rows(__DIR__ . '/Sites.firstPage.sql', ['site' => $siteNodeId])->first(Node::class)?->id;
    }
}
