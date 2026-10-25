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

    /** @return list<array{node: int, lang: string, label: string}> title, description or texts, one entry per page */
    public static function everywhere(Scope $scope, string $term): array
    {
        $out = [];
        foreach ($scope->rows(__DIR__ . '/PageSearch.everywhere.sql', ['term' => self::like($term)])->looseValues() as $row) {
            $out[(int) $row['ps_node_id']] ??= [
                'node' => (int) $row['ps_node_id'],
                'lang' => (string) $row['ps_lang'],
                'label' => trim(html_entity_decode(strip_tags((string) $row['ps_label']), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            ];
        }
        return array_values($out);
    }

    /** @return list<array{node: int, page: int, label: string, file: string, type: string}> */
    public static function media(Scope $scope, string $term): array
    {
        return array_map(static fn(array $row): array => [
            'node' => (int) $row['ps_node_id'],
            'page' => (int) $row['ps_page_id'],
            'label' => (string) $row['ps_label'],
            'file' => (string) $row['ps_file'],
            'type' => (string) $row['ps_type'],
        ], $scope->rows(__DIR__ . '/PageSearch.media.sql', ['term' => self::like($term)])->looseValues());
    }

    private static function like(string $term): string
    {
        return '%' . addcslashes(mb_strtolower(trim($term)), '%_\\') . '%';
    }
}
