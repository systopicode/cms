<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\TrashedPages;

use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * The tops of the trashed branches — `TrashedPages.sql`.
 */
final class TrashedPages
{
    /** @return array<int, array{node: Node, pages: list<PageRow>}> by node id */
    public static function of(Scope $scope): array
    {
        $trashed = [];
        foreach ($scope->rows(__DIR__ . '/TrashedPages.sql', [])->records() as $record) {
            $node = $record[Node::class] ?? null;
            $page = $record[PageRow::class] ?? null;
            if ($node === null) {
                continue;
            }
            $trashed[(int) $node->id]['node'] = $node;
            $trashed[(int) $node->id]['pages'] ??= [];
            if ($page !== null) {
                $trashed[(int) $node->id]['pages'][] = $page;
            }
        }
        return $trashed;
    }
}
