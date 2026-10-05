<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\PageSearch;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\Cms\Tables\Pages\Model as PageRow;

/**
 * Pages whose label contains a word — the search field over the pylon tree.
 *
 * The query asks for what the result list shows: id, node, label, language.
 */
final class PageSearch
{
    /** @return list<PageRow> not trashed, in the given language, by label */
    public static function byLabel(Scope $scope, string $term, string $lang): array
    {
        if (trim($term) === '') {
            return [];
        }
        Registry::register(PageRow::class);
        Registry::register(Node::class);

        $rows = $scope->rows(__DIR__ . '/PageSearch.sql', ['term' => '%' . mb_strtolower($term) . '%', 'lang' => $lang]);
        return $rows->all(PageRow::class);
    }
}
