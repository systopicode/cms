<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageDetail;

use Systopic\Db\Scope;
use Systopic\Cms\Queries\PageTree\PageTreeLevel;
use Systopic\Cms\Queries\Params;

/**
 * One page, whole — every column of node and page, one statement.
 *
 * The query behind `Tables\Pages\Page::load()`. The level queries are lean
 * on purpose (a tree needs a label and a flag); the editor needs all of it.
 * Through the scope's identity map, so a node the tree already holds comes
 * back as the same object, completed with the columns missing so far.
 */
final class PageDetail
{
    /** The node as a level of its own — empty when there is no such node. */
    public static function level(Scope $scope, Params $params, int $nodeId): PageTreeLevel
    {
        return PageTreeLevel::ofFile($scope, $params, __DIR__ . '/PageDetail.sql', ['nodeId' => $nodeId]);
    }
}
