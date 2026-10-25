<?php

declare(strict_types=1);

namespace Systopic\Cms\Queries\Dashboard;

use Systopic\Db\Scope;

/**
 * What the dashboard widgets of cms/pages show — figures, page lists and the
 * newest media. Loose values only: the widgets print them, nothing is changed.
 */
final class Dashboard
{
    /** @return array{pages: int, hidden: int, nodescription: int, planned: int, week: int, media: int, noalt: int, tags: int, trashed: int} */
    public static function figures(Scope $scope): array
    {
        $row = $scope->rows(__DIR__ . '/Dashboard.live.sql', [])->looseValues()[0] ?? [];
        return [
            'pages'   => (int) ($row['dash_pages'] ?? 0),
            'hidden'  => (int) ($row['dash_hidden'] ?? 0),
            'nodescription' => (int) ($row['dash_nodescription'] ?? 0),
            'planned' => (int) ($row['dash_planned'] ?? 0),
            'week'    => (int) ($row['dash_week'] ?? 0),
            'media'   => (int) ($row['dash_media'] ?? 0),
            'noalt'   => (int) ($row['dash_noalt'] ?? 0),
            'tags'    => (int) ($row['dash_tags'] ?? 0),
            'trashed' => (int) ($row['dash_trashed'] ?? 0),
        ];
    }

    /**
     * One row per page (its newest language), newest change first.
     *
     * @param 'recent'|'nodescription'|'hidden'|'planned' $filter
     * @return list<array{id: int, label: string, published: bool, start: ?string, modified: ?string, user: string}>
     */
    public static function pages(Scope $scope, string $filter, int $limit): array
    {
        $out = [];
        foreach ($scope->rows(__DIR__ . '/Dashboard.pages.sql', ['filter' => $filter])->looseValues() as $row) {
            $id = (int) $row['dash_node_id'];
            if (isset($out[$id])) {
                continue;
            }
            $out[$id] = [
                'id'        => $id,
                'label'     => trim(strip_tags((string) $row['dash_label'])),
                'published' => (bool) $row['dash_published'],
                'start'     => $row['dash_start'] !== null ? (string) $row['dash_start'] : null,
                'modified'  => $row['dash_modified'] !== null ? (string) $row['dash_modified'] : null,
                'user'      => (string) ($row['dash_user'] ?? ''),
            ];
        }
        return array_slice(array_values($out), 0, $limit);
    }

    /** @return list<array{node: int, page: int, filename: string, type: string}> the newest media without a label */
    public static function unlabelled(Scope $scope): array
    {
        return array_map(static fn(array $row): array => [
            'node'     => (int) $row['dash_node_id'],
            'page'     => (int) $row['dash_page_id'],
            'filename' => (string) $row['dash_filename'],
            'type'     => (string) $row['dash_type'],
        ], $scope->rows(__DIR__ . '/Dashboard.unlabelled.sql', [])->looseValues());
    }

    /** @return list<array{node: int, page: int}> the newest images and videos */
    public static function media(Scope $scope): array
    {
        return array_map(static fn(array $row): array => [
            'node' => (int) $row['dash_node_id'],
            'page' => (int) $row['dash_page_id'],
        ], $scope->rows(__DIR__ . '/Dashboard.media.sql', [])->looseValues());
    }
}
