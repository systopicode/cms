<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\RewritepathIndex;

use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;
use Systopic\Cms\Tables\Rewritepaths\Model as Rewritepath;

/**
 * Rewrite paths across all pages — for maintenance, not for routing
 * (that is `RewritepathLookup`).
 */
final class RewritepathIndex
{
    /** @return list<array{path: Rewritepath, page: ?PageRow, node: ?Node}> */
    public static function list(Scope $scope): array
    {
        $list = [];
        foreach ($scope->rows(__DIR__ . '/RewritepathIndex.sql', [])->records() as $record) {
            if (($path = $record[Rewritepath::class] ?? null) !== null) {
                $list[] = ['path' => $path, 'page' => $record[PageRow::class] ?? null, 'node' => $record[Node::class] ?? null];
            }
        }
        return $list;
    }

    public static function byId(Scope $scope, int $id): ?Rewritepath
    {
        foreach (self::list($scope) as $entry) {
            if ((int) $entry['path']->id === $id) {
                return $entry['path'];
            }
        }
        return null;
    }

    /** @return list<Rewritepath> every path there is */
    public static function all(Scope $scope): array
    {
        return $scope->rows(__DIR__ . '/RewritepathIndex.all.sql', [])->all(Rewritepath::class);
    }

    /**
     * @param list<int> $nodeIds
     * @return list<Rewritepath> the paths of the pages of these nodes
     */
    public static function ofNodes(Scope $scope, array $nodeIds): array
    {
        return $nodeIds === [] ? [] : $scope->rows(__DIR__ . '/RewritepathIndex.ofNodes.sql', ['nodeIds' => array_values($nodeIds)])->all(Rewritepath::class);
    }

    /** @return list<Rewritepath> the paths of pages in the trash */
    public static function trashed(Scope $scope): array
    {
        return $scope->rows(__DIR__ . '/RewritepathIndex.trashed.sql', [])->all(Rewritepath::class);
    }

    /** @return list<array{nodeId: int, lang: string}> every page that should have an automatic path */
    public static function pages(Scope $scope): array
    {
        $pages = [];
        foreach ($scope->rows(__DIR__ . '/RewritepathIndex.pages.sql', [])->records() as $record) {
            $page = $record[PageRow::class] ?? null;
            $node = $record[Node::class] ?? null;
            if ($page !== null && $node !== null) {
                $pages[] = ['nodeId' => (int) $node->id, 'lang' => (string) $page->languageIso];
            }
        }
        return $pages;
    }
}
